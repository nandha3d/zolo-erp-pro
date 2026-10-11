<?php

namespace App\Services\Tax;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Masters India GST verification API (https://docs.mastersindia.co/gst-verification-api).
 * Lookups are paid per call, so results are cached; any failure returns null/[] and the form falls back to manual entry.
 */
class MastersIndiaGstClient
{
    private const BASE = 'https://commonapi.mastersindia.co';

    public function configured(): bool
    {
        $c = config('compliance.mastersindia');
        return !empty($c['username']) && !empty($c['password']) && !empty($c['client_id']) && !empty($c['client_secret']);
    }

    /** Registered details for one GSTIN, normalized to the party form fields. */
    public function searchGstin(string $gstin): ?array
    {
        $data = $this->get('searchgstin', ['gstin' => $gstin], 'gst-mi:gstin:'.$gstin);
        return is_array($data) && !empty($data['gstin']) ? self::normalize($data) : null;
    }

    /** Registrations whose legal name matches; each entry normalized like searchGstin(). */
    public function searchLegalName(string $name): array
    {
        $name = trim($name);
        $data = $this->get('searchlegalname', ['legalname' => $name], 'gst-mi:name:'.md5(strtolower($name)));
        if (!is_array($data)) return [];
        $rows = array_is_list($data) ? $data : [$data];
        return array_values(array_map([self::class, 'normalize'], array_filter($rows, fn ($row) => !empty($row['gstin']))));
    }

    public static function normalize(array $row): array
    {
        $addr = $row['pradr']['addr'] ?? [];
        $address = implode(', ', array_filter([$addr['bno'] ?? '', $addr['flno'] ?? '', $addr['bnm'] ?? '', $addr['st'] ?? '', $addr['loc'] ?? '']));
        return [
            'gstin' => $row['gstin'],
            'legal_name' => $row['lgnm'] ?? '',
            'trade_name' => $row['tradeNam'] ?? '',
            'status' => $row['sts'] ?? '',
            'address' => $address,
            'city' => ($addr['city'] ?? '') ?: ($addr['dst'] ?? ''),
            'state' => $addr['stcd'] ?? '',
            'postal_code' => $addr['pncd'] ?? '',
        ];
    }

    private function get(string $endpoint, array $query, string $cacheKey): mixed
    {
        if (!$this->configured()) return null;
        if (Cache::has($cacheKey)) return Cache::get($cacheKey);
        try {
            $response = Http::timeout(8)->acceptJson()
                ->withToken($this->token())
                ->withHeaders(['client_id' => config('compliance.mastersindia.client_id')])
                ->get(self::BASE.'/commonapis/'.$endpoint, $query);
            $body = $response->json();
            if (!$response->successful() || !is_array($body) || ($body['error'] ?? true) !== false) return null;
            Cache::put($cacheKey, $body['data'] ?? null, now()->addDays(30));
            return $body['data'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function token(): string
    {
        // Tokens last 6 hours; refresh a little early.
        return Cache::remember('gst-mi:token', now()->addHours(5), function () {
            $c = config('compliance.mastersindia');
            return Http::timeout(8)->acceptJson()->post(self::BASE.'/oauth/access_token', [
                'username' => $c['username'], 'password' => $c['password'],
                'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'grant_type' => 'password',
            ])->throw()->json('access_token') ?: throw new \RuntimeException('Masters India returned no access token.');
        });
    }
}
