<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Activity entries belong to the company they were recorded in, so one company's owner never reads another's log. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs') && !Schema::hasColumn('activity_logs', 'company_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('user_id');
                $table->index(['company_id', 'id']);
            });
            // With a single company the old entries are unambiguously its own; with several they stay unassigned.
            if (Schema::hasTable('companies') && \Illuminate\Support\Facades\DB::table('companies')->count() === 1) {
                \Illuminate\Support\Facades\DB::table('activity_logs')->whereNull('company_id')
                    ->update(['company_id' => \Illuminate\Support\Facades\DB::table('companies')->value('id')]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'company_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropIndex(['company_id', 'id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
