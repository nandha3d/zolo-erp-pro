<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Platform\CompanyContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutstandingDuesAndGstTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('role_id', 1)->first() ?? User::create([
            'name' => 'Admin User',
            'email' => 'admin_dues@test.com',
            'phone' => '9876543210',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        $this->actingAs($this->user);
        $this->company = Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);

        if (!$this->company->users()->where('users.id', $this->user->id)->exists()) {
            $this->company->users()->attach($this->user->id, ['is_default' => true]);
        }
        $branch = $this->company->branches()->first() ?? $this->company->branches()->create(['code' => 'MAIN', 'name' => 'Main Branch']);
        DB::table('company_user_branches')->updateOrInsert(
            ['company_id' => $this->company->id, 'user_id' => $this->user->id, 'branch_id' => $branch->id],
            ['company_id' => $this->company->id, 'user_id' => $this->user->id, 'branch_id' => $branch->id]
        );
        $year = \App\Models\Accounting\FiscalYear::where('company_id', $this->company->id)->first() ?? \App\Models\Accounting\FiscalYear::create([
            'company_id' => $this->company->id, 'name' => 'FY 2026', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'status' => 'open'
        ]);

        $this->withSession([
            'company_id' => $this->company->id,
            'current_company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'financial_year_id' => $year->id,
            'warehouse_id' => 1,
        ]);
    }

    public function test_outstanding_dues_report_loads_receivables_via_get_and_post(): void
    {
        $responseGet = $this->get(route('report.outstandingDues', ['type' => 'receivables']));
        $responseGet->assertOk();
        $responseGet->assertViewIs('backend.report.outstanding_dues');
        $responseGet->assertSee('Outstanding Payments (Dues) Command Center');
        $responseGet->assertSee('Receivables (Customers)');

        $responsePost = $this->post(route('report.outstandingDues'), ['type' => 'receivables']);
        $responsePost->assertOk();
        $responsePost->assertViewIs('backend.report.outstanding_dues');
    }

    public function test_outstanding_dues_report_loads_payables_via_get(): void
    {
        $response = $this->get(route('report.outstandingDues', ['type' => 'payables']));
        $response->assertOk();
        $response->assertViewIs('backend.report.outstanding_dues');
        $response->assertSee('Payables (Suppliers)');
    }

    public function test_legacy_due_reports_support_get_without_405_or_missing_keys(): void
    {
        $customerDue = $this->get(route('report.customerDueByDate'));
        $customerDue->assertOk();
        $customerDue->assertViewIs('backend.report.due_report');

        $supplierDue = $this->get(route('report.supplierDueByDate'));
        $supplierDue->assertOk();
        $supplierDue->assertViewIs('backend.report.supplier_due_report');
    }

    public function test_company_creation_requires_gst_type(): void
    {
        $response = $this->post('/company-picker/companies', [
            'company_group_id' => '',
            'trade_name' => 'Test Co',
            'legal_name' => 'Test Co Pvt Ltd',
            'code' => 'TESTCO' . rand(100, 999),
            'industry' => 'general_trading',
            'subtype' => 'trading',
            'registration_type' => '', // Empty registration_type
            'timezone' => 'Asia/Kolkata',
            'fy_start' => '2026-04-01',
            'fy_end' => '2027-03-31',
        ]);

        $response->assertSessionHasErrors('registration_type');
    }

    public function test_company_creation_accepts_valid_gst_type(): void
    {
        $uniqueCode = 'TST' . rand(1000, 9999);
        $response = $this->post('/company-picker/companies', [
            'company_group_id' => '',
            'trade_name' => 'Test Co Valid',
            'legal_name' => 'Test Co Valid Pvt Ltd',
            'code' => $uniqueCode,
            'industry' => 'general_trading',
            'subtype' => 'trading',
            'registration_type' => 'regular',
            'timezone' => 'Asia/Kolkata',
            'fy_start' => '2026-04-01',
            'fy_end' => '2027-03-31',
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_customer_and_supplier_action_menus_link_to_outstanding_dues_report(): void
    {
        $customer = Customer::first() ?? Customer::factory()->create(['company_id' => $this->company->id]);
        $supplier = Supplier::where('company_id', $this->company->id)->first() ?? Supplier::create([
            'name' => 'Demo Test Supplier',
            'company_name' => 'Test Supplier Co',
            'phone_number' => '9894411999',
            'email' => 'supplier@test.com',
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        // Customer Data AJAX endpoint returns action menu containing outstanding dues link
        $customerData = $this->post('/customers/customer-data', [
            'all_permission' => ['customers-index', 'due-report'],
            'length' => 10,
            'start' => 0,
            'order' => [['column' => 0, 'dir' => 'desc']],
        ]);
        $customerData->assertOk();
        $this->assertStringContainsString('report\/outstanding-due-report?type=receivables', $customerData->getContent());

        // Supplier index view contains action menu link to outstanding dues report
        $supplierIndex = $this->get(route('supplier.index'));
        $supplierIndex->assertOk();
        $supplierIndex->assertSee('report/outstanding-due-report?type=payables', false);
    }

    public function test_sidebar_outstanding_dues_menu_has_no_wallet_icon(): void
    {
        $response = $this->get('/dashboard');
        $response->assertOk();
        $response->assertSee('id="outstanding-due-report-menu"', false);
        $response->assertDontSee('<li id="outstanding-due-report-menu"><a href="' . route('report.outstandingDues') . '" data-keywords="customer supplier dues receivables payables outstanding"><i class="dripicons-wallet', false);
    }

    public function test_outstanding_dues_report_has_theme_nav_pills(): void
    {
        $response = $this->get(route('report.outstandingDues'));
        $response->assertOk();
        $response->assertSee('dues-report-nav-toolbar', false);
        $response->assertSee('pill-receivables', false);
        $response->assertSee('pill-payables', false);
        $response->assertSee('pill-ageing', false);
        $response->assertSee('pill-hub', false);
    }
}
