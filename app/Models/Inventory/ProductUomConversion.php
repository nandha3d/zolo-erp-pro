<?php

namespace App\Models\Inventory;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

/** qty_in_to_uom = qty_in_from_uom * factor */
class ProductUomConversion extends Model
{
    use ScopesCompanyQueries;

    protected $table = 'product_uom_conversions';

    protected $guarded = ['id'];

    protected $casts = [
        'factor' => 'decimal:6',
        'is_purchase_default' => 'boolean',
        'is_sale_default' => 'boolean',
    ];
}
