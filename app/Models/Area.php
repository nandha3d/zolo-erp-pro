<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use ScopesCompanyQueries;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'city',
        'state',
        'pincode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }
}
