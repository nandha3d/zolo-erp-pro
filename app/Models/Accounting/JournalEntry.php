<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
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
        return abs((float)$this->total_debit - (float)$this->total_credit) < 0.0001;
    }
}
