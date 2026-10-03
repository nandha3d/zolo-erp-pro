<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Bakery / Cafe Daily Raw Materials Consumption
        if (!Schema::hasTable('cafe_raw_materials')) {
            Schema::create('cafe_raw_materials', function (Blueprint $table) {
                $table->id();
                $table->date('log_date');
                $table->unsignedInteger('product_id'); // e.g. Flour, Sugar, Butter, Milk, Coffee Beans
                $table->string('item_name');
                $table->double('opening_stock', 10, 2)->default(0);
                $table->double('received_today', 10, 2)->default(0);
                $table->double('consumed_today', 10, 2)->default(0);
                $table->double('closing_stock', 10, 2)->default(0);
                $table->string('unit_code')->default('kg');
                $table->unsignedInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        // 2. Evening Counter Cash & UPI Reconciliation Drawer
        if (!Schema::hasTable('cafe_cash_drawers')) {
            Schema::create('cafe_cash_drawers', function (Blueprint $table) {
                $table->id();
                $table->date('drawer_date');
                $table->unsignedInteger('cashier_id');
                $table->double('opening_float', 10, 2)->default(0);
                $table->double('system_cash_sales', 12, 2)->default(0);
                $table->double('system_upi_sales', 12, 2)->default(0);
                $table->double('total_system_sales', 12, 2)->default(0);
                $table->double('physical_cash_counted', 12, 2)->default(0);
                $table->double('upi_settlement_counted', 12, 2)->default(0);
                $table->double('total_physical_collected', 12, 2)->default(0);
                $table->double('discrepancy_amount', 10, 2)->default(0); // short or excess
                $table->string('status')->default('balanced'); // balanced, short, over
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Catalogue QR & Digital Contactless Menu
        if (!Schema::hasTable('catalogue_qrs')) {
            Schema::create('catalogue_qrs', function (Blueprint $table) {
                $table->id();
                $table->string('title'); // Main Dining, Takeaway Counter, Table 1
                $table->unsignedInteger('warehouse_id')->nullable();
                $table->unsignedInteger('table_id')->nullable();
                $table->string('slug')->unique();
                $table->string('qr_code_path')->nullable();
                $table->string('theme_color')->default('#4f46e5');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('catalogue_qrs');
        Schema::dropIfExists('cafe_cash_drawers');
        Schema::dropIfExists('cafe_raw_materials');
    }
};
