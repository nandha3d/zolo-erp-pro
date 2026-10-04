<?php

namespace Tests\Feature;

use App\Models\{Product, Returns};
use App\Services\Commercial\{ReturnService, StockLossService, ExchangeService, CommercialReversalService, PurchaseApplicationService, PurchaseCommand, SaleApplicationService, SaleCommand};
use App\Services\Inventory\{InventoryMovementService, StockMovementCommand, StockLine};
use App\Services\Documents\{DocumentRenderingService, CommunicationDispatchService};
use App\Services\Tax\{GstProjectionService, TaxSetupService};
use Illuminate\Support\Facades\{DB, Http, Queue, Crypt};
use Illuminate\Validation\ValidationException;
use Tests\Support\ReturnsDocumentTestCase;

class ReturnsDocumentsTest extends ReturnsDocumentTestCase
{
    public function test_pending_delivery_does_not_send_when_commercial_gate_closes(): void
    {
        Queue::fake(); $sale = $this->gstSale(); $service = app(CommunicationDispatchService::class);
        $log = $service->request('sale', $sale->id, 'email', 'fixture@example.invalid', 'gated-dispatch', $this->context(), 1);
        config(['commercial.enabled' => false]); $service->deliver($log->id);
        $this->assertSame('pending', DB::table('document_dispatch_logs')->value('status'));
        $this->assertSame(0, DB::table('document_dispatch_logs')->value('attempts'));
        $this->assertSame(1, DB::table('journal_entries')->count());
    }
    public function test_dimensioned_return_restores_exact_piece_and_preserves_measurements(): void
    {
        $p = $this->gstProduct(); DB::table('products')->where('id', $p->id)->update(['qty' => 0]);
        DB::table('product_warehouse')->where('product_id', $p->id)->update(['qty' => 0]);
        $movement = app(InventoryMovementService::class)->receive(new StockMovementCommand('2026-10-03',
            [new StockLine($p->id, 1.5, unitCost: 5, dimensions: ['identity_no' => 'LOG-1', 'length' => 5, 'width' => .5, 'thickness' => .6, 'dimension_uom' => 'm', 'grade' => 'A'])],
            1, context: $this->context(), userId: 1));
        $identity = $movement->lines->first()->stock_identity_id;
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($p, ['items' => [
            ['product_id' => $p->id, 'qty' => 1, 'net_unit_price' => 10, 'stock_identity_id' => $identity]]]), 'timber', 1, $this->context()));
        $stockLine = DB::table('stock_movement_lines')->where('stock_identity_id', $identity)->where('qty_base', '<', 0)->value('id');
        $note = $this->postedReturn($sale, ['items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 1, 'stock_line_ids' => [$stockLine]]]]);
        $this->assertEquals(1.5, $p->fresh()->qty);
        $this->assertSame($identity, $note->products->first()->stock_details_json['chunks'][0]['stock_identity_id']);
        $this->assertEquals(1.5, DB::table('stock_dimensions')->value('computed_volume'));
        $this->assertSame('returned', DB::table('stock_identities')->where('id', $identity)->value('warranty_state'));
    }

    public function test_expired_batch_return_requires_quarantine_and_keeps_original_batch(): void
    {
        $p = $this->gstProduct(); DB::table('products')->where('id', $p->id)->update(['qty' => 0, 'is_batch' => true]);
        DB::table('product_warehouse')->where('product_id', $p->id)->update(['qty' => 0]);
        app(InventoryMovementService::class)->receive(new StockMovementCommand('2026-10-03',
            [new StockLine($p->id, 2, unitCost: 5, batch: ['batch_no' => 'B-1', 'expired_date' => '2026-10-03'])], 1, context: $this->context(), userId: 1));
        $batch = DB::table('product_batches')->value('id');
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($p, ['items' => [
            ['product_id' => $p->id, 'qty' => 2, 'net_unit_price' => 10, 'product_batch_id' => $batch]]]), 'batch', 1, $this->context()));
        try { $this->postedReturn($sale); $this->fail('Expired stock became sellable.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('disposition', $error->errors()); }
        $note = $this->postedReturn($sale, ['items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 1, 'warehouse_id' => 2, 'disposition' => 'expired']]], 'quarantined');
        $this->assertSame($batch, $note->products->first()->stock_details_json['chunks'][0]['batch_id']);
        $this->assertEquals(1, DB::table('product_warehouse')->where('warehouse_id', 2)->where('product_batch_id', $batch)->value('qty'));
    }
    public function test_approval_then_partial_return_restores_stock_and_offsets_ar_once(): void
    {
        $sale = $this->gstSale(); $product = $sale->productSales->first()->product;
        $data = $this->noteData($sale); $service = app(ReturnService::class);
        $pending = $service->create('sale', $sale->id, $data, 'return', $this->context(), 1);
        $this->assertSame('awaiting_approval', $pending->status); $this->assertEquals(18, $product->fresh()->qty);
        $this->assertSame(1, DB::table('journal_entries')->count());
        DB::table('tax_rates')->update(['rate' => 5]);
        $note = $service->approve('sale', $pending->id, $this->context(), 1);
        $this->assertEquals(19, $product->fresh()->qty); $this->assertEquals(11.8, $note->grand_total);
        $this->assertEquals(18, $note->products->first()->tax_snapshot_json['rate']);
        $this->assertSame($note->id, $service->create('sale', $sale->id, $data, 'return', $this->context(), 1)->id);
        $this->assertSame($note->id, $service->approve('sale', $note->id, $this->context(), 1)->id);
        $this->assertEquals(11.8, DB::table('account_open_items')->sum('open_amount'));
        $this->assertSame(2, DB::table('stock_movements')->count()); $this->assertSame(2, DB::table('gst_transaction_projections')->count());
        $this->assertEquals(1.8, $note->total_tax);
        $this->assertLessThanOrEqual(16, strlen($note->reference_no));
        $report = app(GstProjectionService::class);
        $totals = $report->summary($report->report($this->context(), '2026-10-01', '2026-10-31'));
        $this->assertSame('10.0000', $totals['outward_taxable']['taxable_value']);
        $this->assertSame('0.9000', $totals['outward_taxable']['cgst']);
    }

    public function test_over_return_at_approval_rolls_back_without_extra_effects(): void
    {
        $sale = $this->gstSale(); $service = app(ReturnService::class); $data = $this->noteData($sale, ['items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 2]]]);
        $first = $service->create('sale', $sale->id, $data, 'one', $this->context(), 1);
        $second = $service->create('sale', $sale->id, $data, 'two', $this->context(), 1);
        $service->approve('sale', $first->id, $this->context(), 1);
        try { $service->approve('sale', $second->id, $this->context(), 1); $this->fail('Over-return accepted.'); }
        catch (ValidationException $error) { $this->assertNotEmpty(array_intersect(['qty', 'amount'], array_keys($error->errors()))); }
        $this->assertNull($second->fresh()->posted_at); $this->assertSame(2, DB::table('stock_movements')->count());
        $this->assertEquals(20, Product::first()->qty);
    }

    public function test_financial_notes_do_not_move_stock_and_source_cannot_reverse_twice(): void
    {
        $sale = $this->gstSale();
        $note = $this->postedReturn($sale, ['adjustment_type' => 'discount', 'items' => [['line_id' => $sale->productSales->first()->id, 'amount' => 2]]]);
        $this->assertEquals(2.36, $note->grand_total); $this->assertEquals(0, $note->total_qty);
        $this->assertSame(1, DB::table('stock_movements')->count()); $this->assertEquals(18, Product::first()->qty);
        $this->expectException(ValidationException::class); app(CommercialReversalService::class)->reverse($sale, '2026-10-04', 'Cancel again', $this->context());
    }

    public function test_purchase_return_reduces_ap_inventory_and_original_input_tax(): void
    {
        $p = $this->gstProduct();
        $purchase = app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($p), 'purchase', 1, $this->context()));
        $data = ['business_date' => '2026-10-04', 'reason' => 'Supplier quality rejection', 'note_type' => 'debit', 'adjustment_type' => 'quantity',
            'items' => [['line_id' => $purchase->productPurchases->first()->id, 'qty' => 1]]];
        $pending = app(ReturnService::class)->create('purchase', $purchase->id, $data, 'purchase-return', $this->context(), 1);
        $note = app(ReturnService::class)->approve('purchase', $pending->id, $this->context(), 1);
        $this->assertEquals(21, $p->fresh()->qty); $this->assertEquals(5.9, $note->grand_total);
        $this->assertEquals(5.9, DB::table('account_open_items')->sum('open_amount'));
        $this->assertEquals(.9, DB::table('journal_items')->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.chart_of_account_id')
            ->whereIn('sub_type', ['input_tax_cgst', 'input_tax_sgst'])->sum('credit'));
    }

    public function test_quarantine_return_cannot_be_sold_and_disposal_uses_ledger_cost(): void
    {
        $sale = $this->gstSale(); $p = $sale->productSales->first()->product;
        $note = $this->postedReturn($sale, ['items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 1, 'disposition' => 'expired', 'warehouse_id' => 2]]]);
        $this->assertEquals(1, DB::table('product_warehouse')->where('product_id', $p->id)->where('warehouse_id', 2)->value('qty'));
        try { app(InventoryMovementService::class)->issue(new StockMovementCommand('2026-10-04', [new StockLine($p->id, 1)], 2, context: $this->context(), userId: 1)); $this->fail('Quarantine sold.'); }
        catch (\App\Services\Inventory\StockPolicyException $error) { $this->assertStringContainsString('Quarantined', $error->getMessage()); }
        $loss = app(StockLossService::class)->create(['business_date' => '2026-10-04', 'product_id' => $p->id, 'warehouse_id' => 2,
            'qty' => 1, 'disposition' => 'expired', 'reason' => 'Expired returned goods disposed'], 'loss', $this->context(), 1);
        $this->assertEquals(5, $loss->total_loss); $this->assertEquals(18, $p->fresh()->qty);
    }

    public function test_exact_serial_return_preserves_warranty_period_and_records_return_state(): void
    {
        $p = $this->gstProduct(); DB::table('products')->where('id', $p->id)->update(['qty' => 0, 'is_imei' => true]);
        DB::table('product_warehouse')->where('product_id', $p->id)->update(['qty' => 0]);
        app(InventoryMovementService::class)->receive(new StockMovementCommand('2026-10-03',
            [new StockLine($p->id, 2, unitCost: 5, serials: ['SER-1','SER-2'])], 1, context: $this->context(), userId: 1));
        $data = $this->saleData($p, ['items' => [['product_id' => $p->id, 'qty' => 2, 'net_unit_price' => 10, 'serials' => ['SER-1','SER-2']]]]);
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($data, 'serial-sale', 1, $this->context()));
        $identity = \App\Models\Inventory\StockIdentity::where('identity_no', 'SER-1')->firstOrFail();
        $identity->update(['warranty_start' => '2026-10-03', 'warranty_end' => '2027-10-03']);
        $stockId = DB::table('stock_movement_lines')->where('stock_identity_id', $identity->id)->where('qty_base', '<', 0)->value('id');
        $note = $this->postedReturn($sale, ['items' => [['line_id' => $sale->productSales->first()->id, 'qty' => 1, 'stock_line_ids' => [$stockId]]]]);
        $this->assertSame('in_stock', $identity->fresh()->status); $this->assertSame('returned', $identity->fresh()->warranty_state);
        $this->assertSame('2027-10-03', $identity->fresh()->warranty_end->toDateString());
        $this->assertSame('issued', \App\Models\Inventory\StockIdentity::where('identity_no', 'SER-2')->value('status'));
    }

    public function test_print_formats_use_identical_saved_totals_after_master_changes_without_new_numbers(): void
    {
        $sale = $this->gstSale(); $renderer = app(DocumentRenderingService::class); $dto = $renderer->dto('sale', $sale->id, $this->context());
        DB::table('products')->update(['name' => 'Changed item']); $this->company->update(['legal_name' => 'Changed company']);
        $this->assertEquals($dto, $renderer->dto('sale', $sale->id, $this->context()));
        $reservations = DB::table('document_number_reservations')->count();
        foreach (['a4','thermal'] as $format) $this->assertStringContainsString('23.6000', $renderer->html($dto, $renderer->profile('sale', $this->context(), null, $format)));
        $profile = $renderer->profile('sale', $this->context(), null, 'dot_matrix'); $text = $renderer->fixedWidth($dto, $profile);
        $this->assertStringContainsString('23.6000', $text); $this->assertCount(68, explode("\r\n", $text));
        $this->assertSame($reservations, DB::table('document_number_reservations')->count());
    }

    public function test_dispatch_outbox_waits_for_commit_and_provider_failure_can_retry_without_reposting(): void
    {
        Queue::fake(); $sale = $this->gstSale(); $service = app(CommunicationDispatchService::class);
        $log = DB::transaction(function () use ($service, $sale) {
            $log = $service->request('sale', $sale->id, 'sms', '+919876543210', 'send', $this->context(), 1);
            Queue::assertNothingPushed(); return $log;
        });
        Queue::assertPushed(\App\Jobs\DispatchDocument::class);
        $this->assertSame('pending', $log->status); $service->deliver($log->id);
        $this->assertSame('failed', DB::table('document_dispatch_logs')->value('status'));
        DB::table('document_channels')->insert(['company_id' => $this->company->id, 'channel' => 'sms', 'is_active' => true,
            'configuration_encrypted' => Crypt::encryptString(json_encode(['account_sid' => 'AC'.str_repeat('a',32), 'auth_token' => str_repeat('b',32), 'from' => '+919999999999']))]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM'.str_repeat('c',32)], 201)]);
        $service->retry($log->id, $this->context(), 1); $service->deliver($log->id); $service->deliver($log->id);
        Http::assertSentCount(1); $this->assertSame('sent', DB::table('document_dispatch_logs')->value('status'));
        $this->assertSame(1, DB::table('sales')->count()); $this->assertSame(1, DB::table('journal_entries')->count());
        $this->assertSame($log->id, $service->request('sale', $sale->id, 'sms', '+919876543210', 'send', $this->context(), 1)->id);
    }

    public function test_exchange_uses_return_new_sale_and_open_item_allocation(): void
    {
        $sale = $this->gstSale(['paid_amount' => 23.6]);
        $return = $this->postedReturn($sale); $p = $sale->productSales->first()->product;
        $data = $this->saleData($p, ['business_date' => '2026-10-04', 'items' => [['product_id' => $p->id, 'qty' => 1, 'net_unit_price' => 10]]]);
        $exchange = app(ExchangeService::class)->create($return->id, $data, 'exchange', $this->context(), 1);
        $this->assertNotNull($exchange->replacement_sale_id); $this->assertSame($return->id, $exchange->return_id);
        $this->assertEquals(0, DB::table('account_open_items')->sum('open_amount')); $this->assertSame(2, DB::table('sales')->count());
        $this->assertSame(3, DB::table('stock_movements')->count());
        $this->assertSame($exchange->id, app(ExchangeService::class)->create($return->id, $data, 'exchange', $this->context(), 1)->id);
    }

    public function test_setup_overlap_policy_and_foreign_read_are_validated(): void
    {
        $service = app(TaxSetupService::class); $service->save('return-policy', ['amount' => 10000, 'days' => 30], $this->context(), 1);
        $this->assertEquals(30, $this->company->fresh()->settings_json['return_policy']['days']);
        $sale = $this->gstSale(); $note = app(ReturnService::class)->create('sale', $sale->id, $this->noteData($sale), 'policy', $this->context(), 1);
        $this->assertSame('posted', $note->status);
        $foreign = new \App\Services\Platform\CompanyContext($this->other->id, $this->otherBranch->id, $this->otherYear->id);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class); app(DocumentRenderingService::class)->dto('sale', $sale->id, $foreign);
    }
}
