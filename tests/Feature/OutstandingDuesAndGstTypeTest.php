<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Platform\CompanyContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OutstandingDuesAndGstTypeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::first() ?? User::factory()->create();
        $this->actingAs($this->user);
        $this->company = Company::find(2) ?? Company::first();
        session(['company_id' => $this->company->id]);
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
        $this->assertDatabaseHas('companies', ['code' => $uniqueCode]);
    }
}
