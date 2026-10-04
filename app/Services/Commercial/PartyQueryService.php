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
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term));
        $base = $model::forCompany($context)->where('is_active', true)->select(['id', 'name', 'city', 'phone_number']);
        if ($term === '') return $base->orderBy('name')->limit(20)->get()->toArray();
        $query = (clone $base)->whereRaw("name LIKE ? ESCAPE '!'", [$term.'%']);
        foreach (['search_alias', 'city', 'phone_number'] as $column) {
            $query->union((clone $base)->whereRaw($column." LIKE ? ESCAPE '!'", [$term.'%']));
        }
        return $query->orderBy('name')->limit(20)->get()->toArray();
    }
}
