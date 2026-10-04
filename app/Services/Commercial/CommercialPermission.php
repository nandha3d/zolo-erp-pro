<?php

namespace App\Services\Commercial;

use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CommercialPermission
{
    public function assert(string $permission, CompanyContext $context, int $actor): void
    {
        if (!$this->allows($permission, $context, $actor)) throw new AuthorizationException('Permission required: '.$permission);
    }

    public function allows(string $permission, CompanyContext $context, int $actor): bool
    {
        $resolver = app(CompanyContextResolver::class);
        $resolver->forActor($context, $actor);
        if ($resolver->canManageFinancialYears($actor, $context->companyId)) {
            return true;
        }
        $role = DB::table('company_user')->where('company_id', $context->companyId)->where('user_id', $actor)->value('role_id_override')
            ?? DB::table('users')->where('id', $actor)->value('role_id');
        $allowed = DB::table('permissions')->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('roles.is_active', true)->where('roles.id', $role)->where('permissions.name', $permission)->exists();
        return $allowed;
    }
}
