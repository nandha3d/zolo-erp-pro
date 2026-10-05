<?php

namespace App\Models\Concerns;

use App\Services\Platform\BranchAccess;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Records whose company and branch are derived from a warehouse or commercial parent. */
trait ScopesBranchQueries
{
    public static function bootScopesBranchQueries(): void
    {
        static::addGlobalScope('request_branch', function (Builder $query) {
            if ($context = request()->attributes->get(CompanyContext::class)) {
                app(BranchAccess::class)->scope($query->getQuery(), $query->getModel()->getTable(), $query->getModel()->getTable(), $context);
            }
        });
        static::saving(function (Model $model) {
            if ($context = request()->attributes->get(CompanyContext::class)) {
                app(BranchAccess::class)->validateReferences($model->getTable(), $model->getAttributes(), $context);
            }
        });
    }
}
