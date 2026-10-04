<?php

namespace App\Services\Tax;

use App\Services\Platform\CompanyContext;
use App\Services\ERP\CompanyWriteGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Provider lookup is read-only. An unavailable provider leaves party and invoice data untouched. */
class GstinLookupService
{
    public function lookup(string $gstin, CompanyContext $context, int $actor): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor);
        $gstin = Gstin::normalize($gstin);
        $result = Cache::remember('gst-lookup:'.$context->companyId.':'.$gstin, 3600, function () use ($gstin) {
            $url = config('compliance.gst_lookup_url');
            if (!$url) return ['gstin' => $gstin, 'status' => 'manual_required', 'verified' => false];
            if (!str_starts_with($url, 'https://')) return ['gstin' => $gstin, 'status' => 'unavailable', 'verified' => false, 'failure_category' => 'configuration'];
            try {
                $response = Http::withToken((string) config('compliance.gst_lookup_token'))
                    ->timeout(config('compliance.gst_lookup_timeout'))->get($url, ['gstin' => $gstin]);
                $data = $response->throw()->json();
                // The configured adapter endpoint must return this explicit normalized contract.
                if (($data['gstin'] ?? '') !== $gstin || !in_array($data['status'] ?? '', ['active', 'cancelled', 'suspended'], true)
                    || !is_string($data['legal_name'] ?? null) || strlen($data['legal_name']) > 191) {
                    throw new \UnexpectedValueException('Invalid registration response.');
                }
                return ['gstin' => $gstin, 'status' => $data['status'], 'legal_name' => $data['legal_name'],
                    'verified' => true, 'verified_at' => now()->toIso8601String()];
            } catch (\Throwable $error) {
                return ['gstin' => $gstin, 'status' => 'unavailable', 'verified' => false, 'failure_category' => 'provider'];
            }
        });
        DB::table('gst_lookup_audits')->insert(['company_id' => $context->companyId, 'user_id' => $actor,
            'gstin' => $gstin, 'status' => $result['status'], 'failure_category' => $result['failure_category'] ?? null, 'created_at' => now()]);
        return $result;
    }
}
