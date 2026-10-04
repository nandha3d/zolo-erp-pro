<?php

namespace App\Models\Operations;

use App\Models\Concerns\ScopesCompanyQueries;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class OperationRecord extends Model
{
    use ScopesCompanyQueries;

    protected $guarded = ['id'];
    protected $casts = ['details_json' => 'array', 'site_json' => 'array', 'posted_at' => 'datetime'];

    public function scopeVisibleIn($query, CompanyContext $context)
    {
        return $query->where('company_id', $context->companyId)->where('branch_id', $context->branchId);
    }

    protected static function booted(): void
    {
        static::updating(function (self $record) {
            if ($record->getRawOriginal('posted_at')) {
                throw new LogicException('Posted operation history is immutable; use its reversal service.');
            }
        });
        static::deleting(fn () => throw new LogicException('Operation history cannot be deleted.'));
    }
}
