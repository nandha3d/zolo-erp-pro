<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseProductReturn extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ProtectsReturnLineHistory;
    protected $casts = ['stock_details_json' => 'array', 'tax_snapshot_json' => 'array'];
    protected $table = 'purchase_product_return';

    protected $fillable =[
        "return_id", "product_id", "product_batch_id", "variant_id", "imei_number", "qty", "purchase_unit_id", "net_unit_cost", "discount", "tax_rate", "tax", "total"
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(ReturnPurchase::class, 'return_id'); // check actual column
    }
}
