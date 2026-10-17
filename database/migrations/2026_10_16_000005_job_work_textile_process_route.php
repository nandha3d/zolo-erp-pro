<?php

use App\Services\Industry\IndustryProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Job work as a configurable process route: a process can name its default output item, processing-charge service item
 * and rate; a dispatch keeps its transport details for the GST job work challan. Textile companies get the
 * grey → dyed fabric conversion preset, and auto-generated job work series switch to short readable prefixes.
 */
return new class extends Migration
{
    private const PREFIXES = ['job_work_order' => 'JWO-', 'job_work_dispatch' => 'JWDC-', 'job_work_receipt' => 'JWGRN-'];

    public function up(): void
    {
        if (Schema::hasTable('job_work_dispatches') && !Schema::hasColumn('job_work_dispatches', 'details_json')) {
            Schema::table('job_work_dispatches', fn (Blueprint $t) => $t->json('details_json')->nullable());
        }
        if (Schema::hasTable('process_types')) {
            Schema::table('process_types', function (Blueprint $t) {
                if (!Schema::hasColumn('process_types', 'output_product_id')) $t->unsignedBigInteger('output_product_id')->nullable();
                if (!Schema::hasColumn('process_types', 'service_product_id')) $t->unsignedBigInteger('service_product_id')->nullable();
                if (!Schema::hasColumn('process_types', 'service_rate')) $t->decimal('service_rate', 18, 4)->nullable();
            });
        }
        // Lots without an expiry (grey and dyed fabric lots); batch/expiry companies still have to enter one.
        if (Schema::hasTable('product_batches') && Schema::hasColumn('product_batches', 'expired_date')) {
            Schema::table('product_batches', fn (Blueprint $t) => $t->date('expired_date')->nullable()->change());
        }
        if (Schema::hasTable('company_industry_settings')) {
            foreach (DB::table('company_industry_settings')->where('profile_key', 'textile')->pluck('company_id') as $companyId) {
                IndustryProfileService::ensureProcesses((int) $companyId, 'textile');
            }
        }
        if (Schema::hasTable('document_series')) {
            // Only system-generated defaults ("ERP-JWD-<company>-<branch>-<year>-") change; numbers already issued keep their text.
            foreach (self::PREFIXES as $type => $prefix) {
                DB::table('document_series')->where('document_type', $type)->where('prefix', 'like', 'ERP-%')
                    ->update(['prefix' => $prefix, 'padding' => 5, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('process_types')) {
            Schema::table('process_types', function (Blueprint $t) {
                foreach (['output_product_id', 'service_product_id', 'service_rate'] as $column) {
                    if (Schema::hasColumn('process_types', $column)) $t->dropColumn($column);
                }
            });
        }
        if (Schema::hasColumn('job_work_dispatches', 'details_json')) Schema::table('job_work_dispatches', fn (Blueprint $t) => $t->dropColumn('details_json'));
    }
};
