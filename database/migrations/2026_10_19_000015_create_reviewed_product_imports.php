<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_imports')) return;
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('company_id'); $table->unsignedBigInteger('branch_id');
            $table->unsignedInteger('user_id'); $table->string('status', 20)->default('preview');
            $table->string('source_hash', 64); $table->longText('rows_json'); $table->longText('results_json')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('committed_at')->nullable(); $table->timestamps();
            $table->index(['company_id', 'user_id', 'id']);
        });
    }
    public function down(): void
    {
        if (Schema::hasTable('product_imports') && \Illuminate\Support\Facades\DB::table('product_imports')->exists()) {
            throw new RuntimeException('Retain reviewed import history.');
        }
        Schema::dropIfExists('product_imports');
    }
};
