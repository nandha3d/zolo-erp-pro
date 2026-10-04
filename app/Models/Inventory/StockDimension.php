<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockDimension extends Model
{
    protected $table = 'stock_dimensions';

    protected $guarded = ['id'];

    protected $casts = [
        'length' => 'decimal:4',
        'width' => 'decimal:4',
        'thickness' => 'decimal:4',
        'computed_volume' => 'decimal:6',
    ];
}
