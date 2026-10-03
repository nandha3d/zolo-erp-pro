<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockMovement;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Transfer;
use App\Services\ERP\InventoryService;
use App\Services\ERP\PurchaseService;
use App\Services\ERP\SaleService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockPolicyException;
use Tests\Support\InventoryLedgerTestCase;

class ErpServiceStockLedgerTest extends InventoryLedgerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['1010', 'asset', 'cash'], ['1100', 'asset', 'accounts_receivable'],
            ['1200', 'asset', 'inventory'], ['2010', 'liability', 'accounts_payable'],
            ['4010', 'revenue', 'sales_revenue'], ['5010', 'expense', 'cogs'],
            ['GIT', 'asset', 'goods_in_transit'],
        ] as [$code, $type, $subType]) {
            ChartOfAccount::create(['code' => $code, 'name' => $subType, 'type' => $type, 'sub_type' => $subType]);
        }
    }

    public function test_purchases_and_sale_post_ledger_movements_and_cogs_uses_posted_average_cost(): void
    {
        $product = $this->product(['cost' => 1]);
        foreach ([5, 7] as $cost) {
            app(PurchaseService::class)->createPurchase([
                'supplier_id' => 1, 'warehouse_id' => 1,
                'items' => [['product_id' => $product->id, 'qty' => 10, 'net_unit_cost' => $cost]],
            ], 1);
        }

        $sale = app(SaleService::class)->createSale([
            'customer_id' => 1, 'warehouse_id' => 1,
            'items' => [['product_id' => $product->id, 'qty' => 5, 'net_unit_price' => 10]],
        ], 1);

        $issue = StockMovement::where('source_type', 'sale')->sole();
        $this->assertSame($sale->id, (int) $issue->source_id);
        $this->assertSame($sale->reference_no, $issue->source_no);
        $this->assertEquals(-30, $issue->lines->sole()->value);
        // Product master cost was overwritten to 7 by the last purchase; COGS stays at the posted average of 6.
        $journal = JournalEntry::where('reference_type', 'sale')->sole();
        $this->assertEquals(30, $journal->items()->whereHas('account', fn ($q) => $q->where('code', '5010'))->sum('debit'));
        $this->assertSame(2, StockMovement::where('source_type', 'purchase')->count());
        $this->assertEquals(15, $product->fresh()->qty);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_partial_purchase_receives_only_received_quantity_and_pending_sale_issues_nothing(): void
    {
        $product = $this->product();
        $purchase = app(PurchaseService::class)->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 2,
            'items' => [['product_id' => $product->id, 'qty' => 10, 'received_qty' => 4, 'net_unit_cost' => 5]],
        ], 1);
        $this->assertEquals(4, StockMovement::where('source_id', $purchase->id)->sole()->lines->sole()->qty_base);

        app(PurchaseService::class)->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1, 'status' => 3,
            'items' => [['product_id' => $product->id, 'qty' => 10, 'net_unit_cost' => 5]],
        ], 1);
        app(SaleService::class)->createSale([
            'customer_id' => 1, 'warehouse_id' => 1, 'sale_status' => 2,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_price' => 10]],
        ], 1);

        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(4, $this->warehouseQty($product));
    }

    public function test_sale_beyond_stock_is_blocked_and_rolls_back_document_and_payment(): void
    {
        $product = $this->product();
        $this->receive($product, 1, 5);

        try {
            app(SaleService::class)->createSale([
                'customer_id' => 1, 'warehouse_id' => 1, 'paid_amount' => 20,
                'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]],
            ], 1);
            $this->fail('Overselling must be blocked.');
        } catch (StockPolicyException $error) {
            $this->assertStringContainsString('Insufficient stock', $error->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(1, $this->warehouseQty($product));
    }

    public function test_transfer_service_posts_one_balanced_movement_and_blocks_missing_source_stock(): void
    {
        $product = $this->product();
        $this->receive($product, 5, 2);

        $transfer = app(InventoryService::class)->transferStock([
            'from_warehouse_id' => 1, 'to_warehouse_id' => 2,
            'items' => [['product_id' => $product->id, 'qty' => 3, 'net_unit_cost' => 99]],
        ], 1);

        $movement = StockMovement::where('source_type', 'transfer')->sole();
        $this->assertSame($transfer->id, (int) $movement->source_id);
        $this->assertEquals(0, $movement->lines->sum('value'));
        $this->assertEquals(2, $movement->lines->last()->unit_cost);
        $this->assertEquals(2, $this->warehouseQty($product, 1));
        $this->assertEquals(3, $this->warehouseQty($product, 2));

        $this->expectException(StockPolicyException::class);
        try {
            app(InventoryService::class)->transferStock([
                'from_warehouse_id' => 1, 'to_warehouse_id' => 2,
                'items' => [['product_id' => $product->id, 'qty' => 3, 'net_unit_cost' => 2]],
            ], 1);
        } finally {
            $this->assertSame(1, Transfer::count());
        }
    }
}
