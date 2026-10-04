<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\MigrationConstraints;

return new class extends Migration {
    public function up(): void
    {
        foreach (['tax_registrations' => ['company_id', 'branch_id', 'gstin', 'legal_name', 'trade_name', 'state_code', 'address', 'registration_type', 'status', 'effective_from', 'effective_to', 'verified_at', 'created_at', 'updated_at'],
            'party_tax_profiles' => ['company_id', 'party_type', 'party_id', 'gstin', 'state_code', 'registration_type', 'verified_at', 'verification_json', 'created_at', 'updated_at'],
            'tax_categories' => ['company_id', 'name', 'supply_type', 'input_credit_allowed', 'created_at', 'updated_at'],
            'tax_rates' => ['company_id', 'tax_category_id', 'rate', 'cess_rate', 'effective_from', 'effective_to', 'created_at', 'updated_at'],
            'hsn_sac_codes' => ['company_id', 'code', 'kind', 'description', 'created_at', 'updated_at'],
            'gst_lookup_audits' => ['company_id', 'user_id', 'gstin', 'status', 'failure_category', 'created_at'],
            'gst_transaction_projections' => ['company_id', 'branch_id', 'financial_year_id', 'source_type', 'source_id', 'document_no', 'document_date', 'registration_gstin', 'version', 'snapshot_json', 'created_at']] as $table => $columns) {
            if (Schema::hasTable($table)) MigrationConstraints::requireColumns($table, ['id', ...$columns]);
        }
        foreach (['products', 'sales', 'purchases', 'product_sales', 'product_purchases'] as $table) MigrationConstraints::requireColumns($table, ['id', 'company_id']);
        if (!Schema::hasTable('tax_registrations')) Schema::create('tax_registrations', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('branch_id'); $t->string('gstin', 15); $t->string('legal_name');
            $t->string('trade_name')->nullable(); $t->string('state_code', 2);
            $t->text('address')->nullable();
            $t->string('registration_type', 20)->default('regular'); $t->string('status', 20)->default('active');
            $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamp('verified_at')->nullable();
            $t->timestamps(); $t->unique(['company_id', 'gstin', 'effective_from'], 'tax_registration_period');
            $t->foreign(['branch_id', 'company_id'])->references(['id', 'company_id'])->on('company_branches')->restrictOnDelete();
        });
        if (!Schema::hasTable('party_tax_profiles')) Schema::create('party_tax_profiles', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('party_type', 20); $t->unsignedInteger('party_id'); $t->string('gstin', 15)->nullable();
            $t->string('state_code', 2); $t->string('registration_type', 20)->default('unregistered');
            $t->timestamp('verified_at')->nullable(); $t->json('verification_json')->nullable();
            $t->timestamps(); $t->unique(['company_id', 'party_type', 'party_id'], 'party_tax_owner');
        });
        if (!Schema::hasTable('tax_categories')) Schema::create('tax_categories', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name'); $t->string('supply_type', 20)->default('taxable');
            $t->boolean('input_credit_allowed')->default(false); $t->timestamps();
            $t->unique(['id', 'company_id']); $t->unique(['company_id', 'name']);
        });
        if (!Schema::hasTable('tax_rates')) Schema::create('tax_rates', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('tax_category_id');
            $t->decimal('rate', 8, 4); $t->decimal('cess_rate', 8, 4)->default(0);
            $t->date('effective_from'); $t->date('effective_to')->nullable(); $t->timestamps();
            $t->foreign(['tax_category_id', 'company_id'])->references(['id', 'company_id'])->on('tax_categories')->restrictOnDelete();
            $t->index(['company_id', 'tax_category_id', 'effective_from'], 'tax_rate_period');
        });
        if (!Schema::hasTable('hsn_sac_codes')) Schema::create('hsn_sac_codes', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 8); $t->string('kind', 10); $t->string('description');
            $t->timestamps(); $t->unique(['company_id', 'code']);
        });
        if (!Schema::hasTable('gst_lookup_audits')) Schema::create('gst_lookup_audits', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('user_id'); $t->string('gstin', 15); $t->string('status', 30);
            $t->string('failure_category', 40)->nullable(); $t->timestamp('created_at');
        });
        if (!Schema::hasTable('gst_transaction_projections')) Schema::create('gst_transaction_projections', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('branch_id');
            $t->unsignedInteger('financial_year_id'); $t->string('source_type', 30); $t->unsignedBigInteger('source_id');
            $t->string('document_no', 100); $t->date('document_date'); $t->string('registration_gstin', 15);
            $t->string('version', 40); $t->json('snapshot_json'); $t->timestamp('created_at');
            $t->unique(['company_id', 'source_type', 'source_id'], 'gst_projection_source');
            $t->index(['company_id', 'branch_id', 'document_date'], 'gst_projection_period');
        });
        foreach (['tax_registrations', 'party_tax_profiles', 'tax_categories', 'hsn_sac_codes', 'gst_lookup_audits'] as $table) {
            MigrationConstraints::foreign($table, $table.'_company_id_foreign', ['company_id'], 'companies');
        }
        MigrationConstraints::foreign('tax_registrations', 'tax_registrations_branch_id_company_id_foreign', ['branch_id', 'company_id'], 'company_branches', ['id', 'company_id']);
        MigrationConstraints::foreign('tax_rates', 'tax_rates_tax_category_id_company_id_foreign', ['tax_category_id', 'company_id'], 'tax_categories', ['id', 'company_id']);
        foreach ([['tax_registrations', 'tax_registration_period', ['company_id', 'gstin', 'effective_from']],
            ['party_tax_profiles', 'party_tax_owner', ['company_id', 'party_type', 'party_id']],
            ['tax_categories', 'tax_categories_id_company_id_unique', ['id', 'company_id']],
            ['tax_categories', 'tax_categories_company_id_name_unique', ['company_id', 'name']],
            ['hsn_sac_codes', 'hsn_sac_codes_company_id_code_unique', ['company_id', 'code']],
            ['gst_transaction_projections', 'gst_projection_source', ['company_id', 'source_type', 'source_id']]] as [$table, $name, $columns]) MigrationConstraints::unique($table, $name, $columns);
        MigrationConstraints::index('tax_rates', 'tax_rate_period', ['company_id', 'tax_category_id', 'effective_from']);
        MigrationConstraints::index('gst_transaction_projections', 'gst_projection_period', ['company_id', 'branch_id', 'document_date']);
        if (!Schema::hasColumn('products', 'tax_category_id')) Schema::table('products', function (Blueprint $t) { $t->unsignedBigInteger('tax_category_id')->nullable(); });
        MigrationConstraints::index('products', 'products_tax_category_id_index', ['tax_category_id']);
        foreach (['sales', 'purchases', 'product_sales', 'product_purchases'] as $table) {
            if (!Schema::hasColumn($table, 'tax_snapshot_json')) Schema::table($table, fn (Blueprint $t) => $t->json('tax_snapshot_json')->nullable());
        }
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('gst_transaction_projections')->exists()) {
            throw new RuntimeException('Export and review posted tax history before rolling back compliance.');
        }
        foreach (['sales', 'purchases', 'product_sales', 'product_purchases'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('tax_snapshot_json'));
        }
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('tax_category_id'));
        foreach (['gst_transaction_projections', 'gst_lookup_audits', 'hsn_sac_codes', 'tax_rates', 'tax_categories', 'party_tax_profiles', 'tax_registrations'] as $table) Schema::dropIfExists($table);
    }
};
