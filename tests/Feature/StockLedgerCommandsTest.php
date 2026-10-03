<?php

namespace Tests\Feature;

use App\Models\Inventory\StockIdentity;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementLine;
use App\Services\Inventory\InventoryReconciliationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryLedgerTestCase;

class StockLedgerCommandsTest extends InventoryLedgerTestCase
{
    public function test_opening_records_existing_projections_once_without_changing_them(): void
    {
        $plain = $this->product(['qty' => 20, 'cost' => 5]);
        DB::table('product_warehouse')->insert([
            ['product_id' => $plain->id, 'warehouse_id' => 1, 'qty' => 12, 'imei_number' => null],
            ['product_id' => $plain->id, 'warehouse_id' => 2, 'qty' => 8, 'imei_number' => null],
        ]);
        $serial = $this->product(['code' => 'SER', 'qty' => 2, 'cost' => 50, 'is_imei' => true]);
        DB::table('product_warehouse')->insert(['product_id' => $serial->id, 'warehouse_id' => 1, 'qty' => 2, 'imei_number' => 'X1, X2']);
        $negative = $this->product(['code' => 'NEG', 'qty' => -3, 'cost' => 1]);
        DB::table('product_warehouse')->insert(['product_id' => $negative->id, 'warehouse_id' => 1, 'qty' => -3, 'imei_number' => null]);
        $this->assertCount(7, app(InventoryReconciliationService::class)->differences());

        $this->assertSame(0, Artisan::call('erp:stock-opening', ['--dry-run' => true, '--date' => '2026-04-01']));
        $this->assertSame(0, StockMovement::count());

        $this->assertSame(0, Artisan::call('erp:stock-opening', ['--date' => '2026-04-01']));
        $this->assertSame(4, StockMovement::where('movement_type', 'opening')->count());
        $this->assertEquals(20, $plain->fresh()->qty);
        $this->assertEquals(12, $this->warehouseQty($plain, 1));
        $this->assertEquals(100, StockMovementLine::where('product_id', $plain->id)->sum('value'));
        $this->assertSame(['X1', 'X2'], StockIdentity::where('status', StockIdentity::IN_STOCK)->orderBy('identity_no')->pluck('identity_no')->all());
        $this->assertEquals(-3, StockMovementLine::where('product_id', $negative->id)->sum('qty_base'));
        $this->assertSame(0, Artisan::call('erp:stock-reconcile'));

        $this->assertSame(0, Artisan::call('erp:stock-opening'));
        $this->assertStringContainsString('already_recorded', Artisan::output());
        $this->assertSame(4, StockMovement::count());

        // An opening line can be reversed without touching projections.
        $this->movements()->reverse(StockMovement::where('source_id', DB::table('product_warehouse')->where('warehouse_id', 2)->value('id'))->sole(), 'Duplicate opening');
        $this->assertEquals(8, $this->warehouseQty($plain, 2));
        $this->assertSame(1, Artisan::call('erp:stock-reconcile'));
    }

    public function test_opening_reports_rows_that_cannot_be_recorded_and_continues(): void
    {
        $product = $this->product(['qty' => 3]);
        DB::table('product_warehouse')->insert([
            ['product_id' => $product->id, 'warehouse_id' => 1, 'qty' => 1, 'variant_id' => 77],
            ['product_id' => $product->id, 'warehouse_id' => 2, 'qty' => 2, 'variant_id' => null],
        ]);

        $this->assertSame(1, Artisan::call('erp:stock-opening'));

        $this->assertStringContainsString('Variant 77 does not belong', Artisan::output());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_reconcile_reports_drift_and_rebuilds_only_products_with_ledger_history(): void
    {
        $tracked = $this->product();
        $this->receive($tracked, 10, 1);
        $untracked = $this->product(['code' => 'LEGACY', 'qty' => 4]);
        DB::table('product_warehouse')->insert(['product_id' => $untracked->id, 'warehouse_id' => 1, 'qty' => 4]);
        // Simulate a legacy direct write that bypassed the ledger.
        DB::table('product_warehouse')->where('product_id', $tracked->id)->decrement('qty', 3);
        DB::table('products')->where('id', $tracked->id)->decrement('qty', 3);

        $this->assertSame(1, Artisan::call('erp:stock-reconcile'));
        $this->assertStringContainsString('4 stock differences', Artisan::output());
        $this->assertSame(1, Artisan::call('erp:stock-reconcile', ['--rebuild' => true, '--no-interaction' => true]));
        $this->assertEquals(7, $this->warehouseQty($tracked));

        $this->assertSame(1, Artisan::call('erp:stock-reconcile', ['--rebuild' => true, '--force' => true]));
        $this->assertEquals(10, $this->warehouseQty($tracked));
        $this->assertEquals(10, $tracked->fresh()->qty);
        $this->assertEquals(4, $this->warehouseQty($untracked));
        $remaining = app(InventoryReconciliationService::class)->differences();
        $this->assertSame([$untracked->id], array_values(array_unique(array_column($remaining, 'product_id'))));
    }
}
