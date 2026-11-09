<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('city');
                }
                if (!Schema::hasColumn('customers', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
                if (!Schema::hasColumn('customers', 'geofence_radius')) {
                    $table->integer('geofence_radius')->default(150)->after('longitude');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('customers', 'geofence_radius')) {
                    $columns[] = 'geofence_radius';
                }
                if (Schema::hasColumn('customers', 'longitude')) {
                    $columns[] = 'longitude';
                }
                if (Schema::hasColumn('customers', 'latitude')) {
                    $columns[] = 'latitude';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
