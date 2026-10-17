<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job work is the parent workflow: a Material DC can be a first send ("process") or a send-back of a GRN for rework,
 * and a Job Work GRN is either the processed receipt or material returned unprocessed against a DC.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_work_dispatches')) {
            Schema::table('job_work_dispatches', function (Blueprint $t) {
                if (!Schema::hasColumn('job_work_dispatches', 'purpose')) $t->string('purpose', 20)->default('process');
                if (!Schema::hasColumn('job_work_dispatches', 'rework_of_receipt_id')) $t->unsignedBigInteger('rework_of_receipt_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('job_work_receipts') && !Schema::hasColumn('job_work_receipts', 'receipt_kind')) {
            Schema::table('job_work_receipts', fn (Blueprint $t) => $t->string('receipt_kind', 20)->default('process'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_work_receipts', 'receipt_kind')) Schema::table('job_work_receipts', fn (Blueprint $t) => $t->dropColumn('receipt_kind'));
        foreach (['rework_of_receipt_id', 'purpose'] as $column) {
            if (Schema::hasColumn('job_work_dispatches', $column)) Schema::table('job_work_dispatches', fn (Blueprint $t) => $t->dropColumn($column));
        }
    }
};
