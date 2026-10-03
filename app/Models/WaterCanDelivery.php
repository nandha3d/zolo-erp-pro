<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterCanDelivery extends Model
{
    protected $fillable = [
        'delivery_no',
        'delivery_date',
        'route_id',
        'customer_id',
        'morning_loaded_cans',
        'cans_delivered',
        'empty_cans_returned',
        'can_rate',
        'total_amount',
        'paid_amount',
        'payment_mode',
        'balance_cans_held',
        'status',
        'created_by'
    ];

    public function route()
    {
        return $this->belongsTo(WaterCanRoute::class, 'route_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
