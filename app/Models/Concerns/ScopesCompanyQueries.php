<?php

namespace App\Models\Concerns;

use App\Services\Platform\CompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit company scope plus request-context isolation for legacy readers and writers.
 *
 * While an authorized request carries a CompanyContext (set by the company middleware), every Eloquent read of a
 * company-owned model is limited to that company and every new row is stamped with it; a row that names another
 * company is refused. Without a request context (installer, console, tests building fixtures) nothing changes, so
 * setup, backfill and administrative paths keep their explicit behaviour. CompanyScopedBuilder covers raw reads.
 */
trait ScopesCompanyQueries
{
    public static function bootScopesCompanyQueries(): void
    {
        static::addGlobalScope('request_company', function (Builder $query) {
            if ($context = static::requestCompanyContext()) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $context->companyId);
                app(\App\Services\Platform\BranchAccess::class)->scope($query->getQuery(), $query->getModel()->getTable(), $query->getModel()->getTable(), $context);
            }
        });
        static::creating(function (Model $model) {
            if (!$context = static::requestCompanyContext()) {
                return;
            }
            if ($model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', $context->companyId);
            } elseif ((int) $model->getAttribute('company_id') !== $context->companyId) {
                throw new AuthorizationException('The record belongs to another company.');
            }
            if ($model->getTable() === 'warehouses' && $model->getAttribute('branch_id') === null) {
                $model->setAttribute('branch_id', $context->branchId);
            }
        });
        static::saving(function (Model $model) {
            if ($context = static::requestCompanyContext()) {
                if (($model->exists || $model->getAttribute('company_id') !== null) && (int) $model->getAttribute('company_id') !== $context->companyId) {
                    throw new AuthorizationException('The record belongs to another company.');
                }
                app(\App\Services\Platform\BranchAccess::class)->validateReferences($model->getTable(), $model->getAttributes(), $context);
            }
        });
    }

    public static function requestCompanyContext(): ?CompanyContext
    {
        return app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null;
    }

    /** Mass inserts bypass the creating hook; stamp the request company onto each row. */
    public static function withRequestCompany(array $rows): array
    {
        if (!$context = static::requestCompanyContext()) {
            return $rows;
        }

        return array_map(fn (array $row) => $row + ['company_id' => $context->companyId], $rows);
    }

    public function scopeForCompany(Builder $query, CompanyContext $context): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('company_id'), $context->companyId);
    }
}
