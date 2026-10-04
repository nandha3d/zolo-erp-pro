<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ProtectsReturnLineHistory;
    protected $casts = ['stock_details_json' => 'array', 'tax_snapshot_json' => 'array'];
    protected $table = 'product_returns';
    protected $fillable =[
        "return_id", "product_id", "variant_id", "imei_number", "product_batch_id", "qty", "sale_unit_id", "net_unit_price", "discount", "tax_rate", "tax", "total"
    ];
}
