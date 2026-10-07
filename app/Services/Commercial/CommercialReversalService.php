<?php

namespace App\Services\Commercial;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\StockMovement;
use App\Services\Accounting\AccountingPostingService;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialReversalService
{
    public function reverse(Sale|Purchase $document, string $date, string $reason, ?CompanyContext $context = null): Sale|Purchase
    {
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, auth()->id());
        $kind = $document instanceof Sale ? 'sale' : 'purchase';
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-delete' : 'purchases-delete', $context, auth()->id());
        if (strlen(trim($reason)) < 3 || strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Give a reason between 3 and 500 characters.']);
        }
        return DB::transaction(function () use ($document, $guard, $context, $date, $reason, $kind) {
            $guard->begin($context, $date);
            $document = $document::visibleIn($context)->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->reversed_at) {
                return $document;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn($kind === 'sale' ? 'returns' : 'return_purchases', 'posted_at')
                && DB::table($kind === 'sale' ? 'returns' : 'return_purchases')->where('company_id', $context->companyId)
                    ->where($kind.'_id', $document->id)->whereNotNull('posted_at')->exists()) {
                throw ValidationException::withMessages(['document' => 'A source with posted notes cannot be reversed again. Post a reviewed financial adjustment instead.']);
            }
            if ((int) $document->branch_id !== $context->branchId || !$document->financial_year_id || $date < $document->created_at->toDateString()) {
                throw ValidationException::withMessages(['document' => 'Legacy documents require reviewed migration before reversal.']);
            }
            // All settlement journals reverse too; original payments stay as immutable source history.
            $paymentIds = $document->payments()->forCompany($context)->pluck('id');
            $journals = JournalEntry::forCompany($context)->where(function ($q) use ($kind, $document, $paymentIds) {
                $q->where(fn ($q) => $q->where('reference_type', $kind)->where('reference_id', $document->id))
                    ->orWhere(fn ($q) => $q->where('reference_type', 'payment')->whereIn('reference_id', $paymentIds));
            })->whereNull('reversal_of_id')->orderByDesc('id')->lockForUpdate()->get();
            foreach ($journals as $journal) {
                app(AccountingPostingService::class)->reverse($journal->id, $date, $reason, $context);
            }
            $movements = StockMovement::where('company_id', $context->companyId)->where('branch_id', $context->branchId)
                ->where('source_type', $kind)->where('source_id', $document->id)->where('status', 'posted')
                ->whereNull('reversal_of_id')->orderByDesc('id')->lockForUpdate()->get();
            foreach ($movements as $movement) {
                if ($movement->projection_mode !== 'applied') {
                    throw ValidationException::withMessages(['document' => 'Shadow stock requires reviewed migration before reversal.']);
                }
                app(InventoryMovementService::class)->reverse($movement, $reason, auth()->id(), $date);
            }
            $document->forceFill(['reversed_at' => now(), 'reversal_reason' => $reason])->save();
            app(\App\Services\Tax\GstProjectionService::class)->reverse($document, $kind, $date, $context);
            app(CommercialApplicationService::class)->audit('reversed', $document, $context, auth()->id(), ['date' => $date, 'reason' => $reason]);
            return $document;
        });
    }

    public function replace(Sale|Purchase $document, array $data, string $key, string $date, string $reason, ?CompanyContext $context = null): Sale|Purchase
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        return DB::transaction(function () use ($document, $data, $key, $date, $reason, $context) {
            $kind = $document instanceof Sale ? 'sale' : 'purchase';
            $document = $document::visibleIn($context)->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->reversed_at) {
                $retry = DB::table('idempotency_keys')->where('company_id', $context->companyId)->where('key', $key)
                    ->where('response_type', $kind)->value('response_ref');
                if (!$retry || !$document::visibleIn($context)->whereKey($retry)->where('replaces_id', $document->id)->exists()) {
                    throw ValidationException::withMessages(['document' => 'This document has already been reversed. Edit its active replacement instead.']);
                }
            }
            // Include the source and reason in the retry contract to prevent reuse for another replacement.
            $data['replacement_reason'] = [$date, $reason];
            $this->reverse($document, $date, $reason, $context);
            $replacement = app(CommercialApplicationService::class)->create($kind, $data, $key, auth()->id(), $context, $document->id);
            return $replacement;
        });
    }
}
