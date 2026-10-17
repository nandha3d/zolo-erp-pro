<?php

namespace App\Support;

use App\Services\Platform\CompanyContext;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Rule;

/** Party masters are unique among the active rows of the current company only; blank values are never duplicates. */
final class CompanyUnique
{
    /** @return array<int, string|Unique> */
    public static function rules(string $table, ?int $ignore = null): array
    {
        $rule = Rule::unique($table)->ignore($ignore)->where(function ($query) {
            $query->where('is_active', 1);
            $context = app()->bound('request') ? request()->attributes->get(CompanyContext::class) : null;
            if ($context) $query->where('company_id', $context->companyId);
        });
        return ['nullable', 'max:255', $rule];
    }
}
