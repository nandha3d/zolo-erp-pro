<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeliveryChallanWebTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.enabled' => true]);
        config(['compliance.enabled' => true]);
        putenv('ERP_OPTIONAL_ACTIVATION_READY=true');
        $_ENV['ERP_OPTIONAL_ACTIVATION_READY'] = 'true';
        \Illuminate\Support\Facades\Cache::flush();
        $this->adminUser = User::first();
    }

    public function test_delivery_challan_command_center_renders_entry_workspace(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/delivery-challans');

        $response->assertStatus(200);
        $response->assertSee('Delivery Challan Command Center');
        $response->assertSee('comm-entry-workspace', false);
        $response->assertSee('New Delivery Challan');
        $response->assertSee('order-table', false);
        $response->assertSee('comm-drawer', false);
        $response->assertSee('desk-bill-list-panel', false);
        $response->assertSee('GRAND TOTAL');
    }

    public function test_delivery_challan_store_creates_record_and_items(): void
    {
        $customer = Customer::first();
        $warehouse = Warehouse::first();
        $product = Product::first();

        $postData = [
            'challan_date' => now()->toDateString(),
            'customer_id' => $customer ? $customer->id : 1,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'transport_name' => 'Test Transporter',
            'lr_no' => 'LR-998877',
            'bale_no' => 'BL-12',
            'no_of_bales' => 2,
            'station_to' => 'Erode',
            'remarks' => 'Handle with care',
            'product_id' => [$product ? $product->id : 1],
            'unit_id' => [1],
            'qty' => [10],
            'rate' => [150],
            'amount' => [1500],
            'tax_rate' => [5],
            'tax_amount' => [75],
            'total' => [1575],
            'total_qty' => 10,
            'total_amount' => 1500,
            'total_tax' => 75,
            'grand_total' => 1575,
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/delivery-challans', $postData);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('delivery_challans', [
            'customer_id' => $customer ? $customer->id : 1,
            'transport_name' => 'Test Transporter',
            'lr_no' => 'LR-998877',
            'status' => 'pending',
        ]);

        $challan = DeliveryChallan::where('lr_no', 'LR-998877')->first();
        $this->assertNotNull($challan);
        $this->assertDatabaseHas('delivery_challan_items', [
            'delivery_challan_id' => $challan->id,
            'qty' => 10,
            'rate' => 150,
        ]);
    }

    public function test_delivery_challan_json_endpoint_returns_data(): void
    {
        $customer = Customer::first();
        $warehouse = Warehouse::first();
        $product = Product::first();

        $challan = DeliveryChallan::create([
            'company_id' => 1,
            'challan_no' => 'DC-TEST-' . uniqid(),
            'challan_date' => now()->toDateString(),
            'customer_id' => $customer ? $customer->id : 1,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'total_qty' => 5,
            'total_amount' => 500,
            'total_tax' => 0,
            'grand_total' => 500,
            'status' => 'pending',
        ]);

        DeliveryChallanItem::create([
            'company_id' => 1,
            'delivery_challan_id' => $challan->id,
            'product_id' => $product ? $product->id : 1,
            'unit_id' => 1,
            'qty' => 5,
            'rate' => 100,
            'amount' => 500,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 500,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/delivery-challans/' . $challan->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'challan', 'items']);
        $this->assertEquals($challan->challan_no, $response->json('challan.challan_no'));
    }

    public function test_delivery_challan_convert_to_sale_route(): void
    {
        $customer = Customer::first();
        $warehouse = Warehouse::first();

        $challan = DeliveryChallan::create([
            'company_id' => 1,
            'challan_no' => 'DC-CONV-' . uniqid(),
            'challan_date' => now()->toDateString(),
            'customer_id' => $customer ? $customer->id : 1,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'total_qty' => 2,
            'total_amount' => 200,
            'total_tax' => 0,
            'grand_total' => 200,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/delivery-challans/' . $challan->id . '/convert-to-sale');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertStringContainsString('from_dc=' . $challan->id, $response->json('redirect_url'));
    }
}
