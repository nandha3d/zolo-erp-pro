<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the stock ledger to ERP service fixtures. Test classes that exercise units, variants,
 * batches or serials return true from usesInventoryMasters() to also get the original tables.
 */
trait CreatesInventoryLedgerFixtures
{
    protected function usesInventoryMasters(): bool
    {
        return false;
    }

    protected function createInventoryLedgerFixtures(): void
    {
        if ($this->usesInventoryMasters()) {
            $migrations = [
                '2018_03_03_040448_create_units_table.php' => 'CreateUnitsTable',
                '2019_11_13_150206_create_product_variants_table.php' => 'CreateProductVariantsTable',
                '2019_11_25_134041_add_qty_to_product_variants_table.php' => 'AddQtyToProductVariantsTable',
                '2021_03_07_093606_create_product_batches_table.php' => 'CreateProductBatchesTable',
            ];
            foreach ($migrations as $file => $class) {
                require_once database_path('migrations/'.$file);
                (new $class)->up();
            }
            Schema::table('products', function (Blueprint $table) {
                $table->integer('unit_id')->nullable();
                foreach (['is_batch', 'is_variant', 'is_imei'] as $flag) {
                    $table->boolean($flag)->nullable();
                }
            });
        }
        foreach (['product_batch_id', 'variant_id', 'imei_number'] as $column) {
            if (!Schema::hasColumn('product_warehouse', $column)) {
                Schema::table('product_warehouse', fn (Blueprint $table) => $column === 'imei_number'
                    ? $table->text($column)->nullable() : $table->integer($column)->nullable());
            }
        }

        (require database_path('migrations/2026_10_04_000001_create_stock_ledger_tables.php'))->up();
        (require database_path('migrations/2026_10_04_000002_add_projection_mode_to_stock_movements.php'))->up();
    }
}
