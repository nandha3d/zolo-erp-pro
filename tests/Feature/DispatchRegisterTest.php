<?php

namespace Tests\Feature;

use App\Http\Controllers\DispatchRegisterController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CommercialTestCase;

class DispatchRegisterTest extends CommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['packing_slips', 'deliveries'] as $table) Schema::create($table, function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('sale_id'); $t->string('reference_no'); $t->string('status')->default('pending'); $t->timestamps();
        });
        Schema::create('challans', function (Blueprint $t) {
            $t->increments('id'); $t->string('reference_no'); $t->string('packing_slip_list'); $t->string('status')->default('pending'); $t->timestamps();
        });
        Schema::create('delivery_challans', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('branch_id'); $t->unsignedInteger('customer_id'); $t->string('challan_no'); $t->timestamps();
        });
    }

    private function register(string $tab): array
    {
        $request = Request::create('/dispatch-register', 'GET', ['tab' => $tab]);
        $request->attributes->set(\App\Services\Platform\CompanyContext::class, $this->context());
        $request->setUserResolver(fn () => User::findOrFail(1));
        return app(DispatchRegisterController::class)->index($request)->getData();
    }

    public function test_each_dispatch_type_excludes_other_company_or_branch_and_mixed_courier_sources(): void
    {
        $product = $this->stock(100);
        $sale = app(\App\Services\Commercial\CommercialApplicationService::class)->create('sale', $this->saleData($product), 'dispatch-sale', 1, $this->context());
        $branch = $this->company->branches()->create(['code' => 'SECOND', 'name' => 'Second']);
        DB::table('warehouses')->where('id', 2)->update(['branch_id' => $branch->id]);
        $foreign = $sale->replicate(); $foreign->forceFill(['warehouse_id' => 2, 'branch_id' => $branch->id, 'reference_no' => 'OTHER-BRANCH'])->save();
        foreach (['packing_slips', 'deliveries'] as $table) DB::table($table)->insert([
            ['id' => 1, 'sale_id' => $sale->id, 'reference_no' => 'OWN'], ['id' => 2, 'sale_id' => $foreign->id, 'reference_no' => 'OTHER'],
        ]);
        DB::table('challans')->insert([
            ['reference_no' => 'OWN', 'packing_slip_list' => '1'], ['reference_no' => 'MIXED', 'packing_slip_list' => '1,2'],
            ['reference_no' => 'OTHER', 'packing_slip_list' => '2'], ['reference_no' => 'UNKNOWN', 'packing_slip_list' => '999'],
        ]);
        DB::table('delivery_challans')->insert([
            ['company_id' => $this->company->id, 'branch_id' => $this->context()->branchId, 'customer_id' => 1, 'challan_no' => 'OWN'],
            ['company_id' => $this->company->id, 'branch_id' => $branch->id, 'customer_id' => 1, 'challan_no' => 'OTHER'],
            ['company_id' => $this->other->id, 'branch_id' => $this->context()->branchId, 'customer_id' => 1, 'challan_no' => 'FOREIGN'],
        ]);
        foreach (['dc', 'packing', 'delivery', 'challan'] as $tab) {
            $rows = $this->register($tab)['rows'];
            $this->assertCount(1, $rows, $tab);
            $this->assertEquals('OWN', $rows->first()->{$tab === 'dc' ? 'challan_no' : 'reference_no'});
        }
    }

    public function test_delivery_only_permission_can_use_delivery_tab_but_cannot_read_customer_dc(): void
    {
        Schema::create('permissions', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedInteger('role_id'); $t->unsignedInteger('permission_id'); });
        DB::table('permissions')->insert(['id' => 1, 'name' => 'delivery']);
        DB::table('role_has_permissions')->insert(['role_id' => 4, 'permission_id' => 1]);
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $this->assertSame(['delivery'], array_keys($this->register('delivery')['tabs']));
        try { $this->register('dc'); $this->fail('DC permission required'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }
}
