<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('quotations')) return;
        if (!Schema::hasColumn('quotations', 'next_follow_up')) {
            Schema::table('quotations', fn (Blueprint $table) => $table->date('next_follow_up')->nullable()->index());
        }
        if (!Schema::hasTable('quotation_follow_ups')) {
            Schema::create('quotation_follow_ups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedInteger('quotation_id');
                $table->date('previous_date')->nullable();
                $table->date('next_date')->nullable();
                $table->unsignedInteger('actor_id')->nullable();
                $table->timestamp('created_at');
                $table->index(['company_id', 'quotation_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        // Follow-up changes are retained as audit history, including after conversion.
    }
};
