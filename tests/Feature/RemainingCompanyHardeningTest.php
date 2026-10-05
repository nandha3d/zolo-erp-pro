<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use Tests\Support\UsesDisposableMysql;

class RemainingCompanyHardeningTest extends TestCase
{
    use UsesDisposableMysql;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ERP_TEST_MYSQL') === '1') {
            $this->configureDisposableMysql();
        } else config(['database.default' => 'ownership_test', 'database.connections.ownership_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        foreach (['companies', 'products', 'warehouses', 'variants', 'discounts', 'discount_plans', 'employees'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $name === 'companies' ? $t->bigIncrements('id') : $t->increments('id');
                $t->string('name')->nullable(); $t->timestamps();
                if (in_array($name, ['products', 'warehouses'])) $t->unsignedBigInteger('company_id')->nullable();
                if ($name === 'discounts') $t->string('product_list')->nullable();
                if ($name === 'employees') $t->unsignedInteger('warehouse_id')->nullable();
            });
        }
        Schema::create('product_variants', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->integer('variant_id'); $t->timestamps();
        });
        Schema::create('discount_plan_discounts', function (Blueprint $t) {
            $t->increments('id'); $t->integer('discount_plan_id'); $t->integer('discount_id'); $t->timestamps();
        });
        foreach (['accounts', 'boms'] as $table) Schema::create($table, function (Blueprint $t) {
            $t->increments('id'); $t->unsignedBigInteger('company_id');
        });
        Schema::create('bom_lines', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('bom_id'); $t->unsignedInteger('component_product_id');
        });
        Schema::create('payrolls', function (Blueprint $t) {
            $t->increments('id'); $t->integer('employee_id'); $t->integer('account_id'); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('ownership_test');
        if (getenv('ERP_TEST_MYSQL') === '1') DB::purge('erp_regression');
        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_12_000001_harden_remaining_company_relations.php');
    }

    private function companies(): void
    {
        DB::table('companies')->insert([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
        foreach (['products', 'warehouses'] as $table) DB::table($table)->insert([
            ['id' => 1, 'name' => 'A', 'company_id' => 1], ['id' => 2, 'name' => 'B', 'company_id' => 2],
        ]);
    }

    public function test_parent_backfill_resumes_and_company_names_are_independent(): void
    {
        $this->companies();
        DB::table('variants')->insert(['id' => 1, 'name' => 'Large']);
        DB::table('product_variants')->insert(['product_id' => 1, 'variant_id' => 1]);
        $this->migration()->up();
        $this->assertSame(1, (int) DB::table('variants')->value('company_id'));
        $this->assertSame(1, (int) DB::table('product_variants')->value('company_id'));
        DB::table('variants')->insert(['id' => 2, 'name' => 'Large', 'company_id' => 2]);
        $foreignCount = count(Schema::getForeignKeys('product_variants'));
        $this->migration()->up();
        $this->assertSame($foreignCount, count(Schema::getForeignKeys('product_variants')));
        $this->assertFalse(collect(Schema::getColumns('variants'))->firstWhere('name', 'company_id')['nullable']);
    }

    public function test_database_rejects_cross_company_variant_product_without_request_context(): void
    {
        $this->companies(); $this->migration()->up();
        DB::table('variants')->insert(['id' => 1, 'name' => 'Large', 'company_id' => 1]);
        $this->expectException(QueryException::class);
        DB::table('product_variants')->insert(['product_id' => 2, 'variant_id' => 1, 'company_id' => 1]);
    }

    public function test_database_rejects_cross_company_employee_warehouse(): void
    {
        $this->companies(); $this->migration()->up();
        $this->expectException(QueryException::class);
        DB::table('employees')->insert(['name' => 'Employee A', 'company_id' => 1, 'warehouse_id' => 2]);
    }

    public function test_database_rejects_cross_company_discount_plan_pivot(): void
    {
        $this->companies(); $this->migration()->up();
        DB::table('discounts')->insert(['id' => 1, 'name' => 'Discount A', 'company_id' => 1]);
        DB::table('discount_plans')->insert(['id' => 1, 'name' => 'Plan B', 'company_id' => 2]);
        $this->expectException(QueryException::class);
        DB::table('discount_plan_discounts')->insert(['discount_id' => 1, 'discount_plan_id' => 1, 'company_id' => 1]);
    }

    public function test_operational_child_cannot_mix_company_product_and_bom(): void
    {
        $this->companies(); $this->migration()->up();
        DB::table('boms')->insert(['id' => 1, 'company_id' => 1]);
        $this->expectException(QueryException::class);
        DB::table('bom_lines')->insert(['bom_id' => 1, 'component_product_id' => 2, 'company_id' => 1]);
    }

    public function test_legacy_zero_payroll_account_remains_unassigned_without_inventing_account(): void
    {
        DB::table('companies')->insert(['id' => 1, 'name' => 'DEFAULT']);
        DB::table('employees')->insert(['id' => 1, 'name' => 'Employee']);
        DB::table('payrolls')->insert(['employee_id' => 1, 'account_id' => 0]);
        $this->migration()->up();
        $this->assertNull(DB::table('payrolls')->value('account_id'));
        $payroll = Payroll::forceCreate(['company_id' => 1, 'employee_id' => 1, 'account_id' => 0]);
        $this->assertNull($payroll->fresh()->account_id);
    }

    public function test_incompatible_named_artifact_aborts_before_ownership_ddl(): void
    {
        $this->companies();
        Schema::table('variants', fn (Blueprint $t) => $t->unique('name', 'variants_owner_id_unique'));
        try {
            $this->migration()->up(); $this->fail('Incompatible index must abort.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Incompatible ownership index', $error->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('discounts', 'company_id'));
    }

    public function test_discount_csv_references_reject_foreign_products_before_write(): void
    {
        $this->companies(); $this->migration()->up();
        $discount = new Discount(['name' => 'Invalid', 'product_list' => '2']);
        $discount->company_id = 1;
        $this->expectException(ValidationException::class);
        $discount->save();
    }

    public function test_model_rejects_foreign_employee_warehouse_before_write(): void
    {
        $this->companies(); $this->migration()->up();
        $employee = new Employee(['name' => 'Invalid', 'warehouse_id' => 2]);
        $employee->company_id = 1;
        $this->expectException(ValidationException::class);
        $employee->save();
    }

    public function test_ambiguous_retained_master_fails_before_any_ddl(): void
    {
        $this->companies();
        DB::table('variants')->insert(['name' => 'Unassigned']);
        try {
            $this->migration()->up(); $this->fail('Ambiguous retained ownership must abort.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Ambiguous company', $error->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('variants', 'company_id'));
        $this->assertFalse(Schema::hasColumn('discounts', 'company_id'));
    }

    public function test_shared_legacy_variant_with_conflicting_company_parents_is_not_reassigned(): void
    {
        $this->companies();
        DB::table('variants')->insert(['id' => 1, 'name' => 'Shared']);
        DB::table('product_variants')->insert([['product_id' => 1, 'variant_id' => 1], ['product_id' => 2, 'variant_id' => 1]]);
        try {
            $this->migration()->up(); $this->fail('Conflicting parents must abort.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Conflicting companies', $error->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('product_variants', 'company_id'));
        $this->assertSame(2, DB::table('product_variants')->count());
    }

    public function test_fresh_install_defers_non_null_constraints_until_reviewed_foundation_exists(): void
    {
        $this->migration()->up();
        DB::table('variants')->insert(['id' => 1, 'name' => 'Seed']);
        DB::table('companies')->insert(['id' => 1, 'name' => 'DEFAULT']);
        $this->migration()->up();
        $this->assertSame(1, (int) DB::table('variants')->value('company_id'));
        $this->expectException(QueryException::class);
        DB::table('variants')->insert(['name' => 'Unowned']);
    }
}
