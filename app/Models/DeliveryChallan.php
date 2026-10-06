<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallan extends Model
{
    use ScopesCompanyQueries;

    protected $table = 'delivery_challans';

    protected $guarded = ['id'];

    protected $casts = [
        'challan_date' => 'date',
        'lr_date' => 'date',
        'sundries_json' => 'array',
        'total_qty' => 'float',
        'total_amount' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class, 'delivery_challan_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function saleType(): BelongsTo
    {
        return $this->belongsTo(SaleType::class, 'sale_type_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
