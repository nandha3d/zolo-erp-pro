<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['companies', 'currencies'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Required currency reference table {$table} is missing.");
            }
        }
        $column = collect(Schema::getColumns('companies'))->firstWhere('name', 'base_currency_id');
        $currency = collect(Schema::getColumns('currencies'))->firstWhere('name', 'id');
        $sqlite = DB::getDriverName() === 'sqlite';
        $allowed = $sqlite ? ['integer'] : ['int unsigned', 'bigint unsigned'];
        if (!$column || !in_array($column['type'], $allowed, true) || !$column['nullable']
            || $column['default'] !== null || $column['auto_increment']
            || !$currency || $currency['type'] !== ($sqlite ? 'integer' : 'bigint unsigned')) {
            throw new RuntimeException('Incompatible company/currency ID types; no currency DDL applied.');
        }
        if (DB::table('companies as company')->leftJoin('currencies as currency', 'company.base_currency_id', '=', 'currency.id')
            ->whereNotNull('company.base_currency_id')->whereNull('currency.id')->exists()) {
            throw new RuntimeException('Orphan company currency reference; no currency DDL applied.');
        }
        $foreign = collect(Schema::getForeignKeys('companies'))->firstWhere('name', 'companies_base_currency_id_foreign');
        if ($foreign && ($foreign['columns'] !== ['base_currency_id'] || $foreign['foreign_table'] !== 'currencies'
            || $foreign['foreign_columns'] !== ['id'] || $foreign['on_delete'] !== 'restrict')) {
            throw new RuntimeException('Incompatible company currency foreign key; no currency DDL applied.');
        }
        if (!$sqlite && $column['type'] === 'int unsigned') {
            Schema::table('companies', fn (Blueprint $table) => $table->unsignedBigInteger('base_currency_id')->nullable()->change());
        }
        if (!$foreign) {
            Schema::table('companies', fn (Blueprint $table) => $table->foreign('base_currency_id')->references('id')->on('currencies')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && collect(Schema::getForeignKeys('companies'))
            ->contains('name', 'companies_base_currency_id_foreign')) {
            Schema::table('companies', fn (Blueprint $table) => $table->dropForeign(['base_currency_id']));
        }
        // Preserve the widened ID type: shrinking could truncate valid currency IDs.
    }
};
