<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['goods_received_note_items', 'delivery_challan_items'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'variant_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('variant_id')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['goods_received_note_items', 'delivery_challan_items'] as $table) {
            if (Schema::hasColumn($table, 'variant_id') && DB::table($table)->whereNotNull('variant_id')->exists()) {
                throw new RuntimeException('Material variant identity is retained history. Disable variant entry before a reviewed rollback.');
            }
        }
        foreach (['goods_received_note_items', 'delivery_challan_items'] as $table) {
            if (Schema::hasColumn($table, 'variant_id')) Schema::table($table, fn (Blueprint $t) => $t->dropColumn('variant_id'));
        }
    }
};
