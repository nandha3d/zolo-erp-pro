<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Water Tankers Fleet (9KL, 16KL, 24KL)
        if (!Schema::hasTable('water_tankers')) {
            Schema::create('water_tankers', function (Blueprint $table) {
                $table->id();
                $table->string('vehicle_number')->unique();
                $table->string('model_type')->default('Tanker');
                $table->integer('capacity_kl')->default(16); // 9, 16, 24 KL
                $table->integer('capacity_liters')->default(16000);
                $table->string('driver_name')->nullable();
                $table->string('driver_phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Water Tanker Trip Sheets
        if (!Schema::hasTable('water_tanker_trips')) {
            Schema::create('water_tanker_trips', function (Blueprint $table) {
                $table->id();
                $table->string('trip_number')->unique();
                $table->unsignedBigInteger('tanker_id');
                $table->unsignedInteger('customer_id');
                $table->date('trip_date');
                $table->string('source_plant')->default('Borewell Plant 1');
                $table->string('destination_site');
                $table->double('water_quantity_kl', 8, 2);
                $table->double('trip_rate', 12, 2)->default(0);
                $table->double('diesel_expense', 10, 2)->default(0);
                $table->double('toll_expense', 10, 2)->default(0);
                $table->double('driver_batta', 10, 2)->default(0);
                $table->string('site_receiver_name')->nullable();
                $table->text('site_receiver_signature')->nullable();
                $table->string('status')->default('completed'); // dispatched, delivered, invoiced
                $table->unsignedInteger('invoice_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 3. 20L Water Can Distribution Routes (Tata Ace)
        if (!Schema::hasTable('water_can_routes')) {
            Schema::create('water_can_routes', function (Blueprint $table) {
                $table->id();
                $table->string('route_name'); // Route A - Industrial, Route B - Town, Route C - Textiles
                $table->string('vehicle_no')->nullable(); // Tata Ace TN-74-XXXX
                $table->string('driver_name')->nullable();
                $table->string('driver_phone')->nullable();
                $table->integer('daily_avg_cans')->default(60);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Daily Can Deliveries & Route Manifests
        if (!Schema::hasTable('water_can_deliveries')) {
            Schema::create('water_can_deliveries', function (Blueprint $table) {
                $table->id();
                $table->string('delivery_no')->unique();
                $table->date('delivery_date');
                $table->unsignedBigInteger('route_id');
                $table->unsignedInteger('customer_id');
                $table->integer('morning_loaded_cans')->default(0);
                $table->integer('cans_delivered')->default(0);
                $table->integer('empty_cans_returned')->default(0);
                $table->double('can_rate', 8, 2)->default(35.00);
                $table->double('total_amount', 12, 2)->default(0);
                $table->double('paid_amount', 12, 2)->default(0);
                $table->string('payment_mode')->default('Cash'); // Cash, UPI, Corporate Credit
                $table->integer('balance_cans_held')->default(0);
                $table->string('status')->default('delivered');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 5. Customer Can Balance & Anti-Theft Ledger
        if (!Schema::hasTable('water_can_inventories')) {
            Schema::create('water_can_inventories', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('customer_id')->unique();
                $table->integer('total_cans_held')->default(0);
                $table->double('deposit_held', 12, 2)->default(0);
                $table->date('last_delivery_date')->nullable();
                $table->date('last_audit_date')->nullable();
                $table->integer('can_variance_detected')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('water_can_inventories');
        Schema::dropIfExists('water_can_deliveries');
        Schema::dropIfExists('water_can_routes');
        Schema::dropIfExists('water_tanker_trips');
        Schema::dropIfExists('water_tankers');
    }
};
