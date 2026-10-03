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
}
