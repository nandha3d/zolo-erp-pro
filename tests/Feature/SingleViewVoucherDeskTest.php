<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SingleViewVoucherDeskTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role_id', 1)->first() ?? User::first();
        $this->actingAs($this->adminUser);

        $company = Company::find(14) ?? Company::first();
        if ($company) {
            if (!$company->users()->where('users.id', $this->adminUser->id)->exists()) {
                $company->users()->attach($this->adminUser->id, ['is_default' => true]);
            }
            $branch = $company->branches()->first() ?? $company->branches()->create(['code' => 'MAIN', 'name' => 'Main Branch']);
            DB::table('company_user_branches')->updateOrInsert(
                ['company_id' => $company->id, 'user_id' => $this->adminUser->id, 'branch_id' => $branch->id],
                ['company_id' => $company->id, 'user_id' => $this->adminUser->id, 'branch_id' => $branch->id]
            );
            $year = \App\Models\Accounting\FiscalYear::where('company_id', $company->id)->first() ?? \App\Models\Accounting\FiscalYear::create([
                'company_id' => $company->id, 'name' => 'FY 2026', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'status' => 'open'
            ]);
            $this->withSession([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'financial_year_id' => $year->id,
            ]);
        }
    }

    public function test_purchase_edit_redirects_to_single_view_workspace(): void
    {
        $purchase = Purchase::withoutGlobalScopes()->first();
        if (!$purchase) {
            $this->markTestSkipped('No purchase records in test database');
        }
        if ($purchase->company_id) {
            session(['company_id' => $purchase->company_id]);
        }

        $response = $this->get(route('purchases.edit', $purchase->id));
        $response->assertRedirect(route('purchases.index', ['edit' => $purchase->id]));
    }

    public function test_purchase_duplicate_redirects_to_single_view_workspace(): void
    {
        $purchase = Purchase::withoutGlobalScopes()->first();
        if (!$purchase) {
            $this->markTestSkipped('No purchase records in test database');
        }
        if ($purchase->company_id) {
            session(['company_id' => $purchase->company_id]);
        }

        $response = $this->get(route('purchase.duplicate', $purchase->id));
        $response->assertRedirect(route('purchases.index', ['duplicate' => $purchase->id]));
    }

    public function test_purchase_browser_view_redirects_to_single_view_workspace(): void
    {
        $purchase = Purchase::withoutGlobalScopes()->first();
        if (!$purchase) {
            $this->markTestSkipped('No purchase records in test database');
        }
        if ($purchase->company_id) {
            session(['company_id' => $purchase->company_id]);
        }

        $response = $this->get(url('purchases/' . $purchase->id));
        $response->assertRedirect(route('purchases.index', ['view_id' => $purchase->id]));
    }

    public function test_purchase_ajax_show_returns_json(): void
    {
        $purchase = Purchase::withoutGlobalScopes()->first();
        if (!$purchase) {
            $this->markTestSkipped('No purchase records in test database');
        }

        $sessionData = $purchase->company_id ? ['company_id' => $purchase->company_id] : [];
        $response = $this->withSession($sessionData)->getJson(url('purchases/' . $purchase->id));
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['purchase', 'items']);
    }

    public function test_sale_edit_redirects_to_single_view_workspace(): void
    {
        $sale = Sale::withoutGlobalScopes()->first();
        if (!$sale) {
            $this->markTestSkipped('No sale records in test database');
        }
        if ($sale->company_id) {
            session(['company_id' => $sale->company_id]);
        }

        $response = $this->get(route('sales.edit', $sale->id));
        $response->assertRedirect(route('sales.index', ['edit' => $sale->id]));
    }

    public function test_sale_browser_view_redirects_to_single_view_workspace(): void
    {
        $sale = Sale::withoutGlobalScopes()->first();
        if (!$sale) {
            $this->markTestSkipped('No sale records in test database');
        }
        if ($sale->company_id) {
            session(['company_id' => $sale->company_id]);
        }

        $response = $this->get(url('sales/' . $sale->id));
        $response->assertRedirect(route('sales.index', ['view_id' => $sale->id]));
    }

    public function test_purchase_workspace_contains_single_view_elements(): void
    {
        $response = $this->get(route('purchases.index'));
        $response->assertOk();

        // Check for view mode action buttons in the single view setup
        $response->assertSee('view-mode-actions', false);
        $response->assertSee('btn-view-to-edit', false);
        $response->assertSee('btn-view-to-duplicate', false);
        $response->assertSee('btn-view-close', false);

        // Check for unhidden Duplicate button on side cards
        $response->assertSee('btn-side-copy', false);
        $response->assertSee('Duplicate', false);
    }

    public function test_sale_workspace_contains_single_view_elements(): void
    {
        $response = $this->get(route('sales.index'));
        $response->assertOk();

        // Check for view mode action buttons in the single view setup
        $response->assertSee('view-mode-actions', false);
        $response->assertSee('btn-view-to-edit', false);
        $response->assertSee('btn-view-to-duplicate', false);
        $response->assertSee('btn-view-close', false);

        // Check for unhidden Duplicate button on side cards
        $response->assertSee('btn-side-copy', false);
        $response->assertSee('Duplicate', false);
    }
}
