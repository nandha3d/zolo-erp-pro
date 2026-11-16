<?php

declare(strict_types=1);

namespace App\Ai\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class AiCreditReconciliationService
{
    /**
     * Reconcile all expired active reservations and release stranded holds.
     *
     * @return array{reconciled_count: int, released_credits: int}
     */
    public function sweepExpiredReservations(): array
    {
        $now = now()->toDateTimeString();

        $expired = DB::table('ai_credit_reservations')
            ->where('status', 'active')
            ->where('expires_at', '<', $now)
            ->get();

        $count = 0;
        $totalReleased = 0;

        foreach ($expired as $res) {
            DB::transaction(function () use ($res, &$count, &$totalReleased): void {
                $locked = DB::table('ai_credit_reservations')
                    ->where('id', $res->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    return;
                }

                $held = (int) $locked->remaining_held_credits;
                if ($held > 0) {
                    DB::table('ai_credit_periods')
                        ->where('id', $locked->period_id)
                        ->update([
                            'reserved_credits' => DB::raw("GREATEST(0, reserved_credits - {$held})"),
                            'updated_at' => now(),
                        ]);
                    $totalReleased += $held;
                }

                DB::table('ai_credit_reservations')
                    ->where('id', $locked->id)
                    ->update([
                        'remaining_held_credits' => 0,
                        'status' => 'reconcile_required',
                        'updated_at' => now(),
                    ]);

                $count++;
            }, 3);
        }

        return [
            'reconciled_count' => $count,
            'released_credits' => $totalReleased,
        ];
    }

    /**
     * Audit period aggregate counters against immutable ledger rows.
     *
     * @return array{
     *     period_id: string,
     *     account_id: string,
     *     is_reconciled: bool,
     *     period_spent: int,
     *     ledger_spent: int,
     *     period_included: int,
     *     ledger_included: int,
     *     period_topups: int,
     *     ledger_topups: int,
     *     discrepancies: array<string>
     * }
     */
    public function reconcilePeriod(string $periodId): array
    {
        $period = DB::table('ai_credit_periods')->where('id', $periodId)->first();
        if (! $period) {
            return [
                'period_id' => $periodId,
                'account_id' => '',
                'is_reconciled' => false,
                'period_spent' => 0,
                'ledger_spent' => 0,
                'period_included' => 0,
                'ledger_included' => 0,
                'period_topups' => 0,
                'ledger_topups' => 0,
                'discrepancies' => ['Period not found'],
            ];
        }

        $ledgerEntries = DB::table('ai_credit_ledger')->where('period_id', $periodId)->get();

        $ledgerSpent = 0;
        $ledgerIncluded = 0;
        $ledgerTopups = 0;

        foreach ($ledgerEntries as $entry) {
            match ($entry->kind) {
                'usage_debit' => $ledgerSpent += abs((int) $entry->amount_credits),
                'included_grant' => $ledgerIncluded += (int) $entry->amount_credits,
                'topup_grant' => $ledgerTopups += (int) $entry->amount_credits,
                default => null,
            };
        }

        $discrepancies = [];
        if ((int) $period->spent_credits !== $ledgerSpent) {
            $discrepancies[] = "Spent mismatch: period has [{$period->spent_credits}], ledger has [{$ledgerSpent}]";
        }
        if ((int) $period->included_granted !== $ledgerIncluded) {
            $discrepancies[] = "Included grant mismatch: period has [{$period->included_granted}], ledger has [{$ledgerIncluded}]";
        }
        if ((int) $period->topups_granted !== $ledgerTopups) {
            $discrepancies[] = "Top-ups mismatch: period has [{$period->topups_granted}], ledger has [{$ledgerTopups}]";
        }

        return [
            'period_id' => $periodId,
            'account_id' => $period->account_id,
            'is_reconciled' => empty($discrepancies),
            'period_spent' => (int) $period->spent_credits,
            'ledger_spent' => $ledgerSpent,
            'period_included' => (int) $period->included_granted,
            'ledger_included' => $ledgerIncluded,
            'period_topups' => (int) $period->topups_granted,
            'ledger_topups' => $ledgerTopups,
            'discrepancies' => $discrepancies,
        ];
    }
}
