<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('quotations')) return;
        foreach (['converted_kind', 'converted_document_id', 'converted_at'] as $column) {
            if (Schema::hasColumn('quotations', $column)) continue;
            Schema::table('quotations', function (Blueprint $table) use ($column) {
                match ($column) {
                    'converted_kind' => $table->string($column, 20)->nullable(),
                    'converted_document_id' => $table->unsignedBigInteger($column)->nullable(),
                    'converted_at' => $table->timestamp($column)->nullable(),
                };
            });
        }
        if (!Schema::hasColumn('quotations', 'conversion_review_required')) {
            // Existing status 2 means either Sent or Converted; no consumer link was retained to distinguish them.
            Schema::table('quotations', fn (Blueprint $table) => $table->boolean('conversion_review_required')->default(true));
            DB::table('quotations')->where('quotation_status', 1)->whereNull('converted_document_id')->update(['conversion_review_required' => false]);
        }
        Schema::table('quotations', fn (Blueprint $table) => $table->boolean('conversion_review_required')->default(false)->change());
    }
    public function down(): void
    {
        // Conversion identity and review decisions are retained; never reopen historical quotes by dropping this state.
    }
};
