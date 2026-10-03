<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Damage Stocks
        if (!Schema::hasTable('damage_stocks')) {
            Schema::create('damage_stocks', function (Blueprint $table) {
                $table->id();
                $table->string('reference_no')->unique();
                $table->unsignedInteger('warehouse_id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('variant_id')->nullable();
                $table->double('qty', 10, 2);
                $table->double('unit_cost', 12, 2)->default(0);
                $table->double('total_loss', 12, 2)->default(0);
                $table->string('reason')->default('Damaged'); // Damaged, Expired, Spillage, Theft
                $table->text('note')->nullable();
                $table->unsignedInteger('user_id');
                $table->timestamps();
            });
        }

        // 2. Product Exchanges
        if (!Schema::hasTable('exchanges')) {
            Schema::create('exchanges', function (Blueprint $table) {
                $table->id();
                $table->string('reference_no')->unique();
                $table->unsignedInteger('original_sale_id')->nullable();
                $table->unsignedInteger('warehouse_id');
                $table->unsignedInteger('customer_id');
                $table->unsignedInteger('biller_id')->nullable();
                $table->json('returned_items');
                $table->double('returned_total', 12, 2)->default(0);
                $table->json('exchanged_items');
                $table->double('exchanged_total', 12, 2)->default(0);
                $table->double('difference_amount', 12, 2)->default(0); // positive: customer pays, negative: refund
                $table->string('payment_status')->default('completed');
                $table->string('payment_method')->nullable();
                $table->text('note')->nullable();
                $table->unsignedInteger('user_id');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('exchanges');
        Schema::dropIfExists('damage_stocks');
    }
};
