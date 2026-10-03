<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterTankerTrip extends Model
{
    protected $fillable = [
        'trip_number',
        'tanker_id',
        'customer_id',
        'trip_date',
        'source_plant',
        'destination_site',
        'water_quantity_kl',
        'trip_rate',
        'diesel_expense',
        'toll_expense',
        'driver_batta',
        'site_receiver_name',
        'site_receiver_signature',
        'status',
        'invoice_id',
        'notes',
        'created_by'
    ];

    public function tanker()
    {
        return $this->belongsTo(WaterTanker::class, 'tanker_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
