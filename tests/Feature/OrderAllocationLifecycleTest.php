<?php

namespace Tests\Feature;

use App\Models\CommercialOrderItem;
use App\Models\Purchase;
use App\Services\Commercial\OrderLifecycleService;
use App\Services\ERP\PurchaseService;
use App\Services\ERP\SaleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommercialTestCase;

class OrderAllocationLifecycleTest extends CommercialTestCase
{
    public function test_fractional_partial_fulfilment_keeps_unchanged_conversion_despite_rounding(): void
    {
        $product = $this->stock(100); $product->forceFill(['unit_id' => 1])->save();
        DB::table('units')->insert(['id' => 2, 'company_id' => $this->company->id, 'name' => 'Yard', 'unit_name' => 'Yard', 'unit_code' => 'YD']);
        DB::table('product_uom_conversions')->insert(['company_id' => $this->company->id, 'product_id' => $product->id,
            'from_uom_id' => 2, 'to_uom_id' => 1, 'factor' => 0.9144, 'rounding_scale' => 4]);
        $order = app(OrderLifecycleService::class)->createSale($this->saleData($product, ['items' => [
            ['product_id' => $product->id, 'qty' => 1, 'sale_unit_id' => 2, 'net_unit_price' => 10]]]), 'fractional-order', $this->context(), 1);
        app(\App\Services\Commercial\CommercialApplicationService::class)->create('sale', $this->saleData($product, ['sales_order_id' => $order->id,
            'items' => [['product_id' => $product->id, 'qty' => 0.1, 'sale_unit_id' => 2, 'net_unit_price' => 10, 'order_item_id' => $order->items()->first()->id]]]),
            'fractional-invoice', 1, $this->context());
        $remaining = app(OrderLifecycleService::class)->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(0.0914, $remaining['billed_qty_base']);
        $this->assertEquals(0.823, $remaining['remaining_billing_base']);
    }

    public function test_order_migration_resumes_partial_install_and_missing_indexes(): void
    {
        foreach (['commercial_order_events', 'commercial_order_allocations', 'commercial_order_items'] as $table) Schema::drop($table);
        Schema::table('sales_orders', fn ($table) => $table->dropUnique('sales_orders_company_id_reference_no_unique'));
        $migration = require database_path('migrations/2026_10_17_000008_create_commercial_order_lifecycle.php');
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasTable('commercial_order_events'));
        $this->assertTrue(collect(Schema::getIndexes('sales_orders'))->contains('name', 'sales_orders_company_id_reference_no_unique'));
        $order = $this->order();
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_client_internal_quantity_keys_cannot_bypass_order_limits(): void
    {
        $order = $this->order(false, 5);
        $item = $order->items()->first();
        $data = ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'sales_order_id' => $order->id,
            'items' => [['product_id' => $item->product_id, 'qty' => 50, 'net_unit_price' => 10, 'order_item_id' => $item->id,
                '_base_override' => 1, '_material_base' => 50, '_order_parts' => [['order_item_id' => $item->id, 'qty_base' => 1]]]]];
        $this->postJson('/commercial/sale', $data, ['Idempotency-Key' => 'poisoned-quantity'])->assertUnprocessable();
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('commercial_order_allocations')->count());
    }

    public function test_material_plus_direct_quantity_on_one_line_consumes_billing_once(): void
    {
        $order = $this->order(false, 10); $material = $this->materialFor($order, false, 5);
        $item = $order->items()->first(); $source = $material->items()->first();
        $this->postJson('/commercial/sale', ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'delivery_challan_id' => $material->id, 'items' => [['product_id' => $item->product_id, 'order_item_id' => $item->id,
                'qty' => 7, 'net_unit_price' => 10, 'material_sources' => [['document_id' => $material->id, 'item_id' => $source->id, 'qty_base' => 5]]]]],
            ['Idempotency-Key' => 'mixed-order'])->assertCreated();
        $remaining = $this->orders->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(3, $remaining['remaining_billing_base']);
        $this->assertEquals(3, $remaining['remaining_delivery_base']);
    }

    private OrderLifecycleService $orders;
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('commercial_order_items')) (require database_path('migrations/2026_10_17_000008_create_commercial_order_lifecycle.php'))->up();
        $this->orders = app(OrderLifecycleService::class);
    }

    private function order(bool $purchase = false, float $qty = 30, array $items = [])
    {
        $product = $this->stock(100); $product->forceFill(['unit_id' => 1])->save();
        $lines = $items ?: [['product_id' => $product->id, 'qty' => $qty, $purchase ? 'net_unit_cost' : 'net_unit_price' => 10,
            $purchase ? 'purchase_unit_id' : 'sale_unit_id' => 1]];
        $data = [$purchase ? 'supplier_id' : 'customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'items' => $lines, 'status' => 4];
        if (!$purchase) return $this->orders->createSale($data, 'sales-order-'.uniqid(), $this->context(), 1);
        return DB::transaction(function () use ($data) {
            $order = app(PurchaseService::class)->createPurchase($data, 1, $this->context(), true);
            $order->forceFill(['branch_id' => $this->branch->id, 'financial_year_id' => $this->year->id])->save();
            $this->orders->initializePurchase($order, $data, $this->context());
            return $order;
        });
    }

    private function fulfil($order, float $qty, bool $purchase = false, ?int $itemId = null)
    {
        return DB::transaction(function () use ($order, $qty, $purchase, $itemId) {
            $kind = $purchase ? 'purchase' : 'sale';
            $item = CommercialOrderItem::where('order_kind', $kind)->where('order_id', $order->id)->orderBy('id')->firstOrFail();
            $data = [$purchase ? 'supplier_id' : 'customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
                $purchase ? 'purchase_order_id' : 'sales_order_id' => $order->id,
                'items' => [['product_id' => $item->product_id, 'qty' => $qty, 'order_item_id' => $itemId ?? $item->id,
                    $purchase ? 'purchase_unit_id' : 'sale_unit_id' => $item->uom_id, $purchase ? 'net_unit_cost' : 'net_unit_price' => 10]]];
            $plan = $this->orders->prepareConsumer($kind, $data, $this->context(), 1);
            $document = $purchase ? app(PurchaseService::class)->createPurchase($data, 1, $this->context(), true)
                : app(SaleService::class)->createSale($data, 1, $this->context(), true);
            $this->orders->consume($document, $plan, $this->context());
            return $document;
        });
    }

    public function test_sales_orders_have_separate_numbering_no_stock_financial_or_pending_sale_effects_and_retry_is_exact(): void
    {
        $order = $this->order();
        $this->assertStringContainsString('-SO-', $order->reference_no);
        $this->assertSame(0, DB::table('sales')->count());
        foreach (['stock_movements', 'journal_entries', 'account_open_items', 'payments'] as $table) $this->assertSame(0, DB::table($table)->count());
        $item = $order->items()->first();
        $data = ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'items' => [['product_id' => $item->product_id, 'qty' => 3, 'net_unit_price' => 10]]];
        $first = $this->orders->createSale($data, 'repeat', $this->context(), 1);
        $this->assertSame($first->id, $this->orders->createSale($data, 'repeat', $this->context(), 1)->id);
        $data['items'][0]['qty'] = 4;
        $this->expectException(ValidationException::class);
        $this->orders->createSale($data, 'repeat', $this->context(), 1);
    }

    public function test_sales_partial_full_reversal_reopens_exactly_once(): void
    {
        $order = $this->order();
        $this->fulfil($order, 10);
        $this->assertSame('partial', $order->fresh()->status);
        $bill = $this->fulfil($order, 20);
        $this->assertSame('fulfilled', $order->fresh()->status);
        DB::transaction(function () use ($bill) { $this->orders->release($bill, $this->context()); $this->orders->release($bill, $this->context()); });
        $remaining = $this->orders->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(20, $remaining['remaining_qty']);
        $this->assertEquals(10, $remaining['delivered_qty_base']);
        $this->assertSame('partial', $order->fresh()->status);
    }

    public function test_purchase_partial_full_reversal_uses_line_allocations_and_reopens(): void
    {
        $order = $this->order(true, 3);
        $this->assertStringContainsString('-PO-', $order->reference_no);
        $this->fulfil($order, 2, true);
        $this->assertSame('partial', $order->fresh()->attributes_json['order_status']);
        $bill = $this->fulfil($order, 1, true);
        $this->assertNotEmpty($order->fresh()->attributes_json['fulfilled_at']);
        DB::transaction(fn () => $this->orders->release($bill, $this->context()));
        $this->assertArrayNotHasKey('fulfilled_at', $order->fresh()->attributes_json);
        $this->assertEquals(1, $this->orders->remaining('purchase', $order->id, $this->context())['items'][0]['remaining_qty']);
    }

    public function test_over_fulfilment_rolls_back_then_a_second_consumer_cannot_take_same_balance(): void
    {
        $order = $this->order(); $this->fulfil($order, 29);
        try { $this->fulfil($order, 2); $this->fail('Over-fulfilment accepted.'); } catch (ValidationException) {}
        $this->assertSame(1, DB::table('sales')->count());
        $this->fulfil($order, 1);
        $this->expectException(ValidationException::class); $this->fulfil($order, 1);
    }

    public function test_cancellation_preserves_consumed_quantities_and_retry_has_one_audit_event(): void
    {
        $order = $this->order(); $this->fulfil($order, 10);
        $this->orders->cancelRemainder('sale', $order->id, 'Customer cancelled balance', '2026-10-03', $this->context(), 1);
        $this->orders->cancelRemainder('sale', $order->id, 'Customer cancelled balance', '2026-10-03', $this->context(), 1);
        $item = $this->orders->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(20, $item['cancelled_qty_base']); $this->assertEquals(10, $item['billed_qty_base']);
        $this->assertSame(1, DB::table('commercial_order_events')->where('event', 'cancel_remainder')->count());
        $this->expectException(ValidationException::class); $this->fulfil($order, 1);
    }

    public function test_duplicate_product_lines_require_exact_order_item_identity(): void
    {
        $product = $this->stock(); $product->forceFill(['unit_id' => 1])->save();
        $order = $this->order(false, 30, [['product_id' => $product->id, 'qty' => 10, 'net_unit_price' => 10], ['product_id' => $product->id, 'qty' => 20, 'net_unit_price' => 12]]);
        $items = $order->items()->get();
        $this->fulfil($order, 10, false, $items[0]->id);
        $this->assertEquals(20, $this->orders->remaining('sale', $order->id, $this->context())['items'][1]['remaining_qty']);
        $this->expectException(ValidationException::class); $this->fulfil($order, 1, false, $items[0]->id);
    }

    public function test_foreign_party_warehouse_and_earlier_date_fail_without_allocations(): void
    {
        $order = $this->order(); $item = $order->items()->first();
        foreach ([['customer_id' => 999], ['warehouse_id' => 2], ['business_date' => '2026-10-02']] as $override) {
            try {
                DB::transaction(fn () => $this->orders->prepareConsumer('sale', $override + ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'sales_order_id' => $order->id,
                    'items' => [['product_id' => $item->product_id, 'qty' => 1, 'sale_unit_id' => 1, 'order_item_id' => $item->id]]], $this->context(), 1));
                $this->fail('Invalid context accepted.');
            } catch (ValidationException) {}
        }
        $this->assertSame(0, DB::table('commercial_order_allocations')->count());
    }

    public function test_order_migration_refuses_destructive_rollback_after_history_exists(): void
    {
        $this->order();
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_17_000008_create_commercial_order_lifecycle.php'))->down();
    }

    public function test_amendments_keep_fulfilled_identity_quantity_and_rate_and_reject_stale_version(): void
    {
        $order = $this->order(); $this->fulfil($order, 10); $item = $order->items()->first();
        $data = ['business_date' => '2026-10-03', 'reason' => 'Customer increased quantity', 'version' => 1,
            'items' => [['order_item_id' => $item->id, 'qty' => 40, 'unit_rate' => 10]]];
        $this->orders->amend('sale', $order->id, $data, 'amend', $this->context(), 1);
        $this->orders->amend('sale', $order->id, $data, 'amend', $this->context(), 1);
        $this->assertEquals(30, $this->orders->remaining('sale', $order->id, $this->context())['items'][0]['remaining_qty']);
        $this->assertSame(1, DB::table('commercial_order_events')->where('event', 'amended')->count());
        foreach ([['version' => 1], ['items' => [['order_item_id' => $item->id, 'qty' => 9, 'unit_rate' => 10]]], ['items' => [['order_item_id' => $item->id, 'qty' => 40, 'unit_rate' => 12]]]] as $change) {
            try { $this->orders->amend('sale', $order->id, $change + ['version' => 2] + $data, uniqid(), $this->context(), 1); $this->fail('Unsafe amendment accepted.'); }
            catch (ValidationException) {}
        }
        $this->assertEquals(40, $item->fresh()->qty);
    }

    public function test_purchase_order_amendment_reuses_shared_pricing_and_retains_line_ids(): void
    {
        $order = $this->order(true, 3); $item = CommercialOrderItem::where('order_kind', 'purchase')->where('order_id', $order->id)->first();
        $source = $item->source_line_id;
        $this->orders->amend('purchase', $order->id, ['business_date' => '2026-10-03', 'reason' => 'Supplier confirmed extra quantity', 'version' => 1,
            'items' => [['order_item_id' => $item->id, 'qty' => 4, 'unit_rate' => 15]]], 'po-amend', $this->context(), 1);
        $this->assertEquals(60, $order->fresh()->grand_total);
        $this->assertEquals(4, DB::table('product_purchases')->where('id', $source)->value('qty'));
        $this->assertSame($source, $item->fresh()->source_line_id);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_converted_units_consume_base_quantities_while_remaining_preserves_original_unit(): void
    {
        $order = $this->order(false, 24); $item = $order->items()->first();
        DB::table('units')->insert(['id' => 2, 'company_id' => $this->company->id, 'name' => 'Dozen', 'unit_name' => 'DOZ', 'unit_code' => 'DOZ']);
        DB::table('product_uom_conversions')->insert(['company_id' => $this->company->id, 'product_id' => $item->product_id,
            'from_uom_id' => 2, 'to_uom_id' => 1, 'factor' => 12, 'rounding_scale' => 4]);
        DB::transaction(function () use ($order, $item) {
            $data = ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'sales_order_id' => $order->id,
                'items' => [['order_item_id' => $item->id, 'product_id' => $item->product_id, 'qty' => 1, 'sale_unit_id' => 2, 'net_unit_price' => 120]]];
            $plan = $this->orders->prepareConsumer('sale', $data, $this->context(), 1);
            $bill = app(SaleService::class)->createSale($data, 1, $this->context(), true);
            $this->orders->consume($bill, $plan, $this->context());
        });
        $remaining = $this->orders->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(12, $remaining['remaining_qty']); $this->assertEquals(12, $remaining['billed_qty_base']);
        $this->assertSame(1, $remaining['uom_id']);
    }

    public function test_pristine_legacy_purchase_order_is_snapshotted_without_changing_stock_or_numbers(): void
    {
        $order = $this->order(true, 3);
        DB::table('commercial_order_items')->delete();
        (require database_path('migrations/2026_10_17_000009_snapshot_provable_purchase_orders.php'))->up();
        (require database_path('migrations/2026_10_17_000009_snapshot_provable_purchase_orders.php'))->up();
        $this->assertSame(1, DB::table('commercial_order_items')->count());
        $this->assertSame($order->reference_no, $order->fresh()->reference_no);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_later_purchase_receipt_advances_delivery_without_billing_order_twice(): void
    {
        $order = $this->order(true, 3); $item = CommercialOrderItem::where('order_kind', 'purchase')->where('order_id', $order->id)->first();
        $bill = DB::transaction(function () use ($order, $item) {
            $data = ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'purchase_order_id' => $order->id, 'status' => 2,
                'items' => [['order_item_id' => $item->id, 'product_id' => $item->product_id, 'qty' => 3, 'received_qty' => 1, 'purchase_unit_id' => 1, 'net_unit_cost' => 10]]];
            $plan = $this->orders->prepareConsumer('purchase', $data, $this->context(), 1);
            $bill = app(PurchaseService::class)->createPurchase($data, 1, $this->context(), true); $this->orders->consume($bill, $plan, $this->context());
            return $bill;
        });
        $this->assertSame('partial', $order->fresh()->attributes_json['order_status']);
        DB::transaction(function () use ($bill) {
            $bill->productPurchases()->first()->forceFill(['recieved' => 3])->save();
            $this->orders->receivePurchase($bill, $this->context());
            $this->orders->receivePurchase($bill, $this->context());
        });
        $remaining = $this->orders->remaining('purchase', $order->id, $this->context())['items'][0];
        $this->assertEquals(3, $remaining['billed_qty_base']); $this->assertEquals(3, $remaining['delivered_qty_base']);
        $this->assertSame('fulfilled', $order->fresh()->attributes_json['order_status']);
    }

    private function materialFor($order, bool $purchase, float $qty)
    {
        if (!Schema::hasTable('material_settlements')) {
            if (!Schema::hasColumn('warehouses', 'is_active')) Schema::table('warehouses', fn (\Illuminate\Database\Schema\Blueprint $t) => $t->boolean('is_active')->default(true));
            (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
            (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
            foreach ([['GRNI', 'liability', 'goods_received_not_invoiced'], ['GDNI', 'asset', 'goods_delivered_not_invoiced'], ['VAR', 'expense', 'inventory_variance']] as [$code, $type, $subType]) {
                \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => $code, 'name' => $subType, 'type' => $type, 'sub_type' => $subType]);
            }
            app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
        }
        $kind = $purchase ? 'purchase' : 'sale';
        $item = CommercialOrderItem::where('order_kind', $kind)->where('order_id', $order->id)->firstOrFail();
        $data = [$purchase ? 'supplier_id' : 'customer_id' => 1, 'warehouse_id' => 1, $purchase ? 'grn_date' : 'challan_date' => '2026-10-03',
            'unit_id' => [1], 'product_id' => [$item->product_id], 'qty' => [$qty], $purchase ? 'cost' : 'rate' => [10], 'purpose' => 'approval'];
        $response = $this->postJson($purchase ? '/goods-received-notes' : '/delivery-challans', $data);
        $this->assertSame(201, $response->status(), $response->getContent());
        $document = ($purchase ? \App\Models\GoodsReceivedNote::class : \App\Models\DeliveryChallan::class)::findOrFail($response->json($purchase ? 'grn.id' : 'challan.id'));
        DB::transaction(function () use ($order, $purchase, $item, $qty, $document) {
            $data = [$purchase ? 'supplier_id' : 'customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', $purchase ? 'purchase_order_id' : 'sales_order_id' => $order->id,
                'items' => [['order_item_id' => $item->id, 'product_id' => $item->product_id, 'qty' => $qty, 'unit_id' => 1]]];
            $this->orders->consume($document, $this->orders->prepareConsumer($purchase ? 'grn' : 'dc', $data, $this->context(), 1), $this->context());
        });
        return $document;
    }

    public function test_grn_bill_tracks_delivery_and_billing_once_then_bill_reversal_preserves_received_quantity(): void
    {
        $order = $this->order(true); $material = $this->materialFor($order, true, 10);
        $item = CommercialOrderItem::where('order_kind', 'purchase')->where('order_id', $order->id)->first();
        $bill = app(\App\Services\Commercial\CommercialApplicationService::class)->create('purchase', ['supplier_id' => 1, 'warehouse_id' => 1,
            'business_date' => '2026-10-03', 'goods_received_note_id' => $material->id,
            'items' => [['product_id' => $item->product_id, 'qty' => 10, 'purchase_unit_id' => 1, 'net_unit_cost' => 10]]], 'grn-order-bill', 1, $this->context());
        $remaining = $this->orders->remaining('purchase', $order->id, $this->context())['items'][0];
        $this->assertEquals(10, $remaining['delivered_qty_base']); $this->assertEquals(10, $remaining['billed_qty_base']);
        $this->assertSame(1, DB::table('stock_movements')->whereNull('reversal_of_id')->count());
        app(\App\Services\Commercial\CommercialReversalService::class)->reverse($bill, '2026-10-03', 'Wrong order bill', $this->context());
        $remaining = $this->orders->remaining('purchase', $order->id, $this->context())['items'][0];
        $this->assertEquals(10, $remaining['delivered_qty_base']); $this->assertEquals(0, $remaining['billed_qty_base']);
    }

    public function test_dc_invoice_does_not_count_delivery_twice_and_partial_return_reopens_only_delivery(): void
    {
        $order = $this->order(); $material = $this->materialFor($order, false, 10);
        $item = $order->items()->first();
        app(\App\Services\Commercial\CommercialApplicationService::class)->create('sale', ['customer_id' => 1, 'warehouse_id' => 1,
            'business_date' => '2026-10-03', 'delivery_challan_id' => $material->id,
            'items' => [['product_id' => $item->product_id, 'qty' => 4, 'sale_unit_id' => 1, 'net_unit_price' => 10]]], 'dc-order-invoice', 1, $this->context());
        $remaining = $this->orders->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(10, $remaining['delivered_qty_base']); $this->assertEquals(4, $remaining['billed_qty_base']);
        $materialItem = $material->items()->first();
        DB::transaction(function () use ($material, $materialItem) {
            app(\App\Services\Commercial\MaterialDocumentService::class)->returnOpen($material, [$materialItem->id => 3], '2026-10-03', 'Unapproved cloth returned', $this->context(), 1, 'dc-order-return');
            app(\App\Services\Commercial\MaterialDocumentService::class)->returnOpen($material, [$materialItem->id => 3], '2026-10-03', 'Unapproved cloth returned', $this->context(), 1, 'dc-order-return');
        });
        $remaining = $this->orders->remaining('sale', $order->id, $this->context())['items'][0];
        $this->assertEquals(7, $remaining['delivered_qty_base']); $this->assertEquals(4, $remaining['billed_qty_base']);
        $this->assertEquals(23, $remaining['remaining_delivery_base']);
        $this->assertEquals(3, DB::table('commercial_order_allocations')->where('consumer_type', 'dc')->value('returned_qty_base'));
    }
}
