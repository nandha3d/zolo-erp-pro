<?php

namespace App\Models\Accounting;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class CollectionEvent extends Model
{
    use ScopesCompanyQueries;
    public $timestamps = false;
    protected $guarded = ['id', 'company_id'];
    protected $casts = ['snapshot' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Collection audit events are immutable.'));
        static::deleting(fn () => throw new \LogicException('Collection audit events cannot be deleted.'));
    }
}
