<?php

namespace App\Models\Concerns;

use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait ScopesCompanyQueries
{
    public function scopeForCompany(Builder $query, CompanyContext $context): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('company_id'), $context->companyId);
    }
}
