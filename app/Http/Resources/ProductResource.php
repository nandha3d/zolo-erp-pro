<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource->only(['id', 'name', 'code', 'type', 'price', 'cost', 'qty', 'image', 'unit_id', 'category_id', 'brand_id', 'tax_id', 'is_batch', 'is_imei', 'is_variant']);
        foreach (['category', 'brand', 'unit'] as $relation) {
            if ($this->resource->relationLoaded($relation)) $data[$relation] = $this->resource->$relation?->only(['id', 'name', 'unit_name', 'code']);
        }
        if ($this->resource->relationLoaded('productWarehouse')) {
            $data['product_warehouse'] = $this->resource->productWarehouse->map(fn ($stock) => $stock->only(['id', 'product_id', 'warehouse_id', 'variant_id', 'product_batch_id', 'qty'])
                + ['warehouse' => $stock->warehouse?->only(['id', 'name'])])->all();
        }
        return $data;
    }
}
