<?php

namespace App\Models\Inventory;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A serial number or dimensioned piece. Location/status are maintained only by InventoryMovementService. */
class StockIdentity extends Model
{
    use ScopesCompanyQueries;

    public const SERIAL = 'serial';
    public const PIECE = 'piece';

    public const IN_STOCK = 'in_stock';
    public const ISSUED = 'issued';

    protected $table = 'stock_identities';

    protected $guarded = ['id'];

    protected $casts = [
        'warranty_start' => 'date',
        'warranty_end' => 'date',
    ];

    public function dimension(): HasOne
    {
        return $this->hasOne(StockDimension::class);
    }
}
