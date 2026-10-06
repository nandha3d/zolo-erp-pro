<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class Supplier extends Model
{
    use ScopesCompanyQueries;

    protected $fillable =[
        "name", "print_name", "image", "company_name", "contact_person", "vat_number", "tax_no", "tin_no",
        "email", "phone_number", "wa_number", "address", "city", "state", "postal_code", "country",
        "area_id", "agent_id", "opening_balance", "credit_days", "credit_limit", "cd_days", "cd_percent", "bill_by_bill",
        "pay_term_no", "pay_term_period", "is_active"
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function product()
    {
    	return $this->hasMany('App\Models\Product');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function returnPurchases()
    {
        return $this->hasMany(ReturnPurchase::class, 'supplier_id');
    }
}
