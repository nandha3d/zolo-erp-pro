<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverIsolationAndLocationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(\App\Services\Platform\CapabilityService::class, fn () => new class extends \App\Services\Platform\CapabilityService {
            public function enabled(string $key, $company = null): bool {
                return true;
            }
        });

        // Ensure companies 2 and 3 exist
        DB::table('companies')->updateOrInsert(['id' => 2], [
            'code' => 'TMWS',
            'legal_name' => 'TMWS Water Tankers',
            'trade_name' => 'TMWS',
            'country_code' => 'IN',
            'status' => 'active',
        ]);
        DB::table('companies')->updateOrInsert(['id' => 3], [
            'code' => 'DKW',
            'legal_name' => 'DK Waters',
            'trade_name' => 'DK Waters',
            'country_code' => 'IN',
            'status' => 'active',
        ]);

        if (Schema::hasTable('company_branches')) {
            DB::table('company_branches')->updateOrInsert(['id' => 2], [
                'company_id' => 2,
                'code' => 'TMWS-MAIN',
                'name' => 'TMWS Main Branch',
                'is_active' => true,
            ]);
            DB::table('company_branches')->updateOrInsert(['id' => 3], [
                'company_id' => 3,
                'code' => 'DKW-MAIN',
                'name' => 'DK Waters Main Branch',
                'is_active' => true,
            ]);
        }

        if (Schema::hasTable('fiscal_years')) {
            DB::table('fiscal_years')->updateOrInsert(['company_id' => 2, 'name' => 'FY 2026-27'], [
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_closed' => 0,
                'status' => 'open',
            ]);
            DB::table('fiscal_years')->updateOrInsert(['company_id' => 3, 'name' => 'FY 2026-27'], [
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_closed' => 0,
                'status' => 'open',
            ]);
        }

        $admin = User::where('name', 'admin')->first();
        if ($admin) {
            DB::table('company_user')->where('user_id', $admin->id)->update(['is_default' => 0]);
            DB::table('company_user')->updateOrInsert(['company_id' => 2, 'user_id' => $admin->id], ['is_default' => 1]);
            DB::table('company_user')->updateOrInsert(['company_id' => 1, 'user_id' => $admin->id], ['is_default' => 0]);
            DB::table('company_user')->updateOrInsert(['company_id' => 3, 'user_id' => $admin->id], ['is_default' => 0]);
        }

        $tankerDriver = User::where('name', 'tanker_driver')->first();
        if ($tankerDriver) {
            DB::table('company_user')->where('user_id', $tankerDriver->id)->update(['is_default' => 0]);
            DB::table('company_user')->updateOrInsert(['company_id' => 2, 'user_id' => $tankerDriver->id], ['is_default' => 1]);
        }

        $driver = User::where('name', 'driver')->first();
        if ($driver) {
            DB::table('company_user')->where('user_id', $driver->id)->update(['is_default' => 0]);
            DB::table('company_user')->updateOrInsert(['company_id' => 3, 'user_id' => $driver->id], ['is_default' => 1]);
        }

        if (Schema::hasTable('company_user_branches')) {
            if ($tankerDriver) {
                DB::table('company_user_branches')->updateOrInsert(['company_id' => 2, 'user_id' => $tankerDriver->id, 'branch_id' => 2], []);
            }
            if ($driver) {
                DB::table('company_user_branches')->updateOrInsert(['company_id' => 3, 'user_id' => $driver->id, 'branch_id' => 3], []);
            }
            if ($admin) {
                DB::table('company_user_branches')->updateOrInsert(['company_id' => 1, 'user_id' => $admin->id, 'branch_id' => 1], []);
                DB::table('company_user_branches')->updateOrInsert(['company_id' => 2, 'user_id' => $admin->id, 'branch_id' => 2], []);
                DB::table('company_user_branches')->updateOrInsert(['company_id' => 3, 'user_id' => $admin->id, 'branch_id' => 3], []);
            }
        }

        // Ensure customer 4 exists with coordinates
        DB::table('customers')->updateOrInsert(['id' => 4], [
            'name' => 'The Chennai Silks',
            'phone_number' => '9842100004',
            'company_id' => 2,
            'customer_group_id' => 1,
            'latitude' => 11.0845000,
            'longitude' => 77.2941000,
        ]);

        // Ensure tanker 1 exists
        if (Schema::hasTable('water_tankers')) {
            DB::table('water_tankers')->updateOrInsert(['id' => 1], [
                'vehicle_number' => 'TN-58-BW-2001',
                'model_type' => 'Tata LPT 2518 (20KL Dual Chamber)',
                'capacity_kl' => 20,
                'capacity_liters' => 20000,
                'driver_name' => 'K. Murugan',
                'driver_phone' => '9842100001',
                'is_active' => true,
            ]);
        }

        // Ensure can route 1 exists
        if (Schema::hasTable('water_can_routes')) {
            DB::table('water_can_routes')->updateOrInsert(['id' => 1], [
                'route_name' => 'Route A - West Zone (WA-101)',
                'vehicle_no' => 'TN-58-WA-101',
                'driver_name' => 'driver',
                'driver_phone' => '9842100010',
                'daily_avg_cans' => 60,
                'is_active' => true,
            ]);
        }

        // Ensure active trip for tanker 1
        if (Schema::hasTable('water_tanker_trips')) {
            DB::table('water_tanker_trips')->updateOrInsert(['id' => 1], [
                'trip_number' => 'TRIP-2026-001',
                'tanker_id' => 1,
                'customer_id' => 4,
                'trip_date' => date('Y-m-d'),
                'source_plant' => 'Borewell Plant 1',
                'destination_site' => 'The Chennai Silks Plant Site',
                'water_quantity_kl' => 20.0,
                'trip_rate' => 3500.0,
                'diesel_expense' => 600.0,
                'start_km' => 45200,
                'end_km' => 45240,
                'status' => 'dispatched',
            ]);
        }
    }

    public function test_driver_roles_are_correctly_normalized_on_login(): void
    {
        // Tanker Driver
        $response = $this->postJson('/api/v1/mobile/login', [
            'username' => 'tanker_driver',
            'password' => 'driver123',
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.user.role_slug', 'tankerDriver');

        // Delivery Driver
        $response = $this->postJson('/api/v1/mobile/login', [
            'username' => 'driver',
            'password' => 'driver123',
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.user.role_slug', 'deliveryDriver');
    }

    public function test_tanker_driver_only_sees_assigned_tanker_and_destination_customer(): void
    {
        $user = User::where('name', 'tanker_driver')->first();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 2)
            ->getJson('/api/v1/mobile/fleet/live?vertical=bulk_water');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertTrue($data['is_driver_view']);
        // Only 1 tanker returned (their assigned vehicle), NOT all 3 tankers!
        $this->assertCount(1, $data['tankers']);
        $this->assertEquals('TN-58-BW-2001', $data['tankers'][0]['vehicle_number']);

        // Only their assigned customer returned, NOT unrelated customers!
        $this->assertLessThanOrEqual(2, count($data['customers']));
    }

    public function test_delivery_driver_only_sees_assigned_route_truck_and_route_stops(): void
    {
        $user = User::where('name', 'driver')->first();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 3)
            ->getJson('/api/v1/mobile/fleet/live?vertical=packaged_water');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertTrue($data['is_driver_view']);
        // Only 1 route returned (Route 1), NOT all routes!
        $this->assertCount(1, $data['can_routes']);
        $this->assertStringContainsString('WA-101', $data['can_routes'][0]['vehicle_no']);
    }

    public function test_driver_querying_financials_has_company_ledgers_restricted(): void
    {
        $user = User::where('name', 'tanker_driver')->first();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 2)
            ->getJson('/api/v1/mobile/financials');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertTrue($data['is_restricted']);
        $this->assertEquals(0.0, $data['cash_balance']);
        $this->assertEquals(0.0, $data['bank_balance']);
        $this->assertEquals(0.0, $data['receivables']);
        $this->assertEquals(0.0, $data['payables']);
    }

    public function test_whatsapp_location_link_capture_and_persistence(): void
    {
        $user = User::where('name', 'admin')->first();
        $token = $user->createToken('test-token')->plainTextToken;

        // Customer ID 4 (The Chennai Silks)
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/mobile/customers/4/location', [
                'whatsapp_text' => 'https://maps.google.com/?q=11.0845000,77.2941000',
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(11.0845, $data['latitude']);
        $this->assertEquals(77.2941, $data['longitude']);
        $this->assertStringContainsString('google.com/maps', $data['google_maps_url']);

        // Verify in database
        $customer = Customer::find(4);
        $this->assertEquals('11.0845000', $customer->latitude);
        $this->assertEquals('77.2941000', $customer->longitude);
    }

    public function test_driver_manifest_endpoint(): void
    {
        $tankerUser = User::where('name', 'tanker_driver')->first();
        $token = $tankerUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/mobile/driver/manifest');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('tanker', $data['driver_type']);
        $this->assertEquals('TN-58-BW-2001', $data['vehicle']['vehicle_number']);
        $this->assertNotNull($data['active_trip']);
        $this->assertTrue($data['active_trip']['has_gps']);
    }

    public function test_delivery_driver_can_create_customer_with_whatsapp_location_and_cans(): void
    {
        $driverUser = User::where('name', 'driver')->first();
        $token = $driverUser->createToken('test-driver-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 3)
            ->postJson('/api/v1/mobile/customers', [
                'name' => 'New Route Cafe & Snacks',
                'company_name' => 'Route Cafe Ltd',
                'phone_number' => '9842199887',
                'address' => '25, Anna Nagar Main Road, Erode',
                'city' => 'Erode',
                'route_id' => 1,
                'whatsapp_location' => 'https://maps.google.com/?q=11.3412000,77.7175000',
                'opening_cans_held' => 10,
                'deposit_held' => 1500,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertEquals('New Route Cafe & Snacks', $data['name']);
        $this->assertEquals('9842199887', $data['phone']);
        $this->assertEquals(11.3412, $data['latitude']);
        $this->assertEquals(77.7175, $data['longitude']);
        $this->assertEquals(10, $data['initial_cans_held']);
        $this->assertEquals(1500, $data['deposit_held']);

        // Verify that driver immediately sees this newly created customer in their customers directory
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 3)
            ->getJson('/api/v1/mobile/customers?search=New+Route+Cafe');

        $listResponse->assertStatus(200);
        $listData = $listResponse->json('data');
        $this->assertTrue(collect($listData)->contains('name', 'New Route Cafe & Snacks'));
    }

    public function test_tanker_driver_can_create_customer_and_destinations(): void
    {
        $tankerUser = User::where('name', 'tanker_driver')->first();
        $token = $tankerUser->createToken('test-tanker-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 2)
            ->postJson('/api/v1/customer-onboard', [
                'name' => 'Senthil Spinning Mills Sump #2',
                'company_name' => 'Senthil Textiles Group',
                'phone_number' => '9842177665',
                'address' => 'SF 120, SIPCOT Phase 2, Perundurai',
                'city' => 'Perundurai',
                'latitude' => 11.2825,
                'longitude' => 77.5892,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertEquals('Senthil Spinning Mills Sump #2', $data['name']);

        // Verify tanker driver sees their created customer
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Company-ID', 2)
            ->getJson('/api/v1/mobile/customers?search=Senthil+Spinning');

        $listResponse->assertStatus(200);
        $listData = $listResponse->json('data');
        $this->assertTrue(collect($listData)->contains('name', 'Senthil Spinning Mills Sump #2'));
    }
}
