<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageStock extends Model
{
    protected $fillable = [
        'reference_no',
        'warehouse_id',
        'product_id',
        'variant_id',
        'qty',
        'unit_cost',
        'total_loss',
        'reason',
        'note',
        'user_id'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
