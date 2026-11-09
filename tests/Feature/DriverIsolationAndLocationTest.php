<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DriverIsolationAndLocationTest extends TestCase
{
    use DatabaseTransactions;

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
