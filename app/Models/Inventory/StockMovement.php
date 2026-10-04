<?php

namespace App\Models\Inventory;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Posted stock history. Only the posted -> reversed status change is permitted after insert. */
class StockMovement extends Model
{
    use ScopesCompanyQueries;

    public const TYPES = ['opening', 'receipt', 'issue', 'transfer', 'adjustment', 'reversal', 'production_consume', 'production_output', 'production_scrap', 'expiry_writeoff'];

    protected $table = 'stock_movements';

    protected $guarded = ['id'];

    protected $casts = [
        'movement_date' => 'date',
        'posted_at' => 'datetime',
        'warnings_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $movement) {
            $setOnce = ['movement_no', 'warnings_json'];
            $changed = array_diff(array_keys($movement->getDirty()), ['status', 'updated_at', ...$setOnce]);
            foreach ($setOnce as $column) {
                if ($movement->isDirty($column) && $movement->getRawOriginal($column) !== null) {
                    $changed[] = $column;
                }
            }
            if ($changed !== [] || ($movement->isDirty('status') && $movement->status !== 'reversed')) {
                throw new LogicException('Posted stock movements are immutable; post a reversal instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Posted stock movements cannot be deleted; post a reversal instead.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class)->orderBy('line_no');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }
}
