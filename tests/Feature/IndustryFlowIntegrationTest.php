<?php

namespace Tests\Feature;

use App\Models\Inventory\{StockDimension, StockIdentity, ProductUomConversion};
use App\Services\Commercial\{PurchaseApplicationService, PurchaseCommand, SaleApplicationService, SaleCommand};
use App\Services\Documents\DocumentRenderingService;
use App\Services\Industry\{FmcgInventoryService, IndustryProfileService, IndustryStockQueries};
use App\Services\Inventory\{InventoryMovementService, InventoryReconciliationService, StockLine, StockMovementCommand};
use App\Services\JobWork\JobWorkService;
use Illuminate\Support\Facades\DB;
use Tests\Support\{CreatesOperationsFixtures, ReturnsDocumentTestCase};

/** Full shared commercial/GST/document stack with profile extensions, not another posting implementation. */
class IndustryFlowIntegrationTest extends ReturnsDocumentTestCase
{
    use CreatesOperationsFixtures;

    protected function setUp(): void { parent::setUp(); $this->setUpOperationsFixtures(); }

    public function test_grey_fabric_purchase_job_work_service_credit_sale_partial_receipt_and_print_reconcile(): void
    {
        app(IndustryProfileService::class)->apply('textile', 'wholesale', $this->context(), 1);
        DB::table('units')->where('id', 1)->update(['unit_name' => 'MTR']);
        $fabric = $this->gstProduct(); $fabric->forceFill(['qty' => 0, 'unit_id' => 1])->save();
        DB::table('product_warehouse')->where('product_id', $fabric->id)->update(['qty' => 0]);
        $purchase = app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($fabric, [
            'items' => [['product_id' => $fabric->id, 'qty' => 500, 'net_unit_cost' => 2]]]), 'grey-purchase', 1, $this->context()));
        $this->assertEquals(1180, $purchase->grand_total); $this->assertEquals(500, $fabric->fresh()->qty);
        [, $dispatch, $line] = $this->job($fabric);
        $job = app(JobWorkService::class);
        $receipt = $job->receive($dispatch->id, ['warehouse_id' => 1, 'business_date' => '2026-10-03', 'lines' => [
            ['dispatch_line_id' => $line->id, 'accepted_qty' => 480, 'rejected_qty' => 0, 'loss_qty' => 20]]], 'grey-return', $this->context(), 1);
        $service = $this->gstProduct('service'); $service->forceFill(['qty' => 0])->save();
        DB::table('product_warehouse')->where('product_id', $service->id)->update(['qty' => 0]);
        $job->serviceBill($receipt->id, ['business_date' => '2026-10-03', 'items' => [
            ['product_id' => $service->id, 'qty' => 1, 'net_unit_cost' => 25]]], 'grey-service', $this->context(), 1);
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($fabric, [
            'items' => [['product_id' => $fabric->id, 'qty' => 480, 'net_unit_price' => 3]],
            'transport_name' => 'Fixture carrier', 'lr_number' => 'LR-100', 'lr_date' => '2026-10-03', 'bale_count' => 5, 'bundle_count' => 10,
        ]), 'grey-sale', 1, $this->context()));
        app(\App\Services\ERP\PaymentService::class)->addPayment($sale, ['amount' => 200, 'account_id' => 1,
            'paying_method' => 'Cash', 'business_date' => '2026-10-03', 'idempotency_key' => 'grey-payment'], 1, $this->context());
        $this->assertEquals(1499.2, DB::table('account_open_items')->where('source_type', 'sale')->where('source_id', $sale->id)->sum('open_amount'));
        $this->assertEquals(0, $fabric->fresh()->qty); $this->assertSame([], $job->pending($this->context(), 1));
        $print = app(DocumentRenderingService::class); $document = $print->dto('sale', $sale->id, $this->context());
        $text = $print->fixedWidth($document, $print->profile('sale', $this->context(), null, 'dot_matrix'));
        $this->assertCount(68, explode("\r\n", $text)); $this->assertStringContainsString('Qty 480.000 Rate', $text);
        $this->assertStringContainsString('Bale count: 5', $text); $this->assertStringContainsString('LR-100', $text);
        app(IndustryProfileService::class)->apply('general_trading', 'trading', $this->context(), 1);
        $this->assertSame($document, $print->dto('sale', $sale->id, $this->context()));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_case_purchase_box_sale_free_scheme_and_mrp_use_normalized_base_quantities(): void
    {
        app(IndustryProfileService::class)->apply('fmcg', 'distribution', $this->context(), 1);
        foreach ([2 => 'BOX', 3 => 'CASE'] as $id => $name) DB::table('units')->insert(['id' => $id, 'name' => $name, 'unit_name' => $name, 'company_id' => $this->company->id]);
        $product = $this->material(0, 1, ['is_batch' => true, 'tax_category_id' => DB::table('tax_categories')->value('id'), 'hsn_code' => '1001']);
        foreach ([2 => 12, 3 => 144] as $id => $factor) ProductUomConversion::create(['company_id' => $this->company->id,
            'product_id' => $product->id, 'from_uom_id' => $id, 'to_uom_id' => 1, 'factor' => $factor, 'rounding_scale' => 4]);
        app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($product, ['items' => [[
            'product_id' => $product->id, 'qty' => 1, 'purchase_unit_id' => 3, 'net_unit_cost' => 144,
            'batch' => ['batch_no' => 'CASE-A', 'mfg_date' => '2026-09-01', 'expired_date' => '2027-09-01', 'mrp' => 5],
        ]]]), 'case-purchase', 1, $this->context()));
        $scheme = app(FmcgInventoryService::class)->configureScheme(['product_id' => $product->id, 'name' => '12+1',
            'buy_qty' => 12, 'free_qty' => 1, 'valid_from' => '2026-10-01', 'valid_to' => '2026-12-31'], $this->context(), 1);
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, ['items' => [[
            'product_id' => $product->id, 'qty' => 1, 'sale_unit_id' => 2, 'net_unit_price' => 24, 'quantity_scheme_id' => $scheme,
        ]]]), 'box-sale', 1, $this->context()));
        $this->assertEquals(28.32, $sale->grand_total); $this->assertEquals(131, $product->fresh()->qty);
        $this->assertEquals(5, DB::table('product_batches')->value('mrp'));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_piece_picker_and_invoice_freeze_selected_volume_after_profile_or_formula_changes(): void
    {
        $profile = app(IndustryProfileService::class); $profile->apply('timber', 'trading', $this->context(), 1);
        $product = $this->material(0, 5, ['tax_category_id' => DB::table('tax_categories')->value('id'), 'hsn_code' => '1001']); $profile->attributes($product->id, $this->context(), 1, ['species' => 'Teak', 'grade' => 'A']);
        app(InventoryMovementService::class)->receive(new StockMovementCommand(date: '2026-10-03', lines: [
            new StockLine(productId: $product->id, qty: 20, unitCost: 3, dimensions: ['identity_no' => 'TEAK-1',
                'length' => 10, 'width' => 1, 'thickness' => 1, 'pieces' => 2, 'dimension_uom' => 'ft', 'grade' => 'A'])], warehouseId: 1, context: $this->context(), userId: 1));
        $pieces = app(IndustryStockQueries::class)->pieces($product->id, 1, 'Teak', $this->context(), 1);
        $this->assertCount(1, $pieces); $this->assertEquals(20, $pieces[0]['qty_base']);
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, ['items' => [[
            'product_id' => $product->id, 'qty' => 5, 'net_unit_price' => 6, 'stock_identity_id' => StockIdentity::sole()->id,
        ]]]), 'piece-sale', 1, $this->context()));
        $renderer = app(DocumentRenderingService::class); $dto = $renderer->dto('sale', $sale->id, $this->context());
        $this->assertEquals(5, $dto['lines'][0]['dimensions']['line_cft']);
        $html = $renderer->html($dto, $renderer->profile('sale', $this->context(), null, 'a4'));
        $this->assertStringContainsString('TEAK-1', $html); $this->assertStringContainsString('rectangular-v1', $html);
        $this->assertEquals(15, $product->fresh()->qty); $this->assertEquals(20, StockDimension::sole()->computed_cft);
        $profile->apply('general_trading', 'trading', $this->context(), 1);
        $this->assertSame($dto, $renderer->dto('sale', $sale->id, $this->context()));
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }

    public function test_solar_5kw_quote_missing_purchase_serial_commission_invoice_payment_and_credit_margin(): void
    {
        $profiles = app(IndustryProfileService::class); $profiles->apply('solar', 'epc', $this->context(), 1);
        $panel = $this->material(0, 50, ['is_imei' => true, 'price' => 100,
            'tax_category_id' => DB::table('tax_categories')->value('id'), 'hsn_code' => '1001']);
        $profiles->attributes($panel->id, $this->context(), 1, ['wattage' => 500, 'warranty_months' => 120]);
        $system = $this->material(0); $bom = $this->bom($panel, $system, ['output_qty' => 5,
            'lines' => [['component_product_id' => $panel->id, 'qty' => 10, 'uom_id' => 1]]]);
        $projects = app(\App\Services\Industry\ProjectService::class);
        $project = $projects->create(['title' => '5kW rooftop', 'client_id' => 1, 'system_kw' => 5,
            'site_address' => 'Test rooftop', 'business_date' => '2026-10-03'], 'site-5kw', $this->context(), 1);
        $projects->quotation($project->id, ['bom_id' => $bom->id, 'qty' => 5, 'warehouse_id' => 1,
            'business_date' => '2026-10-03'], 'quote-5kw', $this->context(), 1);
        $this->assertEquals(1180, DB::table('sales')->value('grand_total')); $this->assertEquals(0, $panel->fresh()->qty);
        $serials = array_map(fn ($i) => 'PANEL-'.$i, range(1, 10));
        app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($panel, ['project_id' => $project->id,
            'items' => [['product_id' => $panel->id, 'qty' => 10, 'net_unit_cost' => 50, 'serials' => $serials]]]), 'missing-panels', 1, $this->context()));
        foreach ($serials as $serial) {
            $identity = StockIdentity::where('identity_no', $serial)->sole();
            $allocation = $projects->allocate($project->id, ['stock_identity_id' => $identity->id, 'business_date' => '2026-10-03'], 'allocate-'.$serial, $this->context(), 1);
            $projects->dispatch($allocation->id, ['business_date' => '2026-10-03'], 'dispatch-'.$serial, $this->context(), 1);
            $installed = $projects->install($allocation->id, ['business_date' => '2026-10-03'], 'install-'.$serial, $this->context(), 1);
            $projects->commission($installed->id, ['business_date' => '2026-10-03', 'amc_months' => 12], 'commission-'.$serial, $this->context(), 1);
        }
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($panel, ['project_id' => $project->id,
            'warehouse_id' => $project->site_warehouse_id, 'items' => [['product_id' => $panel->id, 'qty' => 10, 'net_unit_price' => 100, 'serials' => $serials]]]), 'site-invoice', 1, $this->context()));
        app(\App\Services\ERP\PaymentService::class)->addPayment($sale, ['amount' => 1180, 'account_id' => 1,
            'paying_method' => 'Cash', 'business_date' => '2026-10-03', 'idempotency_key' => 'site-payment'], 1, $this->context());
        $this->assertEquals(0, DB::table('account_open_items')->where('source_type', 'sale')->where('source_id', $sale->id)->sum('open_amount'));
        $this->assertEquals(0, $panel->fresh()->qty); $this->assertSame(20, DB::table('warranty_contracts')->count());
        $this->assertSame('500.0000', $projects->margin($project->id, $this->context(), 1)['margin']);
        $this->postedReturn($sale, ['adjustment_type' => 'rate_difference', 'items' => [['line_id' => $sale->productSales->first()->id, 'amount' => 100]]], 'site-rate-credit');
        $margin = $projects->margin($project->id, $this->context(), 1);
        $this->assertSame('900.0000', $margin['revenue']); $this->assertSame('500.0000', $margin['material_cost']);
        $this->assertSame('400.0000', $margin['margin']);
        $this->assertSame([], app(InventoryReconciliationService::class)->differences());
    }
}
