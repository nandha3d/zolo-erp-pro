<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CompanyContextTestCase;

class LegacyCatalogIsolationTest extends CompanyContextTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCompanyTransactionFixtures();
        $this->actingAs(\App\Models\User::findOrFail(1), 'web');
        foreach (['warehouses', 'billers', 'brands', 'categories', 'customer_groups', 'accounts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        }
        Schema::table('accounts', fn (Blueprint $table) => $table->boolean('is_default')->default(false));
        Schema::table('products', function (Blueprint $table) {
            $table->integer('tax_id')->nullable();
            $table->integer('tax_method')->default(1);
            $table->decimal('alert_quantity')->default(0);
            $table->text('product_details')->default('');
        });
        DB::table('products')->update(['image' => '']);
        DB::table('products')->insert(['id' => 3, 'name' => 'Restricted stock only', 'code' => 'A-EMPTY',
            'qty' => 1000, 'company_id' => $this->company->id, 'image' => '', 'category_id' => 1, 'brand_id' => 1, 'unit_id' => 1]);
        DB::table('product_warehouse')->insert(['product_id' => '3', 'warehouse_id' => 3,
            'qty' => 1000, 'company_id' => $this->company->id]);
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('belongs_to'); $table->boolean('is_table')->default(false);
        });
        Route::middleware(['web', 'auth', 'company.context'])->group(function () {
            Route::post('/__test/catalog', [ProductController::class, 'productData']);
            Route::get('/__test/pos-lookups', function (SaleController $controller) {
                $data = $controller->posSale()->getData();
                return response()->json(['warehouses' => $data['lims_warehouse_list']->pluck('name'),
                    'customers' => $data['lims_customer_list']->pluck('name'),
                    'billers' => $data['lims_biller_list']->pluck('name')]);
            });
        });
    }

    public function test_product_quantities_and_stock_filters_ignore_ungranted_and_foreign_stock(): void
    {
        $this->postJson('/__test/catalog', $this->filters())
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', 1)->assertJsonPath('data.0.qty', 4)
            ->assertJsonPath('data.1.qty', 0)->assertDontSee('Secret B');
        $this->postJson('/__test/catalog', $this->filters(['stock_filter' => 'with']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1)->assertJsonPath('recordsFiltered', 1);
        $this->postJson('/__test/catalog', $this->filters(['stock_filter' => 'without']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 3)->assertJsonPath('recordsFiltered', 1);
        $this->assertSame(99.0, (float) DB::table('products')->where('id', 1)->value('qty'));
    }

    public function test_product_quantity_ordering_uses_visible_stock_and_warehouse_filter_is_authorized(): void
    {
        $this->postJson('/__test/catalog', $this->filters(['order' => [['column' => 5, 'dir' => 'asc']]]))
            ->assertOk()->assertJsonPath('data.0.id', 3)->assertJsonPath('data.1.id', 1);
        $this->postJson('/__test/catalog', $this->filters(['warehouse_id' => 3]))->assertForbidden();
        $this->postJson('/__test/catalog', $this->filters(['warehouse_id' => 2]))->assertUnprocessable();
        $this->postJson('/__test/catalog', $this->filters(['warehouse_id' => '3malformed']))->assertUnprocessable();
        $this->postJson('/__test/catalog', $this->filters(['warehouse_id' => 1, 'stock_filter' => 'with']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.qty', 4);
    }

    public function test_combo_quantity_does_not_expose_company_aggregate(): void
    {
        DB::table('products')->where('id', 1)->update(['type' => 'combo']);
        $this->postJson('/__test/catalog', $this->filters())->assertOk()->assertJsonPath('data.0.qty', 4);
        $this->assertSame(99.0, (float) DB::table('products')->where('id', 1)->value('qty'));
    }

    public function test_authorized_multibranch_catalog_aggregates_only_granted_stock(): void
    {
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'user_id' => 1,
            'branch_id' => DB::table('warehouses')->where('id', 3)->value('branch_id')]);
        $headers = ['X-Branch-ID' => (string) $this->branch->id];
        $this->postJson('/__test/catalog', $this->filters(), $headers)->assertOk()->assertJsonPath('data.0.qty', 99);
        $this->postJson('/__test/catalog', $this->filters(['warehouse_id' => 3]), $headers)
            ->assertOk()->assertJsonPath('data.0.qty', 95);
    }

    public function test_pos_scoped_lookups_ignore_shared_cache_contents_and_do_not_poison_cache(): void
    {
        $this->createPosFixtures();
        $contaminated = collect([(object) ['name' => 'Secret shared cache']]);
        foreach (['warehouse_list', 'customer_list', 'biller_list'] as $key) {
            cache()->put($key, $contaminated);
        }
        $this->getJson('/__test/pos-lookups')->assertOk()->assertJsonPath('warehouses', ['Regression fixture'])
            ->assertJsonPath('customers', ['Visible A'])->assertDontSee('Secret')->assertDontSee('Restricted stock');
        foreach (['warehouse_list', 'customer_list', 'biller_list'] as $key) {
            $this->assertSame('Secret shared cache', cache()->get($key)->first()->name);
        }
        cache()->forget('warehouse_list');
        cache()->forget('customer_list');
        $this->getJson('/__test/pos-lookups')->assertOk()->assertDontSee('Secret');
        $this->assertFalse(cache()->has('warehouse_list'));
        $this->assertFalse(cache()->has('customer_list'));
        $this->actingAs(\App\Models\User::findOrFail(2), 'web')->getJson('/__test/pos-lookups')
            ->assertOk()->assertJsonPath('warehouses', ['Destination'])->assertJsonPath('customers', ['Secret B'])
            ->assertJsonPath('billers', ['Secret B'])->assertDontSee('Visible A');
    }

    private function filters(array $overrides = []): array
    {
        return array_replace(['warehouse_id' => 0, 'stock_filter' => 'all', 'product_type' => 'all',
            'brand_id' => 0, 'category_id' => 0, 'unit_id' => 0, 'tax_id' => 0, 'all_permission' => [],
            'draw' => 1, 'length' => 20, 'start' => 0, 'order' => [['column' => 2, 'dir' => 'asc']]], $overrides);
    }

    private function createPosFixtures(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name')->default('Admin'); $table->string('guard_name')->default('web');
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('guard_name'); $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('role_id'); $table->unsignedBigInteger('permission_id');
        });
        DB::table('permissions')->insert(['id' => 1, 'name' => 'sales-add', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 1]);
        foreach (['reward_point_settings', 'pos_setting', 'taxes', 'tables', 'coupons', 'currencies'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->string('name')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }
        Schema::create('general_settings', function (Blueprint $table) {
            $table->id(); $table->string('modules')->default('');
        });
        DB::table('general_settings')->insert(['modules' => '']);
    }
}
