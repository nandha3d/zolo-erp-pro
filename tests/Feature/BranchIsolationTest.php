<?php

namespace Tests\Feature;

use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\WarehouseController;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CompanyContextTestCase;

class BranchIsolationTest extends CompanyContextTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCompanyTransactionFixtures();
        DB::table('warehouses')->where('id', 1)->update(['name' => 'Visible A']);
        Schema::table('warehouses', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('warehouse_id');
            $table->integer('user_id');
            $table->decimal('cash_in_hand')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        DB::table('cash_registers')->insert([
            ['id' => 1, 'warehouse_id' => 1, 'user_id' => 1],
            ['id' => 2, 'warehouse_id' => 3, 'user_id' => 1],
            ['id' => 3, 'warehouse_id' => 2, 'user_id' => 2],
        ]);
        Route::middleware(['web', 'auth', 'company.context'])->group(function () {
            Route::get('/__test/branch-warehouses', [WarehouseController::class, 'warehouseAll']);
            Route::get('/__test/branch-warehouse/{id}', [WarehouseController::class, 'edit']);
            Route::get('/__test/branch-register/{id}', [CashRegisterController::class, 'getDetails']);
            Route::post('/__test/branch-register', [CashRegisterController::class, 'store']);
        });
    }

    public function test_legacy_warehouse_dropdown_and_detail_hide_ungranted_branches_even_for_admin(): void
    {
        $this->getJson('/__test/branch-warehouses')->assertOk()->assertSee('Visible A')->assertDontSee('Restricted stock')->assertDontSee('Secret B');
        $this->getJson('/__test/branch-warehouse/3')->assertNotFound();
        $this->getJson('/__test/branch-warehouse/2')->assertNotFound();
    }

    public function test_foreign_branch_cash_register_cannot_be_read_or_opened(): void
    {
        $this->getJson('/__test/branch-register/2')->assertNotFound();
        $this->getJson('/__test/branch-register/3')->assertNotFound();
        $this->postJson('/__test/branch-register', ['warehouse_id' => 3, 'cash_in_hand' => 10])->assertUnprocessable();
        $this->assertSame(3, CashRegister::count());
    }

    public function test_authorized_multibranch_admin_lists_all_granted_branches_but_cannot_see_another_company(): void
    {
        DB::table('company_user_branches')->insert(['company_id' => $this->company->id, 'user_id' => 1,
            'branch_id' => DB::table('warehouses')->where('id', 3)->value('branch_id')]);
        $this->getJson('/__test/branch-warehouses', ['X-Branch-ID' => (string) $this->branch->id])->assertOk()
            ->assertSee('Visible A')->assertSee('Restricted stock')->assertDontSee('Secret B');
    }

    public function test_model_and_raw_reports_restrict_documents_stock_children_and_or_predicates(): void
    {
        $this->withContext(function () {
            $this->assertSame([1], Sale::pluck('id')->all());
            $this->assertSame([1], DB::table('product_sales')->pluck('id')->all());
            $this->assertSame(4.0, (float) DB::table('product_warehouse')->sum('qty'));
            $this->assertSame(0, DB::table('sales')->where('company_id', $this->other->id)->count());
            $this->assertSame([1], DB::table('sales')->where('company_id', $this->other->id)->orWhere('id', 1)->pluck('id')->all());
            $this->assertSame([1], Warehouse::pluck('id')->all());
        });
    }

    public function test_subqueries_cannot_use_foreign_or_ungranted_stock_to_change_counts_or_eligibility(): void
    {
        DB::table('product_warehouse')->insert(['product_id' => '1', 'warehouse_id' => 2, 'qty' => 1000, 'company_id' => $this->other->id]);
        $this->withContext(function () {
            $this->assertSame(0, DB::table('products')->whereExists(function ($query) {
                $query->selectRaw('1')->from('product_warehouse')->whereColumn('product_id', 'products.id')->where('qty', '>', 50);
            })->count());
            $this->assertSame(0, DB::table('products')->whereIn('id', function ($query) {
                $query->select('product_id')->from('product_warehouse')->where('qty', '>', 50);
            })->count());
            $balance = DB::table('product_warehouse')->select('product_id')->selectRaw('SUM(qty) as balance')->groupBy('product_id');
            $this->assertSame(4.0, (float) DB::table('products')->joinSub($balance, 'stock', 'stock.product_id', '=', 'products.id')->value('balance'));
            $this->assertSame(4.0, (float) DB::query()->fromSub(DB::table('product_warehouse')->select('qty'), 'stock')->sum('qty'));
            $this->assertSame(0, DB::table('products')->whereExists(function ($query) {
                $query->selectRaw('1')->from('product_warehouse')->whereColumn('product_id', 'products.id')->where('qty', '>', 50);
            })->update(['name' => 'Foreign eligibility']));
        });
        $this->assertSame('Visible A', DB::table('products')->where('id', 1)->value('name'));
    }

    public function test_other_warehouse_documents_and_payment_registers_are_branch_scoped(): void
    {
        foreach (['incomes', 'damage_stocks', 'exchanges'] as $table) {
            Schema::create($table, function (Blueprint $schema) {
                $schema->id(); $schema->unsignedBigInteger('company_id'); $schema->integer('warehouse_id');
            });
            DB::table($table)->insert([
                ['company_id' => $this->company->id, 'warehouse_id' => 1],
                ['company_id' => $this->company->id, 'warehouse_id' => 3],
                ['company_id' => $this->other->id, 'warehouse_id' => 2],
            ]);
        }
        Schema::table('payments', fn (Blueprint $schema) => $schema->integer('cash_register_id')->nullable());
        DB::table('payments')->insert(['company_id' => $this->company->id, 'sale_id' => 1,
            'cash_register_id' => 2, 'amount' => 500, 'payment_reference' => 'Hidden register', 'user_id' => 1,
            'account_id' => 1, 'change' => 0, 'paying_method' => 'Cash']);
        DB::table('payments')->insert(['company_id' => $this->company->id, 'sale_id' => 1,
            'purchase_id' => 3, 'amount' => 500, 'payment_reference' => 'Mixed parents', 'user_id' => 1,
            'account_id' => 1, 'change' => 0, 'paying_method' => 'Cash']);
        $this->withContext(function () {
            foreach (['incomes', 'damage_stocks', 'exchanges'] as $table) $this->assertSame(1, DB::table($table)->count());
            $this->assertSame(20.0, (float) DB::table('payments')->sum('amount'));
        });
    }

    public function test_query_reuse_and_compilation_cannot_freeze_or_escape_context(): void
    {
        $this->withContext(function () {
            $query = DB::table('products')->where('id', 1);
            $this->assertSame([1], $query->pluck('id')->all());
            $query->orWhere('id', 2);
            $this->assertSame([1], $query->pluck('id')->all());
            $query->toSql(); $query->getBindings();
            $query->orWhere('company_id', $this->other->id);
            $this->assertSame([1], $query->pluck('id')->all());
            request()->attributes->set(CompanyContext::class, new CompanyContext($this->other->id,
                (int) DB::table('company_branches')->where('company_id', $this->other->id)->value('id'),
                (int) DB::table('fiscal_years')->where('company_id', $this->other->id)->value('id')));
            $this->assertSame([2], $query->pluck('id')->all());
        });
    }

    public function test_payment_sources_validate_before_writing_and_zero_remains_unassigned(): void
    {
        DB::table('payments')->insert(['company_id' => $this->company->id, 'sale_id' => 0,
            'amount' => 5, 'payment_reference' => 'Unassigned', 'user_id' => 1,
            'account_id' => 1, 'change' => 0, 'paying_method' => 'Cash']);
        $this->withContext(function () {
            $this->assertSame(25.0, (float) DB::table('payments')->sum('amount'));
            foreach ([['sale_id' => 3], ['purchase_id' => 2], ['cash_register_id' => 2]] as $values) {
                try {
                    DB::table('payments')->where('sale_id', 1)->update($values);
                    $this->fail('A foreign or ungranted payment source was accepted.');
                } catch (\Illuminate\Auth\Access\AuthorizationException $error) {
                    $this->assertStringContainsString('payment source', $error->getMessage());
                }
            }
        });
    }

    private function withContext(callable $action): void
    {
        request()->attributes->set(CompanyContext::class, new CompanyContext($this->company->id, $this->branch->id, $this->year->id));
        try {
            $action();
        } finally {
            request()->attributes->remove(CompanyContext::class);
        }
    }
}
