<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use ScopesCompanyQueries;

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /** Legacy zero/null references mean no linked source, never an ownership grant. */
    public function scopeVisibleIn(Builder $query, CompanyContext $context): Builder
    {
        return $query->forCompany($context)
            ->where(fn ($q) => $q->whereNull('account_id')->orWhere('account_id', 0)
                ->orWhereHas('account', fn ($q) => $q->forCompany($context)))
            ->where(fn ($q) => $q->whereNull('sale_id')->orWhere('sale_id', 0)
                ->orWhereHas('sale', fn ($q) => $q->visibleIn($context)))
            ->where(fn ($q) => $q->whereNull('purchase_id')->orWhere('purchase_id', 0)
                ->orWhereHas('purchase', fn ($q) => $q->visibleIn($context)));
    }

    protected $fillable =[
        "purchase_id", "user_id", "sale_id", "cash_register_id", "account_id","payment_receiver", "payment_reference", "amount", "currency_id", "installment_id", "exchange_rate", "payment_at", "used_points", "change", "paying_method", "payment_proof", "document", "payment_note"
    ];

    protected $casts = [
        'payment_at' => 'datetime',
    ];
}
