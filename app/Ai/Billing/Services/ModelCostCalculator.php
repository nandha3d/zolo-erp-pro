<?php

declare(strict_types=1);

namespace App\Ai\Billing\Services;

use Illuminate\Support\Facades\DB;

class ModelCostCalculator
{
    /**
     * Compute exact chargeable cost in minor INR and debited credits for a provider attempt.
     *
     * @return array{
     *     credits: int,
     *     cost_minor_inr: int,
     *     usd_cost: float,
     *     rate_card_version: string,
     *     fx_rate_version: string,
     *     fx_rate: float
     * }
     */
    public function calculate(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $imagesCount = 0,
        int $audioSeconds = 0,
        ?string $rateCardVersion = null,
        ?string $fxRateVersion = null
    ): array {
        $policyVersion = $rateCardVersion ?: config('ai_credits.credit_policy_version', 'cost_v1');
        $fxVersion = $fxRateVersion ?: config('ai_credits.default_fx_version', 'fx_2026_11');

        $inputPrice = $this->resolveUnitPrice($policyVersion, $provider, $model, 'input_token');
        $outputPrice = $this->resolveUnitPrice($policyVersion, $provider, $model, 'output_token');
        $imagePrice = $this->resolveUnitPrice($policyVersion, $provider, $model, 'image');
        $audioPrice = $this->resolveUnitPrice($policyVersion, $provider, $model, 'audio_second');

        $fxRate = $this->resolveFxRate($fxVersion);

        // Compute total USD using bcmath or high precision
        $inputUsd = bcmul((string) $inputTokens, $inputPrice, 10);
        $outputUsd = bcmul((string) $outputTokens, $outputPrice, 10);
        $imageUsd = bcmul((string) $imagesCount, $imagePrice, 10);
        $audioUsd = bcmul((string) $audioSeconds, $audioPrice, 10);

        $totalUsd = bcadd(bcadd($inputUsd, $outputUsd, 10), bcadd($imageUsd, $audioUsd, 10), 10);

        // Convert USD to INR
        $totalInr = bcmul($totalUsd, (string) $fxRate, 8);

        // Convert INR to paise (minor units)
        $costMinorInr = (int) round((float) $totalInr * 100);

        // 1 credit = 1 paisa (₹0.01). Debited credits = ceil(cost_in_inr / 0.01) = ceil(costMinorInr)
        // If cost is > 0 but less than 1 paisa, round up to 1 credit minimum billable unit.
        $floatCredits = (float) $totalInr / 0.01;
        $credits = (int) ceil($floatCredits);

        if ($credits === 0 && ($inputTokens > 0 || $outputTokens > 0 || $imagesCount > 0)) {
            $credits = 1;
            $costMinorInr = max(1, $costMinorInr);
        }

        return [
            'credits' => $credits,
            'cost_minor_inr' => $costMinorInr,
            'usd_cost' => (float) $totalUsd,
            'rate_card_version' => $policyVersion,
            'fx_rate_version' => $fxVersion,
            'fx_rate' => $fxRate,
        ];
    }

    /**
     * Estimate worst-case credits needed for preflight reservation.
     */
    public function estimatePreflightCredits(
        string $provider,
        string $model,
        int $estimatedInputTokens,
        int $maxOutputTokens,
        int $imagesCount = 0,
        ?string $rateCardVersion = null
    ): int {
        $calc = $this->calculate(
            $provider,
            $model,
            $estimatedInputTokens,
            $maxOutputTokens,
            $imagesCount,
            0,
            $rateCardVersion
        );

        $configuredMax = (int) config('ai_credits.max_credits_per_request', 5000);

        return min($configuredMax, max(1, $calc['credits']));
    }

    private function resolveUnitPrice(string $version, string $provider, string $model, string $unit): string
    {
        $dbRate = DB::table('ai_model_rate_cards')
            ->where('version', $version)
            ->where('provider', $provider)
            ->where('model', $model)
            ->where('billable_unit', $unit)
            ->value('unit_price_usd_decimal');

        if ($dbRate !== null) {
            return (string) $dbRate;
        }

        // Check fallback config
        $configCards = config("ai_credits.default_rate_cards.{$version}.{$provider}.{$model}.{$unit}");
        if ($configCards !== null) {
            return (string) $configCards;
        }

        $defaultCard = config("ai_credits.default_rate_cards.{$version}.default.{$unit}");
        if ($defaultCard !== null) {
            return (string) $defaultCard;
        }

        // Conservative safe default: $0.50/1M input ($0.00000050), $1.50/1M output ($0.00000150)
        return match ($unit) {
            'input_token' => '0.00000050',
            'output_token' => '0.00000150',
            'image' => '0.00200000',
            'audio_second' => '0.00050000',
            default => '0.00000100',
        };
    }

    private function resolveFxRate(string $version): float
    {
        $dbFx = DB::table('ai_fx_rates')
            ->where('version', $version)
            ->where('source', 'USD_INR')
            ->orderByDesc('date')
            ->value('rate_decimal');

        if ($dbFx !== null) {
            return (float) $dbFx;
        }

        return (float) config('ai_credits.default_fx_rate_usd_inr', 84.5000);
    }
}
