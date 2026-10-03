<?php

namespace Tests\Feature;

use App\Models\Accounting\FiscalYear;
use App\Models\Company;
use App\Models\CompanyBranch;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\QueryException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ErpServiceTestCase;
use RuntimeException;

class CompanyFoundationTest extends ErpServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
        DB::table('users')->insert(['id' => 1, 'name' => 'Existing operator']);
        Schema::create('currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code');
        });
        DB::table('currencies')->insert([['id' => 1, 'code' => 'INR'], ['id' => 2, 'code' => 'USD']]);
        Schema::create('general_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('company_name')->nullable();
            $table->string('site_title');
            $table->string('currency');
            $table->string('timezone');
            $table->timestamps();
        });
        DB::table('general_settings')->insert([
            'company_name' => 'Existing Legal Entity', 'site_title' => 'Trading Business',
            'currency' => '1', 'timezone' => 'Asia/Kolkata',
        ]);
        DB::table('fiscal_years')->insert([
            'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_closed' => true,
        ]);
        $this->stock();

        (require database_path('migrations/2026_10_03_000001_create_company_context_tables.php'))->up();
        (require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php'))->up();

        $maintenance = \Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(true);
        $this->app->instance(MaintenanceMode::class, $maintenance);
    }

    public function test_dry_run_does_not_create_company_branch_memberships_or_assignments(): void
    {
        $this->assertSame(0, Artisan::call('erp:backfill-company-context', ['--dry-run' => true]));
        $this->assertStringContainsString('Dry run: no writes', Artisan::output());
        $this->assertSame(0, Company::count());
        $this->assertSame(0, CompanyBranch::count());
        $this->assertSame(0, DB::table('company_user')->count());
        $this->assertNull(DB::table('products')->value('company_id'));
        $this->assertNull(DB::table('warehouses')->value('branch_id'));
        $this->assertNull(DB::table('fiscal_years')->value('company_id'));
    }

    public function test_backfill_maps_legacy_records_and_preserves_financial_year_and_settings(): void
    {
        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $company = Company::sole();
        $branch = $company->branches()->sole();
        $this->assertSame('DEFAULT', $company->code);
        $this->assertSame('Existing Legal Entity', $company->legal_name);
        $this->assertSame('Trading Business', $company->trade_name);
        $this->assertSame('Asia/Kolkata', $company->timezone);
        $this->assertEquals(1, $company->base_currency_id);
        $this->assertSame('MAIN', $branch->code);
        $this->assertEquals($company->id, DB::table('products')->value('company_id'));
        $this->assertEquals(20, DB::table('products')->value('qty'));
        $this->assertEquals($branch->id, DB::table('warehouses')->value('branch_id'));
        $this->assertEquals($company->id, DB::table('product_warehouse')->value('company_id'));
        $this->assertSame(1, DB::table('company_user')->count());
        $this->assertSame(1, DB::table('company_user_branches')->count());
        $this->assertSame(1, $company->users()->sole()->id);
        $year = $company->fiscalYears()->sole();
        $this->assertSame('2026-01-01', $year->start_date->toDateString());
        $this->assertSame('2026-12-31', $year->end_date->toDateString());
        $this->assertTrue($year->is_closed);
        $this->assertSame('closed', $year->status);
        $this->assertFalse(Schema::hasTable('financial_years'));
    }

    public function test_repeating_backfill_preserves_customized_company_and_membership(): void
    {
        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        Company::sole()->update(['legal_name' => 'Reviewed legal name']);
        DB::table('company_user')->update(['role_id_override' => 7]);

        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $this->assertSame(1, Company::count());
        $this->assertSame(1, CompanyBranch::count());
        $this->assertSame(1, DB::table('company_user')->count());
        $this->assertSame('Reviewed legal name', Company::sole()->legal_name);
        $this->assertEquals(7, DB::table('company_user')->value('role_id_override'));
    }

    public function test_orphan_references_block_backfill_before_any_write(): void
    {
        DB::table('product_warehouse')->update(['product_id' => '999']);

        $this->assertSame(1, Artisan::call('erp:backfill-company-context'));
        $this->assertStringContainsString('product_warehouse.product_id: 1 orphan references', Artisan::output());
        $this->assertSame(0, Company::count());
        $this->assertNull(DB::table('products')->value('company_id'));
    }

    public function test_multiple_companies_block_automatic_legacy_assignment(): void
    {
        Company::create(['code' => 'OTHER', 'legal_name' => 'Other entity']);

        $this->assertSame(1, Artisan::call('erp:backfill-company-context'));
        $this->assertStringContainsString('Review ownership', Artisan::output());
        $this->assertSame(1, Company::count());
        $this->assertNull(DB::table('products')->value('company_id'));
    }

    public function test_live_application_cannot_run_real_backfill(): void
    {
        $maintenance = \Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(false);
        $this->app->instance(MaintenanceMode::class, $maintenance);

        $this->assertSame(1, Artisan::call('erp:backfill-company-context'));
        $this->assertStringContainsString('requires maintenance mode', Artisan::output());
        $this->assertSame(0, Company::count());
    }

    public function test_overlapping_financial_years_require_review_without_changing_dates(): void
    {
        DB::table('fiscal_years')->insert([
            'name' => '2026-27', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $this->assertSame(1, Artisan::call('erp:backfill-company-context', ['--dry-run' => true]));
        $this->assertStringContainsString('1 overlapping pairs', Artisan::output());
        $this->assertSame(0, Company::count());
        $this->assertSame('2026-01-01', FiscalYear::first()->start_date->toDateString());
    }

    public function test_branch_assignment_cannot_reference_another_company(): void
    {
        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $other = Company::create(['code' => 'OTHER', 'legal_name' => 'Other entity']);
        $branch = $other->branches()->create(['code' => 'MAIN', 'name' => 'Other branch']);

        $this->expectException(QueryException::class);
        DB::table('company_user_branches')->insert([
            'company_id' => Company::where('code', 'DEFAULT')->value('id'),
            'user_id' => 1, 'branch_id' => $branch->id,
        ]);
    }

    public function test_additive_migrations_can_roll_back_without_deleting_legacy_rows(): void
    {
        (require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php'))->down();
        (require database_path('migrations/2026_10_03_000001_create_company_context_tables.php'))->down();

        $this->assertFalse(Schema::hasTable('companies'));
        $this->assertFalse(Schema::hasColumn('products', 'company_id'));
        $this->assertSame(1, DB::table('products')->count());
        $this->assertSame('2026', DB::table('fiscal_years')->value('name'));
        $this->assertEquals(20, DB::table('products')->value('qty'));
    }

    public function test_mid_backfill_failure_rolls_back_company_and_legacy_assignments(): void
    {
        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/^update [`"]products[`"]/', $query->sql)) {
                throw new RuntimeException('Simulated interrupted backfill');
            }
        });

        try {
            Artisan::call('erp:backfill-company-context');
            $this->fail('Backfill must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated interrupted backfill', $error->getMessage());
        }
        $this->assertSame(0, Company::count());
        $this->assertSame(0, CompanyBranch::count());
        $this->assertSame(0, DB::table('company_user')->count());
        $this->assertNull(DB::table('customers')->value('company_id'));
        $this->assertNull(DB::table('products')->value('company_id'));
    }

    public function test_backfill_processes_more_than_one_batch_without_skipping_rows(): void
    {
        $products = [];
        for ($id = 2; $id <= 502; $id++) {
            $products[] = ['id' => $id, 'name' => 'Batch product', 'code' => 'BATCH-'.$id];
        }
        DB::table('products')->insert($products);

        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $this->assertSame(502, DB::table('products')->where('company_id', Company::sole()->id)->count());
        $this->assertSame(0, DB::table('products')->whereNull('company_id')->count());
    }

    public function test_existing_1970_opening_balance_dates_are_reported_and_preserved(): void
    {
        DB::table('sales')->insert([
            'reference_no' => 'opening', 'user_id' => 1, 'customer_id' => 1,
            'warehouse_id' => 1, 'biller_id' => 1, 'item' => 0, 'total_qty' => 0,
            'total_discount' => 0, 'total_tax' => 0, 'total_price' => 100, 'grand_total' => 100,
            // Noon is inside MySQL TIMESTAMP's valid range; retain the legacy opening date exactly.
            'sale_status' => 1, 'payment_status' => 2, 'created_at' => '1970-01-01 12:00:00',
        ]);

        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $this->assertStringContainsString('sales: 1 legacy opening-date records', Artisan::output());
        $this->assertSame('1970-01-01 12:00:00', DB::table('sales')->value('created_at'));
        $this->assertEquals(100, DB::table('sales')->value('grand_total'));
    }

    public function test_legacy_zero_unit_and_opening_payment_account_are_preserved(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('unit_id')->nullable();
        });
        DB::table('products')->update(['type' => 'digital', 'unit_id' => 0]);
        DB::table('payments')->insert([
            'user_id' => 1, 'account_id' => 0, 'payment_reference' => 'opening-stock',
            'amount' => 50, 'change' => 0, 'paying_method' => 'Cash',
        ]);

        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $this->assertEquals(0, DB::table('products')->value('unit_id'));
        $this->assertEquals(0, DB::table('payments')->value('account_id'));
        $this->assertEquals(Company::sole()->id, DB::table('payments')->value('company_id'));
    }

    public function test_default_company_uses_latest_general_settings(): void
    {
        DB::table('general_settings')->insert([
            'company_name' => 'Current Legal Entity', 'site_title' => 'Current business',
            'currency' => '2', 'timezone' => 'UTC', 'created_at' => '2026-10-03 12:00:00',
        ]);

        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $this->assertSame('Current Legal Entity', Company::sole()->legal_name);
        $this->assertSame('UTC', Company::sole()->timezone);
        $this->assertEquals(2, Company::sole()->base_currency_id);
    }

    public function test_dry_run_rejects_invalid_timezone_without_writes(): void
    {
        DB::table('general_settings')->update(['timezone' => 'invalid/timezone']);

        $this->assertSame(1, Artisan::call('erp:backfill-company-context', ['--dry-run' => true]));
        $this->assertStringContainsString('invalid timezone', Artisan::output());
        $this->assertSame(0, Company::count());
    }

    public function test_repeat_backfill_does_not_reimport_obsolete_general_settings(): void
    {
        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        DB::table('general_settings')->update(['timezone' => 'invalid/timezone']);

        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        $this->assertSame('Asia/Kolkata', Company::sole()->timezone);
    }

    public function test_invalid_numeric_currency_blocks_dry_run_without_writes(): void
    {
        DB::table('general_settings')->update(['currency' => '999']);
        $this->assertSame(1, Artisan::call('erp:backfill-company-context', ['--dry-run' => true]));
        $this->assertStringContainsString('currency ID does not exist', Artisan::output());
        $this->assertSame(0, Company::count());
        $this->assertNull(DB::table('products')->value('company_id'));
    }

    public function test_existing_company_currency_is_validated_on_repeat(): void
    {
        $this->assertSame(0, Artisan::call('erp:backfill-company-context'));
        Company::sole()->update(['base_currency_id' => 999]);
        $this->assertSame(1, Artisan::call('erp:backfill-company-context', ['--dry-run' => true]));
    }

    public function test_company_key_migration_can_resume_completed_or_partially_applied_ddl(): void
    {
        $migration = require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php');
        $migration->up();
        $migration->down();
        Schema::table('products', fn (Blueprint $table) => $table->unsignedBigInteger('company_id')->nullable());
        $migration->up();
        $this->assertTrue(Schema::hasIndex('products', 'products_company_id_index'));
        $this->assertTrue(Schema::hasColumn('customers', 'company_id'));
        $this->assertSame(1, DB::table('products')->count());
        $this->assertEquals(20, DB::table('products')->value('qty'));
    }

    public function test_incompatible_preexisting_column_blocks_all_company_key_ddl(): void
    {
        $migration = require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php');
        $migration->down();
        Schema::table('products', fn (Blueprint $table) => $table->string('company_id')->nullable());
        try {
            $migration->up();
            $this->fail('Incompatible column must fail preflight.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Incompatible column products.company_id', $error->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('customers', 'company_id'));
    }

    public function test_incompatible_preexisting_index_blocks_all_company_key_ddl(): void
    {
        $migration = require database_path('migrations/2026_10_03_000002_add_nullable_company_keys_to_core_tables.php');
        $migration->down();
        Schema::table('products', fn (Blueprint $table) => $table->index('qty', 'products_company_id_index'));
        try {
            $migration->up();
            $this->fail('Incompatible index must fail preflight.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Incompatible index products_company_id_index', $error->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('customers', 'company_id'));
    }
}
