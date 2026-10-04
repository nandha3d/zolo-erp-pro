<?php

namespace App\Services\Accounting;

use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Support\Facades\DB;

final class AccountingAccess
{
    public static function assert(CompanyContext $context, string $permission = 'accounting.voucher.post'): void
    {
        $resolver = app(CompanyContextResolver::class);
        $resolver->forActor($context);
        $actor = auth()->id();
        if ($resolver->canManageFinancialYears($actor, $context->companyId)) {
            return;
        }
        $role = DB::table('company_user')->where('company_id', $context->companyId)->where('user_id', $actor)->value('role_id_override')
            ?? auth()->user()->role_id;
        $allowed = DB::table('roles')->where('id', $role)->where('is_active', true)->exists()
            && DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_id', $role)->where('permissions.name', $permission)->exists();
        if (!$allowed) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Accounting permission required.');
        }
    }
}
