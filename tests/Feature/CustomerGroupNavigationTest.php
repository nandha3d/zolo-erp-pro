<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerGroupNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::where('role_id', 1)->first() ?? User::factory()->create(['role_id' => 1]);
        $this->company = Company::first() ?? Company::factory()->create();

        $this->actingAs($this->adminUser);
        session([
            'company_id' => $this->company->id,
            'current_company_id' => $this->company->id,
            'warehouse_id' => 1,
        ]);
    }

    public function test_parties_sidebar_has_customer_groups_link(): void
    {
        $response = $this->get('/dashboard');
        $response->assertOk();
        $response->assertSee('id="customer-group-menu"', false);
        $response->assertSee(route('customer_group.index'), false);
    }

    public function test_customers_index_nav_has_customer_groups_pill(): void
    {
        $response = $this->get(route('customer.index'));
        $response->assertOk();
        $response->assertSee(route('customer_group.index'), false);
        $response->assertSee('Customer Groups');
    }

    public function test_customer_group_index_page_loads_successfully(): void
    {
        $response = $this->get(route('customer_group.index'));
        $response->assertOk();
        $response->assertSee('Add Customer Group');
    }
}

