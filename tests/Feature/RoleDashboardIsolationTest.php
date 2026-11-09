<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleDashboardIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(\App\Services\Platform\CapabilityService::class, fn () => new class extends \App\Services\Platform\CapabilityService {
            public function enabled(string $key, $company = null): bool {
                return true;
            }
        });
    }
    /**
     * Tanker driver logs in on web and sees dedicated tanker operations dashboard
     */
    public function test_tanker_driver_web_dashboard_dispatch(): void
    {
        $user = User::where('name', 'tanker_driver')->first();
        $this->assertNotNull($user, 'Tanker driver user must exist');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('backend.role_dashboards.tanker_driver');
        $response->assertSee('TN-58-BW-2001');
        $response->assertSee('Bulk Tanker Operational Dispatch');
        $response->assertSee('Current Odometer');
        $response->assertSee('Decanting Flow Telemetry');
        $response->assertSee('Anti-Siphon Valve Secured');

        // Executive corporate financial isolation
        $response->assertDontSee('Gross Margin');
        $response->assertDontSee('Operating Profit');
        $response->assertDontSee('Corporate Credit Aging');
    }

    /**
     * 20L can delivery driver logs in on web and sees dedicated route manifest dashboard
     */
    public function test_delivery_driver_web_dashboard_dispatch(): void
    {
        $user = User::where('name', 'driver')->first();
        $this->assertNotNull($user, 'Delivery driver user must exist');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('backend.role_dashboards.can_driver');
        $response->assertSee('TN-58-WA-101');
        $response->assertSee('20L Packaged Water Distribution Manifest');
        $response->assertSee('Loaded Morning');
        $response->assertSee('Delivered');
        $response->assertSee('Empty Collected');
        $response->assertSee('Return Ratio');
        $response->assertSee('Route Cash in Van');

        // Executive corporate financial isolation
        $response->assertDontSee('Gross Margin');
        $response->assertDontSee('Operating Profit');
    }

    /**
     * Cashier logs in on web and sees dedicated cafe cashier counter dashboard
     */
    public function test_cashier_web_dashboard_dispatch(): void
    {
        $user = User::where('name', 'cashier')->first();
        $this->assertNotNull($user, 'Cashier user must exist');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('backend.role_dashboards.cashier');
        $response->assertSee('Aran Cafe Cashier Desk');
        $response->assertSee('Counter Sales');
        $response->assertSee('Orders Billed');
        $response->assertSee('Physical Cash in Drawer');
        $response->assertSee('Launch Touch POS');
        $response->assertSee('Cash Drawer OPEN');

        // Executive corporate financial isolation
        $response->assertDontSee('Gross Margin');
        $response->assertDontSee('Operating Profit');
    }

    /**
     * Field agent logs in on web and sees dedicated KYC & dues collections dashboard
     */
    public function test_field_agent_web_dashboard_dispatch(): void
    {
        $user = User::where('name', 'agent')->first();
        $this->assertNotNull($user, 'Field agent user must exist');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('backend.role_dashboards.field_agent');
        $response->assertSee('Field Operations & KYC Desk');
        $response->assertSee('Assigned Accounts');
        $response->assertSee('Total Outstanding Receivables');
        $response->assertSee('Field Collections');
        $response->assertSee('Recovery Rate');

        // Executive corporate financial isolation
        $response->assertDontSee('Gross Margin');
        $response->assertDontSee('Operating Profit');
    }

    /**
     * Admin logs in on web and retains full executive dashboard
     */
    public function test_admin_web_dashboard_retains_executive_view(): void
    {
        $admin = User::where('name', 'admin')->first();
        $this->assertNotNull($admin, 'Admin user must exist');

        $company = \App\Models\Company::first();
        $response = $this->actingAs($admin)
            ->withSession(['company_id' => $company?->id ?? 1])
            ->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewIs('backend.index');
        $response->assertSee('Dashboard');
        $response->assertSee('Revenue');
        $response->assertSee('Profit');
    }

    /**
     * Mobile API cashier insights endpoint returns dynamic metrics
     */
    public function test_mobile_api_cashier_insights(): void
    {
        $cashier = User::where('name', 'cashier')->first();
        $token = $cashier->createToken('test-cashier')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/mobile/dashboard/cashier');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.role', 'cashier');
        $response->assertJsonPath('data.drawer_status', 'OPEN');
        $response->assertJsonStructure([
            'data' => [
                'role',
                'counter_name',
                'company_name',
                'drawer_status',
                'summary' => [
                    'today_sales',
                    'today_orders',
                    'cash_in_drawer',
                    'digital_collections',
                    'average_order_value',
                    'pending_kitchen_orders',
                ],
            ],
        ]);
    }

    /**
     * Mobile API field agent insights endpoint returns dynamic metrics
     */
    public function test_mobile_api_field_agent_insights(): void
    {
        $agent = User::where('name', 'agent')->first();
        $token = $agent->createToken('test-agent')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/mobile/dashboard/fieldAgent');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.role', 'fieldAgent');
        $response->assertJsonStructure([
            'data' => [
                'role',
                'desk_name',
                'summary' => [
                    'assigned_customers',
                    'geocoded_customers',
                    'missing_gps_customers',
                    'total_receivables',
                    'today_collected',
                    'recovery_rate_percent',
                    'overdue_accounts_count',
                ],
            ],
        ]);
    }
}

