<?php

namespace Tests\Feature;

use App\Models\{Adjustment, Challan, Courier, PackingSlip, Purchase, Quotation, ReturnPurchase, Returns, Sale, Transfer, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Render real Blade forms with unsaved source models; never post business records. */
class DocumentLayoutFixtureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_source_bound_document_forms_share_the_keyboard_and_viewport_layout(): void
    {
        config(['commercial.enabled' => true]);
        $this->actingAs(User::first());
        $this->get('/sales')->assertOk();
        $attributes = ['id' => 999999, 'reference_no' => 'LAYOUT-TEST', 'created_at' => now(),
            'customer_id' => 1, 'biller_id' => 1, 'warehouse_id' => 1, 'supplier_id' => 1,
            'from_warehouse_id' => 1, 'to_warehouse_id' => 1, 'sale_status' => 1,
            'purchase_status' => 1, 'payment_status' => 1, 'status' => 1,
            'total_qty' => 0, 'total_discount' => 0, 'total_tax' => 0, 'total_price' => 0,
            'order_tax_rate' => 0, 'order_tax' => 0, 'order_discount' => 0, 'shipping_cost' => 0,
            'grand_total' => 0, 'paid_amount' => 0, 'exchange_rate' => 1];
        $sale = (new Sale)->forceFill($attributes);
        $purchase = (new Purchase)->forceFill($attributes);
        $quotation = (new Quotation)->forceFill($attributes);
        $return = (new Returns)->forceFill($attributes)->setRelation('sale', $sale);
        $base = ['lims_sale_data' => $sale, 'lims_purchase_data' => $purchase,
            'lims_quotation_data' => $quotation, 'lims_transfer_data' => (new Transfer)->forceFill($attributes),
            'lims_adjustment_data' => (new Adjustment)->forceFill($attributes),
            'lims_return_data' => $return, 'currency' => [], 'currency_exchange_rate' => 1, 'productArray' => [],
            'numberOfInvoice' => 1, 'custom_fields' => collect(), 'deposit' => [],
            'lims_pos_setting_data' => (object) ['stripe_public_key' => ''],
            'sale' => null, 'referenceNo' => 'LAYOUT-TEST', 'customers' => collect(),
            'warehouses' => collect(), 'products' => collect()];
        foreach (['customer', 'warehouse', 'biller', 'supplier', 'tax', 'account'] as $kind) {
            $base['lims_'.$kind.'_list'] = collect();
        }
        foreach (['sale', 'purchase', 'quotation', 'transfer', 'adjustment', 'return'] as $kind) {
            $base['lims_product_'.$kind.'_data'] = collect();
        }
        $base['lims_product_list_without_variant'] = $base['lims_product_list_with_variant'] = $base['currency_list'] = collect();
        $courier = (new Courier)->forceFill(['id' => 999999, 'name' => 'Layout courier', 'phone_number' => '0000000000']);
        $packing = (new PackingSlip)->forceFill(['id' => 999999, 'reference_no' => 'LAYOUT-TEST', 'amount' => 0])->setRelation('sale', $sale);
        $base['courier_list'] = collect([$courier]);
        $base['packing_slip_all'] = collect([$packing]);

        $pages = ['sale/edit', 'purchase/edit', 'quotation/edit', 'transfer/edit', 'adjustment/edit',
            'return/edit', 'return_purchase/edit', 'quotation/create_sale', 'quotation/create_purchase',
            'challan/create', 'exchange/create'];
        foreach ($pages as $page) {
            $data = $base;
            if ($page === 'return_purchase/edit') {
                $data['lims_return_data'] = (new ReturnPurchase)->forceFill($attributes);
            }
            $this->renderFixture($page, $data);
        }

        // Finalize's legacy template reads its packing slips directly. Reuse only existing records.
        $existing = PackingSlip::whereHas('sale')->with('sale')->first();
        if ($existing) {
            $challan = (new Challan)->forceFill(['id' => 999999, 'reference_no' => 'LAYOUT-TEST',
                'created_at' => now(), 'packing_slip_list' => (string) $existing->id, 'amount_list' => '0'])
                ->setRelation('courier', $courier);
            $this->renderFixture('challan/finalize', ['challan' => $challan]);
        }
    }

    private function renderFixture(string $page, array $data): void
    {
        $html = view('backend.'.str_replace('/', '.', $page), $data)->render();
        $this->assertStringContainsString('css/document-entry-layout.css', $html, $page);
        $this->assertStringContainsString('js/document-keyboard.js', $html, $page);
        $this->assertStringContainsString('name="_token"', $html, $page);
        if (getenv('ERP_EXPORT_DOCUMENT_LAYOUT') === '1') {
            if (!is_dir(base_path('scratch'))) mkdir(base_path('scratch'), 0777, true);
            file_put_contents(base_path('scratch/document-'.str_replace('/', '-', $page).'-fixture.html'), $html);
        }
    }
}
