<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageStock extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ProtectsAdjustmentHistory;
    protected $casts = ['stock_details_json' => 'array', 'posted_at' => 'datetime'];
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
