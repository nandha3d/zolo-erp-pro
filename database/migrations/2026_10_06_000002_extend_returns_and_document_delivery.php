<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\MigrationConstraints;

return new class extends Migration {
    private function extensions(): array
    {
        $headers = ['branch_id', 'financial_year_id', 'note_type', 'adjustment_type', 'status', 'posted_at', 'idempotency_key', 'request_hash', 'approved_by', 'approved_at', 'attributes_json', 'tax_snapshot_json', 'document_snapshot_json'];
        $lines = ['source_line_id', 'disposition', 'stock_details_json', 'tax_snapshot_json', 'valuation_amount'];
        $operations = ['company_id', 'branch_id', 'financial_year_id', 'idempotency_key', 'request_hash', 'posted_at'];
        return ['sales' => ['document_snapshot_json'], 'purchases' => ['document_snapshot_json'],
            'returns' => $headers, 'return_purchases' => $headers, 'product_returns' => $lines, 'purchase_product_return' => $lines,
            'warehouses' => ['is_quarantine'], 'stock_identities' => ['warranty_state', 'return_inspected_at'],
            'damage_stocks' => [...$operations, 'stock_details_json'], 'exchanges' => [...$operations, 'return_id', 'replacement_sale_id']];
    }

    public function up(): void
    {
        foreach ($this->extensions() as $table => $columns) MigrationConstraints::requireColumns($table, ['id']);
        foreach (['returns', 'return_purchases', 'product_returns', 'purchase_product_return'] as $table) MigrationConstraints::requireColumns($table, ['company_id']);
        foreach (['print_profiles' => ['company_id', 'document_type', 'name', 'format', 'paper_width', 'height_lines', 'template_key', 'copies_json', 'printer_id', 'settings_json', 'created_at', 'updated_at'],
            'document_dispatch_logs' => ['company_id', 'branch_id', 'financial_year_id', 'document_type', 'document_id', 'channel', 'recipient', 'template', 'idempotency_key', 'request_hash', 'status', 'provider_message_id', 'attempts', 'attempted_at', 'failure_category', 'created_by', 'payload_json', 'created_at', 'updated_at'],
            'document_channels' => ['company_id', 'channel', 'configuration_encrypted', 'is_active', 'created_at', 'updated_at']] as $table => $columns) {
            if (Schema::hasTable($table)) MigrationConstraints::requireColumns($table, ['id', ...$columns]);
        }
        foreach ($this->extensions() as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) continue;
                Schema::table($table, function (Blueprint $t) use ($column) {
                    match ($column) {
                        'company_id', 'branch_id' => $t->unsignedBigInteger($column)->nullable(),
                        'financial_year_id', 'source_line_id', 'return_id', 'replacement_sale_id', 'approved_by' => $t->unsignedInteger($column)->nullable(),
                        'posted_at', 'approved_at', 'return_inspected_at' => $t->timestamp($column)->nullable(),
                        'document_snapshot_json', 'tax_snapshot_json', 'attributes_json', 'stock_details_json' => $t->json($column)->nullable(),
                        'valuation_amount' => $t->decimal($column, 18, 4)->nullable(),
                        'is_quarantine' => $t->boolean($column)->default(false),
                        'idempotency_key' => $t->string($column, 150)->nullable(),
                        'request_hash' => $t->string($column, 64)->nullable(),
                        'note_type', 'status' => $t->string($column, 20)->nullable(),
                        default => $t->string($column, 25)->nullable(),
                    };
                });
            }
        }
        foreach (['returns', 'return_purchases', 'damage_stocks', 'exchanges'] as $table) {
            MigrationConstraints::unique($table, $table.'_retry', ['company_id', 'idempotency_key']);
            MigrationConstraints::index($table, $table.'_context', ['company_id', 'branch_id', 'financial_year_id']);
        }
        foreach (['product_returns', 'purchase_product_return'] as $table) MigrationConstraints::index($table, $table.'_source_line_id_index', ['source_line_id']);
        Schema::table('damage_stocks', function (Blueprint $t) {
            $t->decimal('qty', 18, 4)->change(); $t->decimal('unit_cost', 18, 6)->default(0)->change(); $t->decimal('total_loss', 18, 4)->default(0)->change();
        });
        Schema::table('exchanges', function (Blueprint $t) {
            foreach (['returned_total', 'exchanged_total', 'difference_amount'] as $column) $t->decimal($column, 18, 4)->default(0)->change();
        });
        if (!Schema::hasTable('print_profiles')) Schema::create('print_profiles', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('document_type', 30); $t->string('name'); $t->string('format', 20);
            $t->unsignedInteger('paper_width')->nullable(); $t->unsignedInteger('height_lines')->nullable();
            $t->string('template_key', 50)->default('commercial'); $t->json('copies_json')->nullable();
            $t->unsignedInteger('printer_id')->nullable(); $t->json('settings_json')->nullable(); $t->timestamps();
            $t->unique(['company_id', 'document_type', 'name'], 'print_profile_owner');
        });
        if (!Schema::hasTable('document_dispatch_logs')) Schema::create('document_dispatch_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('branch_id'); $t->unsignedInteger('financial_year_id');
            $t->string('document_type', 30); $t->unsignedBigInteger('document_id');
            $t->string('channel', 20); $t->string('recipient'); $t->string('template', 50);
            $t->string('idempotency_key', 150); $t->string('request_hash', 64);
            $t->string('status', 25)->default('pending'); $t->string('provider_message_id')->nullable();
            $t->unsignedInteger('attempts')->default(0); $t->timestamp('attempted_at')->nullable();
            $t->string('failure_category', 40)->nullable(); $t->unsignedInteger('created_by');
            $t->json('payload_json'); $t->timestamps(); $t->unique(['company_id', 'idempotency_key'], 'dispatch_retry');
            $t->index(['status', 'attempted_at'], 'dispatch_pending');
        });
        if (!Schema::hasTable('document_channels')) Schema::create('document_channels', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('channel', 20); $t->text('configuration_encrypted'); $t->boolean('is_active')->default(false);
            $t->timestamps(); $t->unique(['company_id', 'channel']);
        });
        foreach (['print_profiles', 'document_dispatch_logs', 'document_channels'] as $table) MigrationConstraints::foreign($table, $table.'_company_id_foreign', ['company_id'], 'companies');
        MigrationConstraints::unique('print_profiles', 'print_profile_owner', ['company_id', 'document_type', 'name']);
        MigrationConstraints::unique('document_dispatch_logs', 'dispatch_retry', ['company_id', 'idempotency_key']);
        MigrationConstraints::index('document_dispatch_logs', 'dispatch_pending', ['status', 'attempted_at']);
        MigrationConstraints::unique('document_channels', 'document_channels_company_id_channel_unique', ['company_id', 'channel']);
    }

    public function down(): void
    {
        foreach (['sales', 'purchases', 'returns', 'return_purchases'] as $table) {
            if (DB::table($table)->whereNotNull('document_snapshot_json')->exists()) throw new RuntimeException('Saved document history prevents rollback. Restore a reviewed backup instead.');
        }
        foreach (['returns', 'return_purchases'] as $table) {
            if (DB::table($table)->whereNotNull('note_type')->exists()) throw new RuntimeException('Pending or posted note history prevents rollback.');
        }
        foreach (['returns', 'return_purchases', 'damage_stocks', 'exchanges'] as $table) {
            if (DB::table($table)->whereNotNull('posted_at')->exists()) throw new RuntimeException('Posted adjustment history prevents rollback. Restore a reviewed backup instead.');
        }
        if (DB::table('document_dispatch_logs')->exists()) throw new RuntimeException('Export dispatch history before rollback.');
        Schema::dropIfExists('document_channels');
        Schema::dropIfExists('document_dispatch_logs'); Schema::dropIfExists('print_profiles');
        foreach (['sales', 'purchases', 'returns', 'return_purchases'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('document_snapshot_json'));
        }
        Schema::table('exchanges', fn (Blueprint $t) => $t->dropColumn(['return_id', 'replacement_sale_id']));
        Schema::table('damage_stocks', fn (Blueprint $t) => $t->dropColumn('stock_details_json'));
        foreach (['damage_stocks', 'exchanges'] as $table) {
            Schema::table($table, function (Blueprint $t) { $t->dropUnique($t->getTable().'_retry'); $t->dropIndex($t->getTable().'_context'); $t->dropColumn(['branch_id', 'financial_year_id', 'idempotency_key', 'request_hash', 'posted_at']); });
        }
        Schema::table('warehouses', fn (Blueprint $t) => $t->dropColumn('is_quarantine'));
        Schema::table('stock_identities', fn (Blueprint $t) => $t->dropColumn(['warranty_state', 'return_inspected_at']));
        foreach (['product_returns', 'purchase_product_return'] as $table) {
            Schema::table($table, function (Blueprint $t) { $t->dropIndex($t->getTable().'_source_line_id_index'); $t->dropColumn(['source_line_id', 'disposition', 'stock_details_json', 'tax_snapshot_json', 'valuation_amount']); });
        }
        foreach (['returns', 'return_purchases'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique($t->getTable().'_retry'); $t->dropIndex($t->getTable().'_context');
                $t->dropColumn(['branch_id', 'financial_year_id', 'note_type', 'adjustment_type', 'status', 'posted_at', 'idempotency_key', 'request_hash', 'approved_by', 'approved_at', 'attributes_json', 'tax_snapshot_json']);
            });
        }
    }
};
