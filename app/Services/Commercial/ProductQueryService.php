<?php

namespace App\Services\Commercial;

use App\Models\Product;
use App\Services\Platform\CompanyContext;

class ProductQueryService
{
    public function search(string $term, CompanyContext $context): array
    {
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term));
        $base = Product::forCompany($context)->where('is_active', true)->whereIn('type', ['standard', 'service', 'digital'])
            ->select(['id', 'name', 'code', 'type', 'price', 'cost', 'unit_id', 'tax_id', 'is_batch', 'is_imei', 'is_variant']);
        if ($term === '') return $base->orderBy('code')->limit(20)->get()->toArray();
        // Separate prefix ranges allow each company index to serve its own lookup.
        $query = (clone $base)->whereRaw("code LIKE ? ESCAPE '!'", [$term.'%']);
        $query->union((clone $base)->whereRaw("name LIKE ? ESCAPE '!'", [$term.'%']));
        return $query->orderBy('code')->limit(20)->get()->toArray();
    }
}
