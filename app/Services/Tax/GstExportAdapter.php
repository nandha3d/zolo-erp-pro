<?php

namespace App\Services\Tax;

use App\Services\Platform\CompanyContext;
use Illuminate\Validation\ValidationException;

/** Versioned review format. No claim that this is accepted by the GST portal. */
class GstExportAdapter
{
    public function export(string $version, CompanyContext $context, string $from, string $to): array
    {
        if ($version !== 'zolo-gst-review-v1') {
            throw ValidationException::withMessages(['version' => 'Statutory filing requires a currently verified government schema adapter. Use the review export.']);
        }
        $projections = app(GstProjectionService::class); $rows = $projections->report($context, $from, $to);
        return ['schema_version' => $version, 'purpose' => 'review_only', 'filing_ready' => false,
            'company_id' => $context->companyId, 'branch_id' => $context->branchId,
            'period' => [$from, $to], 'summary' => $projections->summary($rows), 'documents' => $rows];
    }
}
