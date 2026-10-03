<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Chart of Accounts Table
        if (!Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 255);
                $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
                $table->string('sub_type', 100); // e.g. current_asset, bank, cash, accounts_receivable, inventory, fixed_asset, accounts_payable, tax_payable, sales_revenue, cogs, operating_expense
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedInteger('currency_id')->nullable();
                $table->decimal('opening_balance', 18, 4)->default(0);
                $table->decimal('current_balance', 18, 4)->default(0);
                $table->boolean('is_reconciled')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false); // Protect core system accounts (AR, AP, COGS, Inventory) from deletion
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('parent_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
                $table->index(['type', 'sub_type']);
                $table->index('is_active');
            });
        }

        // 2. Fiscal Years Table
        if (!Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_closed')->default(false);
                $table->timestamps();
            });
        }

        // 3. Journal Entries Table (Master header)
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_number', 50)->unique();
                $table->date('entry_date');
                $table->string('reference_type', 100)->nullable(); // e.g. sale, purchase, payment, return_sale, return_purchase, expense, payroll, manual
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_no', 100)->nullable(); // Invoice no / PO no
                $table->text('description')->nullable();
                $table->enum('status', ['draft', 'posted', 'void'])->default('posted');
                $table->decimal('total_debit', 18, 4)->default(0);
                $table->decimal('total_credit', 18, 4)->default(0);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['reference_type', 'reference_id']);
                $table->index('entry_date');
                $table->index('status');
            });
        }

        // 4. Journal Items Table (Lines with Debit / Credit)
        if (!Schema::hasTable('journal_items')) {
            Schema::create('journal_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('journal_entry_id');
                $table->unsignedBigInteger('chart_of_account_id');
                $table->string('partner_type', 50)->nullable(); // customer, supplier, employee
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->decimal('debit', 18, 4)->default(0);
                $table->decimal('credit', 18, 4)->default(0);
                $table->text('memo')->nullable();
                $table->timestamps();

                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
                $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
                $table->index(['partner_type', 'partner_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_items');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('chart_of_accounts');
    }
};
