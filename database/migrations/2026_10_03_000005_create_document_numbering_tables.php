<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\MigrationConstraints;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_series')) {
            Schema::create('document_series', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('company_branches')->restrictOnDelete();
                $table->foreignId('financial_year_id')->constrained('fiscal_years')->restrictOnDelete();
                $table->string('document_type', 50);
                $table->string('code', 50);
                $table->string('prefix', 100)->default('');
                $table->string('suffix', 50)->default('');
                $table->unsignedBigInteger('next_number')->default(1);
                $table->unsignedTinyInteger('padding')->default(6);
                $table->string('reset_policy', 20)->default('financial_year');
                $table->boolean('is_default')->default(true);
                // NULL allows multiple nondefault series but only one default per scope.
                $table->unsignedTinyInteger('default_slot')->nullable()->storedAs('case when is_default = 1 then 1 else null end');
                $table->unique(['company_id', 'branch_id', 'financial_year_id', 'document_type', 'code'], 'document_series_scope_code');
                $table->unique(['company_id', 'branch_id', 'financial_year_id', 'document_type', 'default_slot'], 'document_series_scope_default');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('document_number_reservations')) {
            Schema::create('document_number_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('series_id')->constrained('document_series')->restrictOnDelete();
                $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $table->string('document_type', 50);
                $table->unsignedBigInteger('reserved_number');
                $table->string('formatted_number', 191);
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('status', 20)->default('reserved');
                $table->unique(['series_id', 'reserved_number'], 'reservation_series_number');
                $table->unique(['company_id', 'document_type', 'formatted_number'], 'reservation_company_document_number');
                $table->unique(['source_type', 'source_id'], 'reservation_document_source');
                $table->timestamps();
            });
        }
        foreach ([
            ['document_series', 'document_series_scope_code', ['company_id', 'branch_id', 'financial_year_id', 'document_type', 'code']],
            ['document_series', 'document_series_scope_default', ['company_id', 'branch_id', 'financial_year_id', 'document_type', 'default_slot']],
            ['document_number_reservations', 'reservation_series_number', ['series_id', 'reserved_number']],
            ['document_number_reservations', 'reservation_company_document_number', ['company_id', 'document_type', 'formatted_number']],
            ['document_number_reservations', 'reservation_document_source', ['source_type', 'source_id']],
        ] as [$table, $name, $columns]) {
            MigrationConstraints::unique($table, $name, $columns);
        }
        foreach ([
            ['document_series', 'company_id', 'companies'],
            ['document_series', 'branch_id', 'company_branches'],
            ['document_series', 'financial_year_id', 'fiscal_years'],
            ['document_number_reservations', 'series_id', 'document_series'],
            ['document_number_reservations', 'company_id', 'companies'],
        ] as [$table, $column, $parent]) {
            MigrationConstraints::foreign($table, $table.'_'.$column.'_foreign', [$column], $parent);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_reservations');
        Schema::dropIfExists('document_series');
    }
};
