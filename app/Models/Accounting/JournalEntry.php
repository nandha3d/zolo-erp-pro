<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use ScopesCompanyQueries;

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') === 'posted' && $entry->isDirty()) {
                throw new \LogicException('Posted journals are immutable; post a reversal.');
            }
        });
        static::deleting(function (self $entry) {
            if ($entry->status === 'posted') {
                throw new \LogicException('Posted journals cannot be deleted.');
            }
        });
    }

    public function scopeWithOwnedItems(Builder $query, CompanyContext $context): Builder
    {
        return $query->forCompany($context)->with([
            'items' => fn ($q) => $q->forCompany($context)
                ->whereHas('account', fn ($q) => $q->forCompany($context))
                ->with(['account' => fn ($q) => $q->forCompany($context)]),
        ]);
    }

    protected $table = 'journal_entries';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'reference_type',
        'reference_id',
        'reference_no',
        'description',
        'status', // draft, posted, void
        'total_debit',
        'total_credit',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:4',
        'total_credit' => 'decimal:4',
        'posted_at' => 'datetime',
        'cheque_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if total debit equals total credit (Double Entry Balanced Rule).
     */
    public function isBalanced(): bool
    {
        return \App\Support\LedgerAmount::units($this->total_debit) === \App\Support\LedgerAmount::units($this->total_credit);
    }
}
