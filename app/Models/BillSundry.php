<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class BillSundry extends Model
{
    use ScopesCompanyQueries;

    protected $fillable = [
        'company_id',
        'name',
        'nature',
        'calculation_type',
        'default_value',
        'affect_cost',
        'calculate_before_tax',
        'tax_rate',
        'account_id',
        'cgst_account_id',
        'sgst_account_id',
        'igst_account_id',
        'cess_account_id',
        'is_active',
    ];

    protected $casts = [
        'default_value' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'affect_cost' => 'boolean',
        'calculate_before_tax' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function cgstAccount()
    {
        return $this->belongsTo(Account::class, 'cgst_account_id');
    }

    public function sgstAccount()
    {
        return $this->belongsTo(Account::class, 'sgst_account_id');
    }

    public function igstAccount()
    {
        return $this->belongsTo(Account::class, 'igst_account_id');
    }

    public function cessAccount()
    {
        return $this->belongsTo(Account::class, 'cess_account_id');
    }
}
