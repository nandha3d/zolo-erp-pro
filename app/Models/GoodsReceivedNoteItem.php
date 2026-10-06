<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNoteItem extends Model
{
    protected $table = 'goods_received_note_items';

    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'float',
        'cost' => 'float',
        'amount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'total' => 'float',
    ];

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'goods_received_note_id');
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
