<?php

namespace App\Models\Accounting;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class CollectionRecord extends Model
{
    use ScopesCompanyQueries;

    protected $guarded = ['id', 'company_id', 'branch_id', 'created_by'];
    protected $casts = ['action_date' => 'date:Y-m-d', 'amount' => 'decimal:4'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Cancel collection records instead of deleting audit history.'));
        static::updating(function (self $record) {
            if ($record->isDirty(['company_id', 'branch_id', 'open_item_id', 'kind', 'created_by'])) {
                throw new \LogicException('Collection ownership and source are immutable.');
            }
        });
    }
}
