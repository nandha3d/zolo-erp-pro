<?php

namespace Tests\Feature;

use App\Services\Commercial\{SaleApplicationService, SaleCommand, PurchaseApplicationService, PurchaseCommand, CommercialReversalService};
use App\Services\Tax\{Gstin, GstinLookupService, GstProjectionService, GstExportAdapter};
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Validation\ValidationException;
use Tests\Support\ComplianceTestCase;

class TaxComplianceTest extends ComplianceTestCase
{
    public function test_reverse_charge_and_blocked_input_credit_have_explicit_mapped_effects(): void
    {
        $p = $this->gstProduct();
        DB::table('party_tax_profiles')->where('party_type', 'supplier')->update(['gstin' => null, 'registration_type' => 'unregistered']);
        DB::table('tax_categories')->update(['input_credit_allowed' => false]);
        $purchase = app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($p, ['gst' => ['reverse_charge' => true]]), 'rcm', 1, $this->context()));
        $this->assertEquals(10, $purchase->grand_total); $this->assertEquals(10, $purchase->productPurchases->first()->valuation_amount);
        $this->assertEquals(1.8, DB::table('journal_items')->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.chart_of_account_id')
            ->whereIn('sub_type', ['output_tax_cgst', 'output_tax_sgst'])->sum('credit'));
        $this->assertEquals(0, DB::table('journal_items')->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.chart_of_account_id')
            ->whereIn('sub_type', ['input_tax_cgst', 'input_tax_sgst'])->sum('debit'));
        $this->assertEquals(DB::table('journal_items')->sum('debit'), DB::table('journal_items')->sum('credit'));
        $export = app(GstExportAdapter::class)->export('zolo-gst-review-v1', $this->context(), '2026-10-01', '2026-10-31');
        $this->assertSame('0.9000', $export['summary']['inward_reverse_charge']['cgst']);
        $this->assertSame('0.9000', $export['summary']['blocked_input_credit']['sgst']);
        $this->assertArrayNotHasKey('eligible_input_credit', $export['summary']);
    }

    public function test_nil_rated_non_gst_and_composition_never_charge_or_claim_gst(): void
    {
        foreach (['nil_rated', 'non_gst'] as $i => $classification) {
            DB::table('tax_categories')->update(['supply_type' => $classification]);
            $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($this->gstProduct()), 'zero-'.$i, 1, $this->context()));
            $this->assertEquals(20, $sale->grand_total); $this->assertEquals(0, $sale->total_tax);
        }
        DB::table('tax_categories')->update(['supply_type' => 'taxable']);
        DB::table('tax_registrations')->update(['registration_type' => 'composition']);
        $purchase = app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($this->gstProduct()), 'composition', 1, $this->context()));
        $this->assertFalse($purchase->productPurchases->first()->tax_snapshot_json['input_credit_allowed']);
        $this->assertEquals(11.8, $purchase->productPurchases->first()->valuation_amount);
    }
    public function test_discount_allocation_conserves_value_without_negative_tiny_lines(): void
    {
        foreach ([249, 251, 500] as $amount) {
            $split = \App\Services\Tax\TaxDeterminationService::spread($amount, array_fill(0, 500, 1));
            $this->assertSame($amount, array_sum($split)); $this->assertGreaterThanOrEqual(0, min($split)); $this->assertLessThanOrEqual(1, max($split));
        }
    }
    public function test_intra_state_split_snapshot_mapping_and_retry_are_atomic(): void
    {
        $product = $this->gstProduct(); $data = $this->saleData($product);
        $sale = app(SaleApplicationService::class)->create(new SaleCommand($data, 'intra', 1, $this->context()));
        $snapshot = $sale->productSales->first()->tax_snapshot_json;
        $this->assertEquals(1.8, $snapshot['cgst']); $this->assertEquals(1.8, $snapshot['sgst']); $this->assertEquals(0, $snapshot['igst']);
        $this->assertEquals(23.6, $sale->grand_total); $this->assertSame('1001', $snapshot['hsn_sac']);
        DB::table('tax_rates')->update(['rate' => 5]);
        $retry = app(SaleApplicationService::class)->create(new SaleCommand($data, 'intra', 1, $this->context()));
        $this->assertSame($sale->id, $retry->id); $this->assertEquals(18, $retry->productSales->first()->tax_snapshot_json['rate']);
        $this->assertSame(1, DB::table('gst_transaction_projections')->count());
        $this->assertEquals(3.6, DB::table('journal_items')->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.chart_of_account_id')
            ->whereIn('sub_type', ['output_tax_cgst', 'output_tax_sgst'])->sum('credit'));
    }

    public function test_inter_state_b2c_exempt_and_service_sac(): void
    {
        DB::table('party_tax_profiles')->where('party_type', 'customer')->update(['gstin' => null, 'registration_type' => 'unregistered', 'state_code' => '29']);
        $sale = $this->gstSale();
        $this->assertEquals(3.6, $sale->productSales->first()->tax_snapshot_json['igst']);
        $this->assertSame('b2c', app(GstProjectionService::class)->report($this->context(), '2026-10-01', '2026-10-31')[0]['section']);
        DB::table('tax_categories')->update(['supply_type' => 'exempt']);
        $service = $this->gstProduct('service');
        $sale2 = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($service), 'sac', 1, $this->context()));
        $this->assertEquals(20, $sale2->grand_total); $this->assertSame('998313', $sale2->productSales->first()->tax_snapshot_json['hsn_sac']);
        $this->assertEquals(20, $service->fresh()->qty);
    }

    public function test_discount_apportions_taxable_base_and_purchase_itc_excludes_inventory(): void
    {
        $sale = $this->gstSale(['order_discount' => 2]);
        $this->assertEquals(21.24, $sale->grand_total);
        $this->assertEquals(18, $sale->productSales->first()->tax_snapshot_json['taxable_value']);
        $p = $this->gstProduct();
        $purchase = app(PurchaseApplicationService::class)->create(new PurchaseCommand($this->purchaseData($p), 'itc', 1, $this->context()));
        $this->assertEquals(11.8, $purchase->grand_total); $this->assertEquals(10, $purchase->productPurchases->first()->valuation_amount);
        $this->assertEquals(1.8, DB::table('journal_items')->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.chart_of_account_id')
            ->whereIn('sub_type', ['input_tax_cgst', 'input_tax_sgst'])->sum('debit'));
    }

    public function test_missing_component_mapping_rolls_back_all_effects(): void
    {
        $p = $this->gstProduct();
        DB::table('semantic_account_mappings')->where('semantic_role', 'output_tax_cgst')->update(['is_active' => false]);
        try { app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($p), 'missing', 1, $this->context())); $this->fail('Missing mapping accepted.'); }
        catch (ValidationException $error) { $this->assertStringContainsString('output_tax_cgst', json_encode($error->errors())); }
        foreach (['sales', 'stock_movements', 'journal_entries', 'gst_transaction_projections', 'idempotency_keys', 'document_number_reservations'] as $table) $this->assertSame(0, DB::table($table)->count());
        $this->assertEquals(20, $p->fresh()->qty);
    }

    public function test_overlapping_rates_and_foreign_categories_reject_without_writes(): void
    {
        $p = $this->gstProduct();
        DB::table('tax_rates')->insert(['company_id' => $this->company->id, 'tax_category_id' => $p->tax_category_id, 'rate' => 5, 'effective_from' => '2026-01-01']);
        $this->expectException(ValidationException::class);
        app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($p), 'overlap', 1, $this->context()));
    }

    public function test_provider_outage_is_audited_and_never_mutates_party(): void
    {
        config(['compliance.gst_lookup_url' => 'https://gst-provider.invalid/lookup']); Http::fake(fn () => Http::response([], 503));
        $before = DB::table('party_tax_profiles')->get()->toJson();
        $result = app(GstinLookupService::class)->lookup($this->gstin('33'), $this->context(), 1);
        $this->assertFalse($result['verified']); $this->assertSame('unavailable', $result['status']);
        $this->assertSame($before, DB::table('party_tax_profiles')->get()->toJson()); $this->assertSame(1, DB::table('gst_lookup_audits')->count());
    }

    public function test_reversal_uses_saved_tax_and_review_export_is_explicitly_not_filing_ready(): void
    {
        $sale = $this->gstSale(); DB::table('tax_rates')->update(['rate' => 5]);
        app(CommercialReversalService::class)->reverse($sale, '2026-10-04', 'Cancelled order', $this->context());
        $export = app(GstExportAdapter::class)->export('zolo-gst-review-v1', $this->context(), '2026-10-01', '2026-10-31');
        $this->assertFalse($export['filing_ready']); $this->assertCount(2, $export['documents']);
        $this->assertEquals(-1, $export['documents'][1]['snapshot']['sign']);
        $this->assertEquals(18, $export['documents'][1]['snapshot']['lines'][0]['rate']);
        $this->assertSame('0.0000', $export['summary']['outward_taxable']['taxable_value']);
        $this->assertSame('0.0000', $export['summary']['outward_taxable']['cgst']);
    }

    public function test_gstin_checksum_and_shape_are_not_registration_verification(): void
    {
        $this->assertSame('27AAPFU0939F1ZV', Gstin::normalize('27aapfu0939f1zv'));
        $this->expectException(ValidationException::class); Gstin::normalize('27AAPFU0939F1Z0');
    }

    public function test_review_export_rejects_unverified_statutory_schema(): void
    {
        $this->expectException(ValidationException::class);
        app(GstExportAdapter::class)->export('gstr1-filing', $this->context(), '2026-10-01', '2026-10-31');
    }
}
