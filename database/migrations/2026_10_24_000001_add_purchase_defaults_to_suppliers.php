<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** A supplier's standard bill terms (payment mode, item nature, place of supply, reverse charge, remarks) prefilled on purchase entry. */
    public function up(): void
    {
        if (!Schema::hasTable('suppliers') || Schema::hasColumn('suppliers', 'purchase_defaults')) return;
        Schema::table('suppliers', fn (Blueprint $table) => $table->json('purchase_defaults')->nullable());
    }
    public function down(): void
    {
        if (Schema::hasColumn('suppliers', 'purchase_defaults')) Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('purchase_defaults'));
    }
};
