<?php

namespace App\Models\Inventory;

use App\Models\Concerns\ScopesCompanyQueries;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovementLine extends Model
{
    use ScopesCompanyQueries;

    protected $table = 'stock_movement_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'qty_base' => 'decimal:4',
        'uom_qty' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'value' => 'decimal:4',
        'attributes_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movement lines are immutable.'));
        static::deleting(fn () => throw new LogicException('Stock movement lines are immutable.'));
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(StockIdentity::class, 'stock_identity_id');
    }
}
