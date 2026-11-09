<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerSupplierReportsNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::first() ?? User::factory()->create(['role_id' => 1]);
        $this->actingAs($admin);

        $company = Company::first();
        if ($company) {
            $this->withSession(['company_id' => $company->id]);
        }
    }

    public function test_sidebar_contains_customer_and_supplier_report_links(): void
    {
        $response = $this->get('/dashboard');
        $response->assertOk();

        $response->assertSee('id="customer-report-menu"', false);
        $response->assertSee(route('report.customer'), false);

        $response->assertSee('id="due-report-menu"', false);
        $response->assertSee(route('report.customerDueByDate'), false);

        $response->assertSee('id="customer-group-report-menu"', false);
        $response->assertSee(route('report.customer_group'), false);

        $response->assertSee('id="supplier-report-menu"', false);
        $response->assertSee(route('report.supplier'), false);

        $response->assertSee('id="supplier-due-report-menu"', false);
        $response->assertSee(route('report.supplierDueByDate'), false);

        $response->assertSee('id="outstanding-due-report-menu"', false);
        $response->assertSee(route('report.outstandingDues'), false);
    }

    public function test_customer_report_page_loads_successfully(): void
    {
        $response = $this->get(route('report.customer'));
        $response->assertOk();
        $response->assertViewIs('backend.report.customer_report');
        $response->assertSee('id="customer-ledger-table"', false);
        $response->assertSee('ledger-opening-row', false);
        $response->assertSee('Running Balance', false);
        $response->assertSee('Print Complete Ledger', false);
        $response->assertDontSee('desk-report-tabs', false);
        $response->assertDontSee('nav-tabs', false);
    }

    public function test_supplier_report_page_loads_successfully(): void
    {
        $response = $this->get(route('report.supplier'));
        $response->assertOk();
        $response->assertViewIs('backend.report.supplier_report');
        $response->assertSee('id="supplier-ledger-table"', false);
        $response->assertSee('ledger-opening-row', false);
        $response->assertSee('Running Balance', false);
        $response->assertSee('Print Complete Ledger', false);
        $response->assertDontSee('desk-report-tabs', false);
        $response->assertDontSee('nav-tabs', false);
    }

    public function test_customer_report_filter_post(): void
    {
        $customer = Customer::where('is_active', true)->where('type', '!=', 'walkin')->first();
        if ($customer) {
            $response = $this->post(route('report.customer'), [
                'customer_id' => $customer->id,
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
                'transaction_type' => 'sale',
            ]);
            $response->assertOk();
            $response->assertViewIs('backend.report.customer_report');
            $response->assertSee('id="customer-ledger-table"', false);
            $response->assertDontSee('desk-report-tabs', false);
        }
    }

    public function test_supplier_report_filter_post(): void
    {
        $supplier = Supplier::where('is_active', true)->first();
        if ($supplier) {
            $response = $this->post(route('report.supplier'), [
                'supplier_id' => $supplier->id,
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
                'transaction_type' => 'purchase',
            ]);
            $response->assertOk();
            $response->assertViewIs('backend.report.supplier_report');
            $response->assertSee('id="supplier-ledger-table"', false);
            $response->assertDontSee('desk-report-tabs', false);
        }
    }

    public function test_customer_group_report_page_loads_successfully(): void
    {
        $response = $this->get(route('report.customer_group'));
        $response->assertOk();
        $response->assertViewIs('backend.report.customer_group_report');
    }

    public function test_customer_report_renders_all_ledger_rows(): void
    {
        $customer = Customer::withoutGlobalScopes()->where('name', 'like', '%Kaveri%')->first()
            ?? Customer::withoutGlobalScopes()->where('name', 'like', '%Tiruppur%')->first()
            ?? Customer::first();
        if (!$customer) {
            $this->markTestSkipped('Customer does not exist');
        }

        $this->withSession(['company_id' => $customer->company_id]);

        $response = $this->get(route('report.customer', ['customer_id' => $customer->id, 'start_date' => '2025-11-06', 'end_date' => '2026-11-06', 'transaction_type' => 'all']));
        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('Opening Balance Brought Forward', $content);
        if (str_contains($customer->name, 'Kaveri')) {
            $this->assertStringContainsString('MJ-00005', $content);
        }
    }
}

