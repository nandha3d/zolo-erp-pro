<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterTanker extends Model
{
    protected $fillable = [
        'vehicle_number',
        'model_type',
        'capacity_kl',
        'capacity_liters',
        'driver_name',
        'driver_phone',
        'is_active'
    ];

    public function trips()
    {
        return $this->hasMany(WaterTankerTrip::class, 'tanker_id');
    }
}
