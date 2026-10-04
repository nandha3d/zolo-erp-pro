<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock movement ledger (doc 09). Movements are the history; products.qty,
 * product_warehouse.qty, product_variants.qty and product_batches.qty remain
 * rebuildable projections. Quantities are base-unit decimal(18,4).
 *
 * MySQL commits every DDL statement on its own, and Laravel issues indexes and foreign keys as
 * separate statements after CREATE TABLE. Each table is therefore created only when absent, then
 * every column, index and foreign key is checked and any missing one added, so a run interrupted
 * at any statement can be repeated. A table that lacks owned columns is refused, not patched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'warehouses'] as $required) {
            if (!Schema::hasTable($required)) {
                throw new RuntimeException("Required core table {$required} is missing; no stock ledger DDL was applied.");
            }
        }
        $definitions = $this->definitions();
        // Validate every existing artifact before the first DDL statement.
        foreach ($definitions as $table => $definition) {
            if (Schema::hasTable($table)) {
                MigrationConstraints::requireColumns($table, $definition['columns']);
            }
        }

        foreach ($definitions as $table => $definition) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $blueprint) use ($definition, $table) {
                    $definition['create']($blueprint);
                    $this->declare($blueprint, $table, $definition);
                });
            }
            // A failure between CREATE TABLE and its indexes/foreign keys is repaired here.
            $this->ensure($table, $definition);
        }

        // Extend existing batches rather than adding a parallel stock_batches table.
        if (Schema::hasTable('product_batches')) {
            $columns = [
                'company_id' => fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable(),
                'mfg_date' => fn (Blueprint $t) => $t->date('mfg_date')->nullable(),
                'mrp' => fn (Blueprint $t) => $t->decimal('mrp', 18, 4)->nullable(),
                'status' => fn (Blueprint $t) => $t->string('status', 20)->default('active'),
            ];
            foreach ($columns as $name => $add) {
                if (!Schema::hasColumn('product_batches', $name)) {
                    Schema::table('product_batches', $add);
                }
            }
            MigrationConstraints::index('product_batches', 'product_batches_company_id_index', ['company_id']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_batches')) {
            if (Schema::hasIndex('product_batches', 'product_batches_company_id_index')) {
                Schema::table('product_batches', fn (Blueprint $table) => $table->dropIndex('product_batches_company_id_index'));
            }
            foreach (['company_id', 'mfg_date', 'mrp', 'status'] as $column) {
                if (Schema::hasColumn('product_batches', $column)) {
                    Schema::table('product_batches', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
        foreach (['product_uom_conversions', 'stock_movement_lines', 'stock_dimensions', 'stock_identities', 'stock_movements'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Columns, indexes and foreign keys per table, declared once for creation and for resumption.
     * Foreign keys to companies/product_batches apply only when those tables exist.
     */
    private function definitions(): array
    {
        return [
            'stock_movements' => [
                'create' => function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('company_id')->nullable();
                    $table->unsignedBigInteger('branch_id')->nullable();
                    $table->unsignedBigInteger('financial_year_id')->nullable();
                    $table->string('movement_no', 50)->nullable();
                    $table->date('movement_date');
                    $table->string('movement_type', 30);
                    $table->string('source_type', 50)->nullable();
                    $table->unsignedBigInteger('source_id')->nullable();
                    $table->string('source_no', 100)->nullable();
                    $table->unsignedInteger('warehouse_from_id')->nullable();
                    $table->unsignedInteger('warehouse_to_id')->nullable();
                    $table->string('status', 20)->default('posted');
                    $table->unsignedBigInteger('reversal_of_id')->nullable();
                    $table->string('idempotency_key', 150)->nullable();
                    $table->text('reason')->nullable();
                    $table->json('warnings_json')->nullable();
                    $table->unsignedInteger('created_by')->nullable();
                    $table->timestamp('posted_at')->nullable();
                    $table->timestamps();
                },
                'columns' => ['id', 'company_id', 'branch_id', 'financial_year_id', 'movement_no', 'movement_date', 'movement_type',
                    'source_type', 'source_id', 'source_no', 'warehouse_from_id', 'warehouse_to_id', 'status', 'reversal_of_id',
                    'idempotency_key', 'reason', 'warnings_json', 'created_by', 'posted_at', 'created_at', 'updated_at'],
                'unique' => [['movement_no'], ['reversal_of_id'], ['idempotency_key']],
                'index' => [['company_id'], ['company_id', 'movement_date'], ['source_type', 'source_id']],
                'foreign' => ['reversal_of_id' => 'stock_movements', 'company_id' => 'companies'],
            ],
            'stock_identities' => [
                'create' => function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('company_id')->nullable();
                    $table->unsignedInteger('product_id');
                    // serial: one sellable unit; piece: a dimensioned lot such as a timber log or fabric roll.
                    $table->string('identity_type', 20);
                    $table->string('identity_no', 191);
                    $table->unsignedBigInteger('batch_id')->nullable();
                    $table->unsignedInteger('warehouse_id')->nullable();
                    $table->string('status', 20);
                    $table->date('warranty_start')->nullable();
                    $table->date('warranty_end')->nullable();
                    $table->unsignedBigInteger('last_movement_id')->nullable();
                    $table->timestamps();
                },
                'columns' => ['id', 'company_id', 'product_id', 'identity_type', 'identity_no', 'batch_id', 'warehouse_id', 'status',
                    'warranty_start', 'warranty_end', 'last_movement_id', 'created_at', 'updated_at'],
                'unique' => [['product_id', 'identity_type', 'identity_no']],
                'index' => [['company_id'], ['warehouse_id', 'status']],
                'foreign' => ['product_id' => 'products', 'warehouse_id' => 'warehouses', 'last_movement_id' => 'stock_movements',
                    'batch_id' => 'product_batches', 'company_id' => 'companies'],
            ],
            'stock_dimensions' => [
                'create' => function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('stock_identity_id');
                    $table->decimal('length', 18, 4)->nullable();
                    $table->decimal('width', 18, 4)->nullable();
                    $table->decimal('thickness', 18, 4)->nullable();
                    $table->string('dimension_uom', 10)->nullable();
                    $table->unsignedInteger('pieces')->default(1);
                    $table->decimal('computed_volume', 18, 6)->nullable();
                    $table->string('volume_uom', 10)->nullable();
                    $table->string('grade', 50)->nullable();
                    $table->timestamps();
                },
                'columns' => ['id', 'stock_identity_id', 'length', 'width', 'thickness', 'dimension_uom', 'pieces', 'computed_volume',
                    'volume_uom', 'grade', 'created_at', 'updated_at'],
                'unique' => [['stock_identity_id']],
                'index' => [],
                'foreign' => ['stock_identity_id' => 'stock_identities'],
            ],
            'stock_movement_lines' => [
                'create' => function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('stock_movement_id');
                    $table->unsignedBigInteger('company_id')->nullable();
                    $table->unsignedInteger('line_no');
                    $table->unsignedInteger('product_id');
                    $table->unsignedInteger('variant_id')->nullable();
                    $table->unsignedInteger('warehouse_id');
                    $table->unsignedInteger('uom_id')->nullable();
                    $table->decimal('uom_qty', 18, 4)->nullable();
                    // Signed: positive into warehouse_id, negative out of it.
                    $table->decimal('qty_base', 18, 4);
                    $table->decimal('unit_cost', 18, 6);
                    $table->decimal('value', 18, 4);
                    $table->unsignedBigInteger('batch_id')->nullable();
                    $table->unsignedBigInteger('stock_identity_id')->nullable();
                    $table->json('attributes_json')->nullable();
                    $table->timestamps();
                },
                'columns' => ['id', 'stock_movement_id', 'company_id', 'line_no', 'product_id', 'variant_id', 'warehouse_id', 'uom_id',
                    'uom_qty', 'qty_base', 'unit_cost', 'value', 'batch_id', 'stock_identity_id', 'attributes_json', 'created_at', 'updated_at'],
                'unique' => [['stock_movement_id', 'line_no']],
                'index' => [['product_id', 'warehouse_id'], ['company_id', 'product_id'], ['stock_identity_id'], ['batch_id']],
                'foreign' => ['stock_movement_id' => 'stock_movements', 'product_id' => 'products', 'warehouse_id' => 'warehouses',
                    'stock_identity_id' => 'stock_identities', 'batch_id' => 'product_batches', 'company_id' => 'companies'],
            ],
            'product_uom_conversions' => [
                'create' => function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('company_id')->nullable();
                    $table->unsignedInteger('product_id');
                    $table->unsignedInteger('from_uom_id');
                    $table->unsignedInteger('to_uom_id');
                    // qty_in_to_uom = qty_in_from_uom * factor
                    $table->decimal('factor', 18, 6);
                    $table->unsignedTinyInteger('rounding_scale')->default(4);
                    $table->boolean('is_purchase_default')->default(false);
                    $table->boolean('is_sale_default')->default(false);
                    $table->timestamps();
                },
                'columns' => ['id', 'company_id', 'product_id', 'from_uom_id', 'to_uom_id', 'factor', 'rounding_scale',
                    'is_purchase_default', 'is_sale_default', 'created_at', 'updated_at'],
                'unique' => [['product_id', 'from_uom_id', 'to_uom_id']],
                'index' => [['company_id']],
                'foreign' => ['product_id' => 'products', 'company_id' => 'companies'],
            ],
        ];
    }

    /** Declares constraints inside CREATE TABLE's blueprint so SQLite keeps its inline foreign keys. */
    private function declare(Blueprint $blueprint, string $table, array $definition): void
    {
        foreach ($definition['unique'] as $columns) {
            $blueprint->unique($columns, $this->name($table, $columns, 'unique'));
        }
        foreach ($definition['index'] as $columns) {
            $blueprint->index($columns, $this->name($table, $columns, 'index'));
        }
        foreach ($this->foreignKeys($table, $definition) as $column => $parent) {
            $blueprint->foreign($column, $this->name($table, [$column], 'foreign'))->references('id')->on($parent)->restrictOnDelete();
        }
    }

    /** Adds whichever index or foreign key is missing; validates the ones that exist. */
    private function ensure(string $table, array $definition): void
    {
        foreach ($definition['unique'] as $columns) {
            MigrationConstraints::unique($table, $this->name($table, $columns, 'unique'), $columns);
        }
        foreach ($definition['index'] as $columns) {
            MigrationConstraints::index($table, $this->name($table, $columns, 'index'), $columns);
        }
        foreach ($this->foreignKeys($table, $definition) as $column => $parent) {
            MigrationConstraints::foreign($table, $this->name($table, [$column], 'foreign'), [$column], $parent);
        }
    }

    private function foreignKeys(string $table, array $definition): array
    {
        return array_filter($definition['foreign'], fn ($parent) => $parent === $table || Schema::hasTable($parent));
    }

    private function name(string $table, array $columns, string $type): string
    {
        return strtolower($table.'_'.implode('_', $columns).'_'.$type);
    }
};
