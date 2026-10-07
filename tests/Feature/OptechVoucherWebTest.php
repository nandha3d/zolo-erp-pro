<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\DocumentSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OptechVoucherWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.enabled' => true]);
        config(['compliance.enabled' => true]);
        putenv('ERP_OPTIONAL_ACTIVATION_READY=true');
        $_ENV['ERP_OPTIONAL_ACTIVATION_READY'] = 'true';
        \Illuminate\Support\Facades\Cache::flush();
        $this->adminUser = User::first();
    }

    public function test_voucher_entry_page_loads_with_database_masters(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/accounting/voucher/entry');

        $response->assertStatus(200);
        $response->assertViewHas('accounts');
        $response->assertViewHas('series');
        $response->assertViewHas('remarks');
        $response->assertSee('Express Voucher Entry');
        $response->assertSee('F4 · Contra');
        $response->assertSee('F5 · Payment');
        $response->assertSee('F6 · Receipt');
        $response->assertSee('F7 · Journal');
        $response->assertSee('Alt+C');
        $response->assertSee('Alt+S');
    }

    public function test_voucher_next_number_preview(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/accounting/voucher/next-number');

        $response->assertStatus(200);
        $response->assertJsonStructure(['next_number']);
    }

    public function test_voucher_inline_account_creation(): void
    {
        $uniqueCode = 'TEST-ACC-' . uniqid();
        $response = $this->actingAs($this->adminUser)
            ->postJson('/accounting/voucher/inline-account', [
                'code' => $uniqueCode,
                'name' => 'Test Stationery Ledger',
                'type' => 'expense',
                'sub_type' => 'operating_expense',
                'opening_balance' => 0,
            ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('chart_of_accounts', [
            'company_id' => 1,
            'code' => $uniqueCode,
            'name' => 'Test Stationery Ledger',
            'allow_manual_posting' => true,
        ]);
    }

    public function test_voucher_posting_balanced_dr_cr(): void
    {
        $cashAcc = ChartOfAccount::forceCreate([
            'company_id' => 1, 'code' => 'CSH-LF-' . uniqid(), 'name' => 'Cash Leaf',
            'type' => 'asset', 'sub_type' => 'cash', 'control_type' => 'cash',
            'allow_manual_posting' => true, 'is_active' => true,
        ]);
        $expAcc = ChartOfAccount::forceCreate([
            'company_id' => 1, 'code' => 'EXP-LF-' . uniqid(), 'name' => 'Expense Leaf',
            'type' => 'expense', 'sub_type' => 'operating_expense', 'control_type' => 'none',
            'allow_manual_posting' => true, 'is_active' => true,
        ]);

        $series = DocumentSeries::where('company_id', 1)->where('document_type', 'journal')->first();

        $postData = [
            'entry_date' => now()->toDateString(),
            'description' => 'Being office expense paid in cash',
            'voucher_type' => 'payment',
            'series_code' => $series ? $series->code : null,
            'gst_nature' => 'Taxable',
            'cheque_no' => 'CHQ-100201',
            'cheque_date' => now()->toDateString(),
            'idempotency_key' => 'vch-test-' . uniqid(),
            'items' => [
                [
                    'chart_of_account_id' => $expAcc->id,
                    'debit' => 500,
                    'credit' => 0,
                    'memo' => 'Office supplies',
                ],
                [
                    'chart_of_account_id' => $cashAcc->id,
                    'debit' => 0,
                    'credit' => 500,
                    'memo' => 'Paid via cash',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/accounting/journal-entries', $postData);
        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $entryId = $response->json('data.id');
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entryId,
            'company_id' => 1,
            'voucher_type' => 'payment',
            'gst_nature' => 'Taxable',
            'cheque_no' => 'CHQ-100201',
        ]);
    }

    public function test_voucher_posting_rejects_unbalanced_entry(): void
    {
        $cashAcc = ChartOfAccount::where('company_id', 1)->where('type', 'asset')->where('allow_manual_posting', true)->first();
        $expAcc = ChartOfAccount::where('company_id', 1)->where('type', 'expense')->where('allow_manual_posting', true)->first();

        if (!$cashAcc) {
            $cashAcc = ChartOfAccount::forceCreate([
                'company_id' => 1, 'code' => 'CASH-UNBAL-' . uniqid(), 'name' => 'Cash Unbal',
                'type' => 'asset', 'sub_type' => 'cash', 'control_type' => 'cash',
                'allow_manual_posting' => true, 'is_active' => true,
            ]);
        }
        if (!$expAcc) {
            $expAcc = ChartOfAccount::forceCreate([
                'company_id' => 1, 'code' => 'EXP-UNBAL-' . uniqid(), 'name' => 'Expense Unbal',
                'type' => 'expense', 'sub_type' => 'operating_expense', 'control_type' => 'none',
                'allow_manual_posting' => true, 'is_active' => true,
            ]);
        }

        $postData = [
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced entry test',
            'voucher_type' => 'journal',
            'idempotency_key' => 'vch-unbal-' . uniqid(),
            'items' => [
                [
                    'chart_of_account_id' => $expAcc->id,
                    'debit' => 500,
                    'credit' => 0,
                ],
                [
                    'chart_of_account_id' => $cashAcc->id,
                    'debit' => 0,
                    'credit' => 300, // Unbalanced: 500 Dr vs 300 Cr
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/accounting/journal-entries', $postData);
        $response->assertStatus(422);
    }

    public function test_purchase_command_center_renders_entry_workspace(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/purchases');

        $response->assertStatus(200);
        $response->assertSee('Purchase Command Center');
        $response->assertSee('comm-entry-workspace', false);
        $response->assertSee('New Purchase Bill');
        $response->assertSee('comm-drawer', false);
        $response->assertSee('side-toolbar-actions', false);
        $response->assertSee('Charges & remarks', false);
        $response->assertSee('GRAND TOTAL');
    }

    public function test_sales_command_center_renders_entry_workspace(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/sales');

        $response->assertStatus(200);
        $response->assertSee('Sales Command Center');
        $response->assertSee('comm-entry-workspace', false);
        $response->assertSee('New Sales Bill');
        $response->assertSee('comm-drawer', false);
        $response->assertSee('side-toolbar-actions', false);
        $response->assertSee('side-dropdown-filters', false);
        $response->assertSee('Charges & remarks', false);
        $response->assertSee('GRAND TOTAL');
    }

    public function test_purchase_json_endpoint(): void
    {
        $purchase = \App\Models\Purchase::first();
        if ($purchase) {
            $response = $this->actingAs($this->adminUser)->getJson('/purchases/' . $purchase->id);
            $response->assertStatus(200);
            $response->assertJsonStructure(['success', 'purchase', 'items']);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_sale_json_endpoint(): void
    {
        $sale = \App\Models\Sale::first();
        if ($sale) {
            $response = $this->actingAs($this->adminUser)->getJson('/sales/' . $sale->id . '/json');
            $response->assertStatus(200);
            $response->assertJsonStructure(['success', 'sale', 'items']);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_quick_store_category(): void
    {
        $catName = 'Test Yarn Fabrics ' . uniqid();
        $response = $this->actingAs($this->adminUser)->postJson('/categories/quick-store', [
            'name' => $catName,
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'category' => ['id', 'name']]);
        $this->assertDatabaseHas('categories', [
            'name' => $catName,
            'is_active' => true,
        ]);
    }

    public function test_quick_store_brand(): void
    {
        $brandTitle = 'Test Apex Brand ' . uniqid();
        $response = $this->actingAs($this->adminUser)->postJson('/brands/quick-store', [
            'title' => $brandTitle,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'brand' => ['id', 'title']]);
        $this->assertDatabaseHas('brands', [
            'title' => $brandTitle,
            'is_active' => true,
        ]);
    }

    public function test_quick_store_product_full_catalog(): void
    {
        $code = 'ITM-TEST-' . rand(100000, 999999);
        $unit = \App\Models\Unit::first();
        $tax = \App\Models\Tax::where('rate', 18)->first();

        $response = $this->actingAs($this->adminUser)->postJson('/products/quick-store', [
            'name' => 'Premium Grey Cotton 40s',
            'code' => $code,
            'type' => 'standard',
            'barcode_symbology' => 'code128',
            'unit_id' => $unit ? $unit->id : 1,
            'cost' => 450.00,
            'price' => 562.50,
            'profit_margin' => 25.00,
            'profit_margin_type' => 'percentage',
            'wholesale_price' => 520.00,
            'daily_sale_objective' => 10,
            'alert_quantity' => 5,
            'tax_id' => $tax ? $tax->id : null,
            'tax_method' => 1,
            'warranty' => 12,
            'warranty_type' => 'months',
            'guarantee' => 6,
            'guarantee_type' => 'months',
            'is_batch' => 1,
            'is_imei' => 0,
            'featured' => 1,
            'is_online' => 1,
            'in_stock' => 1,
            'product_details' => 'Full catalog created via modal',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('products', [
            'code' => $code,
            'name' => 'Premium Grey Cotton 40s',
            'is_active' => true,
        ]);
    }
}

