<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use ScopesCompanyQueries;
    protected $guarded = ['id'];
    protected $casts = ['expected_json' => 'array', 'validation_errors' => 'array', 'summary_json' => 'array'];
    protected static function booted(): void
    {
        static::updating(function (self $batch) {
            if ($batch->getOriginal('status') === 'committed') throw new \LogicException('Committed import evidence is immutable.');
        });
        static::deleting(fn () => throw new \LogicException('Import evidence cannot be deleted.'));
    }
}
