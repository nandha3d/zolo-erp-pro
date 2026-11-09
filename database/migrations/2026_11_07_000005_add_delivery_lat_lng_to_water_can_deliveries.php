<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('water_can_deliveries')) {
            Schema::table('water_can_deliveries', function (Blueprint $table) {
                if (!Schema::hasColumn('water_can_deliveries', 'delivery_lat')) {
                    $table->decimal('delivery_lat', 10, 7)->nullable()->after('status');
                }
                if (!Schema::hasColumn('water_can_deliveries', 'delivery_lng')) {
                    $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('water_can_deliveries')) {
            Schema::table('water_can_deliveries', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('water_can_deliveries', 'delivery_lng')) {
                    $cols[] = 'delivery_lng';
                }
                if (Schema::hasColumn('water_can_deliveries', 'delivery_lat')) {
                    $cols[] = 'delivery_lat';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

