<?php

namespace App\Services\Commercial;

use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Services\Platform\CompanyContext;

class ProductQueryService
{
    public function search(string $term, CompanyContext $context): array
    {
        $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));
        return Product::forCompany($context)->where('is_active', true)->whereIn('type', ['standard', 'service', 'digital'])
            ->where(fn ($q) => $q->where('code', 'like', $term.'%')->orWhere('name', 'like', $term.'%'))
            ->orderBy('code')->limit(20)->get(['id', 'name', 'code', 'type', 'price', 'cost', 'unit_id', 'tax_id', 'is_batch', 'is_imei', 'is_variant'])->toArray();
    }
}
