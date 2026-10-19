<?php

use App\Services\Industry\IndustryProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // Historical allow_negative_stock defaulted to true while ignored. Do not map it to permission.
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'negative_stock_policy')) {
            Schema::table('products', fn (Blueprint $t) => $t->string('negative_stock_policy', 10)->nullable());
        }
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'default_hsn_code')) {
            Schema::table('categories', fn (Blueprint $t) => $t->string('default_hsn_code', 8)->nullable());
        }
        if (Schema::hasTable('company_industry_settings')) {
            foreach (DB::table('company_industry_settings')->where('profile_key', 'textile')->pluck('company_id') as $companyId) {
                IndustryProfileService::ensureUnits((int) $companyId, 'textile');
                IndustryProfileService::ensureProcesses((int) $companyId, 'textile');
                $row = DB::table('company_industry_settings')->where('company_id', $companyId)->first();
                $settings = json_decode($row->settings_json, true) ?: [];
                $settings['units'] = array_values(array_unique(array_merge($settings['units'] ?? [], ['MTR', 'KGS', 'DOZ', 'PCS'])));
                DB::table('company_industry_settings')->where('company_id', $companyId)->update(['settings_json' => json_encode($settings, JSON_THROW_ON_ERROR)]);
            }
        }
    }

    public function down(): void
    {
        // Retain master decisions and preset identities. Posted data may reference them.
    }
};
