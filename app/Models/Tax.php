<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    protected $fillable =[
        "name", "rate", "is_active", "woocommerce_tax_id", "company_id"
    ];

    public function product()
    {
    	return $this->hasMany('App\ModelsProduct');
    }
}
