<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business document numbers are unique per company, not per installation. The company-scoped index is created
 * before the global one is dropped, so a partly applied run (MySQL commits DDL as it goes) resumes safely.
 */
return new class extends Migration
{
    /** table => [column, existing global unique index] */
    private const NUMBERS = [
        'damage_stocks' => ['reference_no', 'damage_stocks_reference_no_unique'],
        'exchanges' => ['reference_no', 'exchanges_reference_no_unique'],
        'inventory_closes' => ['reference_no', 'inventory_closes_reference_no_unique'],
        'journal_entries' => ['entry_number', 'journal_entries_entry_number_unique'],
    ];

    public function up(): void
    {
        foreach (self::NUMBERS as $table => [$column, $global]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'company_id') || !Schema::hasColumn($table, $column)) {
                continue;
            }
            $indexes = collect(Schema::getIndexes($table))->keyBy('name');
            $scoped = $table.'_company_'.$column.'_unique';
            if (!$indexes->has($scoped)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(['company_id', $column], $scoped));
            }
            if ($indexes->has($global)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($global));
            }
        }
    }

    public function down(): void
    {
        // Widening uniqueness back to the whole installation could fail on legitimate multi-company data.
    }
};
