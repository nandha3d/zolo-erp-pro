<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TextileCompanyMigrationTest extends TestCase
{
    public function test_textile_migration_recovers_from_failure_and_seeds_accounting_without_direct_stock(): void
    {
        $migration = require database_path('migrations/2026_11_06_143000_migrate_zoloerp_textile_company_and_masters.php');

        // Test failure recovery: inject failure inside the transaction
        $fail = true;
        DB::listen(function (QueryExecuted $query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'company_industry_settings')) {
                $fail = false;
                throw new RuntimeException('Injected textile migration failure');
            }
        });

        // Ensure cleanly rolled back if error happens
        try {
            $migration->up();
            $this->fail('Interrupted textile migration must throw exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected textile migration failure', $error->getMessage());
        }

        // Clean re-execution must succeed
        $migration->up();

        $company = Company::withoutGlobalScopes()->where('code', 'ZTEX')->first();
        $this->assertNotNull($company, 'ZTEX company must exist after migration');

        // Verify GST registration
        if (Schema::hasTable('tax_registrations')) {
            $taxReg = DB::table('tax_registrations')->where('company_id', $company->id)->where('gstin', '33ZOLOT1234K1Z5')->first();
            $this->assertNotNull($taxReg, 'GST tax registration must exist');
        }

        // Verify Semantic Account Mappings exist
        if (Schema::hasTable('semantic_account_mappings')) {
            $mappingsCount = DB::table('semantic_account_mappings')->where('company_id', $company->id)->count();
            $this->assertGreaterThan(0, $mappingsCount, 'Semantic account mappings must be seeded for ZTEX');
        }

        // Verify products catalog: no direct stock injection
        if (Schema::hasTable('products')) {
            $directStockSum = (float) DB::table('products')->where('company_id', $company->id)->sum('qty');
            $this->assertSame(0.0, $directStockSum, 'Catalog products must have zero direct stock quantity injected');
        }

        if (Schema::hasTable('product_warehouse')) {
            $warehouseStockSum = (float) DB::table('product_warehouse')->where('company_id', $company->id)->sum('qty');
            $this->assertSame(0.0, $warehouseStockSum, 'Warehouse stock must have zero direct stock quantity injected');
        }
    }
}
