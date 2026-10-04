<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock movement ledger (doc 09). Movements are the history; products.qty,
 * product_warehouse.qty, product_variants.qty and product_batches.qty remain
 * rebuildable projections. Quantities are base-unit decimal(18,4).
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
        $companies = Schema::hasTable('companies');
        $batches = Schema::hasTable('product_batches');

        Schema::create('stock_movements', function (Blueprint $table) use ($companies) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('financial_year_id')->nullable();
            $table->string('movement_no', 50)->nullable()->unique();
            $table->date('movement_date');
            $table->string('movement_type', 30);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_no', 100)->nullable();
            $table->unsignedInteger('warehouse_from_id')->nullable();
            $table->unsignedInteger('warehouse_to_id')->nullable();
            $table->string('status', 20)->default('posted');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->unique();
            $table->string('idempotency_key', 150)->nullable()->unique();
            $table->text('reason')->nullable();
            $table->json('warnings_json')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'movement_date']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('reversal_of_id')->references('id')->on('stock_movements')->restrictOnDelete();
            if ($companies) {
                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            }
        });

        Schema::create('stock_identities', function (Blueprint $table) use ($companies, $batches) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
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
            $table->unique(['product_id', 'identity_type', 'identity_no']);
            $table->index(['warehouse_id', 'status']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('last_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            if ($batches) {
                $table->foreign('batch_id')->references('id')->on('product_batches')->restrictOnDelete();
            }
            if ($companies) {
                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            }
        });

        Schema::create('stock_dimensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_identity_id')->unique();
            $table->decimal('length', 18, 4)->nullable();
            $table->decimal('width', 18, 4)->nullable();
            $table->decimal('thickness', 18, 4)->nullable();
            $table->string('dimension_uom', 10)->nullable();
            $table->unsignedInteger('pieces')->default(1);
            $table->decimal('computed_volume', 18, 6)->nullable();
            $table->string('volume_uom', 10)->nullable();
            $table->string('grade', 50)->nullable();
            $table->timestamps();
            $table->foreign('stock_identity_id')->references('id')->on('stock_identities')->restrictOnDelete();
        });

        Schema::create('stock_movement_lines', function (Blueprint $table) use ($companies, $batches) {
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
            $table->unique(['stock_movement_id', 'line_no']);
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['company_id', 'product_id']);
            $table->index('stock_identity_id');
            $table->index('batch_id');
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('stock_identity_id')->references('id')->on('stock_identities')->restrictOnDelete();
            if ($batches) {
                $table->foreign('batch_id')->references('id')->on('product_batches')->restrictOnDelete();
            }
            if ($companies) {
                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            }
        });

        Schema::create('product_uom_conversions', function (Blueprint $table) use ($companies) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('from_uom_id');
            $table->unsignedInteger('to_uom_id');
            // qty_in_to_uom = qty_in_from_uom * factor
            $table->decimal('factor', 18, 6);
            $table->unsignedTinyInteger('rounding_scale')->default(4);
            $table->boolean('is_purchase_default')->default(false);
            $table->boolean('is_sale_default')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'from_uom_id', 'to_uom_id']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            if ($companies) {
                $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            }
        });

        // Extend existing batches rather than adding a parallel stock_batches table.
        if ($batches) {
            Schema::table('product_batches', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->date('mfg_date')->nullable();
                $table->decimal('mrp', 18, 4)->nullable();
                $table->string('status', 20)->default('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_batches') && Schema::hasColumn('product_batches', 'mfg_date')) {
            Schema::table('product_batches', function (Blueprint $table) {
                $table->dropIndex(['company_id']);
                $table->dropColumn(['company_id', 'mfg_date', 'mrp', 'status']);
            });
        }
        Schema::dropIfExists('product_uom_conversions');
        Schema::dropIfExists('stock_movement_lines');
        Schema::dropIfExists('stock_dimensions');
        Schema::dropIfExists('stock_identities');
        Schema::dropIfExists('stock_movements');
    }
};
