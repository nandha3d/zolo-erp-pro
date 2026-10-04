<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['import_batches' => ['id', 'company_id', 'branch_id', 'financial_year_id', 'actor_id', 'batch_key', 'source_name', 'business_date', 'status', 'source_hash', 'expected_json', 'validation_errors', 'summary_json', 'validated_at', 'committed_at', 'created_at', 'updated_at'], 'import_rows' => ['id', 'batch_id', 'source_key', 'kind', 'payload_json'], 'import_mappings' => ['id', 'batch_id', 'entity', 'source_key', 'target_id']] as $table => $columns) {
            if (Schema::hasTable($table)) \App\Support\MigrationConstraints::requireColumns($table, $columns);
        }
        if (!Schema::hasTable('import_batches')) Schema::create('import_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained('company_branches')->restrictOnDelete();
            $t->foreignId('financial_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $t->unsignedInteger('actor_id'); $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $t->string('batch_key', 100); $t->string('source_name', 100); $t->date('business_date');
            $t->string('status', 20)->default('staged'); $t->char('source_hash', 64);
            $t->json('expected_json'); $t->json('validation_errors')->nullable(); $t->json('summary_json')->nullable();
            $t->timestamp('validated_at')->nullable(); $t->timestamp('committed_at')->nullable(); $t->timestamps();
            $t->unique(['company_id', 'batch_key']);
        });
        if (!Schema::hasTable('import_rows')) Schema::create('import_rows', function (Blueprint $t) {
            $t->id(); $t->foreignId('batch_id')->constrained('import_batches')->restrictOnDelete();
            $t->string('source_key', 100); $t->string('kind', 20); $t->json('payload_json');
            $t->unique(['batch_id', 'source_key']);
        });
        if (!Schema::hasTable('import_mappings')) Schema::create('import_mappings', function (Blueprint $t) {
            $t->id(); $t->foreignId('batch_id')->constrained('import_batches')->restrictOnDelete();
            $t->string('entity', 20); $t->string('source_key', 100); $t->unsignedBigInteger('target_id');
            $t->unique(['batch_id', 'entity', 'source_key']);
        });
        \App\Support\MigrationConstraints::unique('import_batches', 'import_batches_company_id_batch_key_unique', ['company_id', 'batch_key']);
        \App\Support\MigrationConstraints::unique('import_rows', 'import_rows_batch_id_source_key_unique', ['batch_id', 'source_key']);
        \App\Support\MigrationConstraints::unique('import_mappings', 'import_mappings_batch_id_entity_source_key_unique', ['batch_id', 'entity', 'source_key']);
        foreach (['company_id' => 'companies', 'branch_id' => 'company_branches', 'financial_year_id' => 'fiscal_years', 'actor_id' => 'users'] as $column => $parent) {
            \App\Support\MigrationConstraints::foreign('import_batches', 'import_batches_'.$column.'_foreign', [$column], $parent);
        }
        foreach (['import_rows', 'import_mappings'] as $table) \App\Support\MigrationConstraints::foreign($table, $table.'_batch_id_foreign', ['batch_id'], 'import_batches');
    }
    public function down(): void { throw new RuntimeException('Import evidence and posted openings require a reviewed forward rollback.'); }
};
