<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use ScopesCompanyQueries;
    protected $guarded = ['id'];
    protected $casts = ['business_date' => 'date:Y-m-d', 'promised_date' => 'date:Y-m-d', 'attributes_json' => 'array'];
    public function items() { return $this->hasMany(CommercialOrderItem::class, 'order_id')->where('order_kind', 'sale'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function scopeVisibleIn(Builder $query, CompanyContext $context): Builder
    {
        return $query->forCompany($context)->where('branch_id', $context->branchId)
            ->whereHas('customer', fn ($q) => $q->forCompany($context))
            ->whereHas('warehouse', fn ($q) => $q->forCompany($context)->where('branch_id', $context->branchId));
    }
}
