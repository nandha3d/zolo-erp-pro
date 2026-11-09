<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('water_tankers')) {
            Schema::table('water_tankers', function (Blueprint $table) {
                if (!Schema::hasColumn('water_tankers', 'current_odometer_km')) {
                    $table->integer('current_odometer_km')->default(45200)->after('capacity_liters');
                }
                if (!Schema::hasColumn('water_tankers', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('current_odometer_km');
                }
                if (!Schema::hasColumn('water_tankers', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }

        if (Schema::hasTable('water_tanker_trips')) {
            Schema::table('water_tanker_trips', function (Blueprint $table) {
                if (!Schema::hasColumn('water_tanker_trips', 'start_km')) {
                    $table->integer('start_km')->nullable()->default(0)->after('water_quantity_kl');
                }
                if (!Schema::hasColumn('water_tanker_trips', 'end_km')) {
                    $table->integer('end_km')->nullable()->default(0)->after('start_km');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('water_tanker_trips')) {
            Schema::table('water_tanker_trips', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('water_tanker_trips', 'end_km')) {
                    $cols[] = 'end_km';
                }
                if (Schema::hasColumn('water_tanker_trips', 'start_km')) {
                    $cols[] = 'start_km';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('water_tankers')) {
            Schema::table('water_tankers', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('water_tankers', 'longitude')) {
                    $cols[] = 'longitude';
                }
                if (Schema::hasColumn('water_tankers', 'latitude')) {
                    $cols[] = 'latitude';
                }
                if (Schema::hasColumn('water_tankers', 'current_odometer_km')) {
                    $cols[] = 'current_odometer_km';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
