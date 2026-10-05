<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company ownership for the remaining numbered business documents so they can use the company series.
 * Nullable and resumable: retained rows stay unassigned until erp:backfill-company-context assigns them.
 */
return new class extends Migration
{
    private const TABLES = ['quotations', 'deliveries', 'incomes', 'money_transfers', 'payrolls', 'damage_stocks', 'exchanges'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('company_id')->nullable());
            }
            if (!collect(Schema::getIndexes($table))->contains(fn ($index) => $index['columns'] === ['company_id'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('company_id', $table.'_company_id_index'));
            }
        }
    }

    public function down(): void
    {
        // Ownership keys are never dropped: they may already identify retained business records.
    }
};
