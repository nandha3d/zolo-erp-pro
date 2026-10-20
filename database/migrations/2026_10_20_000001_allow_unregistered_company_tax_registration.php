<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** An unregistered business keeps its legal name, address and state as a registration period without a GSTIN. */
    public function up(): void
    {
        if (!Schema::hasTable('tax_registrations')) return;
        Schema::table('tax_registrations', fn (Blueprint $table) => $table->string('gstin', 15)->nullable()->change());
    }
    public function down(): void
    {
        // Unregistered periods are retained history; never force a GSTIN onto them.
    }
};
