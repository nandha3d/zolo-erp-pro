<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            'sales_quantity_schemes' => [['company_id', 'product_id', 'buy_qty', 'free_qty', 'valid_from', 'valid_to'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->unsignedInteger('product_id'); $t->string('name', 100); $t->decimal('buy_qty', 18, 4); $t->decimal('free_qty', 18, 4);
                $t->date('valid_from'); $t->date('valid_to'); $t->boolean('is_active')->default(true);
                $t->unsignedInteger('created_by'); $t->timestamps(); $t->index(['company_id', 'product_id']);
            }],
            'company_industry_settings' => [['company_id', 'profile_key', 'subtype', 'settings_json'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->string('profile_key', 40); $t->string('subtype', 40)->nullable(); $t->json('settings_json');
                $t->unsignedInteger('updated_by'); $t->timestamps(); $t->unique('company_id');
            }],
            'product_attribute_definitions' => [['company_id', 'key', 'label', 'type', 'enabled'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->string('key', 60); $t->string('label', 100); $t->string('type', 20); $t->boolean('enabled')->default(true);
                $t->string('profile_key', 40); $t->unique(['company_id', 'key']);
            }],
            'product_attribute_values' => [['company_id', 'product_id', 'definition_id', 'value_json'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->unsignedInteger('product_id'); $t->foreignId('definition_id')->constrained('product_attribute_definitions')->restrictOnDelete();
                $t->json('value_json'); $t->timestamps(); $t->unique(['product_id', 'definition_id']);
            }],
            'project_serial_reservations' => [['project_id', 'stock_identity_id', 'warehouse_id', 'status', 'details_json'], function (Blueprint $t) {
                $this->document($t);
                $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('stock_identity_id');
                $t->unsignedInteger('warehouse_id'); $t->string('status', 20)->default('allocated');
                $t->string('active_key', 100)->nullable()->unique(); $t->unsignedBigInteger('dispatch_movement_id')->nullable();
                $t->json('details_json');
            }],
            'project_installations' => [['project_id', 'reservation_id', 'stock_identity_id', 'status', 'installed_date', 'details_json'], function (Blueprint $t) {
                $this->document($t); $t->unsignedBigInteger('project_id');
                $t->foreignId('reservation_id')->constrained('project_serial_reservations')->restrictOnDelete();
                $t->unsignedBigInteger('stock_identity_id'); $t->string('status', 20)->default('installed');
                $t->date('installed_date'); $t->date('commissioned_date')->nullable();
                $t->unsignedBigInteger('replaces_installation_id')->nullable(); $t->json('details_json');
                $t->unique('reservation_id');
            }],
            'project_document_links' => [['company_id', 'project_id', 'source_type', 'source_id'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete(); $t->unsignedBigInteger('project_id');
                $t->string('source_type', 30); $t->unsignedBigInteger('source_id'); $t->unsignedInteger('linked_by');
                $t->timestamp('created_at'); $t->unique(['company_id', 'source_type', 'source_id']);
            }],
            'warranty_contracts' => [['company_id', 'installation_id', 'starts_on', 'ends_on', 'kind'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->foreignId('installation_id')->constrained('project_installations')->restrictOnDelete();
                $t->string('kind', 20); $t->date('starts_on'); $t->date('ends_on');
                $t->unsignedBigInteger('replaces_contract_id')->nullable(); $t->timestamp('created_at');
                $t->unique(['installation_id', 'kind']);
            }],
            'serial_service_events' => [['company_id', 'installation_id', 'event', 'business_date', 'notes'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->foreignId('installation_id')->constrained('project_installations')->restrictOnDelete();
                $t->string('event', 30); $t->date('business_date'); $t->text('notes'); $t->unsignedInteger('created_by'); $t->timestamp('created_at');
            }],
        ];
        foreach ($definitions as $table => [$columns]) if (Schema::hasTable($table)) MigrationConstraints::requireColumns($table, ['id', ...$columns]);
        foreach ($definitions as $table => [, $create]) if (!Schema::hasTable($table)) Schema::create($table, $create);
        // Extend the existing project master. Legacy rows remain unowned until reviewed backfill.
        foreach (['company_id', 'branch_id', 'financial_year_id', 'created_by', 'site_warehouse_id'] as $column) {
            if (!Schema::hasColumn('projects', $column)) Schema::table('projects', function (Blueprint $t) use ($column) {
                $column === 'site_warehouse_id' ? $t->unsignedInteger($column)->nullable() : $t->unsignedBigInteger($column)->nullable();
            });
        }
        if (!Schema::hasColumn('projects', 'site_json')) Schema::table('projects', fn (Blueprint $t) => $t->json('site_json')->nullable());
        if (!Schema::hasColumn('projects', 'posted_at')) Schema::table('projects', fn (Blueprint $t) => $t->timestamp('posted_at')->nullable());
        MigrationConstraints::index('projects', 'projects_company_branch_index', ['company_id', 'branch_id']);
        foreach (['computed_cbm', 'computed_cft'] as $column) if (!Schema::hasColumn('stock_dimensions', $column)) {
            Schema::table('stock_dimensions', fn (Blueprint $t) => $t->decimal($column, 18, 6)->nullable());
        }
        if (!Schema::hasColumn('stock_dimensions', 'formula_version')) Schema::table('stock_dimensions', fn (Blueprint $t) => $t->string('formula_version', 30)->nullable());
        // Refresh system presets only; existing company choices remain untouched.
        if (Schema::hasTable('business_profile_capabilities')) (new \Database\Seeders\CapabilitySeeder)->run();
    }

    private function document(Blueprint $t): void
    {
        $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
        $t->unsignedBigInteger('branch_id'); $t->unsignedBigInteger('financial_year_id');
        $t->unsignedInteger('created_by'); $t->timestamp('posted_at')->nullable(); $t->timestamps();
        $t->index(['company_id', 'branch_id']);
    }

    public function down(): void
    {
        throw new RuntimeException('Profile, dimensional and installed-serial history requires a reviewed forward rollback.');
    }
};
