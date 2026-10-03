<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

class Product_Warehouse extends Model
{
    use ScopesCompanyQueries;

	protected $table = 'product_warehouse';
    protected $fillable =[
        "product_id", "product_batch_id", "variant_id", "imei_number", "warehouse_id", "qty", "price"
    ];

    public function scopeFindProductWithVariant($query, $product_id, $variant_id, $warehouse_id)
    {
    	return $query->where([
            ['product_id', $product_id],
            ['variant_id', $variant_id],
            ['warehouse_id', $warehouse_id]
        ]);
    }

    public function scopeFindProductWithoutVariant($query, $product_id, $warehouse_id)
    {
    	return $query->where([
            ['product_id', $product_id],
            ['warehouse_id', $warehouse_id]
        ]);
    }

    /** Stock visibility requires every ownership-bearing parent and the selected branch. */
    public function scopeVisibleIn(Builder $query, CompanyContext $context): Builder
    {
        $warehouse = fn ($q) => $q->forCompany($context)->where('branch_id', $context->branchId);
        $product = fn ($q) => $q->forCompany($context);

        return $query->forCompany($context)->whereHas('product', $product)->whereHas('warehouse', $warehouse);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }
}
