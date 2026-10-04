<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use ScopesCompanyQueries;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'type', // asset, liability, equity, revenue, expense
        'sub_type',
        'parent_id',
        'currency_id',
        'opening_balance',
        'current_balance',
        'is_reconciled',
        'is_active',
        'is_system',
        'description',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'is_reconciled' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Parent Account (Hierarchy Tree)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    /**
     * Child Sub-Accounts
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id')->orderBy('code');
    }

    /**
     * Journal Items for this account
     */
    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'chart_of_account_id');
    }

    /**
     * Determine normal balance side for this account type.
     * Assets and Expenses are Debit normal.
     * Liabilities, Equity, and Revenue are Credit normal.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense']);
    }

    /**
     * Calculate running balance up to a date or for all time.
     */
    public function calculateBalance(?string $asOfDate = null, ?CompanyContext $context = null): float
    {
        $context = app(CompanyContextResolver::class)->forActor($context);
        if ((int) $this->company_id !== $context->companyId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Account access denied.');
        }
        $query = $this->journalItems()->forCompany($context)
            ->whereHas('journalEntry', function ($q) use ($asOfDate, $context) {
                $q->forCompany($context)->where('status', 'posted');
                if ($asOfDate) {
                    $q->whereDate('entry_date', '<=', $asOfDate);
                }
            });

        $totals = $query->selectRaw('COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(credit), 0) AS total_credit')->first();
        $totalDebit = (float) $totals->total_debit;
        $totalCredit = (float) $totals->total_credit;

        if ($this->isDebitNormal()) {
            return (float) $this->opening_balance + ($totalDebit - $totalCredit);
        } else {
            return (float) $this->opening_balance + ($totalCredit - $totalDebit);
        }
    }
}
