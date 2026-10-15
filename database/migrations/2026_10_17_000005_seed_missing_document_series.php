<?php

use Database\Seeders\OptechMasterSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every active branch and open financial year gets the standard numbering (production, adjustment, transfer, ...).
 * Idempotent: series that exist are left alone and an existing default stays the default, so no numbering changes mid-year.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_series') || !Schema::hasTable('company_branches') || !Schema::hasTable('fiscal_years')) {
            return;
        }
        foreach (DB::table('company_branches')->where('is_active', true)->orderBy('id')->get(['id', 'company_id']) as $branch) {
            foreach (DB::table('fiscal_years')->where('company_id', $branch->company_id)->where('is_closed', false)->orderBy('start_date')->pluck('id') as $yearId) {
                OptechMasterSeeder::seedSeries((int) $branch->company_id, (int) $branch->id, (int) $yearId);
            }
        }
    }

    public function down(): void
    {
        // Series may already have issued numbers; keep them.
    }
};
