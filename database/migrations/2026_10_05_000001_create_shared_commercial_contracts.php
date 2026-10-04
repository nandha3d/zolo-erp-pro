<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function extensions(): array
    {
        return [
            'sales' => ['branch_id', 'financial_year_id', 'posted_at', 'reversed_at', 'reversal_reason', 'replaces_id', 'attributes_json'],
            'purchases' => ['branch_id', 'financial_year_id', 'posted_at', 'reversed_at', 'reversal_reason', 'replaces_id', 'attributes_json'],
            'product_sales' => ['stock_details_json'],
            'product_purchases' => ['stock_details_json', 'valuation_amount'],
            'customers' => ['credit_days', 'search_alias'],
            'suppliers' => ['search_alias'],
            'products' => ['hsn_code'],
        ];
    }

    public function up(): void
    {
        foreach ($this->extensions() as $table => $columns) {
            MigrationConstraints::requireColumns($table, ['id', 'company_id']);
        }
        foreach (['idempotency_keys' => ['company_id', 'key', 'request_hash', 'response_type', 'response_ref'],
            'sale_drafts' => ['company_id', 'branch_id', 'financial_year_id', 'user_id', 'kind', 'version', 'payload_json'],
            'commercial_audit_events' => ['company_id', 'branch_id', 'user_id', 'event', 'source_type', 'source_id', 'details_json', 'created_at']] as $table => $columns) {
            if (Schema::hasTable($table)) {
                MigrationConstraints::requireColumns($table, ['id', ...$columns, ...($table === 'commercial_audit_events' ? [] : ['created_at', 'updated_at'])]);
            }
        }
        if (Schema::hasTable('taxes') && !Schema::hasColumn('taxes', 'company_id')) {
            Schema::table('taxes', fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable()->index());
        }
        foreach ($this->extensions() as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($column) {
                    match ($column) {
                        'branch_id', 'financial_year_id', 'replaces_id' => $t->unsignedBigInteger($column)->nullable(),
                        'posted_at', 'reversed_at' => $t->timestamp($column)->nullable(),
                        'attributes_json', 'stock_details_json' => $t->json($column)->nullable(),
                        'valuation_amount' => $t->decimal($column, 18, 4)->nullable(),
                        'credit_days' => $t->unsignedInteger($column)->nullable(),
                        'reversal_reason' => $t->string($column, 500)->nullable(),
                        default => $t->string($column)->nullable(),
                    };
                });
            }
        }
        if (!Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id');
                $t->string('key', 150);
                $t->string('request_hash', 64);
                $t->string('response_type', 30);
                $t->unsignedBigInteger('response_ref');
                $t->timestamps();
            });
        }
        MigrationConstraints::unique('idempotency_keys', 'commercial_company_key_unique', ['company_id', 'key']);
        if (!Schema::hasTable('sale_drafts')) {
            Schema::create('sale_drafts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id');
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('financial_year_id');
                $t->unsignedInteger('user_id');
                $t->string('kind', 10);
                $t->unsignedInteger('version')->default(1);
                $t->json('payload_json');
                $t->timestamps();
            });
        }
        MigrationConstraints::index('sale_drafts', 'commercial_draft_owner_index', ['company_id', 'branch_id', 'user_id', 'kind']);
        if (!Schema::hasTable('commercial_audit_events')) {
            Schema::create('commercial_audit_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id');
                $t->unsignedBigInteger('branch_id');
                $t->unsignedInteger('user_id');
                $t->string('event', 40);
                $t->string('source_type', 10);
                $t->unsignedBigInteger('source_id');
                $t->json('details_json');
                $t->timestamp('created_at');
            });
        }
        MigrationConstraints::index('commercial_audit_events', 'commercial_audit_source_index', ['company_id', 'source_type', 'source_id']);
        foreach (['sales', 'purchases'] as $table) {
            MigrationConstraints::index($table, $table.'_commercial_context_index', ['company_id', 'branch_id', 'financial_year_id']);
        }
        foreach (['products' => ['code', 'name'], 'customers' => ['name', 'city', 'phone_number', 'search_alias'],
            'suppliers' => ['name', 'city', 'phone_number', 'search_alias']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    MigrationConstraints::index($table, $table.'_commercial_'.$column.'_index', ['company_id', $column]);
                }
            }
        }
        if (Schema::hasTable('permissions')) {
            \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert(
                ['name' => 'sales.override_credit', 'guard_name' => 'web'], ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Posted metadata, retries and saved drafts are business history; never erase them on rollback.
        throw new RuntimeException('Commercial rollback requires a reviewed data-preserving forward migration.');
    }
};
