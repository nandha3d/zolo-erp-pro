<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class Biller extends Model
{
    use ScopesCompanyQueries;

    protected $fillable =[
        "name", "image", "company_name", "vat_number",
        "email", "phone_number", "address", "city",
        "state", "postal_code", "country", "is_active"
    ];

    public function sale()
    {
    	return $this->hasMany('App\Models\Sale');
    }
}
