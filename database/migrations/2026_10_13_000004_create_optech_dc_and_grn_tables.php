<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('delivery_challans')) {
            Schema::create('delivery_challans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('financial_year_id')->nullable()->index();
                $table->string('challan_no', 100)->index();
                $table->date('challan_date');
                $table->unsignedInteger('customer_id')->index();
                $table->unsignedInteger('warehouse_id')->index();
                $table->unsignedBigInteger('sale_type_id')->nullable()->index();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->unsignedBigInteger('area_id')->nullable()->index();
                $table->string('transport_name', 150)->nullable();
                $table->string('lr_no', 100)->nullable();
                $table->date('lr_date')->nullable();
                $table->string('bale_no', 100)->nullable();
                $table->integer('no_of_bales')->nullable();
                $table->string('station_to', 150)->nullable();
                $table->decimal('total_qty', 18, 4)->default(0);
                $table->decimal('total_amount', 18, 4)->default(0);
                $table->decimal('total_tax', 18, 4)->default(0);
                $table->decimal('grand_total', 18, 4)->default(0);
                $table->json('sundries_json')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 30)->default('pending'); // pending, converted_to_sale, cancelled
                $table->unsignedInteger('sale_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('delivery_challan_items')) {
            Schema::create('delivery_challan_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('delivery_challan_id')->index();
                $table->unsignedInteger('product_id')->index();
                $table->unsignedInteger('unit_id')->default(1);
                $table->decimal('qty', 18, 4)->default(0);
                $table->decimal('rate', 18, 4)->default(0);
                $table->decimal('amount', 18, 4)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('tax_amount', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->string('remarks', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_received_notes')) {
            Schema::create('goods_received_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('financial_year_id')->nullable()->index();
                $table->string('grn_no', 100)->index();
                $table->date('grn_date');
                $table->unsignedInteger('supplier_id')->index();
                $table->unsignedInteger('warehouse_id')->index();
                $table->unsignedBigInteger('purchase_type_id')->nullable()->index();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->string('transport_name', 150)->nullable();
                $table->string('lr_no', 100)->nullable();
                $table->date('lr_date')->nullable();
                $table->string('order_no', 100)->nullable();
                $table->decimal('total_qty', 18, 4)->default(0);
                $table->decimal('total_cost', 18, 4)->default(0);
                $table->decimal('total_tax', 18, 4)->default(0);
                $table->decimal('grand_total', 18, 4)->default(0);
                $table->json('sundries_json')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 30)->default('pending'); // pending, converted_to_purchase, cancelled
                $table->unsignedInteger('purchase_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_received_note_items')) {
            Schema::create('goods_received_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('goods_received_note_id')->index();
                $table->unsignedInteger('product_id')->index();
                $table->unsignedInteger('unit_id')->default(1);
                $table->decimal('qty', 18, 4)->default(0);
                $table->decimal('cost', 18, 4)->default(0);
                $table->decimal('amount', 18, 4)->default(0);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('tax_amount', 18, 4)->default(0);
                $table->decimal('total', 18, 4)->default(0);
                $table->string('remarks', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_note_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
    }
};
