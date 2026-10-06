<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use ScopesCompanyQueries;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'commission_rate',
        'account_id',
        'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
