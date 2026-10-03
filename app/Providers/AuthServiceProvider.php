<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Super-admin & role-based permission resolution for SalePro
        Gate::before(function ($user, $ability) {
            if ($user->role_id == 1) {
                return true;
            }

            if ($user->role_id) {
                try {
                    $role = \Spatie\Permission\Models\Role::findById($user->role_id);
                    if ($role && $role->hasPermissionTo($ability)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    return null;
                }
            }
        });
    }
}
