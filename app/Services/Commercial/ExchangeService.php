<?php

namespace App\Services\Commercial;

use App\Models\{Exchange, Returns};
use App\Models\Accounting\AccountOpenItem;
use App\Services\Accounting\OpenItemService;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\{CompanyContext, DocumentNumberService};
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeService
{
    /** Start from an approved ordinary return. New sale and settlement remain authoritative. */
    public function create(int $returnId, array $saleData, string $key, CompanyContext $context, int $actor): Exchange
    {
        abort_unless(config('commercial.enabled') && config('compliance.enabled'), 503, 'Compliance posting remains gated.');
        $context = app(CompanyWriteGuard::class)->context($context, $actor);
        app(CommercialPermission::class)->assert('exchanges-add', $context, $actor);
        app(\App\Services\Platform\CapabilityService::class)->assertEnabled('sales.exchange', $context);
        if (!$key || strlen($key) > 120) throw ValidationException::withMessages(['key' => 'Use a retry key of at most 120 characters.']);
        $hash = hash('sha256', json_encode([$returnId, $context->branchId, $context->financialYearId, $saleData], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($returnId, $saleData, $key, $hash, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $existing = Exchange::forCompany($context)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) throw ValidationException::withMessages(['key' => 'Exchange key already used for different effects.']);
                return $existing;
            }
            $return = Returns::forCompany($context)->where('branch_id', $context->branchId)->lockForUpdate()->findOrFail($returnId);
            if (!$return->posted_at || $return->note_type !== 'credit' || $return->adjustment_type !== 'quantity'
                || Exchange::forCompany($context)->where('return_id', $returnId)->exists()
                || (int) ($saleData['customer_id'] ?? 0) !== (int) $return->customer_id
                || ($saleData['business_date'] ?? '') < $return->created_at->toDateString()) {
                throw ValidationException::withMessages(['return_id' => 'Use an unused approved quantity return for the same customer and a valid replacement date.']);
            }
            $saleData['sale_status'] = 1;
            $sale = app(SaleApplicationService::class)->create(new SaleCommand($saleData, 'exchange-sale:'.$key, $actor, $context));
            $credit = AccountOpenItem::forCompany($context)->where('source_type', 'sale_credit_note')->where('source_id', $return->id)->where('open_amount', '<', 0)->first();
            $invoice = AccountOpenItem::forCompany($context)->where('source_type', 'sale')->where('source_id', $sale->id)->where('open_amount', '>', 0)->first();
            $allocated = 0;
            if ($credit && $invoice) {
                $allocated = min(-LedgerAmount::units($credit->open_amount), LedgerAmount::units($invoice->open_amount));
                app(OpenItemService::class)->allocate($invoice->id, $credit->id, LedgerAmount::decimal($allocated),
                    $saleData['business_date'], 'exchange-allocation:'.$key, $context, null, $actor);
            }
            $numbers = app(DocumentNumberService::class); $reservation = $numbers->reserve('exchange', $context, $saleData['business_date'], $actor);
            $exchange = Exchange::forceCreate(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
                'financial_year_id' => $context->financialYearId, 'reference_no' => $reservation->formatted_number,
                'original_sale_id' => $return->sale_id, 'warehouse_id' => $sale->warehouse_id, 'customer_id' => $sale->customer_id,
                'biller_id' => $sale->biller_id, 'returned_items' => $return->products->toArray(), 'returned_total' => $return->grand_total,
                'exchanged_items' => $sale->productSales->toArray(), 'exchanged_total' => $sale->grand_total,
                'difference_amount' => round((float) $sale->grand_total - (float) $return->grand_total, 4),
                'payment_status' => $sale->fresh()->payment_status === 4 && (!$credit || (float) $credit->fresh()->open_amount === 0.0) ? 'completed' : 'settlement_due',
                'note' => 'Authoritative return '.$return->reference_no.' and replacement '.$sale->reference_no,
                'user_id' => $actor, 'return_id' => $return->id, 'replacement_sale_id' => $sale->id,
                'idempotency_key' => $key, 'request_hash' => $hash, 'created_at' => $saleData['business_date'], 'posted_at' => now()]);
            $numbers->assign($reservation, $exchange);
            app(CommercialApplicationService::class)->audit('exchanged', $sale, $context, $actor, ['exchange_id' => $exchange->id, 'return_id' => $return->id, 'allocated' => $allocated / 10000]);
            return $exchange;
        });
    }
}
