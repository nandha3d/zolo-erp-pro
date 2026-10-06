<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add Transport & Add-ins to Sales table
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'bale_no')) {
                $table->string('bale_no', 100)->nullable()->after('agent_id');
            }
            if (!Schema::hasColumn('sales', 'no_of_bales')) {
                $table->integer('no_of_bales')->nullable()->after('bale_no');
            }
            if (!Schema::hasColumn('sales', 'lr_no')) {
                $table->string('lr_no', 100)->nullable()->after('no_of_bales');
            }
            if (!Schema::hasColumn('sales', 'lr_date')) {
                $table->date('lr_date')->nullable()->after('lr_no');
            }
            if (!Schema::hasColumn('sales', 'transport_name')) {
                $table->string('transport_name', 150)->nullable()->after('lr_date');
            }
            if (!Schema::hasColumn('sales', 'station_to')) {
                $table->string('station_to', 150)->nullable()->after('transport_name');
            }
            if (!Schema::hasColumn('sales', 'order_no')) {
                $table->string('order_no', 100)->nullable()->after('station_to');
            }
            if (!Schema::hasColumn('sales', 'credit_days')) {
                $table->integer('credit_days')->nullable()->after('order_no');
            }
            if (!Schema::hasColumn('sales', 'salesman_id')) {
                $table->unsignedBigInteger('salesman_id')->nullable()->after('credit_days');
            }
        });

        // 2. Add Transport & Order to Purchases table
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'lr_no')) {
                $table->string('lr_no', 100)->nullable()->after('agent_id');
            }
            if (!Schema::hasColumn('purchases', 'lr_date')) {
                $table->date('lr_date')->nullable()->after('lr_no');
            }
            if (!Schema::hasColumn('purchases', 'transport_name')) {
                $table->string('transport_name', 150)->nullable()->after('lr_date');
            }
            if (!Schema::hasColumn('purchases', 'order_no')) {
                $table->string('order_no', 100)->nullable()->after('transport_name');
            }
        });

        // 3. Negative stock policy on Products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'allow_negative_stock')) {
                $table->boolean('allow_negative_stock')->default(true)->after('is_active');
            }
        });

        // 4. GST nature on Journal Entries
        Schema::table('journal_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entries', 'gst_nature')) {
                $table->string('gst_nature', 50)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'bale_no', 'no_of_bales', 'lr_no', 'lr_date',
                'transport_name', 'station_to', 'order_no', 'credit_days', 'salesman_id'
            ]);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['lr_no', 'lr_date', 'transport_name', 'order_no']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_negative_stock');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('gst_nature');
        });
    }
};
