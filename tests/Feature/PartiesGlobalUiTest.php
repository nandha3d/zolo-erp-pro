<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartiesGlobalUiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Company $company;
    protected Customer $customer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.enabled' => true]);
        $this->adminUser = User::where('role_id', 1)->first() ?? User::create([
            'name' => 'Admin User',
            'email' => 'admin_ui@test.com',
            'phone' => '9876543210',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
        $this->company = Company::first() ?? Company::create(['name' => 'Test Company', 'code' => 'TC', 'is_active' => true]);

        if (!$this->company->users()->where('users.id', $this->adminUser->id)->exists()) {
            $this->company->users()->attach($this->adminUser->id, ['is_default' => true]);
        }
        $branch = $this->company->branches()->first() ?? $this->company->branches()->create(['code' => 'MAIN', 'name' => 'Main Branch']);
        DB::table('company_user_branches')->updateOrInsert(
            ['company_id' => $this->company->id, 'user_id' => $this->adminUser->id, 'branch_id' => $branch->id],
            ['company_id' => $this->company->id, 'user_id' => $this->adminUser->id, 'branch_id' => $branch->id]
        );
        $year = \App\Models\Accounting\FiscalYear::where('company_id', $this->company->id)->first() ?? \App\Models\Accounting\FiscalYear::create([
            'company_id' => $this->company->id, 'name' => 'FY 2026', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'status' => 'open'
        ]);

        $group = CustomerGroup::where('company_id', $this->company->id)->first()
            ?? CustomerGroup::first()
            ?? CustomerGroup::create(['name' => 'General', 'percentage' => '0', 'is_active' => true, 'company_id' => $this->company->id]);

        $this->customer = Customer::where('company_id', $this->company->id)->first() ?? Customer::create([
            'name' => 'Test UI Customer',
            'company_name' => 'Test Customer Org',
            'customer_group_id' => $group->id,
            'phone_number' => '9876543210',
            'email' => 'uicustomer@test.com',
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::where('company_id', $this->company->id)->first() ?? Supplier::create([
            'name' => 'Test UI Supplier',
            'company_name' => 'Test Supplier Mills',
            'phone_number' => '9876543211',
            'email' => 'uisupplier@test.com',
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser);
        $this->withSession([
            'company_id' => $this->company->id,
            'current_company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'financial_year_id' => $year->id,
            'warehouse_id' => 1,
        ]);
    }

    public function test_parties_sidebar_has_all_parties_navigation_links(): void
    {
        $response = $this->get('/dashboard');
        $response->assertOk();
        $response->assertSee('id="parties"', false);
        $response->assertSee('id="customer-list-menu"', false);
        $response->assertSee('id="customer-group-menu"', false);
        $response->assertSee('id="supplier-list-menu"', false);
        $response->assertSee('id="agent-list-menu"', false);
        $response->assertSee('id="area-list-menu"', false);
    }

    public function test_customer_create_page_renders_with_parties_global_ui(): void
    {
        $response = $this->get(route('customer.create'));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('party-single-card', false);
        $response->assertSee('party-sec-identity', false);
        $response->assertSee('party-sec-contact', false);
        $response->assertSee('party-sec-address', false);
        $response->assertSee('party-sec-credit', false);
        $response->assertSee('party-sec-user', false);
        $response->assertSee('Both Customer and Supplier', false);
        $response->assertSee('parties-workspace.css', false);
    }

    public function test_customer_edit_page_renders_with_parties_global_ui(): void
    {
        $response = $this->get(route('customer.edit', $this->customer->id));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('party-single-card', false);
        $response->assertSee('party-sec-identity', false);
        $response->assertSee($this->customer->name, false);
    }

    public function test_customer_index_renders_with_parties_command_bar(): void
    {
        $response = $this->get(route('customer.index'));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('customer-workspace-view', false);
        $response->assertSee('desk-card-wrapper', false);
        $response->assertSee('Customer Groups', false);
    }

    public function test_customer_view_renders_with_parties_global_ui(): void
    {
        $response = $this->get(route('customer.show', $this->customer->id));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('party-profile-card', false);
        $response->assertSee('recent-ledger', false);
    }

    public function test_supplier_create_page_renders_with_parties_global_ui(): void
    {
        $response = $this->get(route('supplier.create'));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('party-single-card', false);
        $response->assertSee('supplier-form', false);
    }

    public function test_supplier_edit_page_renders_with_parties_global_ui(): void
    {
        $response = $this->get(route('supplier.edit', $this->supplier->id));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('party-single-card', false);
        $response->assertSee('supplier-form', false);
        $response->assertSee($this->supplier->name, false);
    }

    public function test_supplier_index_renders_with_parties_command_bar(): void
    {
        $response = $this->get(route('supplier.index'));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('supplier-workspace-view', false);
        $response->assertSee('desk-card-wrapper', false);
        $response->assertSee('Procurement Links', false);
    }

    public function test_supplier_view_renders_with_parties_global_ui(): void
    {
        $response = $this->get(route('supplier.show', $this->supplier->id));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('party-profile-card', false);
        $response->assertSee('recent-ledger', false);
    }

    public function test_agent_index_renders_with_parties_command_bar_and_desk_table(): void
    {
        $response = $this->get(route('agent.index'));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('agent-workspace-view', false);
        $response->assertSee('desk-card-wrapper', false);
        $response->assertSee('agent-table', false);
    }

    public function test_area_index_renders_with_parties_command_bar_and_desk_table(): void
    {
        $response = $this->get(route('area.index'));
        $response->assertOk();
        $response->assertSee('party-command-bar', false);
        $response->assertSee('area-workspace-view', false);
        $response->assertSee('desk-card-wrapper', false);
        $response->assertSee('area-table', false);
    }
}
