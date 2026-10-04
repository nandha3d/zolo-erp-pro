<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert(['name' => 'accounting.reports.view', 'guard_name' => 'web'], ['updated_at' => now()]);
        }
        if (!Schema::hasTable('company_setup_audits')) {
            Schema::create('company_setup_audits', function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->unsignedInteger('actor_id'); $t->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
                $t->string('action', 50); $t->json('before_json'); $t->json('after_json'); $t->timestamp('created_at');
                $t->index(['company_id', 'created_at']);
            });
        }
    }
    public function down(): void { throw new RuntimeException('Setup audit history requires a reviewed forward rollback.'); }
};
