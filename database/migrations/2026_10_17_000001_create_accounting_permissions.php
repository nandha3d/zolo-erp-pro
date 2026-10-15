<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The accounting screens check accounting.reports.view, accounting.voucher.post and accounting.journal.reverse.
 * Only the first existed, so a non-admin accountant could never be granted vouchers or reversals.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['accounting.reports.view', 'accounting.voucher.post', 'accounting.journal.reverse'];

    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(['name' => $permission, 'guard_name' => 'web'], ['updated_at' => now()]);
        }
        app()->bound('cache') && app('cache')->forget('spatie.permission.cache');
    }

    public function down(): void
    {
        // Permissions may already be granted to roles; keep them.
    }
};
