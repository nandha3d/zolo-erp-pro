<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\MigrationConstraints;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'sales_orders' => 'id company_id branch_id financial_year_id customer_id warehouse_id user_id reference_no business_date promised_date note status attributes_json created_at updated_at',
            'commercial_order_items' => 'id company_id order_kind order_id source_line_id product_id uom_id variant_id product_batch_id batch_no qty qty_base unit_rate cancelled_qty_base created_at updated_at',
            'commercial_order_allocations' => 'id company_id order_item_id consumer_type consumer_id consumer_item_id stage qty_base returned_qty_base released_at created_at updated_at',
            'commercial_order_events' => 'id company_id order_kind order_id user_id event reason details_json created_at',
        ];
        foreach ($columns as $table => $fields) if (Schema::hasTable($table)) MigrationConstraints::requireColumns($table, explode(' ', $fields));
        if (!Schema::hasTable('sales_orders')) Schema::create('sales_orders', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('branch_id'); $t->unsignedBigInteger('financial_year_id');
            $t->unsignedBigInteger('customer_id'); $t->unsignedBigInteger('warehouse_id'); $t->unsignedBigInteger('user_id');
            $t->string('reference_no', 100); $t->date('business_date'); $t->date('promised_date')->nullable();
            $t->text('note')->nullable(); $t->string('status', 24)->default('open'); $t->json('attributes_json')->nullable();
            $t->timestamps();
        });
        if (!Schema::hasTable('commercial_order_items')) Schema::create('commercial_order_items', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('company_id'); $t->string('order_kind', 12);
            $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('source_line_id')->nullable();
            $t->unsignedBigInteger('product_id'); $t->unsignedBigInteger('uom_id'); $t->unsignedBigInteger('variant_id')->nullable();
            $t->unsignedBigInteger('product_batch_id')->nullable(); $t->string('batch_no')->nullable();
            $t->decimal('qty', 20, 4); $t->decimal('qty_base', 20, 4); $t->decimal('unit_rate', 20, 4);
            $t->decimal('cancelled_qty_base', 20, 4)->default(0); $t->timestamps();
        });
        if (!Schema::hasTable('commercial_order_allocations')) Schema::create('commercial_order_allocations', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('order_item_id');
            $t->string('consumer_type', 12); $t->unsignedBigInteger('consumer_id'); $t->unsignedBigInteger('consumer_item_id');
            $t->string('stage', 12); $t->decimal('qty_base', 20, 4); $t->decimal('returned_qty_base', 20, 4)->default(0);
            $t->timestamp('released_at')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('commercial_order_events')) Schema::create('commercial_order_events', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('company_id'); $t->string('order_kind', 12); $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('user_id'); $t->string('event', 32); $t->text('reason'); $t->json('details_json'); $t->timestamp('created_at');
        });
        MigrationConstraints::unique('sales_orders', 'sales_orders_company_id_reference_no_unique', ['company_id', 'reference_no']);
        MigrationConstraints::index('sales_orders', 'sales_orders_company_id_branch_id_status_index', ['company_id', 'branch_id', 'status']);
        MigrationConstraints::index('commercial_order_items', 'commercial_order_items_company_id_order_kind_order_id_index', ['company_id', 'order_kind', 'order_id']);
        MigrationConstraints::unique('commercial_order_items', 'order_source_line_unique', ['company_id', 'order_kind', 'source_line_id']);
        MigrationConstraints::unique('commercial_order_allocations', 'order_consumer_line_unique', ['company_id', 'order_item_id', 'consumer_type', 'consumer_item_id', 'stage']);
        MigrationConstraints::index('commercial_order_allocations', 'order_consumer_document_index', ['company_id', 'consumer_type', 'consumer_id']);
        MigrationConstraints::index('commercial_order_allocations', 'order_item_stage_active_index', ['order_item_id', 'stage', 'released_at']);
        MigrationConstraints::index('commercial_order_events', 'commercial_order_events_company_id_order_kind_order_id_index', ['company_id', 'order_kind', 'order_id']);
    }

    public function down(): void
    {
        foreach (['sales_orders', 'commercial_order_items', 'commercial_order_allocations', 'commercial_order_events'] as $table)
            if (Schema::hasTable($table) && DB::table($table)->exists()) throw new RuntimeException('Retain order history. Disable new order entry and reverse business effects before a reviewed rollback.');
        foreach (['commercial_order_events', 'commercial_order_allocations', 'commercial_order_items', 'sales_orders'] as $table) Schema::dropIfExists($table);
    }
};
