<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class SaleType extends Model
{
    use ScopesCompanyQueries;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'tax_nature',
        'tax_rate',
        'sales_account_id',
        'cgst_account_id',
        'sgst_account_id',
        'igst_account_id',
        'is_active',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function salesAccount()
    {
        return $this->belongsTo(Account::class, 'sales_account_id');
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

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
