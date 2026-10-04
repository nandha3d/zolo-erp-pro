<?php

namespace Tests\Support;

use App\Models\Product;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class CommercialTestCase extends CompanyErpServiceTestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\App\Services\Accounting\AccountingPostingService::class)) {
            $this->markTestSkipped('Phase 6 integration requires the Phase 5 accounting branch.');
        }
        parent::setUp();
        foreach (['customers', 'suppliers'] as $name) {
            Schema::table($name, function (Blueprint $t) {
                $t->boolean('is_active')->default(true);
                $t->string('city')->nullable();
                $t->string('phone_number')->nullable();
                foreach (['address', 'company_name', 'email'] as $field) $t->string($field)->nullable();
            });
        }
        Schema::table('customers', function (Blueprint $t) { $t->decimal('credit_limit', 18, 4)->nullable(); $t->unsignedInteger('customer_group_id')->nullable(); });
        Schema::table('units', fn (Blueprint $t) => $t->string('unit_name')->nullable());
        DB::table('units')->update(['unit_name' => 'PCS']);
        Schema::table('products', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('unit_id')->nullable();
            $t->unsignedInteger('tax_id')->nullable();
            foreach (['is_batch', 'is_imei', 'is_variant'] as $flag) {
                $t->boolean($flag)->default(false);
            }
        });
        foreach (['categories', 'customer_groups'] as $name) {
            Schema::create($name, function (Blueprint $t) { $t->increments('id'); $t->unsignedBigInteger('company_id'); $t->string('name'); });
            DB::table($name)->insert(['id' => 1, 'company_id' => $this->company->id, 'name' => 'Default']);
        }
        Schema::create('taxes', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->decimal('rate', 8, 4); $t->boolean('is_active')->default(true);
        });
        if (!Schema::hasTable('account_open_items')) {
            (require database_path('migrations/2026_09_19_000004_create_inventory_close_and_mappings_tables.php'))->up();
            Schema::table('semantic_account_mappings', fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable());
            $migration = database_path('migrations/2026_10_04_000002_harden_accounting_and_create_open_items.php');
            if (!is_file($migration)) {
                $migration = getenv('ERP_PHASE5_TEST_ROOT').'/database/migrations/2026_10_04_000002_harden_accounting_and_create_open_items.php';
            }
            (require $migration)->up();
            app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
        }
        foreach ([['EXP', 'expense', 'operating_expense'], ['TAX', 'liability', 'tax_payable'], ['DISC', 'expense', 'sales_discount'],
            ['SHIP', 'revenue', 'shipping_income'], ['BANK', 'asset', 'bank']] as [$code, $type, $subType]) {
            \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => $code,
                'name' => $subType, 'type' => $type, 'sub_type' => $subType]);
        }
        // seedCompany preserves unresolved mappings; explicitly configure these fixture roles.
        foreach (['expense' => 'EXP', 'tax_output' => 'TAX', 'discounts' => 'DISC', 'freight' => 'SHIP', 'bank' => 'BANK'] as $role => $code) {
            $account = \App\Models\Accounting\ChartOfAccount::forCompany($this->context())->where('code', $code)->firstOrFail();
            $account->forceFill(['control_type' => $role === 'bank' ? 'bank' : 'none'])->save();
            DB::table('semantic_account_mappings')->where('company_id', $this->company->id)->where('semantic_role', $role)
                ->update(['account_id' => $account->id, 'is_active' => true]);
        }
        (require database_path('migrations/2026_10_05_000001_create_shared_commercial_contracts.php'))->up();
        config(['commercial.enabled' => true]);
        $this->withoutMiddleware(\App\Http\Middleware\Common::class);
        $this->withoutMiddleware(\App\Http\Middleware\Active::class);
    }

    protected function context(): CompanyContext
    {
        return new CompanyContext($this->company->id, $this->branch->id, $this->year->id);
    }

    protected function saleData(Product $product, array $extra = []): array
    {
        return $extra + ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]]];
    }

    protected function purchaseData(Product $product, array $extra = []): array
    {
        return $extra + ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 5]]];
    }
}
