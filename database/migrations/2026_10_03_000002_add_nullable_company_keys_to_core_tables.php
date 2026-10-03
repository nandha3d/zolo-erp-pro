<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // This list is deliberately frozen to this migration's audited scope.
    private const TABLES = [
        'categories', 'brands', 'units', 'customer_groups', 'products', 'customers',
        'suppliers', 'warehouses', 'billers', 'sales', 'product_sales', 'purchases',
        'product_purchases', 'payments', 'product_warehouse', 'transfers', 'product_transfer',
        'returns', 'product_returns', 'return_purchases', 'purchase_product_return',
        'adjustments', 'product_adjustments', 'stock_counts', 'expenses', 'accounts',
        'chart_of_accounts', 'fiscal_years', 'journal_entries', 'journal_items',
        'semantic_account_mappings', 'inventory_closes',
    ];


    public function up(): void
    {
        foreach (['warehouses', 'fiscal_years'] as $required) {
            if (!Schema::hasTable($required)) {
                throw new RuntimeException("Required core table {$required} is missing; no company-key DDL was applied.");
            }
        }
        $definitions = $this->definitions();
        // MySQL commits DDL independently. Validate every existing artifact before altering any table.
        foreach ($definitions as $table => $fields) {
            $columns = array_column(Schema::getColumns($table), null, 'name');
            $indexes = array_column(Schema::getIndexes($table), null, 'name');
            foreach ($fields as $name => $field) {
                if (isset($columns[$name])) {
                    $this->validateColumn($table, $name, $columns[$name], $field);
                }
                $indexName = $table.'_'.$name.'_index';
                if ($field['indexed'] && isset($indexes[$indexName])) {
                    $index = $indexes[$indexName];
                    if ($index['columns'] !== [$name] || $index['unique'] || $index['primary']
                        || ($index['type'] !== null && $index['type'] !== 'btree')) {
                        throw new RuntimeException("Incompatible index {$indexName}; no company-key DDL was applied.");
                    }
                }
            }
        }
        foreach ($definitions as $table => $fields) {
            foreach ($fields as $name => $field) {
                if (!Schema::hasColumn($table, $name)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($name, $field) {
                        $column = $field['method'] === 'string'
                            ? $blueprint->string($name, 20)
                            : $blueprint->{$field['method']}($name);
                        $column->nullable($field['nullable']);
                        if ($field['default'] !== null) {
                            $column->default($field['default']);
                        }
                    });
                }
                // A failure between column and index creation is safe to resume.
                if ($field['indexed'] && !Schema::hasIndex($table, $table.'_'.$name.'_index')) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->definitions(), true) as $table => $fields) {
            foreach (array_reverse($fields, true) as $name => $field) {
                if (Schema::hasColumn($table, $name)) {
                    if ($field['indexed'] && Schema::hasIndex($table, $table.'_'.$name.'_index')) {
                        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex([$name]));
                    }
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($name));
                }
            }
        }
    }

    private function definitions(): array
    {
        $definitions = [];
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $definitions[$table]['company_id'] = $this->field('unsignedBigInteger', true, true);
            }
        }
        if (Schema::hasTable('warehouses')) {
            $definitions['warehouses']['branch_id'] = $this->field('unsignedBigInteger', true, true);
        }
        if (Schema::hasTable('fiscal_years')) {
            $definitions['fiscal_years'] += [
                'status' => $this->field('string', false, false, 'open'),
                'lock_date' => $this->field('date', true),
                'closed_at' => $this->field('timestamp', true),
                'closed_by' => $this->field('unsignedInteger', true),
            ];
        }

        return $definitions;
    }

    private function field(string $method, bool $nullable, bool $indexed = false, ?string $default = null): array
    {
        return compact('method', 'nullable', 'indexed', 'default');
    }

    private function validateColumn(string $table, string $name, array $column, array $field): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        $expectedType = match ($field['method']) {
            'unsignedBigInteger' => $sqlite ? 'integer' : 'bigint unsigned',
            'unsignedInteger' => $sqlite ? 'integer' : 'int unsigned',
            'string' => $sqlite ? 'varchar' : 'varchar(20)',
            'timestamp' => $sqlite ? 'datetime' : 'timestamp',
            'date' => 'date',
        };
        $default = $column['default'] === null ? null : trim((string) $column['default'], "'\"");
        if (strtolower($column['type']) !== $expectedType
            || $column['nullable'] !== $field['nullable']
            || $default !== $field['default'] || $column['auto_increment']) {
            throw new RuntimeException("Incompatible column {$table}.{$name}; no company-key DDL was applied.");
        }
    }
};
