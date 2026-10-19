<?php

namespace Tests\Feature;

use App\Services\Commercial\CommercialApplicationService;
use App\Services\Commercial\CommercialReversalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\Support\CommercialTestCase;

class RateHistoryTest extends CommercialTestCase
{
    public function test_rate_context_provides_compatible_factors_and_frozen_currency_without_changing_quantities(): void
    {
        $item = $this->stock(100)->forceFill(['unit_id' => 1]); $item->save();
        Schema::table('units', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        DB::table('units')->insert(['id' => 2, 'company_id' => $this->company->id, 'name' => 'Dozen', 'unit_code' => 'DOZ', 'unit_name' => 'Dozen', 'is_active' => true]);
        $bill = app(CommercialApplicationService::class)->create('purchase', $this->purchaseData($item, [
            'items' => [['product_id' => $item->id, 'qty' => 2, 'purchase_unit_id' => 2, 'net_unit_cost' => 120]]]), 'dozen-history', 1, $this->context());
        Schema::table('purchases', fn (Blueprint $t) => $t->json('document_snapshot_json')->nullable());
        DB::table('purchases')->where('id', $bill->id)->update(['document_snapshot_json' => json_encode(['currency' => 'USD', 'private' => 'not a history field'])]);
        DB::table('product_uom_conversions')->insert(['company_id' => $this->company->id, 'product_id' => $item->id,
            'from_uom_id' => 2, 'to_uom_id' => 1, 'factor' => 10, 'rounding_scale' => 4]);
        $response = $this->getJson('/commercial/sale/previous-rates?party_id=1&product_id='.$item->id)->assertOk()
            ->assertJsonPath('unit_factors.1', 1)->assertJsonPath('unit_factors.2', 10)
            ->assertJsonPath('last_purchase.base_factor', 12)->assertJsonPath('last_purchase.rate', 120)
            ->assertJsonPath('last_purchase.currency', 'USD')->assertJsonPath('last_purchase.rate_basis', 'net_excluding_tax');
        $this->assertArrayNotHasKey('document_snapshot_json', $response->json('last_purchase'));
        $this->assertEquals(2, $response->json('last_purchase.qty'));
    }

    public function test_cost_permission_and_history_pagination_are_independent(): void
    {
        $item = $this->stock(100);
        $service = app(CommercialApplicationService::class);
        for ($i = 0; $i < 11; $i++) $service->create('sale', $this->saleData($item), 'sale-'.$i, 1, $this->context());
        Schema::create('permissions', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedInteger('role_id'); $t->unsignedInteger('permission_id'); });
        DB::table('permissions')->insert(['id' => 1, 'name' => 'sales-index']);
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => 1]);
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $url = '/commercial/sale/previous-rates?party_id=1&product_id='.$item->id;
        $this->getJson($url.'&page=')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('next_page', 2)
            ->assertJsonPath('cost_visible', false)->assertJsonPath('last_purchase', null);
        $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('next_page', null);
    }

    public function test_history_uses_business_dates_tie_breaks_and_excludes_foreign_and_unposted_documents(): void
    {
        $item = $this->stock(100);
        $service = app(CommercialApplicationService::class);
        $a = $service->create('sale', $this->saleData($item), 'first', 1, $this->context());
        $b = $service->create('sale', $this->saleData($item), 'second', 1, $this->context());
        $service->create('sale', $this->saleData($item, ['sale_status' => 2]), 'pending', 1, $this->context());
        $c = $service->create('sale', $this->saleData($item, ['business_date' => '2026-10-02']), 'backdated', 1, $this->context());
        $url = '/commercial/sale/previous-rates?party_id=1&product_id='.$item->id;
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.sale_id', $b->id)->assertJsonPath('data.1.sale_id', $a->id)
            ->assertJsonPath('data.2.sale_id', $c->id)->assertJsonCount(3, 'data');
        DB::table('sales')->where('id', $b->id)->update(['company_id' => $this->other->id]);
        $branch = $this->company->branches()->create(['code' => 'OTHER', 'name' => 'Other branch']);
        DB::table('warehouses')->where('id', 2)->update(['branch_id' => $branch->id]);
        DB::table('sales')->where('id', $a->id)->update(['warehouse_id' => 2]);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('last_sale.sale_id', $c->id);
    }

    public function test_sales_cost_uses_product_purchase_history_not_customer_numeric_identity(): void
    {
        $item = $this->stock();
        DB::table('suppliers')->insert(['id' => 2, 'name' => 'Actual mill', 'company_id' => $this->company->id]);
        $service = app(CommercialApplicationService::class);
        $service->create('purchase', $this->purchaseData($item, ['supplier_id' => 2,
            'items' => [['product_id' => $item->id, 'qty' => 2, 'net_unit_cost' => 7]]]), 'mill', 1, $this->context());
        $service->create('sale', $this->saleData($item), 'customer', 1, $this->context());
        $this->getJson('/commercial/sale/previous-rates?party_id=1&product_id='.$item->id)->assertOk()
            ->assertJsonPath('last_purchase.party_name', 'Actual mill')->assertJsonPath('last_purchase.cost', 7)
            ->assertJsonPath('last_sale.rate', 10)->assertJsonCount(1, 'data');
        $this->getJson('/commercial/purchase/previous-rates?party_id=1&product_id='.$item->id)->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('last_purchase', null)->assertJsonPath('last_sale', null);
    }

    public function test_reversed_documents_are_excluded_from_detail_and_summary(): void
    {
        $item = $this->stock();
        $service = app(CommercialApplicationService::class);
        $old = $service->create('purchase', $this->purchaseData($item), 'original', 1, $this->context());
        app(CommercialReversalService::class)->reverse($old, '2026-10-03', 'Mistaken bill', $this->context());
        $this->getJson('/commercial/sale/previous-rates?party_id=1&product_id='.$item->id)->assertOk()
            ->assertJsonPath('last_purchase', null);
        $this->getJson('/commercial/purchase/previous-rates?party_id=1&product_id='.$item->id)->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('last_purchase', null);
    }
}
