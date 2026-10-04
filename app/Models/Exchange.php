<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ProtectsAdjustmentHistory;
    protected $fillable = [
        'reference_no',
        'original_sale_id',
        'warehouse_id',
        'customer_id',
        'biller_id',
        'returned_items',
        'returned_total',
        'exchanged_items',
        'exchanged_total',
        'difference_amount',
        'payment_status',
        'payment_method',
        'note',
        'user_id'
    ];

    protected $casts = [
        'returned_items' => 'array',
        'exchanged_items' => 'array',
    ];

    public function originalSale()
    {
        return $this->belongsTo(Sale::class, 'original_sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
