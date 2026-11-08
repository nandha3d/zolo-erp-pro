<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VoucherPopupAndProductEnhancementsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('role_id', 1)->first() ?? User::first();
        $this->company = Company::first();

        if ($this->company) {
            if (!$this->company->users()->where('users.id', $this->admin->id)->exists()) {
                $this->company->users()->attach($this->admin->id, ['is_default' => true]);
            }
            $branch = $this->company->branches()->first() ?? $this->company->branches()->create(['code' => 'MAIN', 'name' => 'Main Branch']);
            \Illuminate\Support\Facades\DB::table('company_user_branches')->updateOrInsert(
                ['company_id' => $this->company->id, 'user_id' => $this->admin->id, 'branch_id' => $branch->id],
                ['company_id' => $this->company->id, 'user_id' => $this->admin->id, 'branch_id' => $branch->id]
            );
            $year = \App\Models\Accounting\FiscalYear::where('company_id', $this->company->id)->first() ?? \App\Models\Accounting\FiscalYear::create([
                'company_id' => $this->company->id, 'name' => 'FY 2026', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'status' => 'open'
            ]);
            $this->withSession([
                'company_id' => $this->company->id,
                'branch_id' => $branch->id,
                'financial_year_id' => $year->id,
                'warehouse_id' => 1,
            ]);
        }
        $this->actingAs($this->admin);
    }

    public function test_sale_index_has_modern_voucher_preview_card_and_edit_button()
    {
        $response = $this->get(route('sales.index'));

        $response->assertStatus(200);
        $response->assertSee('voucher-preview-card', false);
        $response->assertSee('voucher-edit-btn', false);
        $response->assertSee('voucher-print-btn', false);
        $response->assertSee('voucher-preview-modal.css', false);
        $response->assertSee('openSaleDetailsModal', false);
        $response->assertSee('sale-details-customer-gstin', false);
    }

    public function test_purchase_index_has_modern_voucher_preview_card_and_edit_button()
    {
        $response = $this->get(route('purchases.index'));

        $response->assertStatus(200);
        $response->assertSee('voucher-preview-card', false);
        $response->assertSee('voucher-edit-btn', false);
        $response->assertSee('voucher-print-btn', false);
        $response->assertSee('voucher-preview-modal.css', false);
        $response->assertSee('openPurchaseDetailsModal', false);
        $response->assertSee('purchase-details-supplier-gstin', false);
    }

    public function test_purchase_ajax_show_returns_hydrated_supplier_and_items()
    {
        $purchase = \App\Models\Purchase::first();
        if (!$purchase) {
            $this->markTestSkipped('No purchase available');
        }

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('purchases.show', $purchase->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'purchase' => [
                'id',
                'reference_no',
                'grand_total',
                'paid_amount',
            ],
            'items',
        ]);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals($purchase->id, $data['purchase']['id']);
    }

    public function test_sale_ajax_show_returns_hydrated_customer_and_items()
    {
        $sale = \App\Models\Sale::first();
        if (!$sale) {
            $this->markTestSkipped('No sale available');
        }

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('sales.show', $sale->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'sale' => [
                'id',
                'reference_no',
                'grand_total',
                'paid_amount',
            ],
            'items',
        ]);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals($sale->id, $data['sale']['id']);
    }

    public function test_product_index_has_modern_popup_and_highlight_buttons()
    {
        $response = $this->actingAs($this->admin)->get(route('products.index'));

        $response->assertStatus(200);
        $response->assertSee('product-details', false);
        $response->assertSee('voucher-preview-card', false);
        $response->assertSee('voucher-btn-sales', false);
        $response->assertSee('voucher-btn-purchase', false);
        $response->assertSee('voucher-btn-composition', false);
        $response->assertSee('modal-product-edit-btn', false);
        $response->assertSee('voucher-preview-modal.css', false);
    }

    public function test_product_edit_contains_all_restored_components()
    {
        $product = Product::first() ?? Product::create([
            'name' => 'Demo Master Item',
            'code' => 'DMI-001',
            'type' => 'standard',
            'barcode_symbology' => 'C128',
            'unit_id' => 1,
            'purchase_unit_id' => 1,
            'sale_unit_id' => 1,
            'cost' => 100,
            'price' => 150,
            'qty' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('products.edit', $product->id));

        $response->assertStatus(200);
        $response->assertSee('name="print_name"', false);
        $response->assertSee('data-target="#addBrand"', false);
        $response->assertSee('data-target="#category-modal"', false);
        $response->assertSee('data-target="#createUnitModal"', false);
        $response->assertSee('id="stock-section"', false);
        $response->assertSee('id="variant-option"', false);
        $response->assertSee('id="initial-stock-section"', false);
        $response->assertSee('id="addBrand"', false);
        $response->assertSee('id="createUnitModal"', false);
    }

    public function test_product_history_has_highlight_theme_pills_and_composition_tab()
    {
        $product = Product::first();
        if (!$product) {
            $this->markTestSkipped('No product available for history test.');
        }

        $response = $this->actingAs($this->admin)->get(route('products.history', ['product_id' => $product->id]));

        $response->assertStatus(200);
        $response->assertSee('product-history-nav-pills', false);
        $response->assertSee('pill-sales', false);
        $response->assertSee('pill-purchases', false);
        $response->assertSee('pill-composition', false);
        $response->assertSee('pill-sale-return', false);
        $response->assertSee('pill-purchase-return', false);
        $response->assertSee('id="product-composition"', false);
    }

    public function test_guided_tutorial_modal_and_navbar_launcher_rendered()
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="btn-open-guided-tour"', false);
        $response->assertSee('id="zolo-guided-tour-modal"', false);
        $response->assertSee('Interactive Setup Guide & Tour', false);
        $response->assertSee('tour-rail-step', false);
    }
}
