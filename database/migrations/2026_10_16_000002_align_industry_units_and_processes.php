<?php

use App\Services\Industry\IndustryProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/** Companies set up before presets created units get their industry's units; stale preset processes stop being offered. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_industry_settings')) return;
        foreach (DB::table('company_industry_settings')->get(['company_id', 'profile_key']) as $row) {
            if (!isset(\App\Services\Industry\IndustryCatalog::SETTINGS[$row->profile_key])) continue;
            IndustryProfileService::ensureUnits((int) $row->company_id, $row->profile_key);
            IndustryProfileService::alignProcesses((int) $row->company_id, $row->profile_key);
        }
    }

    public function down(): void
    {
        // Units and process states may already be in use; nothing to undo safely.
    }
};
