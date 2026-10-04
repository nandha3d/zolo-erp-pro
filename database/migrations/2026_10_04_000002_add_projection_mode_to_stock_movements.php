<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "applied" movements updated the quantity projections; "shadow" movements only describe
 * quantity changes that a legacy writer already made (doc 09 phase 4b). Reversals inherit the mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stock_movements', 'projection_mode')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->string('projection_mode', 20)->default('applied')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'projection_mode')) {
            Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('projection_mode'));
        }
    }
};
