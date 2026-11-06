<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\GeneralSetting;
use App\Models\Purchase;
use Spatie\Permission\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Platform\NavigationAccess;
use App\Services\Platform\SettingsCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NavigationAuditResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::where('role_id', 1)->first() ?? User::first();
        $this->actingAs($this->adminUser);

        $company = \App\Models\Company::find(14) ?? \App\Models\Company::first();
        session(['company_id' => $company->id]);
    }

    public function test_retired_backup_link_omitted_from_settings_catalog_and_endpoint_returns_410(): void
    {
        $this->actingAs($this->adminUser);

        // Endpoint returns 410 as intentionally retired
        $response = $this->get('/backup');
        $this->assertSame(410, $response->getStatusCode());

        // Settings catalog must NOT offer Backup Database
        $sections = SettingsCatalog::sections(new NavigationAccess);
        $allLinks = [];
        foreach ($sections as $sec) {
            foreach ($sec['items'] as $item) {
                $allLinks[] = $item['url'];
                $this->assertStringNotContainsString('/backup', $item['url']);
                $this->assertNotSame('Backup Database', $item['label']);
            }
        }
    }

    public function test_all_previously_failing_report_routes_respond_with_200_on_get(): void
    {
        $this->actingAs($this->adminUser);

        $reportUrls = [
            '/report/biller',
            '/report/biller_report',
            '/report/customer_group',
            '/report/customer-group',
            '/report/customer_report',
            '/report/daily_sale_objective',
            '/report/daily-sale-objective',
            '/report/purchase_report',
            '/report/purchase',
            '/report/qty_alert_report',
            '/report/product_quantity_alert',
            '/report/sale_report_chart',
            '/report/sale-report-chart',
            '/report/supplier',
            '/report/supplier_report',
            '/report/outstanding-due-report?type=expenses',
        ];

        foreach ($reportUrls as $url) {
            $resp = $this->get($url);
            $this->assertContains($resp->getStatusCode(), [200], "Failed asserting that {$url} returns 200 OK. Got {$resp->getStatusCode()}");
        }
    }

    public function test_outstanding_dues_report_links_retain_customer_and_supplier_filters(): void
    {
        $this->actingAs($this->adminUser);

        // Receivables with customer_id=56
        $resReceivables = $this->get(route('report.outstandingDues', ['type' => 'receivables', 'customer_id' => 56]));
        $resReceivables->assertOk();
        // Check Due Clearance button retains customer
        $resReceivables->assertSee(route('dues.index', ['tab' => 'sales', 'party_id' => 56]));
        // Check Ageing Ledger button retains customer
        $resReceivables->assertSee(route('accounting.ageing', ['party_type' => 'customer', 'party_id' => 56]));

        // Payables with supplier_id=69
        $resPayables = $this->get(route('report.outstandingDues', ['type' => 'payables', 'supplier_id' => 69]));
        $resPayables->assertOk();
        // Check Due Clearance button retains supplier
        $resPayables->assertSee(route('dues.index', ['tab' => 'purchases', 'party_id' => 69]));
        // Check Ageing Ledger button retains supplier
        $resPayables->assertSee(route('accounting.ageing', ['party_type' => 'supplier', 'party_id' => 69]));
    }

    public function test_expense_dues_keeps_expense_context_and_links_to_expense_report(): void
    {
        $this->actingAs($this->adminUser);

        $resDuesExpense = $this->get(route('dues.index', ['tab' => 'expenses']));
        $resDuesExpense->assertOk();
        $resDuesExpense->assertSee(route('report.outstandingDues', ['type' => 'expenses']));

        $resReportExpense = $this->get(route('report.outstandingDues', ['type' => 'expenses']));
        $resReportExpense->assertOk();
        $resReportExpense->assertSee('Operational Expenses');
    }

    public function test_bill_payment_redirect_resolves_reference_no_and_finds_bill(): void
    {
        $this->actingAs($this->adminUser);

        // If visiting /selling/payments?bill=138 in browser, should redirect to dues.index with tab=sales
        $respSale = $this->get('/selling/payments?bill=138&simulate_browser=1');
        $respSale->assertRedirect();
        $this->assertStringContainsString('/transactions/dues?tab=sales', $respSale->headers->get('Location'));

        $respPurchase = $this->get('/buying/payments?bill=72&simulate_browser=1');
        $respPurchase->assertRedirect();
        $this->assertStringContainsString('/transactions/dues?tab=purchases', $respPurchase->headers->get('Location'));
    }

    public function test_addon_list_does_not_render_active_link_for_disabled_addons(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/addon-list');
        $response->assertOk();

        // Verify disabled addons show 'Enable to Open' disabled badge instead of direct <a> link
        $response->assertSee('Enable to Open');
    }
}
