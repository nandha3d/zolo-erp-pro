<?php

use Database\Seeders\CapabilitySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Textile business types become Wholesale & Distributor, Wholesale, Distributor and Manufacturing (was In-house processing).
 * Job work moves from the textile preset to Manufacturing. Existing companies keep their enabled features and settings
 * until an administrator applies a business type again (the old textile settings already match Manufacturing's).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('business_profile_capabilities')) (new CapabilitySeeder)->run();
        if (!Schema::hasTable('company_industry_settings')) return;
        DB::table('company_industry_settings')->where('profile_key', 'textile')->where('subtype', 'in_house_processing')->update(['subtype' => 'manufacturing']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('company_industry_settings')) return;
        DB::table('company_industry_settings')->where('profile_key', 'textile')->where('subtype', 'manufacturing')->update(['subtype' => 'in_house_processing']);
    }
};
