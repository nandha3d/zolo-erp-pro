<?php

namespace Tests\Feature;

use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Services\Commercial\MaterialDocumentService;
use App\Services\Commercial\ReturnService;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReturnsDocumentTestCase;

/** A credit note on an invoice settled from a Customer DC restores the stock that the DC (not the invoice) issued. */
class MaterialDocumentReturnsTest extends ReturnsDocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
        (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
        foreach (['goods_delivered_not_invoiced' => 'asset', 'goods_received_not_invoiced' => 'liability'] as $role => $type) {
            $account = \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => strtoupper($role),
                'name' => $role, 'type' => $type, 'sub_type' => $role]);
            DB::table('semantic_account_mappings')->updateOrInsert(['company_id' => $this->company->id, 'semantic_role' => $role],
                ['account_id' => $account->id, 'is_active' => true, 'account_code' => $account->code, 'account_name' => $role, 'category' => 'core']);
        }
    }

    public function test_credit_note_on_an_invoice_from_a_dc_returns_the_dc_stock(): void
    {
        $product = $this->gstProduct();
        $dc = DeliveryChallan::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'financial_year_id' => $this->year->id,
            'challan_no' => 'DC-T1', 'challan_date' => '2026-10-03', 'customer_id' => 1, 'warehouse_id' => 1, 'status' => 'pending', 'purpose' => 'approval']);
        DeliveryChallanItem::create(['company_id' => $this->company->id, 'delivery_challan_id' => $dc->id, 'product_id' => $product->id,
            'unit_id' => 1, 'qty' => 2, 'rate' => 10, 'amount' => 20, 'total' => 20]);
        app(MaterialDocumentService::class)->post($dc, $this->context(), 1);
        $this->assertEquals(18, $product->fresh()->qty);

        $sale = app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, ['delivery_challan_id' => $dc->id]), 'dc-sale', 1, $this->context()));
        $this->assertEquals(18, $product->fresh()->qty, 'the invoice settles the DC instead of issuing again');

        $this->postedReturn($sale);
        $this->assertEquals(19, $product->fresh()->qty, 'the returned unit comes back from the DC issue');
        $this->assertSame(0, DB::table('stock_movements')->where('source_type', 'sale')->count(), 'the invoice itself never moved stock');
        $this->assertSame(2, DB::table('stock_movements')->count(), 'the DC issue and the credit note receipt');
        $this->assertSame('converted_to_sale', $dc->fresh()->status);
    }
}
