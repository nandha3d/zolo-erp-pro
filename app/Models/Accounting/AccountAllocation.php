<?php

namespace App\Models\Accounting;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class AccountAllocation extends Model
{
    use ScopesCompanyQueries;

    protected $guarded = ['id', 'company_id'];
    protected $casts = ['allocated_amount' => 'decimal:4', 'allocation_date' => 'date'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Allocations are immutable; post an allocation reversal.'));
        static::deleting(fn () => throw new \LogicException('Allocations cannot be deleted.'));
    }
}
