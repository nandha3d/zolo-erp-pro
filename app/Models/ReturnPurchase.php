<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnPurchase extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ProtectsAdjustmentHistory;
    protected $casts = ['document_snapshot_json' => 'array', 'attributes_json' => 'array', 'tax_snapshot_json' => 'array', 'posted_at' => 'datetime', 'approved_at' => 'datetime'];

    public function products() { return $this->hasMany(PurchaseProductReturn::class, 'return_id'); }
    protected $table = 'return_purchases';
    protected $fillable =[
        "reference_no", "purchase_id", "user_id", "supplier_id", "warehouse_id", "account_id", "currency_id", "exchange_rate", "item", "total_qty", "total_discount", "total_tax", "total_cost","order_tax_rate", "order_tax", "grand_total", "document", "return_note", "staff_note"
    ];

    public function supplier()
    {
    	return $this->belongsTo('App\Models\Supplier');
    }

    public function warehouse()
    {
    	return $this->belongsTo('App\Models\Warehouse');
    }

    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
