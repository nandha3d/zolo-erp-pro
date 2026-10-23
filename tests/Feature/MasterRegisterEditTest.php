<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Area;
use App\Models\BillSundry;
use App\Models\PurchaseType;
use App\Models\SaleType;
use App\Models\StandardRemark;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MasterRegisterEditTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commercial.enabled' => true]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->adminUser = User::first();
    }

    public function test_every_master_register_row_offers_edit_and_delete(): void
    {
        $payloads = [
            '/sale-type' => [SaleType::class, ['name' => 'Edit me sale', 'tax_nature' => 'local', 'tax_rate' => 5]],
            '/purchase-type' => [PurchaseType::class, ['name' => 'Edit me purchase', 'tax_nature' => 'local', 'tax_rate' => 5]],
            '/bill-sundry' => [BillSundry::class, ['name' => 'Edit me sundry', 'nature' => 'both', 'calculation_type' => 'amount']],
            '/agent' => [Agent::class, ['name' => 'Edit me agent']],
            '/area' => [Area::class, ['name' => 'Edit me area']],
            '/standard-remark' => [StandardRemark::class, ['title' => 'Edit me remark', 'type' => 'all', 'remark' => 'Text']],
        ];
        $rows = [];
        foreach ($payloads as $page => [$model, $fields]) {
            $this->actingAs($this->adminUser)->post($page, $fields)->assertRedirect();
            $rows[$page] = $model::latest('id')->firstOrFail();
        }

        foreach ($rows as $page => $row) {
            $this->actingAs($this->adminUser)->get($page)->assertOk()
                ->assertSee('js-master-edit', false)
                ->assertSee($page.'/'.$row->id, false)
                ->assertSee('name="is_active"', false);
        }
    }

    public function test_edit_updates_fields_and_can_deactivate(): void
    {
        $type = SaleType::create(['company_id' => 1, 'name' => 'Old name', 'tax_nature' => 'local', 'tax_rate' => 5, 'is_active' => true]);

        $this->actingAs($this->adminUser)->put('/sale-type/'.$type->id, [
            'name' => 'New name', 'code' => 'NN', 'tax_nature' => 'interstate', 'tax_rate' => 12, 'is_active' => 0,
        ])->assertRedirect()->assertSessionHas('message');

        $type->refresh();
        $this->assertSame('New name', $type->name);
        $this->assertSame('interstate', $type->tax_nature);
        $this->assertFalse((bool) $type->is_active);

        $sundry = BillSundry::create(['company_id' => 1, 'name' => 'Freight X', 'nature' => 'both', 'calculation_type' => 'amount', 'affect_cost' => true, 'is_active' => true]);
        $this->actingAs($this->adminUser)->put('/bill-sundry/'.$sundry->id, [
            'name' => 'Freight Y', 'nature' => 'sales', 'calculation_type' => 'percentage', 'default_value' => 2, 'is_active' => 1,
        ])->assertRedirect();
        $sundry->refresh();
        $this->assertSame('Freight Y', $sundry->name);
        $this->assertFalse((bool) $sundry->affect_cost);
    }

    public function test_used_masters_are_not_deleted_but_unused_ones_are(): void
    {
        $agent = Agent::create(['company_id' => 1, 'name' => 'Busy agent', 'is_active' => true]);
        $customer = DB::table('customers')->value('id');
        $this->assertNotNull($customer, 'needs at least one customer row');
        DB::table('customers')->where('id', $customer)->update(['agent_id' => $agent->id]);

        $this->actingAs($this->adminUser)->delete('/agent/'.$agent->id)->assertRedirect()->assertSessionHas('not_permitted');
        $this->assertTrue(Agent::whereKey($agent->id)->exists());

        $this->actingAs($this->adminUser)->deleteJson('/agent/'.$agent->id)->assertStatus(422);

        $idle = Area::create(['company_id' => 1, 'name' => 'Idle area', 'is_active' => true]);
        $this->actingAs($this->adminUser)->delete('/area/'.$idle->id)->assertRedirect()->assertSessionHas('message');
        $this->assertFalse(Area::whereKey($idle->id)->exists());
    }
}
