<?php

namespace App\Services\Accounting;

use App\Models\Accounting\FiscalYear;
use App\Models\Company;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PeriodCloseService
{
    public function change(CompanyContext $context, string $action, ?string $date, string $reason): FiscalYear
    {
        $resolver = app(CompanyContextResolver::class);
        $context = $resolver->forActor($context);
        if (!$resolver->canManageFinancialYears(auth()->id(), $context->companyId)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Company administrator required for period controls.');
        }
        Validator::make(['action' => $action, 'date' => $date, 'reason' => trim($reason)], [
            'action' => 'required|in:lock,unlock,close', 'date' => 'nullable|date_format:Y-m-d|required_if:action,lock',
            'reason' => 'required|string|max:500',
        ])->validate();
        return DB::transaction(function () use ($context, $action, $date, $reason) {
            Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $year = FiscalYear::where('company_id', $context->companyId)->whereKey($context->financialYearId)->lockForUpdate()->firstOrFail();
            if ($date && ($date < $year->start_date->toDateString() || $date > $year->end_date->toDateString())) {
                throw new \InvalidArgumentException('Lock date must fall inside the selected financial year.');
            }
            if ($action === 'lock' && ($year->is_closed || ($year->lock_date && $date < $year->lock_date->toDateString()))) {
                throw new \InvalidArgumentException('Use an audited unlock to reopen a closed period or move its lock backwards.');
            }
            if ($action === 'close' && !app(LedgerReconciliationService::class)->reconcile($context)['is_reconciled']) {
                throw new \InvalidArgumentException('Reconcile account balances and open items before closing the year.');
            }
            $previous = $year->lock_date?->toDateString();
            $year->forceFill([
                'lock_date' => $action === 'unlock' ? null : ($action === 'close' ? $year->end_date->toDateString() : $date),
                'status' => $action === 'close' ? 'closed' : 'open', 'is_closed' => $action === 'close',
                'closed_at' => $action === 'close' ? now() : null, 'closed_by' => $action === 'close' ? auth()->id() : null,
            ])->save();
            DB::table('accounting_period_events')->insert([
                'company_id' => $context->companyId, 'financial_year_id' => $year->id, 'actor_id' => auth()->id(),
                'action' => $action, 'previous_lock_date' => $previous, 'lock_date' => $year->lock_date?->toDateString(),
                'reason' => $reason, 'created_at' => now(),
            ]);
            return $year;
        });
    }
}
