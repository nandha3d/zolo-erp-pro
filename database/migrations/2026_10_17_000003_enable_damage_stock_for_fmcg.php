<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** FMCG disposes damaged and expired stock routinely; the FMCG preset now includes inventory.damage_stock. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_industry_settings') || !Schema::hasTable('company_capabilities')) {
            return;
        }
        $capability = DB::table('capabilities')->where('key', 'inventory.damage_stock')->value('id');
        if (!$capability) {
            return;
        }
        foreach (DB::table('company_industry_settings')->where('profile_key', 'fmcg')->pluck('company_id') as $companyId) {
            DB::table('company_capabilities')->updateOrInsert(['company_id' => $companyId, 'capability_id' => $capability],
                ['enabled' => true, 'enabled_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
        }
        app()->bound('cache') && app('cache')->flush();
    }

    public function down(): void
    {
        // Stock losses may already be posted under the capability; keep it enabled.
    }
};
