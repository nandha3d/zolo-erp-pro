<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company inventory policy from companies.settings_json["inventory"]:
 *   negative_stock: allow|warn|block   (fallback: legacy general setting without_stock=yes -> allow, else block)
 *   expired_batch:  allow|warn|block   (default block)
 *   valuation:      weighted_average|standard (default weighted_average; standard uses products.cost)
 */
class InventoryPolicy
{
    private const CHOICES = [
        'negative_stock' => ['allow', 'warn', 'block'],
        'expired_batch' => ['allow', 'warn', 'block'],
        'valuation' => ['weighted_average', 'standard'],
    ];

    private array $settings = [];

    public function negativeStock(?int $companyId): string
    {
        return $this->setting($companyId, 'negative_stock')
            ?? (config('without_stock') === 'yes' ? 'allow' : 'block');
    }

    public function expiredBatch(?int $companyId): string
    {
        return $this->setting($companyId, 'expired_batch') ?? 'block';
    }

    public function valuation(?int $companyId): string
    {
        return $this->setting($companyId, 'valuation') ?? 'weighted_average';
    }

    private function setting(?int $companyId, string $key): ?string
    {
        if ($companyId === null) {
            return null;
        }
        if (!array_key_exists($companyId, $this->settings)) {
            $json = Schema::hasTable('companies')
                ? DB::table('companies')->where('id', $companyId)->value('settings_json')
                : null;
            $this->settings[$companyId] = (json_decode((string) $json, true) ?: [])['inventory'] ?? [];
        }
        $value = $this->settings[$companyId][$key] ?? null;

        return in_array($value, self::CHOICES[$key], true) ? $value : null;
    }
}
