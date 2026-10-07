<?php

namespace Tests\Feature;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GoodsReceivedNoteWebTest extends TestCase
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

    public function test_goods_received_note_command_center_renders_entry_workspace(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/goods-received-notes');

        $response->assertStatus(200);
        $response->assertSee('Goods Received Note Command Center');
        $response->assertSee('comm-entry-workspace', false);
        $response->assertSee('New Goods Received Note');
        $response->assertSee('order-table', false);
        $response->assertSee('comm-drawer', false);
        $response->assertSee('desk-bill-list-panel', false);
        $response->assertSee('GRAND TOTAL');
    }

    public function test_goods_received_note_store_creates_record_and_items(): void
    {
        $supplier = Supplier::first();
        $warehouse = Warehouse::first();
        $product = Product::first();

        $postData = [
            'grn_date' => now()->toDateString(),
            'supplier_id' => $supplier ? $supplier->id : 1,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'transport_name' => 'Inward Logistics',
            'lr_no' => 'LR-GRN-1234',
            'order_no' => 'PO-5544',
            'remarks' => 'Received all bales in good condition',
            'product_id' => [$product ? $product->id : 1],
            'unit_id' => [1],
            'qty' => [15],
            'cost' => [120],
            'amount' => [1800],
            'tax_rate' => [5],
            'tax_amount' => [90],
            'total' => [1890],
            'total_qty' => 15,
            'total_cost' => 1800,
            'total_tax' => 90,
            'grand_total' => 1890,
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/goods-received-notes', $postData);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('goods_received_notes', [
            'supplier_id' => $supplier ? $supplier->id : 1,
            'transport_name' => 'Inward Logistics',
            'lr_no' => 'LR-GRN-1234',
            'order_no' => 'PO-5544',
            'status' => 'pending',
        ]);

        $grn = GoodsReceivedNote::where('lr_no', 'LR-GRN-1234')->first();
        $this->assertNotNull($grn);
        $this->assertDatabaseHas('goods_received_note_items', [
            'goods_received_note_id' => $grn->id,
            'qty' => 15,
            'cost' => 120,
        ]);
    }

    public function test_goods_received_note_json_endpoint_returns_data(): void
    {
        $supplier = Supplier::first();
        $warehouse = Warehouse::first();
        $product = Product::first();

        $grn = GoodsReceivedNote::create([
            'company_id' => 1,
            'grn_no' => 'GRN-TEST-' . uniqid(),
            'grn_date' => now()->toDateString(),
            'supplier_id' => $supplier ? $supplier->id : 1,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'total_qty' => 8,
            'total_cost' => 800,
            'total_tax' => 0,
            'grand_total' => 800,
            'status' => 'pending',
        ]);

        GoodsReceivedNoteItem::create([
            'company_id' => 1,
            'goods_received_note_id' => $grn->id,
            'product_id' => $product ? $product->id : 1,
            'unit_id' => 1,
            'qty' => 8,
            'cost' => 100,
            'amount' => 800,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 800,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/goods-received-notes/' . $grn->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'grn', 'items']);
        $this->assertEquals($grn->grn_no, $response->json('grn.grn_no'));
    }

    public function test_goods_received_note_convert_to_purchase_route(): void
    {
        $supplier = Supplier::first();
        $warehouse = Warehouse::first();

        $grn = GoodsReceivedNote::create([
            'company_id' => 1,
            'grn_no' => 'GRN-CONV-' . uniqid(),
            'grn_date' => now()->toDateString(),
            'supplier_id' => $supplier ? $supplier->id : 1,
            'warehouse_id' => $warehouse ? $warehouse->id : 1,
            'total_qty' => 3,
            'total_cost' => 300,
            'total_tax' => 0,
            'grand_total' => 300,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->postJson('/goods-received-notes/' . $grn->id . '/convert-to-purchase');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertStringContainsString('from_grn=' . $grn->id, $response->json('redirect_url'));
    }
}
