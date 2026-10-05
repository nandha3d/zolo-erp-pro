<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\UsesDisposableMysql;
use Tests\TestCase;

class MysqlCompanyMigrationTest extends TestCase
{
    use UsesDisposableMysql;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureDisposableMysql();
    }

    protected function tearDown(): void
    {
        DB::purge('erp_regression');
        parent::tearDown();
    }

    public function test_full_source_migration_chain_runs_on_mysql_and_has_compatible_currency_fk(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'erp_regression', '--force' => true]));
        $this->assertTrue(Schema::hasTable('companies'));
        $column = collect(Schema::getColumns('companies'))->firstWhere('name', 'base_currency_id');
        $this->assertSame('bigint unsigned', $column['type']);
        $this->assertTrue(collect(Schema::getForeignKeys('companies'))->contains('name', 'companies_base_currency_id_foreign'));
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'erp_regression', '--force' => true]));
    }

    public function test_tenant_seeding_preserves_migration_permissions_and_maps_legacy_grants_by_name(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'erp_regression', '--force' => true]));
        $migrationPermission = DB::table('permissions')->where('id', 4)->first();
        $this->assertNotNull($migrationPermission);
        $this->assertNotSame('products-edit', $migrationPermission->name);
        $seed = fn () => Artisan::call('db:seed', [
            '--class' => \Database\Seeders\Tenant\TenantDatabaseSeeder::class, '--force' => true,
        ]);
        $this->assertSame(0, $seed());
        $this->assertEquals($migrationPermission, DB::table('permissions')->where('id', 4)->first());
        $productPermissionId = DB::table('permissions')->where('name', 'products-edit')->where('guard_name', 'web')->value('id');
        $this->assertNotSame(4, (int) $productPermissionId);
        $this->assertTrue(DB::table('role_has_permissions')->where('role_id', 1)->where('permission_id', $productPermissionId)->exists());
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => $migrationPermission->id]);
        $permissionCount = DB::table('permissions')->count();
        $grantCount = DB::table('role_has_permissions')->count();
        $this->assertSame(0, $seed());
        $this->assertSame($permissionCount, DB::table('permissions')->count());
        $this->assertSame($grantCount, DB::table('role_has_permissions')->count());
        $this->assertSame([(int) $migrationPermission->id], DB::table('role_has_permissions')->where('role_id', 4)->pluck('permission_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_committed_partial_ddl_can_resume_after_failure_without_losing_rows(): void
    {
        foreach (['products', 'customers', 'warehouses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
            });
            DB::table($name)->insert(['name' => 'Legacy retained record']);
        }
        Schema::create('fiscal_years', fn (Blueprint $table) => $table->id());
        $fail = true;
        DB::listen(function (QueryExecuted $query) use (&$fail) {
            if ($fail && str_starts_with($query->sql, 'alter table `customers` add `company_id`')) {
                $fail = false;
                throw new RuntimeException('Injected failure after committed MySQL ALTER');
            }
        });
        $migration = require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php');
        try {
            $migration->up();
            $this->fail('Fault must interrupt DDL.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Injected failure', $error->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('products', 'company_id'));
        $this->assertTrue(Schema::hasColumn('customers', 'company_id'));
        $this->assertFalse(Schema::hasIndex('customers', 'customers_company_id_index'));
        $migration->up();
        $this->assertTrue(Schema::hasIndex('customers', 'customers_company_id_index'));
        $this->assertTrue(Schema::hasColumn('warehouses', 'branch_id'));
        $this->assertSame(1, DB::table('products')->count());
        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'company_id'));
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_existing_currency_column_is_widened_and_orphan_updates_are_rejected(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->increments('id'));
        Schema::create('currencies', fn (Blueprint $table) => $table->bigIncrements('id'));
        DB::table('currencies')->insert([['id' => 1], ['id' => 4294967296]]);
        (require database_path('migrations/2026_10_03_000001_create_company_context_tables.php'))->up();
        DB::table('companies')->insert(['code' => 'DEFAULT', 'legal_name' => 'Retained company', 'base_currency_id' => 1]);
        $migration = require database_path('migrations/2026_10_03_000003_align_company_currency_reference.php');
        $migration->up();
        $migration->up();
        DB::table('companies')->update(['base_currency_id' => 4294967296]);
        $this->assertEquals(4294967296, DB::table('companies')->value('base_currency_id'));
        $this->expectException(QueryException::class);
        DB::table('companies')->update(['base_currency_id' => 999]);
    }

    public function test_accounting_migration_resumes_committed_ddl_preserves_records_and_refuses_unsafe_rollback(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'erp_regression', '--force' => true]));
        $migration = require database_path('migrations/2026_10_04_000002_harden_accounting_and_create_open_items.php');
        $migration->down();
        DB::table('companies')->insert([
            ['id' => 1, 'code' => 'A', 'legal_name' => 'Retained A'],
            ['id' => 2, 'code' => 'B', 'legal_name' => 'Retained B'],
        ]);
        DB::table('chart_of_accounts')->insert(['company_id' => 1, 'code' => 'CASH', 'name' => 'Retained cash', 'type' => 'asset', 'sub_type' => 'cash']);
        $fail = true;
        DB::listen(function (QueryExecuted $query) use (&$fail) {
            if ($fail && str_starts_with($query->sql, 'alter table `journal_entries` add `posted_at`')) {
                $fail = false;
                throw new RuntimeException('Injected accounting DDL failure');
            }
        });
        try {
            $migration->up();
            $this->fail('Committed partial DDL must be interrupted.');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected accounting DDL failure', $error->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('journal_entries', 'posted_at'));
        $this->assertFalse(Schema::hasTable('account_open_items'));
        $migration->up();
        $migration->up();
        $this->assertSame('Retained cash', DB::table('chart_of_accounts')->value('name'));
        $this->assertTrue(Schema::hasTable('account_allocations'));
        $this->assertTrue(collect(Schema::getForeignKeys('account_allocations'))->contains('name', 'allocation_reversal_of_id_fk'));
        DB::table('chart_of_accounts')->insert(['company_id' => 2, 'code' => 'CASH', 'name' => 'Company B cash', 'type' => 'asset', 'sub_type' => 'cash']);
        try {
            $migration->down();
            $this->fail('Restoring global unique keys must refuse conflicting company data.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('company-specific', $error->getMessage());
        }
        $this->assertSame(2, DB::table('chart_of_accounts')->count());
        $this->assertTrue(Schema::hasTable('account_open_items'));
    }
}
