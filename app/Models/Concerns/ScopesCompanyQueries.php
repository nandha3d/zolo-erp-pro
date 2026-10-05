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
 * setup, backfill and administrative paths keep their explicit behaviour. Raw DB::table() reads are not covered.
 */
trait ScopesCompanyQueries
{
    public static function bootScopesCompanyQueries(): void
    {
        static::addGlobalScope('request_company', function (Builder $query) {
            if ($context = static::requestCompanyContext()) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $context->companyId);
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
        });
    }

    public static function requestCompanyContext(): ?CompanyContext
    {
        return app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null;
    }

    public function scopeForCompany(Builder $query, CompanyContext $context): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('company_id'), $context->companyId);
    }
}
