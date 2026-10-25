<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Textile trade and compliance features: cash discount and TDS on settlements, price-banded GST rates
 * (garments up to ₹2,500 at 5%), and job work done for a principal (customer-owned material).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'discount')) $table->decimal('discount', 18, 4)->default(0);
                if (!Schema::hasColumn('payments', 'discount_reason')) $table->string('discount_reason', 255)->nullable();
                if (!Schema::hasColumn('payments', 'tds_amount')) $table->decimal('tds_amount', 18, 4)->default(0);
            });
        }
        if (Schema::hasTable('tax_rates')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                // A rate applies when the line's per-unit taxable value is within the band; empty bounds match any value.
                if (!Schema::hasColumn('tax_rates', 'min_unit_value')) $table->decimal('min_unit_value', 18, 4)->nullable();
                if (!Schema::hasColumn('tax_rates', 'max_unit_value')) $table->decimal('max_unit_value', 18, 4)->nullable();
            });
        }
        if (!Schema::hasTable('principal_job_orders')) {
            Schema::create('principal_job_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedInteger('customer_id');
                $table->string('reference_no', 50);
                $table->date('business_date');
                $table->string('process', 100);
                $table->unsignedInteger('service_product_id')->nullable();
                $table->decimal('rate', 18, 4)->default(0);
                $table->decimal('max_loss_percent', 8, 4)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'reference_no']);
            });
        }
        if (!Schema::hasTable('principal_job_movements')) {
            Schema::create('principal_job_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('principal_job_order_id');
                // received = principal's material in; returned = processed goods out; loss = process loss; billed = processing invoice
                $table->string('direction', 20);
                $table->date('business_date');
                $table->string('challan_no', 60)->nullable();
                $table->date('challan_date')->nullable();
                $table->string('description', 255);
                $table->string('hsn_code', 8)->nullable();
                $table->string('uqc', 10)->default('MTR');
                $table->decimal('qty', 18, 4);
                $table->decimal('value', 18, 4)->default(0);
                $table->unsignedInteger('sale_id')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'principal_job_order_id']);
            });
        }
        if (!Schema::hasTable('agent_commissions')) {
            // Brokerage (dalali) booked per invoice, so an invoice is never accrued twice.
            Schema::create('agent_commissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('agent_id');
                $table->unsignedInteger('sale_id');
                $table->decimal('base', 18, 4);
                $table->decimal('rate', 8, 4);
                $table->decimal('amount', 18, 4);
                $table->decimal('tds_amount', 18, 4)->default(0);
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->date('business_date');
                $table->timestamps();
                $table->unique(['company_id', 'sale_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');
        Schema::dropIfExists('principal_job_movements');
        Schema::dropIfExists('principal_job_orders');
        foreach (['payments' => ['discount', 'discount_reason', 'tds_amount'], 'tax_rates' => ['min_unit_value', 'max_unit_value']] as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            $columns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if ($columns) Schema::table($table, fn (Blueprint $t) => $t->dropColumn($columns));
        }
    }
};
