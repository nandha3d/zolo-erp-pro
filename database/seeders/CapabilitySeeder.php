<?php

namespace Database\Seeders;

use App\Services\Platform\CapabilityCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CapabilitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (CapabilityCatalog::DEFINITIONS as $key => [$name, $dependencies]) {
            DB::table('capabilities')->updateOrInsert(['key' => $key], [
                'name' => $name, 'group_key' => explode('.', $key)[0],
                'is_core' => CapabilityCatalog::DEFINITIONS[$key][2] ?? false,
                'module_provider' => 'shared',
                'configuration_schema' => json_encode(CapabilityCatalog::CONFIGURATION[$key] ?? new \stdClass),
                'dependencies_json' => json_encode($dependencies),
            ]);
        }
        $ids = DB::table('capabilities')->pluck('id', 'key');
        foreach (CapabilityCatalog::PROFILES as $key => [$name, $optional]) {
            DB::table('business_profiles')->updateOrInsert(['key' => $key], [
                'name' => $name, 'description' => $name.' capability preset', 'is_system' => true,
            ]);
            $profileId = DB::table('business_profiles')->where('key', $key)->value('id');
            foreach (CapabilityCatalog::DEFINITIONS as $capability => $definition) {
                DB::table('business_profile_capabilities')->updateOrInsert([
                    'profile_id' => $profileId, 'capability_id' => $ids[$capability],
                ], [
                    'default_enabled' => ($definition[2] ?? false) || in_array($capability, $optional, true),
                    'default_config_json' => json_encode($capability === 'printing.dot_matrix' ? ['lines' => 68, 'columns' => 80] : new \stdClass),
                ]);
            }
        }
    }
}
