<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanItem extends Model
{
    protected $table = 'delivery_challan_items';

    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'float',
        'rate' => 'float',
        'amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'total' => 'float',
    ];

    public function challan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
