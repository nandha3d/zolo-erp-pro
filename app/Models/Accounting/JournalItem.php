<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    use ScopesCompanyQueries;

    protected static function booted(): void
    {
        $guard = function (self $item) {
            $ids = array_filter([$item->journal_entry_id, $item->getOriginal('journal_entry_id')]);
            if (JournalEntry::whereIn('id', $ids)->where('status', 'posted')->exists()) {
                throw new \LogicException('Posted journal lines are immutable; post a reversal.');
            }
        };
        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    protected $table = 'journal_items';

    protected $fillable = [
        'journal_entry_id',
        'chart_of_account_id',
        'partner_type', // customer, supplier, employee
        'partner_id',
        'debit',
        'credit',
        'memo',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
