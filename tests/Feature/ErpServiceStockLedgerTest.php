<?php

namespace Tests\Feature;

use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Transfer;
use App\Services\ERP\InventoryService;
use App\Services\ERP\PurchaseService;
use App\Services\ERP\SaleService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryReconciliationService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use App\Services\Inventory\StockPolicyException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CompanyErpServiceTestCase;

class ErpServiceStockLedgerTest extends CompanyErpServiceTestCase
{
    private function product(array $attributes = []): Product
    {
        return Product::forceCreate($attributes + [
            'name' => 'Ledger item', 'code' => 'LEDGER', 'qty' => 0, 'cost' => 0, 'price' => 10,
            'company_id' => $this->company->id,
        ]);
    }

    private function receive(Product $product, float $qty, float $cost, int $warehouseId = 1): StockMovement
    {
        return app(InventoryMovementService::class)->receive(new StockMovementCommand(
            date: '2026-10-03',
            lines: [StockLine::fromArray(['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => $cost])],
            warehouseId: $warehouseId,
            context: $this->resolver->forActor(null, 1),
        ));
    }

    private function warehouseQty(Product $product, int $warehouseId = 1): float
    {
        return (float) DB::table('product_warehouse')->where('product_id', $product->id)->where('warehouse_id', $warehouseId)->sum('qty');
    }

    public function test_purchases_and_sale_post_company_ledger_movements_and_cogs_uses_posted_average_cost(): void
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
        $this->assertSame($this->company->id, (int) $issue->company_id);
        $this->assertSame($this->branch->id, (int) $issue->branch_id);
        $this->assertSame($this->year->id, (int) $issue->financial_year_id);
        $this->assertSame($sale->created_at->toDateString(), $issue->movement_date->toDateString());
        $this->assertEquals(-30, $issue->lines->sole()->value);
        // Product master cost was overwritten to 7 by the last purchase; COGS stays at the posted average of 6.
        $journal = JournalEntry::where('reference_type', 'sale')->sole();
        $this->assertEquals(30, $journal->items()->whereHas('account', fn ($q) => $q->where('code', '5010'))->sum('debit'));
        $this->assertSame(2, StockMovement::where('source_type', 'purchase')->where('company_id', $this->company->id)->count());
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

    public function test_sale_beyond_stock_is_blocked_and_rolls_back_document_payment_and_number(): void
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
        $this->assertSame(0, DB::table('document_number_reservations')->count());
        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(1, $this->warehouseQty($product));
    }

    public function test_foreign_or_duplicate_projection_rows_block_posting(): void
    {
        $product = $this->product();
        $this->receive($product, 5, 2);
        DB::table('product_warehouse')->where('product_id', $product->id)->update(['company_id' => $this->other->id]);

        try {
            app(SaleService::class)->createSale([
                'customer_id' => 1, 'warehouse_id' => 1,
                'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_price' => 10]],
            ], 1);
            $this->fail('A foreign projection row must block the sale.');
        } catch (StockPolicyException $error) {
            $this->assertStringContainsString('requires reconciliation', $error->getMessage());
        }

        DB::table('product_warehouse')->where('product_id', $product->id)->update(['company_id' => $this->company->id]);
        DB::table('product_warehouse')->insert(['product_id' => $product->id, 'warehouse_id' => 1, 'qty' => 0, 'company_id' => $this->company->id]);
        $this->expectException(StockPolicyException::class);
        $this->receive($product, 1, 2);
    }

    public function test_serial_sale_issues_the_named_serials_through_the_service(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->boolean('is_imei')->nullable());
        $product = $this->product(['is_imei' => true]);
        app(PurchaseService::class)->createPurchase([
            'supplier_id' => 1, 'warehouse_id' => 1,
            'items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_cost' => 50, 'imei_number' => 'SN-1,SN-2']],
        ], 1);

        app(SaleService::class)->createSale([
            'customer_id' => 1, 'warehouse_id' => 1,
            'items' => [['product_id' => $product->id, 'qty' => 1, 'net_unit_price' => 80, 'imei_number' => 'SN-2']],
        ], 1);

        $this->assertSame(['SN-1' => 'in_stock', 'SN-2' => 'issued'],
            DB::table('stock_identities')->orderBy('identity_no')->pluck('status', 'identity_no')->all());
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
        $this->assertSame($transfer->reference_no, $movement->source_no);
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
