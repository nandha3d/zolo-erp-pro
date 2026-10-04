<?php

namespace App\Models\Accounting;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class AccountOpenItem extends Model
{
    use ScopesCompanyQueries;

    protected $guarded = ['id', 'company_id'];
    protected $casts = ['document_date' => 'date', 'due_date' => 'date', 'original_amount' => 'decimal:4', 'open_amount' => 'decimal:4'];

    protected static function booted(): void
    {
        static::updating(function (self $item) {
            if ($item->isDirty(array_diff(array_keys($item->getAttributes()), ['open_amount', 'status', 'updated_at']))) {
                throw new \LogicException('Open item source history is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Open items cannot be deleted.'));
    }
}
