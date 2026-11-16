<?php

declare(strict_types=1);

namespace App\Ai\Billing\Services;

use App\Ai\Billing\DTOs\CreditBalance;
use App\Ai\Billing\DTOs\CreditSettlement;
use App\Ai\DTOs\AiPrincipal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AiCreditSettlementService
{
    public function __construct(
        protected CreditAccountResolver $accountResolver,
        protected MonthlyCreditGrantService $periodGrantService,
        protected ModelCostCalculator $calculator
    ) {}

    /**
     * Atomically settle debited credits for a completed provider attempt.
     */
    public function settle(
        string $reservationId,
        string $providerAttemptId,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $imagesCount = 0,
        int $audioSeconds = 0,
        bool $isChargeable = true,
        ?string $reconcileReason = null,
        ?string $rateCardVersion = null,
        ?string $fxRateVersion = null
    ): CreditSettlement {
        if ($reservationId === 'disabled' || ! (bool) config('ai_credits.enabled', true)) {
            return new CreditSettlement(
                reservationId: $reservationId,
                providerAttemptId: $providerAttemptId,
                accountId: 'disabled',
                periodId: 'disabled',
                creditsDebited: 0,
                creditsReleased: 0,
                costMinorInr: 0,
                rateCardVersion: 'cost_v1',
                fxRateVersion: 'fx_2026_11',
                status: 'exempt',
            );
        }

        // Calculate usage and debits
        $cost = $this->calculator->calculate(
            $provider,
            $model,
            $inputTokens,
            $outputTokens,
            $imagesCount,
            $audioSeconds,
            $rateCardVersion,
            $fxRateVersion
        );

        $creditsToDebit = $isChargeable ? $cost['credits'] : 0;
        $costMinorInr = $isChargeable ? $cost['cost_minor_inr'] : 0;

        return DB::transaction(function () use (
            $reservationId,
            $providerAttemptId,
            $creditsToDebit,
            $costMinorInr,
            $cost,
            $isChargeable,
            $reconcileReason
        ): CreditSettlement {
            // Check for duplicate settlement on provider_attempt_id (idempotency)
            $existingLedger = DB::table('ai_credit_ledger')
                ->where('provider_attempt_id', $providerAttemptId)
                ->first();

            if ($existingLedger) {
                return new CreditSettlement(
                    reservationId: $reservationId,
                    providerAttemptId: $providerAttemptId,
                    accountId: $existingLedger->account_id,
                    periodId: $existingLedger->period_id,
                    creditsDebited: abs((int) $existingLedger->amount_credits),
                    creditsReleased: 0,
                    costMinorInr: (int) ($existingLedger->cost_minor_inr ?? 0),
                    rateCardVersion: (string) $existingLedger->rate_card_version,
                    fxRateVersion: (string) $existingLedger->fx_rate_version,
                    status: 'settled',
                    ledgerEntryId: $existingLedger->id,
                );
            }

            $reservation = DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                throw new InvalidArgumentException("Credit reservation [{$reservationId}] not found.");
            }

            $period = DB::table('ai_credit_periods')
                ->where('id', $reservation->period_id)
                ->lockForUpdate()
                ->first();

            $held = (int) $reservation->remaining_held_credits;
            // Strict Accounting Invariant: Always debit the FULL actual usage ($creditsToDebit).
            // Never cap debits at $held, which would cause LLM usage overspend to be hidden and uncharged.
            $debited = $creditsToDebit;
            $released = max(0, $held - $debited);
            $overage = max(0, $debited - $held);

            $status = $reconcileReason !== null
                ? 'reconcile_required'
                : ($overage > 0 ? 'settled_with_overage' : 'settled');

            // Post immutable usage debit to ledger (full actual cost debited)
            $ledgerId = (string) Str::uuid();
            DB::table('ai_credit_ledger')->insert([
                'id' => $ledgerId,
                'account_id' => $reservation->account_id,
                'period_id' => $reservation->period_id,
                'company_id' => $reservation->company_id,
                'actor_id' => $reservation->actor_id,
                'run_id' => $reservation->run_id,
                'provider_attempt_id' => $providerAttemptId,
                'kind' => 'usage_debit',
                'amount_credits' => -$debited, // full actual cost debited
                'currency' => 'INR',
                'cost_minor_inr' => $costMinorInr,
                'rate_card_version' => $cost['rate_card_version'],
                'fx_rate_version' => $cost['fx_rate_version'],
                'source_type' => 'model_invocation',
                'source_id' => $providerAttemptId,
                'idempotency_key' => 'debit_' . $providerAttemptId,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);

            // Update period counters atomically
            DB::table('ai_credit_periods')
                ->where('id', $period->id)
                ->update([
                    'spent_credits' => DB::raw("spent_credits + {$debited}"),
                    'reserved_credits' => DB::raw("GREATEST(0, reserved_credits - {$held})"),
                    'updated_at' => now(),
                ]);

            // Mark reservation as settled and release remaining hold
            DB::table('ai_credit_reservations')
                ->where('id', $reservation->id)
                ->update([
                    'remaining_held_credits' => 0,
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            return new CreditSettlement(
                reservationId: $reservationId,
                providerAttemptId: $providerAttemptId,
                accountId: $reservation->account_id,
                periodId: $reservation->period_id,
                creditsDebited: $debited,
                creditsReleased: $released,
                costMinorInr: $costMinorInr,
                rateCardVersion: $cost['rate_card_version'],
                fxRateVersion: $cost['fx_rate_version'],
                status: $status,
                ledgerEntryId: $ledgerId,
            );
        }, 3);
    }

    /**
     * Safely release an unspent reservation (e.g. cancelled request or preflight abort).
     */
    public function releaseUnsent(string $reservationId, string $reason = 'request_cancelled'): void
    {
        if ($reservationId === 'disabled' || ! (bool) config('ai_credits.enabled', true)) {
            return;
        }

        DB::transaction(function () use ($reservationId, $reason): void {
            $reservation = DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation || $reservation->status !== 'active') {
                return;
            }

            $held = (int) $reservation->remaining_held_credits;
            if ($held > 0) {
                DB::table('ai_credit_periods')
                    ->where('id', $reservation->period_id)
                    ->update([
                        'reserved_credits' => DB::raw("GREATEST(0, reserved_credits - {$held})"),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('ai_credit_reservations')
                ->where('id', $reservationId)
                ->update([
                    'remaining_held_credits' => 0,
                    'status' => 'released',
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    /**
     * Compute current authoritative credit balance and media processing capacity.
     */
    public function balance(AiPrincipal|int $principalOrCompanyId): CreditBalance
    {
        $companyId = $principalOrCompanyId instanceof AiPrincipal
            ? $principalOrCompanyId->companyId
            : $principalOrCompanyId;

        $account = $this->accountResolver->resolve($companyId);
        $period = $this->periodGrantService->resolveCurrentPeriod($account->id);

        $periodRow = DB::table('ai_credit_periods')->where('id', $period->id)->first();

        $granted = (int) ($periodRow->included_granted + $periodRow->topups_granted + $periodRow->refunded_credits);
        $spent = (int) $periodRow->spent_credits;
        $reserved = (int) $periodRow->reserved_credits;
        $available = max(0, $granted - ($spent + $reserved));

        // Count media pages processed in current monthly period
        $monthlyMediaCap = (int) config('ai_credits.monthly_media_pages', 100);
        $mediaPagesUsed = (int) DB::table('ai_media_assets')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$periodRow->period_start_utc, $periodRow->period_end_utc])
            ->count();

        $mediaPagesRemaining = max(0, $monthlyMediaCap - $mediaPagesUsed);

        return new CreditBalance(
            accountId: $account->id,
            periodId: $periodRow->id,
            includedGranted: (int) $periodRow->included_granted,
            topupsGranted: (int) $periodRow->topups_granted,
            spentCredits: $spent,
            reservedCredits: $reserved,
            refundedCredits: (int) $periodRow->refunded_credits,
            availableCredits: $available,
            periodStartUtc: (string) $periodRow->period_start_utc,
            periodEndUtc: (string) $periodRow->period_end_utc,
            mediaPagesRemaining: $mediaPagesRemaining,
            policyVersion: (string) $account->credit_policy_version,
            status: (string) $account->status,
        );
    }
}
