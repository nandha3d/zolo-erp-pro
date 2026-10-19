<?php

namespace Tests\Feature;

use App\Models\DeliveryChallan;
use App\Models\GoodsReceivedNote;
use App\Models\Product;
use App\Services\Commercial\CommercialReversalService;
use App\Services\Commercial\PurchaseApplicationService;
use App\Services\Commercial\PurchaseCommand;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommercialTestCase;

/**
 * Physical movement first, commercial settlement later: a Supplier GRN / Customer DC moves the stock and accrues it,
 * and the bill or invoice made from it settles that accrual without moving the same goods again.
 */
class MaterialDocumentPostingTest extends CommercialTestCase
{
    public function test_price_variance_uses_one_held_quantity_pool_for_repeated_sources_and_bill_lines(): void
    {
        foreach ([false, true] as $splitOneSource) {
            $product = $this->stock(0);
            $a = $this->grn($product, $splitOneSource ? 100 : 50, 5);
            $b = $splitOneSource ? $a : $this->grn($product, 50, 5);
            app(\App\Services\Commercial\CommercialApplicationService::class)->create('sale', $this->saleData($product, [
                'items' => [['product_id' => $product->id, 'qty' => 90, 'net_unit_price' => 10]]]), 'sell-before-price-'.$product->id, 1, $this->context());
            $beforeInventory = $this->balance('inventory'); $beforeCogs = $this->balance('cogs');
            $items = [];
            foreach ([$a, $b] as $source) $items[] = ['product_id' => $product->id, 'qty' => 50, 'net_unit_cost' => 7,
                'material_sources' => [['document_id' => $source->id, 'item_id' => $source->items()->first()->id, 'qty_base' => 50]]];
            $bill = app(\App\Services\Commercial\CommercialApplicationService::class)->create('purchase', $this->purchaseData($product, [
                'goods_received_note_ids' => array_values(array_unique([$a->id, $b->id])), 'items' => $items]), 'shared-held-'.$product->id, 1, $this->context());
            $this->assertEquals(10, $product->fresh()->qty);
            $this->assertEquals($beforeInventory + 20, $this->balance('inventory'), 'Only 10/100 of the total variance remains in stock.');
            $this->assertEquals($beforeCogs + 180, $this->balance('cogs'));
            $movement = DB::table('stock_movements')->where('source_type', 'purchase')->where('source_id', $bill->id)->first();
            $this->assertEquals(20, DB::table('stock_movement_lines')->where('stock_movement_id', $movement->id)->sum('value'));
        }
    }

    public function test_one_bill_line_can_settle_multiple_sources_and_reject_excess_or_earlier_date_atomically(): void
    {
        $product = $this->stock(0);
        $a = $this->grn($product, 3);
        $b = $this->grn($product, 4);
        $sources = [['document_id' => $a->id, 'item_id' => $a->items->first()->id, 'qty_base' => 3],
            ['document_id' => $b->id, 'item_id' => $b->items->first()->id, 'qty_base' => 2]];
        $data = $this->purchaseData($product, ['goods_received_note_ids' => [$a->id, $b->id], 'items' => [
            ['product_id' => $product->id, 'qty' => 5, 'net_unit_cost' => 5, 'material_sources' => $sources]]]);
        $this->postJson('/commercial/purchase', ['business_date' => '2026-10-02'] + $data, ['Idempotency-Key' => 'earlier'])->assertUnprocessable();
        $bad = $data;
        $bad['items'][0]['qty'] = 4;
        $this->postJson('/commercial/purchase', $bad, ['Idempotency-Key' => 'excess-source'])->assertUnprocessable();
        $this->assertSame(0, DB::table('material_settlements')->count());
        $this->assertEquals(0, $a->items()->first()->billed_qty_base);
        $this->postJson('/commercial/purchase', $data, ['Idempotency-Key' => 'merged'])->assertCreated();
        $this->assertEquals(7, $product->fresh()->qty);
        $this->assertEquals(2, app(\App\Services\Commercial\MaterialDocumentService::class)->openBase($b->items()->first()));
    }

    public function test_multiple_grns_bill_explicit_source_lines_without_receiving_twice_and_reverse_together(): void
    {
        $product = $this->stock(0);
        $a = $this->grn($product, 3);
        $b = $this->grn($product, 4);
        $items = [];
        foreach ([$a, $b] as $source) $items[] = ['product_id' => $product->id, 'qty' => $source->items->first()->qty,
            'net_unit_cost' => 5, 'material_sources' => [['document_id' => $source->id, 'item_id' => $source->items->first()->id,
                'qty_base' => $source->items->first()->qty_base]]];
        $data = $this->purchaseData($product, ['goods_received_note_ids' => [$a->id, $b->id], 'items' => $items]);
        $bill = app(\App\Services\Commercial\CommercialApplicationService::class)->create('purchase', $data, 'multi-grn', 1, $this->context());
        $this->assertEquals(7, $product->fresh()->qty);
        $this->assertEquals(0, $this->balance('goods_received_not_invoiced'));
        $this->assertSame(2, DB::table('material_settlements')->where('settled_by_id', $bill->id)->count());
        $retry = app(\App\Services\Commercial\CommercialApplicationService::class)->create('purchase', $data, 'multi-grn', 1, $this->context());
        $this->assertSame($bill->id, $retry->id);
        app(CommercialReversalService::class)->reverse($bill, '2026-10-03', 'Wrong combined bill', $this->context());
        $this->assertEquals(7, $product->fresh()->qty);
        $this->assertSame('pending', $a->fresh()->status);
        $this->assertSame('pending', $b->fresh()->status);
        $this->assertEquals(-35, $this->balance('goods_received_not_invoiced'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('warehouses', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
        (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
        foreach ([['GRNI', 'liability', 'goods_received_not_invoiced'], ['GDNI', 'asset', 'goods_delivered_not_invoiced'],
            ['VAR', 'expense', 'inventory_variance']] as [$code, $type, $subType]) {
            \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => $code,
                'name' => $subType, 'type' => $type, 'sub_type' => $subType]);
        }
        app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
    }

    private function grn(Product $product, float $qty = 10, float $cost = 5, array $extra = []): GoodsReceivedNote
    {
        $id = $this->postJson('/goods-received-notes', $extra + ['grn_date' => '2026-10-03', 'supplier_id' => 1, 'warehouse_id' => 1,
            'unit_id' => [1], 'product_id' => [$product->id], 'qty' => [$qty], 'cost' => [$cost]])->assertCreated()->json('grn.id');
        return GoodsReceivedNote::findOrFail($id);
    }

    private function dc(Product $product, float $qty = 4, float $rate = 10, array $extra = []): DeliveryChallan
    {
        $id = $this->postJson('/delivery-challans', $extra + ['challan_date' => '2026-10-03', 'customer_id' => 1, 'warehouse_id' => 1,
            'purpose' => 'approval', 'unit_id' => [1], 'product_id' => [$product->id], 'qty' => [$qty], 'rate' => [$rate]])->assertCreated()->json('challan.id');
        return DeliveryChallan::findOrFail($id);
    }

    private function bill(Product $product, GoodsReceivedNote $grn, float $qty, float $cost, string $key)
    {
        return app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($product, [
            'goods_received_note_id' => $grn->id, 'items' => [['product_id' => $product->id, 'qty' => $qty, 'net_unit_cost' => $cost]]]), $key, 1, $this->context()));
    }

    private function invoice(Product $product, DeliveryChallan $dc, float $qty, string $key)
    {
        return app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, [
            'delivery_challan_id' => $dc->id, 'items' => [['product_id' => $product->id, 'qty' => $qty, 'net_unit_price' => 10]]]), $key, 1, $this->context()));
    }

    private function balance(string $subType): float
    {
        return round((float) DB::table('journal_items as i')->join('journal_entries as e', 'e.id', '=', 'i.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'i.chart_of_account_id')->where('e.company_id', $this->company->id)
            ->where('e.status', 'posted')->where('a.sub_type', $subType)->sum(DB::raw('i.debit - i.credit')), 4);
    }

    private function stockValue(Product $product): float
    {
        return round((float) DB::table('stock_movement_lines')->where('product_id', $product->id)->sum('value'), 4);
    }

    private function onHand(Product $product): float
    {
        return round((float) DB::table('stock_movement_lines')->where('product_id', $product->id)->sum('qty_base'), 4);
    }

    public function test_a_grn_receives_stock_and_accrues_it_before_any_bill(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);

        $this->assertNotNull($grn->posted_at);
        $this->assertSame('pending', $grn->status);
        $this->assertEquals(10, $this->onHand($product));
        $this->assertEquals(10, $product->fresh()->qty, 'the stock projection shows the goods as soon as they arrive');
        $this->assertEquals(50, $this->balance('inventory'));
        $this->assertEquals(-50, $this->balance('goods_received_not_invoiced'));
        $this->assertEquals(0, $this->balance('accounts_payable'), 'nothing is owed until the supplier bills');
    }

    public function test_the_bill_settles_the_grn_without_receiving_the_goods_twice(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);
        $movements = DB::table('stock_movements')->count();

        $purchase = $this->bill($product, $grn, 10, 6, 'grn-bill');

        $this->assertEquals(10, $this->onHand($product), 'the bill does not receive the GRN quantity again');
        $this->assertEquals(10, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->count() - $movements, 'only a value revaluation, never a second receipt');
        $this->assertSame('revaluation', DB::table('stock_movements')->latest('id')->value('movement_type'));
        $this->assertEquals(0, $this->balance('goods_received_not_invoiced'), 'the accrual is cleared');
        $this->assertEquals(60, $this->balance('inventory'), 'stock still on hand carries the billed price');
        $this->assertEquals(60, $this->stockValue($product), 'stock ledger and inventory account agree');
        $this->assertEquals(-60, $this->balance('accounts_payable'));
        $grn->refresh();
        $this->assertSame('converted_to_purchase', $grn->status);
        $this->assertSame($purchase->id, (int) $grn->purchase_id);
        $this->assertDatabaseHas('material_settlements', ['document_id' => $grn->id, 'settled_by_type' => 'purchase',
            'settled_by_id' => $purchase->id, 'qty_base' => 10]);
    }

    public function test_price_difference_on_goods_already_sold_goes_to_cost_of_sales(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);
        app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, ['items' => [['product_id' => $product->id, 'qty' => 10, 'net_unit_price' => 10]]]), 'sell-all', 1, $this->context()));
        $this->bill($product, $grn, 10, 6, 'late-bill');

        $this->assertEquals(0, $this->balance('inventory'));
        $this->assertEquals(60, $this->balance('cogs'), 'GRN cost 50 at sale plus 10 price difference');
        $this->assertEquals(0, $this->balance('goods_received_not_invoiced'));
    }

    public function test_partial_bills_keep_the_grn_open_and_extra_quantity_is_received_normally(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);

        $this->bill($product, $grn, 4, 5, 'first-part');
        $grn->refresh();
        $this->assertSame('partially_billed', $grn->status);
        $this->assertEquals(-30, $this->balance('goods_received_not_invoiced'));
        $this->assertEquals([$grn->items->first()->id => 6.0], app(\App\Services\Commercial\MaterialDocumentService::class)->openQuantities($grn->load('items')));

        $this->bill($product, $grn, 8, 5, 'rest-plus-extra');
        $this->assertSame('converted_to_purchase', $grn->fresh()->status);
        $this->assertEquals(12, $this->onHand($product), '6 settled from the GRN, 2 more received on the bill');
        $this->assertEquals(0, $this->balance('goods_received_not_invoiced'));
        $this->assertEquals(60, $this->balance('inventory'));
    }

    public function test_reversing_the_bill_reopens_the_grn_and_its_accrual(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);
        $purchase = $this->bill($product, $grn, 10, 5, 'to-reverse');

        app(CommercialReversalService::class)->reverse($purchase, '2026-10-03', 'Wrong supplier invoice', $this->context());

        $grn->refresh();
        $this->assertSame('pending', $grn->status);
        $this->assertNull($grn->purchase_id);
        $this->assertEquals(0, (float) $grn->items()->value('billed_qty_base'));
        $this->assertEquals(10, $this->onHand($product), 'the goods stay received');
        $this->assertEquals(-50, $this->balance('goods_received_not_invoiced'));
        $this->assertEquals(0, $this->balance('accounts_payable'));

        $this->bill($product, $grn, 10, 5, 'billed-again');
        $this->assertSame('converted_to_purchase', $grn->fresh()->status);
    }

    public function test_editing_or_cancelling_a_grn_undoes_its_posting_until_it_is_billed(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);

        $this->putJson('/goods-received-notes/'.$grn->id, ['grn_date' => '2026-10-03', 'supplier_id' => 1, 'warehouse_id' => 1,
            'unit_id' => [1], 'product_id' => [$product->id], 'qty' => [7], 'cost' => [5]])->assertOk();
        $this->assertEquals(7, $this->onHand($product));
        $this->assertEquals(-35, $this->balance('goods_received_not_invoiced'));
        $this->assertSame(1, (int) $grn->fresh()->posting_version);

        $this->deleteJson('/goods-received-notes/'.$grn->id)->assertOk();
        $this->assertSame('cancelled', $grn->fresh()->status, 'a posted GRN is kept, cancelled, for its history');
        $this->assertEquals(0, $this->onHand($product));
        $this->assertEquals(0, $this->balance('goods_received_not_invoiced'));

        $billed = $this->grn($product);
        $this->bill($product, $billed, 10, 5, 'locks-grn');
        $this->deleteJson('/goods-received-notes/'.$billed->id)->assertStatus(422);
        $this->assertEquals(10, $this->onHand($product));
    }

    public function test_unbilled_grn_goods_can_go_back_to_the_supplier(): void
    {
        $product = $this->stock(0);
        $grn = $this->grn($product);
        $item = $grn->items()->first();

        $this->postJson('/goods-received-notes/'.$grn->id.'/return', ['business_date' => '2026-10-03',
            'reason' => 'Damaged bales', 'items' => [$item->id => 3]])->assertOk()->assertJson(['status' => 'partially_billed']);
        $this->assertEquals(7, $this->onHand($product));
        $this->assertEquals(-35, $this->balance('goods_received_not_invoiced'));

        $this->bill($product, $grn, 7, 5, 'bill-rest');
        $this->assertSame('converted_to_purchase', $grn->fresh()->status);
        $this->postJson('/goods-received-notes/'.$grn->id.'/return', ['business_date' => '2026-10-03', 'reason' => 'Too late'])->assertStatus(422);
    }

    public function test_a_dc_issues_stock_and_the_invoice_clears_it_at_the_dc_cost(): void
    {
        $product = $this->stock(0);
        $this->bill($product, $this->grn($product), 10, 5, 'stock-in');
        $dc = $this->dc($product);

        $this->assertSame('approval', $dc->purpose);
        $this->assertEquals(6, $this->onHand($product));
        $this->assertEquals(20, $this->balance('goods_delivered_not_invoiced'));
        $this->assertEquals(0, $this->balance('sales_revenue'), 'a challan is not a sale');

        $movements = DB::table('stock_movements')->count();
        $sale = $this->invoice($product, $dc, 4, 'dc-invoice');
        $this->assertSame($movements, DB::table('stock_movements')->count(), 'the invoice issues nothing again');
        $this->assertEquals(6, $this->onHand($product));
        $this->assertEquals(0, $this->balance('goods_delivered_not_invoiced'));
        $this->assertEquals(20, $this->balance('cogs'));
        $this->assertEquals(-40, $this->balance('sales_revenue'));
        $this->assertSame('converted_to_sale', $dc->fresh()->status);
        $this->assertSame($sale->id, (int) $dc->fresh()->sale_id);
    }

    public function test_goods_on_approval_that_come_back_return_to_stock_at_the_dc_cost(): void
    {
        $product = $this->stock(0);
        $this->bill($product, $this->grn($product), 10, 5, 'stock-in');
        $dc = $this->dc($product);

        $this->invoice($product, $dc, 1, 'kept-one');
        $this->postJson('/delivery-challans/'.$dc->id.'/return', ['business_date' => '2026-10-03', 'reason' => 'Customer returned the rest'])->assertOk();

        $this->assertSame('converted_to_sale', $dc->fresh()->status);
        $this->assertEquals(9, $this->onHand($product));
        $this->assertEquals(0, $this->balance('goods_delivered_not_invoiced'));
        $this->assertEquals(45, $this->balance('inventory'));
        $this->assertEquals(45, $this->stockValue($product));
    }

    public function test_a_dc_purpose_must_be_a_rule_55_case_and_orders_cannot_settle_received_goods(): void
    {
        $product = $this->stock(0);
        $this->postJson('/delivery-challans', ['challan_date' => '2026-10-03', 'customer_id' => 1, 'warehouse_id' => 1,
            'purpose' => 'regular_sale', 'unit_id' => [1], 'product_id' => [$product->id], 'qty' => [1], 'rate' => [1]])->assertStatus(422);

        $grn = $this->grn($product);
        $this->expectException(ValidationException::class);
        app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($product, ['status' => 4,
            'goods_received_note_id' => $grn->id]), 'order-from-grn', 1, $this->context()));
    }

    public function test_a_bill_without_any_grn_item_is_refused(): void
    {
        $product = $this->stock(0);
        $other = $this->stock(0);
        $grn = $this->grn($product);
        $this->expectException(ValidationException::class);
        $this->bill($other, $grn, 1, 5, 'unrelated');
    }
}
