<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class Customer extends Model
{
    use ScopesCompanyQueries;

    protected $fillable =[
        "customer_group_id", "user_id", "name", "print_name", "company_name", "contact_person",
        "email", "type", "phone_number", "wa_number", "tax_no", "tin_no", "is_sez", "address", "city", "credit_days", "cd_days", "cd_percent", "bill_by_bill", "search_alias",
        "area_id", "agent_id",
        "state", "postal_code", "country", "opening_balance", "credit_limit", "points", "deposit", "pay_term_no","pay_term_period", "expense", "wishlist", "is_active"
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo('App\Models\CustomerGroup');
    }

    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function discountPlans()
    {
        return $this->belongsToMany('App\Models\DiscountPlan', 'discount_plan_customers');
    }

    public function points(){
        return $this->hasMany(Point::class,'customer_id');
    }
}
