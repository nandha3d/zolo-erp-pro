<?php

namespace Tests\Support;

use App\Models\Inventory\StockMovement;
use App\Models\Product;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class InventoryLedgerTestCase extends ErpServiceTestCase
{
    protected function usesInventoryMasters(): bool
    {
        return true;
    }

    protected function movements(): InventoryMovementService
    {
        return app(InventoryMovementService::class);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::forceCreate($attributes + ['name' => 'Ledger item', 'code' => 'LEDGER', 'qty' => 0, 'cost' => 0, 'price' => 10]);
    }

    protected function command(array $lines, int $warehouseId = 1, array $options = []): StockMovementCommand
    {
        return new StockMovementCommand(...$options + [
            'date' => '2026-10-04',
            'lines' => array_map(fn ($line) => $line instanceof StockLine ? $line : StockLine::fromArray($line), $lines),
            'warehouseId' => $warehouseId,
        ]);
    }

    protected function receive(Product $product, float $qty, float $cost, int $warehouseId = 1, array $line = []): StockMovement
    {
        return $this->movements()->receive($this->command([
            ['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => $cost] + $line,
        ], $warehouseId));
    }

    protected function warehouseQty(Product $product, int $warehouseId = 1): float
    {
        return (float) DB::table('product_warehouse')->where('product_id', $product->id)->where('warehouse_id', $warehouseId)->sum('qty');
    }

    /** Adds a companies table and warehouse ownership so company inventory policy can be exercised. */
    protected function companyWithInventoryPolicy(array $policy): int
    {
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->json('settings_json')->nullable();
            });
            Schema::table('warehouses', fn (Blueprint $table) => $table->unsignedBigInteger('company_id')->nullable());
        }
        $id = DB::table('companies')->insertGetId(['settings_json' => json_encode(['inventory' => $policy])]);
        DB::table('warehouses')->update(['company_id' => $id]);

        return $id;
    }
}
