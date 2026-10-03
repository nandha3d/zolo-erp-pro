<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterCanRoute extends Model
{
    protected $fillable = [
        'route_name',
        'vehicle_no',
        'driver_name',
        'driver_phone',
        'daily_avg_cans',
        'is_active'
    ];

    public function deliveries()
    {
        return $this->hasMany(WaterCanDelivery::class, 'route_id');
    }
}
