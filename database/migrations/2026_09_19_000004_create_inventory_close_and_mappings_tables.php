<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Periodic Inventory Close
        if (!Schema::hasTable('inventory_closes')) {
            Schema::create('inventory_closes', function (Blueprint $table) {
                $table->id();
                $table->string('reference_no')->unique();
                $table->date('closing_date');
                $table->unsignedInteger('warehouse_id')->nullable(); // null means all warehouses
                $table->string('valuation_method')->default('weighted_average');
                $table->double('total_quantity', 14, 2)->default(0);
                $table->double('total_valuation', 14, 2)->default(0);
                $table->double('adjustment_amount', 14, 2)->default(0);
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('status')->default('completed'); // draft, reviewed, posted
                $table->text('notes')->nullable();
                $table->unsignedInteger('user_id');
                $table->timestamps();
            });
        }

        // 2. Semantic Account Mappings
        if (!Schema::hasTable('semantic_account_mappings')) {
            Schema::create('semantic_account_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('semantic_role')->unique(); // sales_revenue, inventory_asset, cogs, accounts_receivable, accounts_payable, etc.
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('account_code')->nullable();
                $table->string('account_name')->nullable();
                $table->string('category')->default('core'); // core, banking, inventory, expenses, tax
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('semantic_account_mappings');
        Schema::dropIfExists('inventory_closes');
    }
};
