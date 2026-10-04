<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Stable document reads retain the existing envelope and frozen transaction values. */
class CommercialDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource->only(['id', 'reference_no', 'customer_id', 'supplier_id', 'warehouse_id', 'biller_id',
            'business_date', 'sales_status', 'status', 'payment_status', 'total_qty', 'total_price', 'total_discount', 'total_tax',
            'order_discount', 'order_tax_rate', 'order_tax', 'grand_total', 'paid_amount', 'shipping_cost', 'coupon_discount',
            'sale_note', 'purchase_note', 'currency_id', 'exchange_rate', 'tax_snapshot', 'created_at', 'posted_at', 'reversed_at']);
        foreach (['customer', 'supplier'] as $relation) {
            if ($this->resource->relationLoaded($relation)) $data[$relation] = $this->resource->$relation ? (new PartyResource($this->resource->$relation))->resolve($request) : null;
        }
        foreach (['warehouse', 'biller'] as $relation) {
            if ($this->resource->relationLoaded($relation)) $data[$relation] = $this->resource->$relation?->only(['id', 'name']);
        }
        if ($this->resource->relationLoaded('payments')) $data['payments'] = $this->resource->payments->map(fn ($p) => $p->only(['id', 'payment_reference', 'amount', 'paying_method', 'account_id', 'created_at']))->all();
        foreach (['productSales' => 'product_sales', 'productPurchases' => 'product_purchases'] as $relation => $key) {
            if ($this->resource->relationLoaded($relation)) $data[$key] = $this->resource->$relation->map(fn ($line) => $line->only([
                'id', 'product_id', 'variant_id', 'product_batch_id', 'qty', 'recieved', 'received_qty', 'sale_unit_id', 'purchase_unit_id',
                'net_unit_price', 'net_unit_cost', 'discount', 'tax', 'tax_rate', 'total', 'dimension_snapshot', 'tax_snapshot',
            ]) + ['product' => $line->product ? (new ProductResource($line->product))->resolve($request) : null])->all();
        }
        return $data;
    }
}
