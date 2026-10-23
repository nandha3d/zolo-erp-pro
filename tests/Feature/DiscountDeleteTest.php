<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Support\CommercialTestCase;

class DiscountDeleteTest extends CommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('discount_plans')) Schema::create('discount_plans', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedBigInteger('company_id'); $t->string('name'); $t->boolean('is_active'); $t->string('type')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('discounts')) Schema::create('discounts', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedBigInteger('company_id'); $t->string('name'); $t->string('product_list')->nullable(); $t->boolean('is_active'); $t->timestamps();
        });
        foreach (['discount_plan_discounts' => 'discount_id', 'discount_plan_customers' => 'customer_id'] as $table => $other) {
            if (!Schema::hasTable($table)) Schema::create($table, function (Blueprint $t) use ($other) {
                $t->increments('id'); $t->integer('discount_plan_id'); $t->integer($other); $t->timestamps();
            });
        }
    }

    public function test_discount_plan_and_discount_delete_with_their_links(): void
    {
        $this->withoutMiddleware();
        $scope = ['company_id' => $this->company->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()];
        $plan = DB::table('discount_plans')->insertGetId($scope + ['name' => 'Retail']);
        $discount = DB::table('discounts')->insertGetId($scope + ['name' => 'Diwali']);
        DB::table('discount_plan_discounts')->insert(['discount_plan_id' => $plan, 'discount_id' => $discount]);
        DB::table('discount_plan_customers')->insert(['discount_plan_id' => $plan, 'customer_id' => 1]);

        $this->delete('/discounts/'.$discount)->assertRedirect(route('discounts.index'));
        $this->assertFalse(DB::table('discounts')->where('id', $discount)->exists());
        $this->assertFalse(DB::table('discount_plan_discounts')->where('discount_id', $discount)->exists());

        $this->delete('/discount-plans/'.$plan)->assertRedirect(route('discount-plans.index'));
        $this->assertFalse(DB::table('discount_plans')->where('id', $plan)->exists());
        $this->assertFalse(DB::table('discount_plan_customers')->where('discount_plan_id', $plan)->exists());
    }
}
