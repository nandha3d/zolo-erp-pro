<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Product_Warehouse;
use Database\Seeders\TextileMasterSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\UsesDisposableMysql;
use Tests\TestCase;

class TextileCompanyMigrationTest extends TestCase
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

    public function test_textile_migration_recovers_from_failure_and_seeds_accounting_without_direct_stock(): void
    {
        // 1. Run prerequisite migrations up to immediately before the textile migration
        $migrations = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn (string $path) => basename($path) < '2026_11_06_143000_migrate_zoloerp_textile_company_and_masters.php'));
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'erp_regression', '--force' => true, '--realpath' => true, '--path' => $migrations,
        ]));

        $migration = require database_path('migrations/2026_11_06_143000_migrate_zoloerp_textile_company_and_masters.php');

        // 2. Inject failure inside transaction
        $fail = true;
        DB::listen(function (QueryExecuted $query) use (&$fail) {
            if ($fail && str_contains($query->sql, 'company_industry_settings')) {
                $fail = false;
                throw new RuntimeException('Injected textile migration failure');
            }
        });

        // 3. Ensure cleanly rolled back when error occurs
        try {
            $migration->up();
            $this->fail('Interrupted textile migration must throw exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected textile migration failure', $error->getMessage());
        }

        $this->assertNull(DB::table('companies')->where('code', 'ZTEX')->first(), 'ZTEX company must be rolled back on failure');

        // 4. Clean re-execution must succeed
        $migration->up();

        $company = Company::withoutGlobalScopes()->where('code', 'ZTEX')->first();
        $this->assertNotNull($company, 'ZTEX company must exist after migration');

        // 5. Verify GST registration
        if (Schema::hasTable('tax_registrations')) {
            $taxReg = DB::table('tax_registrations')->where('company_id', $company->id)->where('gstin', '33ZOLOT1234K1Z5')->first();
            $this->assertNotNull($taxReg, 'GST tax registration must exist');
        }

        // 6. Verify Semantic Account Mappings exist
        if (Schema::hasTable('semantic_account_mappings')) {
            $mappingsCount = DB::table('semantic_account_mappings')->where('company_id', $company->id)->count();
            $this->assertGreaterThan(0, $mappingsCount, 'Semantic account mappings must be seeded for ZTEX');
        }

        // 7. Verify products catalog: no direct stock injection
        if (Schema::hasTable('products')) {
            $directStockSum = (float) DB::table('products')->where('company_id', $company->id)->sum('qty');
            $this->assertSame(0.0, $directStockSum, 'Catalog products must have zero direct stock quantity injected');
        }

        if (Schema::hasTable('product_warehouse')) {
            $warehouseStockSum = (float) DB::table('product_warehouse')->where('company_id', $company->id)->sum('qty');
            $this->assertSame(0.0, $warehouseStockSum, 'Warehouse stock must have zero direct stock quantity injected');
        }
    }

    public function test_catalog_only_seeding_preserves_existing_stock_without_ledger_movements(): void
    {
        // 1. Run migrations including textile migration on disposable database
        $migrations = array_values(array_filter(glob(database_path('migrations/*.php')),
            fn (string $path) => basename($path) <= '2026_11_06_143000_migrate_zoloerp_textile_company_and_masters.php'));
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'erp_regression', '--force' => true, '--realpath' => true, '--path' => $migrations,
        ]));

        $company = Company::withoutGlobalScopes()->where('code', 'ZTEX')->firstOrFail();

        // 2. Select an existing textile product
        $product = Product::withoutGlobalScope('request_company')
            ->where('company_id', $company->id)
            ->where('code', 'TEX-GRY-63W')
            ->firstOrFail();

        // 3. Establish non-zero stock awaiting the opening ledger import
        $product->update(['qty' => 250.0]);
        Product_Warehouse::where('product_id', $product->id)->update([
            'qty' => 250.0,
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseMissing('stock_movements', ['company_id' => $company->id]);

        // 4. Re-run TextileMasterSeeder with catalogOnly = true
        (new TextileMasterSeeder)->seedCompany($company, catalogOnly: true);

        // 5. Assert existing legitimate stock was NOT wiped out
        $this->assertSame(250.0, (float) $product->fresh()->qty, 'Legitimate existing stock quantity must be preserved by catalog-only seeding');
        $pwQty = (float) Product_Warehouse::where('product_id', $product->id)->where('company_id', $company->id)->value('qty');
        $this->assertSame(250.0, $pwQty, 'Warehouse stock quantity must be preserved by catalog-only seeding');
    }
}
