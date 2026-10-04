<?php

namespace App\Services\Commercial;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Platform\CompanyContext;

class PartyQueryService
{
    public function search(string $kind, string $term, CompanyContext $context): array
    {
        $model = $kind === 'sale' ? Customer::class : Supplier::class;
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));
        return $model::forCompany($context)->where('is_active', true)->where(function ($q) use ($term) {
            $q->where('name', 'like', $term.'%')->orWhere('search_alias', 'like', $term.'%')
                ->orWhere('city', 'like', $term.'%')->orWhere('phone_number', 'like', $term.'%');
        })->orderBy('name')->limit(20)->get(['id', 'name', 'city', 'phone_number'])->toArray();
    }
}
