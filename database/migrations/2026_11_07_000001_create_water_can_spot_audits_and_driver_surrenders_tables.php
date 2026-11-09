<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('water_can_spot_audits')) {
            Schema::create('water_can_spot_audits', function (Blueprint $table) {
                $table->id();
                $table->string('audit_id')->unique();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedInteger('auditor_id')->nullable();
                $table->string('vehicle_number')->nullable();
                $table->string('driver_name')->nullable();
                $table->date('audit_date');
                $table->integer('expected_units')->default(0);
                $table->integer('physical_units')->default(0);
                $table->integer('unit_difference')->default(0);
                $table->decimal('expected_cash', 12, 2)->default(0);
                $table->decimal('physical_cash', 12, 2)->default(0);
                $table->decimal('cash_difference', 12, 2)->default(0);
                $table->boolean('is_fraud_suspected')->default(false);
                $table->string('status')->default('Audited');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('water_can_driver_surrenders')) {
            Schema::create('water_can_driver_surrenders', function (Blueprint $table) {
                $table->id();
                $table->string('surrender_receipt')->unique();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('route_id')->nullable();
                $table->unsignedInteger('driver_id')->nullable();
                $table->date('surrender_date');
                $table->decimal('cash_surrendered', 12, 2)->default(0);
                $table->integer('empty_cans_returned')->default(0);
                $table->integer('filled_cans_returned')->default(0);
                $table->integer('damaged_cans_reported')->default(0);
                $table->string('status')->default('Verified & Deposited to Main Cash Ledger');
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('water_can_driver_surrenders');
        Schema::dropIfExists('water_can_spot_audits');
    }
};

