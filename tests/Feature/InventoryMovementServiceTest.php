<?php

namespace Tests\Feature;

use App\Models\Inventory\StockDimension;
use App\Models\Inventory\StockIdentity;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementLine;
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockPolicyException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\Support\InventoryLedgerTestCase;

class InventoryMovementServiceTest extends InventoryLedgerTestCase
{
    public function test_receipt_then_issue_reconciles_quantity_and_weighted_average_value(): void
    {
        $product = $this->product(['cost' => 4]);
        $this->receive($product, 10, 5);
        $this->receive($product, 10, 7);

        $issue = $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 5]]));

        $line = $issue->lines->sole();
        $this->assertSame('issue', $issue->movement_type);
        $this->assertEquals(-5, $line->qty_base);
        $this->assertEquals(6, $line->unit_cost);
        $this->assertEquals(-30, $line->value);
        $this->assertEquals(15, $product->fresh()->qty);
        $this->assertEquals(15, $this->warehouseQty($product));
        $this->assertEquals(90, StockMovementLine::where('product_id', $product->id)->sum('value'));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
        $this->assertMatchesRegularExpression('/^SM-\d{8}$/', $issue->movement_no);
    }

    public function test_transfer_nets_across_warehouses_at_unchanged_value(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 3);

        $transfer = $this->movements()->transfer($this->command(
            [['product_id' => $product->id, 'qty' => 4]], 1, ['toWarehouseId' => 2],
        ));

        $this->assertCount(2, $transfer->lines);
        $this->assertSame([-4.0, 4.0], $transfer->lines->map(fn ($l) => (float) $l->qty_base)->all());
        $this->assertEquals(0, $transfer->lines->sum('value'));
        $this->assertEquals(6, $this->warehouseQty($product, 1));
        $this->assertEquals(4, $this->warehouseQty($product, 2));
        $this->assertEquals(10, $product->fresh()->qty);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());

        $this->expectException(InvalidArgumentException::class);
        $this->movements()->transfer($this->command([['product_id' => $product->id, 'qty' => 1]], 1, ['toWarehouseId' => 1]));
    }

    public function test_serial_cannot_be_in_two_locations_or_issued_anonymously(): void
    {
        $product = $this->product(['is_imei' => true]);
        $this->receive($product, 2, 100, 1, ['serials' => ['SN-A', 'SN-B']]);
        $this->assertSame(2, StockIdentity::where('status', StockIdentity::IN_STOCK)->where('warehouse_id', 1)->count());

        $this->assertRejected(fn () => $this->receive($product, 1, 100, 2, ['serials' => ['SN-A']]), 'already in stock');
        $this->movements()->transfer($this->command([['product_id' => $product->id, 'qty' => 1, 'serials' => ['SN-A']]], 1, ['toWarehouseId' => 2]));
        $this->assertSame(2, (int) StockIdentity::where('identity_no', 'SN-A')->value('warehouse_id'));
        $this->assertRejected(fn () => $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'serials' => ['SN-A']]], 1)), 'not in stock');
        $this->assertRejected(fn () => $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1]], 1)), 'serial-tracked');
        $this->assertRejected(fn () => $this->receive($product, 2, 100, 1, ['serials' => ['SN-C']]), 'exactly 2');

        $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'imei_number' => 'SN-B']], 1));
        $this->assertSame(StockIdentity::ISSUED, StockIdentity::where('identity_no', 'SN-B')->value('status'));
        $this->assertEquals(0, $this->warehouseQty($product, 1));
        $this->assertEquals(1, $this->warehouseQty($product, 2));
    }

    public function test_expired_batch_is_blocked_by_default_and_warned_when_policy_says_warn(): void
    {
        $product = $this->product(['is_batch' => true]);
        $this->receive($product, 5, 2, 1, ['batch' => ['batch_no' => 'B1', 'expired_date' => '2026-10-01', 'mrp' => 9.5]]);
        $batch = DB::table('product_batches')->sole();
        $this->assertEquals(5, $batch->qty);
        $this->assertEquals(9.5, $batch->mrp);
        $this->assertEquals(5, DB::table('product_warehouse')->where('product_batch_id', $batch->id)->value('qty'));

        $issue = ['product_id' => $product->id, 'qty' => 1, 'product_batch_id' => $batch->id];
        $this->assertRejected(fn () => $this->movements()->issue($this->command([$issue])), 'expired on 2026-10-01');
        $this->assertRejected(fn () => $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1]])), 'batch-tracked');

        $this->companyWithInventoryPolicy(['expired_batch' => 'warn']);
        $movement = $this->movements()->issue($this->command([$issue]));
        $this->assertStringContainsString('expired', $movement->fresh()->warnings_json[0]);
        $this->assertEquals(4, DB::table('product_batches')->value('qty'));
    }

    public function test_negative_stock_policy_blocks_atomically_and_can_allow_or_warn(): void
    {
        $product = $this->product();
        $this->receive($product, 1, 5);
        $other = $this->product(['code' => 'OTHER']);
        $this->receive($other, 5, 5);

        $this->assertRejected(fn () => $this->movements()->issue($this->command([
            ['product_id' => $other->id, 'qty' => 1],
            ['product_id' => $product->id, 'qty' => 2],
        ])), 'Insufficient stock');
        $this->assertSame(2, StockMovement::count());
        $this->assertEquals(5, $this->warehouseQty($other));
        $this->assertEquals(5, $other->fresh()->qty);

        config(['without_stock' => 'yes']);
        $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 2]]));
        $this->assertEquals(-1, $this->warehouseQty($product));

        config(['without_stock' => null]);
        $this->companyWithInventoryPolicy(['negative_stock' => 'warn']);
        $warned = $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1]]));
        $this->assertStringContainsString('Insufficient stock', $warned->warnings_json[0]);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_reversal_restores_stock_with_traceable_history_once(): void
    {
        $product = $this->product(['is_imei' => true]);
        $receipt = $this->receive($product, 2, 10, 1, ['serials' => ['R-1', 'R-2']]);
        $issue = $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'serials' => ['R-1']]]));

        $reversal = $this->movements()->reverse($issue, 'Customer cancelled', 1, '2026-10-05');

        $this->assertSame('reversal', $reversal->movement_type);
        $this->assertSame($issue->id, $reversal->reversal_of_id);
        $this->assertSame('reversed', $issue->fresh()->status);
        $this->assertEquals(10, $reversal->lines->sole()->value);
        $this->assertEquals(2, $this->warehouseQty($product));
        $this->assertSame(StockIdentity::IN_STOCK, StockIdentity::where('identity_no', 'R-1')->value('status'));
        $this->assertRejected(fn () => $this->movements()->reverse($issue, 'again'), 'already reversed');
        $this->assertRejected(fn () => $this->movements()->reverse($reversal, 'undo'), 'cannot itself be reversed');

        // Reversing the receipt removes both serials; it is blocked once stock has left.
        $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'serials' => ['R-2']]]));
        $this->assertRejected(fn () => $this->movements()->reverse($receipt, 'Wrong supplier'), 'not in stock');
        $this->assertSame('posted', $receipt->fresh()->status);
    }

    public function test_idempotent_replay_does_not_post_twice(): void
    {
        $product = $this->product();
        $command = $this->command([['product_id' => $product->id, 'qty' => 3, 'unit_cost' => 1]], 1, ['idempotencyKey' => 'purchase:7']);

        $first = $this->movements()->receive($command);
        $second = $this->movements()->receive($command);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(3, $product->fresh()->qty);
        $this->assertRejected(fn () => $this->movements()->issue($command), 'already used');
    }

    public function test_units_convert_to_base_quantity_and_cost_without_precision_loss(): void
    {
        DB::table('units')->insert([
            ['id' => 1, 'unit_code' => 'PCS', 'unit_name' => 'Piece', 'base_unit' => null, 'operator' => '*', 'operation_value' => 1],
            ['id' => 2, 'unit_code' => 'BOX', 'unit_name' => 'Box', 'base_unit' => 1, 'operator' => '*', 'operation_value' => 12],
            ['id' => 3, 'unit_code' => 'CASE', 'unit_name' => 'Case', 'base_unit' => null, 'operator' => '*', 'operation_value' => 1],
            ['id' => 4, 'unit_code' => 'MTR', 'unit_name' => 'Metre', 'base_unit' => null, 'operator' => '*', 'operation_value' => 1],
        ]);
        $product = $this->product(['unit_id' => 1]);
        DB::table('product_uom_conversions')->insert(['product_id' => $product->id, 'from_uom_id' => 3, 'to_uom_id' => 1, 'factor' => 48, 'rounding_scale' => 4]);

        $boxes = $this->movements()->receive($this->command([['product_id' => $product->id, 'qty' => 2, 'uom_id' => 2, 'unit_cost' => 120]]));
        $this->assertEquals(24, $boxes->lines->sole()->qty_base);
        $this->assertEquals(10, $boxes->lines->sole()->unit_cost);
        $this->assertEquals(2, $boxes->lines->sole()->uom_qty);
        $case = $this->movements()->receive($this->command([['product_id' => $product->id, 'qty' => 1, 'uom_id' => 3, 'unit_cost' => 480]]));
        $this->assertEquals(48, $case->lines->sole()->qty_base);
        $this->assertEquals(72, $product->fresh()->qty);
        $this->assertRejected(fn () => $this->receive($product, 1, 1, 1, ['uom_id' => 4]), 'No conversion');

        $fabric = $this->product(['code' => 'FAB', 'unit_id' => 4]);
        $this->receive($fabric, 125.375, 80, 1, ['uom_id' => 4]);
        $this->assertSame('125.3750', StockMovementLine::where('product_id', $fabric->id)->value('qty_base'));
        $this->assertEquals(125.375, $this->warehouseQty($fabric));
    }

    public function test_variant_quantities_are_projected_and_required(): void
    {
        $product = $this->product(['is_variant' => true]);
        DB::table('product_variants')->insert(['product_id' => $product->id, 'variant_id' => 9, 'position' => 1, 'item_code' => 'V9', 'qty' => 0]);

        $this->receive($product, 6, 2, 1, ['variant_id' => 9]);

        $this->assertEquals(6, DB::table('product_variants')->value('qty'));
        $this->assertEquals(6, DB::table('product_warehouse')->where('variant_id', 9)->value('qty'));
        $this->assertRejected(fn () => $this->receive($product, 1, 2), 'has variants');
        $this->assertRejected(fn () => $this->receive($product, 1, 2, 1, ['variant_id' => 8]), 'does not belong');
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_dimensioned_piece_moves_whole_and_issues_partially(): void
    {
        $product = $this->product();
        $receipt = $this->receive($product, 1.5, 1000, 1, ['dimensions' => [
            'identity_no' => 'LOG-1', 'length' => 5, 'width' => 0.5, 'thickness' => 0.6, 'dimension_uom' => 'm', 'grade' => 'A',
        ]]);
        $piece = StockIdentity::sole();
        $this->assertSame(StockIdentity::PIECE, $piece->identity_type);
        $this->assertEquals(1.5, StockDimension::sole()->computed_volume);
        $this->assertSame($piece->id, $receipt->lines->sole()->stock_identity_id);

        $this->assertRejected(fn () => $this->movements()->transfer($this->command([['product_id' => $product->id, 'qty' => 1, 'stock_identity_id' => $piece->id]], 1, ['toWarehouseId' => 2])), 'moved whole');
        $this->movements()->transfer($this->command([['product_id' => $product->id, 'qty' => 1.5, 'stock_identity_id' => $piece->id]], 1, ['toWarehouseId' => 2]));
        $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'stock_identity_id' => $piece->id]], 2));
        $this->assertSame(StockIdentity::IN_STOCK, $piece->fresh()->status);
        $this->assertRejected(fn () => $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 1, 'stock_identity_id' => $piece->id]], 2)), 'only 0.5');
        $this->movements()->issue($this->command([['product_id' => $product->id, 'qty' => 0.5, 'stock_identity_id' => $piece->id]], 2));
        $this->assertSame(StockIdentity::ISSUED, $piece->fresh()->status);
        $this->assertEquals(0, $product->fresh()->qty);
    }

    public function test_adjustment_is_signed_and_posted_history_is_immutable(): void
    {
        $product = $this->product(['cost' => 3]);
        $adjustment = $this->movements()->adjust($this->command([
            ['product_id' => $product->id, 'qty' => 4],
            ['product_id' => $product->id, 'qty' => -1],
        ], 1, ['reason' => 'Stock count']));
        $this->assertEquals(3, $this->warehouseQty($product));
        $this->assertEquals([12, -3], $adjustment->lines->map(fn ($l) => (float) $l->value)->all());

        $this->assertRejected(fn () => $adjustment->lines->first()->update(['qty_base' => 99]), 'immutable', LogicException::class);
        $this->assertRejected(fn () => $adjustment->delete(), 'cannot be deleted', LogicException::class);
        $this->assertRejected(fn () => $adjustment->update(['movement_date' => '2026-01-01']), 'immutable', LogicException::class);
        $this->assertRejected(fn () => $this->movements()->adjust($this->command([['product_id' => $product->id, 'qty' => 0]])), 'nonzero');
    }

    public function test_availability_reports_on_hand_ledger_and_pending_sale_reservations(): void
    {
        $product = $this->product();
        $this->receive($product, 10, 1);
        $saleId = DB::table('sales')->insertGetId([
            'reference_no' => 'P-1', 'user_id' => 1, 'customer_id' => 1, 'warehouse_id' => 1, 'biller_id' => 1,
            'item' => 1, 'total_qty' => 3, 'total_discount' => 0, 'total_tax' => 0, 'total_price' => 30,
            'grand_total' => 30, 'sale_status' => 2, 'payment_status' => 1,
        ]);
        DB::table('product_sales')->insert([
            'sale_id' => $saleId, 'product_id' => $product->id, 'qty' => 3, 'sale_unit_id' => 0,
            'net_unit_price' => 10, 'discount' => 0, 'tax_rate' => 0, 'tax' => 0, 'total' => 30,
        ]);

        $availability = app(InventoryAvailabilityService::class)->forProduct($product->id, 1);

        $this->assertSame(10.0, $availability['on_hand']);
        $this->assertSame(10.0, $availability['ledger_on_hand']);
        $this->assertSame(3.0, $availability['reserved']);
        $this->assertSame(7.0, $availability['available']);
    }

    public function test_command_rejects_foreign_company_warehouse_and_product(): void
    {
        $company = $this->companyWithInventoryPolicy([]);
        $product = $this->product();
        $context = new \App\Services\Platform\CompanyContext($company + 1, 1, 1);

        $this->assertRejected(fn () => $this->movements()->receive($this->command(
            [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => 1]], 1, ['context' => $context],
        )), 'active company');

        $movement = $this->receive($product, 1, 1);
        $this->assertSame($company, $movement->company_id);
        $this->assertSame($company, StockMovementLine::sole()->company_id);
        $this->assertNotNull(StockLine::fromArray(['product_id' => 1, 'qty' => 1]));
    }

    private function assertRejected(callable $action, string $message, string $class = InvalidArgumentException::class): void
    {
        try {
            $action();
        } catch (\Throwable $error) {
            $this->assertInstanceOf($class, $error, $error->getMessage());
            $this->assertStringContainsString($message, $error->getMessage());

            return;
        }
        $this->fail("Expected rejection containing: {$message}");
    }
}
