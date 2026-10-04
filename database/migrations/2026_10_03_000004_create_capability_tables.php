<?php

use Database\Seeders\CapabilitySeeder;
use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // New tables are created independently so committed MySQL DDL can resume.
        if (!Schema::hasTable('capabilities')) {
            Schema::create('capabilities', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('name');
                $table->string('group_key', 50);
                $table->string('module_provider')->nullable();
                $table->boolean('is_core')->default(false);
                $table->json('configuration_schema')->nullable();
                $table->json('dependencies_json');
            });
        }
        if (!Schema::hasTable('business_profiles')) {
            Schema::create('business_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(true);
            });
        }
        if (!Schema::hasTable('business_profile_capabilities')) {
            Schema::create('business_profile_capabilities', function (Blueprint $table) {
                $table->foreignId('profile_id')->constrained('business_profiles')->restrictOnDelete();
                $table->foreignId('capability_id')->constrained('capabilities')->restrictOnDelete();
                $table->boolean('default_enabled')->default(true);
                $table->json('default_config_json')->nullable();
                $table->primary(['profile_id', 'capability_id'], 'profile_capability_primary');
            });
        }
        if (!Schema::hasTable('company_capabilities')) {
            Schema::create('company_capabilities', function (Blueprint $table) {
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('capability_id')->constrained('capabilities')->restrictOnDelete();
                $table->boolean('enabled');
                $table->json('config_json')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->unsignedInteger('enabled_by')->nullable();
                $table->foreign('enabled_by')->references('id')->on('users')->restrictOnDelete();
                $table->primary(['company_id', 'capability_id']);
                $table->timestamps();
            });
        }
        MigrationConstraints::unique('capabilities', 'capabilities_key_unique', ['key']);
        MigrationConstraints::unique('business_profiles', 'business_profiles_key_unique', ['key']);
        // SQLite reports composite PRIMARY KEY names as "primary"; MySQL uses the same introspected name.
        MigrationConstraints::unique('business_profile_capabilities', 'primary', ['profile_id', 'capability_id'], true);
        MigrationConstraints::unique('company_capabilities', 'primary', ['company_id', 'capability_id'], true);
        foreach ([
            ['business_profile_capabilities', 'profile_id', 'business_profiles'],
            ['business_profile_capabilities', 'capability_id', 'capabilities'],
            ['company_capabilities', 'company_id', 'companies'],
            ['company_capabilities', 'capability_id', 'capabilities'],
            ['company_capabilities', 'enabled_by', 'users'],
        ] as [$table, $column, $parent]) {
            MigrationConstraints::foreign($table, $table.'_'.$column.'_foreign', [$column], $parent);
        }
        (new CapabilitySeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('company_capabilities');
        Schema::dropIfExists('business_profile_capabilities');
        Schema::dropIfExists('business_profiles');
        Schema::dropIfExists('capabilities');
    }
};
