<?php

namespace App\Services\Commercial;

use App\Models\Product;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\Schema;

class ProductQueryService
{
    public function search(string $term, CompanyContext $context, ?string $partyType = null, ?int $partyId = null): array
    {
        app(ProductAliasService::class)->party($partyType, $partyId, $context);
        $normalized = ProductAliasService::normalize($term);
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($term));
        $base = Product::forCompany($context)->where('is_active', true)->whereIn('type', ['standard', 'service', 'digital'])
            ->select(['id', 'name', 'code', 'type', 'price', 'cost', 'unit_id', 'tax_id', 'is_batch', 'is_imei', 'is_variant']);
        if ($term === '') return $base->orderBy('code')->limit(20)->get()->toArray();
        $aliases = Schema::hasTable('product_aliases')
            ? app(ProductAliasService::class)->visible($context, $partyType, $partyId)
                ->whereRaw("normalized_name LIKE ? ESCAPE '!'", [str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalized).'%'])->get()
            : collect();
        $rank = $aliases->groupBy('product_id')->map(fn ($rows) => $rows->min(fn ($alias) =>
            $alias->normalized_name === $normalized ? ($alias->party_type === 'company' ? 2 : 1) : 3));
        $query = $base->where(function ($query) use ($term, $rank) {
            $query->whereRaw("code LIKE ? ESCAPE '!'", [$term.'%'])
                ->orWhereRaw("name LIKE ? ESCAPE '!'", [$term.'%']);
            if ($rank->isNotEmpty()) $query->orWhereIn('id', $rank->keys());
        });
        // Sort the matching set before limiting: alias ambiguity must never depend on catalog order.
        return $query->get()->map(function ($product) use ($rank, $normalized) {
            $product->setAttribute('match_rank', ProductAliasService::normalize($product->code) === $normalized ? 0
                : ($rank->get($product->id) ?? (ProductAliasService::normalize($product->name) === $normalized ? 3 : 4)));
            return $product;
        })->sortBy(fn ($product) => sprintf('%d:%s:%010d', $product->match_rank, $product->code, $product->id))->take(50)->values()->toArray();
    }
}
