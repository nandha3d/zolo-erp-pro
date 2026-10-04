<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Returns extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ProtectsAdjustmentHistory;
    protected $casts = ['document_snapshot_json' => 'array', 'attributes_json' => 'array', 'tax_snapshot_json' => 'array', 'posted_at' => 'datetime', 'approved_at' => 'datetime'];
	protected $table = 'returns';
    protected $fillable =[
        "reference_no", "user_id", "sale_id", "cash_register_id", "customer_id", "warehouse_id", "biller_id", "account_id", "currency_id", "exchange_rate", "item", "total_qty", "total_discount", "total_tax", "total_price","order_tax_rate", "order_tax", "grand_total", "document", "return_note", "staff_note"
    ];

    public function biller()
    {
    	return $this->belongsTo('App\Models\Biller');
    }

    public function customer()
    {
    	return $this->belongsTo('App\Models\Customer');
    }

    public function warehouse()
    {
    	return $this->belongsTo('App\Models\Warehouse');
    }

    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function products()
    {
         return $this->hasMany('App\Models\ProductReturn','return_id');
    }
}
