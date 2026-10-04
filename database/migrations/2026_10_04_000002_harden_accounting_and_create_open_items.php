<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function extensions(): array
    {
        return [
            'chart_of_accounts' => [
                'financial_report_group' => fn (Blueprint $t) => $t->string('financial_report_group', 100)->nullable(),
                'control_type' => fn (Blueprint $t) => $t->string('control_type', 10)->default('none'),
                'allow_manual_posting' => fn (Blueprint $t) => $t->boolean('allow_manual_posting')->default(true),
            ],
            'journal_entries' => [
                'branch_id' => fn (Blueprint $t) => $t->unsignedBigInteger('branch_id')->nullable(),
                'financial_year_id' => fn (Blueprint $t) => $t->unsignedBigInteger('financial_year_id')->nullable(),
                'document_series_id' => fn (Blueprint $t) => $t->unsignedBigInteger('document_series_id')->nullable(),
                'posting_key' => fn (Blueprint $t) => $t->string('posting_key', 150)->nullable(),
                'idempotency_key' => fn (Blueprint $t) => $t->string('idempotency_key', 150)->nullable(),
                'request_hash' => fn (Blueprint $t) => $t->string('request_hash', 64)->nullable(),
                'reversal_of_id' => fn (Blueprint $t) => $t->unsignedBigInteger('reversal_of_id')->nullable(),
                'posted_at' => fn (Blueprint $t) => $t->timestamp('posted_at')->nullable(),
                'void_reason' => fn (Blueprint $t) => $t->text('void_reason')->nullable(),
                'voucher_type' => fn (Blueprint $t) => $t->string('voucher_type', 20)->nullable(),
                'cheque_no' => fn (Blueprint $t) => $t->string('cheque_no', 100)->nullable(),
                'cheque_date' => fn (Blueprint $t) => $t->date('cheque_date')->nullable(),
            ],
            'accounts' => [
                'chart_of_account_id' => fn (Blueprint $t) => $t->unsignedBigInteger('chart_of_account_id')->nullable(),
            ],
        ];
    }

    public function up(): void
    {
        // Preflight all existing data before MySQL can commit any DDL. Never rewrite history.
        foreach (array_keys($this->extensions()) as $table) {
            MigrationConstraints::requireColumns($table, ['id', 'company_id']);
        }
        MigrationConstraints::requireColumns('semantic_account_mappings', ['id', 'company_id', 'semantic_role', 'account_id']);
        foreach (['chart_of_accounts' => 'code', 'semantic_account_mappings' => 'semantic_role'] as $table => $column) {
            if (DB::table($table)->select('company_id', $column)->groupBy('company_id', $column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException("Duplicate {$table} company keys require review before migration.");
            }
        }
        foreach (['account_open_items', 'account_allocations', 'accounting_period_events'] as $table) {
            if (Schema::hasTable($table)) {
                MigrationConstraints::requireColumns($table, $this->columns($table));
            }
        }
        foreach ($this->extensions() as $table => $columns) {
            foreach ($columns as $name => $add) {
                if (!Schema::hasColumn($table, $name)) {
                    Schema::table($table, $add);
                }
            }
        }
        foreach (['chart_of_accounts' => 'code', 'semantic_account_mappings' => 'semantic_role'] as $table => $column) {
            MigrationConstraints::unique($table, $table.'_company_'.$column.'_unique', ['company_id', $column]);
            $old = $table.'_'.$column.'_unique';
            if (Schema::hasIndex($table, $old)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($old));
            }
        }
        MigrationConstraints::unique('journal_entries', 'journal_company_posting_unique', ['company_id', 'posting_key']);
        MigrationConstraints::unique('journal_entries', 'journal_company_idempotency_unique', ['company_id', 'idempotency_key']);
        MigrationConstraints::unique('journal_entries', 'journal_reversal_unique', ['reversal_of_id']);
        MigrationConstraints::index('journal_entries', 'journal_company_period_index', ['company_id', 'financial_year_id', 'branch_id', 'entry_date']);
        MigrationConstraints::foreign('journal_entries', 'journal_reversal_fk', ['reversal_of_id'], 'journal_entries');
        MigrationConstraints::foreign('accounts', 'accounts_chart_fk', ['chart_of_account_id'], 'chart_of_accounts');

        if (!Schema::hasTable('account_open_items')) {
            Schema::create('account_open_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id');
                $t->unsignedBigInteger('branch_id');
                $t->unsignedBigInteger('financial_year_id');
                $t->unsignedBigInteger('journal_item_id');
                $t->unsignedBigInteger('journal_entry_id');
                $t->unsignedBigInteger('account_id');
                $t->string('party_type', 20)->nullable();
                $t->unsignedBigInteger('party_id')->nullable();
                $t->string('source_type', 100);
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('document_no', 100);
                $t->date('document_date');
                $t->date('due_date');
                $t->decimal('original_amount', 18, 4);
                $t->decimal('open_amount', 18, 4);
                $t->string('status', 20)->default('open');
                $t->string('reference_mode', 20)->default('new_reference');
                $t->timestamps();
            });
        }
        MigrationConstraints::unique('account_open_items', 'open_journal_item_unique', ['journal_item_id']);
        MigrationConstraints::index('account_open_items', 'open_company_party_index', ['company_id', 'party_type', 'party_id', 'account_id']);
        foreach (['journal_item_id' => 'journal_items', 'journal_entry_id' => 'journal_entries', 'account_id' => 'chart_of_accounts'] as $column => $parent) {
            MigrationConstraints::foreign('account_open_items', 'open_'.$column.'_fk', [$column], $parent);
        }
        if (!Schema::hasTable('account_allocations')) {
            Schema::create('account_allocations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id');
                $t->string('allocation_no', 100)->nullable();
                $t->string('idempotency_key', 150);
                $t->unsignedBigInteger('debit_open_item_id');
                $t->unsignedBigInteger('credit_open_item_id');
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->decimal('allocated_amount', 18, 4);
                $t->date('allocation_date');
                $t->unsignedBigInteger('reversal_of_id')->nullable();
                $t->text('reversal_reason')->nullable();
                $t->unsignedInteger('created_by');
                $t->timestamps();
            });
        }
        MigrationConstraints::unique('account_allocations', 'allocation_company_key_unique', ['company_id', 'idempotency_key']);
        MigrationConstraints::unique('account_allocations', 'allocation_reversal_unique', ['reversal_of_id']);
        foreach (['debit_open_item_id' => 'account_open_items', 'credit_open_item_id' => 'account_open_items', 'journal_entry_id' => 'journal_entries', 'reversal_of_id' => 'account_allocations'] as $column => $parent) {
            MigrationConstraints::foreign('account_allocations', 'allocation_'.$column.'_fk', [$column], $parent);
        }
        if (!Schema::hasTable('accounting_period_events')) {
            Schema::create('accounting_period_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id');
                $t->unsignedBigInteger('financial_year_id');
                $t->unsignedInteger('actor_id');
                $t->string('action', 20);
                $t->date('previous_lock_date')->nullable();
                $t->date('lock_date')->nullable();
                $t->string('reason', 500);
                $t->timestamp('created_at');
            });
        }
        MigrationConstraints::foreign('accounting_period_events', 'period_year_fk', ['financial_year_id'], 'fiscal_years');
    }

    private function columns(string $table): array
    {
        return match ($table) {
            'account_open_items' => ['id', 'company_id', 'branch_id', 'financial_year_id', 'journal_item_id', 'journal_entry_id', 'account_id', 'party_type', 'party_id', 'source_type', 'source_id', 'document_no', 'document_date', 'due_date', 'original_amount', 'open_amount', 'status', 'reference_mode', 'created_at', 'updated_at'],
            'account_allocations' => ['id', 'company_id', 'allocation_no', 'idempotency_key', 'debit_open_item_id', 'credit_open_item_id', 'journal_entry_id', 'allocated_amount', 'allocation_date', 'reversal_of_id', 'reversal_reason', 'created_by', 'created_at', 'updated_at'],
            'accounting_period_events' => ['id', 'company_id', 'financial_year_id', 'actor_id', 'action', 'previous_lock_date', 'lock_date', 'reason', 'created_at'],
        };
    }

    public function down(): void
    {
        // Restoring global uniqueness may be impossible after multi-company use. Fail before destructive DDL.
        foreach (['chart_of_accounts' => 'code', 'semantic_account_mappings' => 'semantic_role'] as $table => $column) {
            if (DB::table($table)->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Rollback requires review of company-specific account keys.');
            }
        }
        if (DB::table('account_open_items')->exists() || DB::table('account_allocations')->exists()
            || DB::table('accounting_period_events')->exists() || DB::table('journal_entries')->whereNotNull('posting_key')->exists()) {
            throw new RuntimeException('Accounting history exists; restore a reviewed backup instead of dropping Phase 5 records.');
        }
        Schema::dropIfExists('accounting_period_events');
        Schema::dropIfExists('account_allocations');
        Schema::dropIfExists('account_open_items');
        Schema::table('accounts', fn (Blueprint $t) => $t->dropForeign('accounts_chart_fk'));
        Schema::table('journal_entries', fn (Blueprint $t) => $t->dropForeign('journal_reversal_fk'));
        foreach (['journal_company_posting_unique', 'journal_company_idempotency_unique', 'journal_reversal_unique'] as $name) {
            Schema::table('journal_entries', fn (Blueprint $t) => $t->dropUnique($name));
        }
        Schema::table('journal_entries', fn (Blueprint $t) => $t->dropIndex('journal_company_period_index'));
        foreach (['chart_of_accounts' => 'code', 'semantic_account_mappings' => 'semantic_role'] as $table => $column) {
            MigrationConstraints::unique($table, $table.'_'.$column.'_unique', [$column]);
            Schema::table($table, fn (Blueprint $t) => $t->dropUnique($table.'_company_'.$column.'_unique'));
        }
        foreach ($this->extensions() as $table => $columns) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_keys($columns)));
        }
    }
};
