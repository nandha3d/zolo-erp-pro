<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceivedNote extends Model
{
    use ScopesCompanyQueries;

    protected $table = 'goods_received_notes';

    protected $guarded = ['id'];

    protected $casts = [
        'grn_date' => 'date',
        'lr_date' => 'date',
        'sundries_json' => 'array',
        'total_qty' => 'float',
        'total_cost' => 'float',
        'total_tax' => 'float',
        'grand_total' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'goods_received_note_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function purchaseType(): BelongsTo
    {
        return $this->belongsTo(PurchaseType::class, 'purchase_type_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
