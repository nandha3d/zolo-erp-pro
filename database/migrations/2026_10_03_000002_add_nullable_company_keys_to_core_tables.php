<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name)) {
                Schema::table($name, function (Blueprint $table) {
                    // Constraints and non-null enforcement belong to the later cutover.
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }

        Schema::table('warehouses', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->index();
        });
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->string('status', 20)->default('open');
            $table->date('lock_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropColumn(['status', 'lock_date', 'closed_at', 'closed_by']);
        });
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex(['branch_id']);
            $table->dropColumn('branch_id');
        });
        foreach (array_reverse(self::TABLES) as $name) {
            if (Schema::hasTable($name)) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropIndex(['company_id']);
                    $table->dropColumn('company_id');
                });
            }
        }
    }
};
