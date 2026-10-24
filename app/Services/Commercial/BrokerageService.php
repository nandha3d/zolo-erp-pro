<?php

namespace App\Services\Commercial;

use App\Models\Sale;
use App\Services\Accounting\{AccountingPostingService, SemanticAccountResolver};
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Services\Tax\GstSetupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Brokerage (dalali) for agents who bring textile orders: the agent's rate on each invoice's value before GST,
 * either on billing or only on what the customer has paid. Booking it posts brokerage expense against brokerage
 * payable, less TDS under 194H once the agent's commission for the year passes ₹20,000 (2% since 1 October 2024).
 */
class BrokerageService
{
    public const TDS_THRESHOLD = 20000;
    public const TDS_RATE = 2.0;

    /** Invoices with an agent in the period, the commission due on each, and whether it is already booked. */
    public function register(CompanyContext $context, string $from, string $to, ?int $agentId = null, string $basis = 'invoice'): array
    {
        validator(compact('basis'), ['basis' => 'in:invoice,collection'])->validate();
        $agents = DB::table('agents')->where('company_id', $context->companyId)->get()->keyBy('id');
        $booked = DB::table('agent_commissions')->where('company_id', $context->companyId)->pluck('amount', 'sale_id');
        return Sale::visibleIn($context)->whereNotNull('posted_at')->whereNull('reversed_at')->whereNotNull('agent_id')
            ->when($agentId, fn ($q) => $q->where('agent_id', $agentId))
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->orderBy('created_at')->get()
            ->map(function (Sale $sale) use ($agents, $booked, $basis) {
                $agent = $agents[$sale->agent_id] ?? null;
                $taxable = round((float) $sale->total_price - (float) $sale->total_tax, 2);
                $collected = (float) $sale->grand_total > 0 ? min(1, (float) $sale->paid_amount / (float) $sale->grand_total) : 0;
                $base = round($basis === 'collection' ? $taxable * $collected : $taxable, 2);
                $rate = (float) ($agent->commission_rate ?? 0);
                return ['sale_id' => $sale->id, 'invoice' => $sale->reference_no, 'date' => $sale->created_at->toDateString(),
                    'agent_id' => (int) $sale->agent_id, 'agent' => $agent->name ?? '—', 'taxable_value' => $taxable,
                    'collected_percent' => round($collected * 100, 2), 'base' => $base, 'rate' => $rate, 'commission' => round($base * $rate / 100, 2),
                    'booked' => isset($booked[$sale->id])];
            })->values()->all();
    }

    /** Books every unbooked commission of one agent in the period as a single journal. */
    public function book(int $agentId, string $from, string $to, string $date, string $basis, CompanyContext $context, int $actor): array
    {
        $guard = app(CompanyWriteGuard::class);
        $guard->owned(\App\Models\Agent::class, $agentId, $context, 'agent_id');
        return DB::transaction(function () use ($agentId, $from, $to, $date, $basis, $context, $actor, $guard) {
            $guard->begin($context, $date);
            $rows = array_values(array_filter($this->register($context, $from, $to, $agentId, $basis), fn ($row) => !$row['booked'] && $row['commission'] > 0));
            if (!$rows) throw ValidationException::withMessages(['agent_id' => 'No unbooked brokerage for this agent in the period.']);
            $amount = round(array_sum(array_column($rows, 'commission')), 2);
            $fy = DB::table('fiscal_years')->where('id', $context->financialYearId)->first();
            $earlier = (float) DB::table('agent_commissions')->where('company_id', $context->companyId)->where('agent_id', $agentId)
                ->when($fy, fn ($q) => $q->whereBetween('business_date', [$fy->start_date, $fy->end_date]))->sum('amount');
            // 194H: once the year's brokerage passes the threshold, TDS applies to the whole of this booking.
            $tds = $earlier + $amount > self::TDS_THRESHOLD ? round(($earlier > self::TDS_THRESHOLD ? $amount : $earlier + $amount) * self::TDS_RATE / 100) : 0;
            app(GstSetupService::class)->ensureLedgers($context->companyId, array_filter(['commission_expense', 'commission_payable', $tds ? 'tds_payable' : null]));
            $accounts = app(SemanticAccountResolver::class);
            $agent = DB::table('agents')->find($agentId);
            $journal = app(AccountingPostingService::class)->postJournalEntry(['entry_date' => $date, 'reference_type' => 'brokerage',
                'reference_id' => $agentId, 'reference_no' => 'BRK-'.$agentId.'-'.str_replace('-', '', $date), 'created_by' => $actor,
                'description' => 'Brokerage to '.$agent->name.' for '.count($rows).' invoices ('.$from.' to '.$to.')'], array_values(array_filter([
                    ['chart_of_account_id' => $accounts->resolve('commission_expense', $context)->id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $accounts->resolve('commission_payable', $context)->id, 'debit' => 0, 'credit' => round($amount - $tds, 2)],
                    $tds ? ['chart_of_account_id' => $accounts->resolve('tds_payable', $context)->id, 'debit' => 0, 'credit' => $tds] : null,
                ])), $context);
            foreach ($rows as $i => $row) {
                DB::table('agent_commissions')->insert(['company_id' => $context->companyId, 'agent_id' => $agentId, 'sale_id' => $row['sale_id'],
                    'base' => $row['base'], 'rate' => $row['rate'], 'amount' => $row['commission'], 'tds_amount' => $i === 0 ? $tds : 0,
                    'journal_entry_id' => $journal->id, 'business_date' => $date, 'created_at' => now(), 'updated_at' => now()]);
            }
            return ['invoices' => count($rows), 'amount' => $amount, 'tds' => $tds, 'payable' => round($amount - $tds, 2), 'journal_entry_id' => $journal->id];
        });
    }
}
