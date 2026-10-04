<?php

namespace App\Services\Commercial;

use App\Models\DamageStock;
use App\Services\Accounting\AccountingService;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Inventory\{InventoryMovementService, StockLine, StockMovementCommand};
use App\Services\Platform\{CompanyContext, DocumentNumberService};
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLossService
{
    public function create(array $data, string $key, CompanyContext $context, int $actor): DamageStock
    {
        abort_unless(config('commercial.enabled') && config('compliance.enabled'), 503, 'Compliance posting remains gated.');
        $context = app(CompanyWriteGuard::class)->context($context, $actor);
        app(CommercialPermission::class)->assert('damage-stock-add', $context, $actor);
        app(\App\Services\Platform\CapabilityService::class)->assertEnabled('inventory.damage_stock', $context);
        validator($data, ['business_date' => 'required|date_format:Y-m-d', 'product_id' => 'required|integer|min:1',
            'warehouse_id' => 'required|integer|min:1', 'qty' => 'required|numeric|gt:0',
            'disposition' => 'required|in:damaged,expired,spillage,theft,quality_reject,sample,internal_consumption',
            'reason' => 'required|string|min:3|max:500'])->validate();
        if (!$key || strlen($key) > 150) throw ValidationException::withMessages(['key' => 'A bounded retry key is required.']);
        $hash = hash('sha256', json_encode([$context->branchId, $context->financialYearId, $data], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($data, $key, $hash, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $existing = DamageStock::forCompany($context)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) throw ValidationException::withMessages(['key' => 'Key already used for a different stock loss.']);
                return $existing;
            }
            $guard = app(CompanyWriteGuard::class); $guard->begin($context, $data['business_date']);
            $guard->warehouse($data['warehouse_id'], $context, $actor);
            $product = $guard->owned(\App\Models\Product::class, $data['product_id'], $context, 'product_id');
            if (in_array($product->type, ['service', 'digital'], true)) throw ValidationException::withMessages(['product_id' => 'Service items have no stock loss.']);
            $qty = app(CommercialPricing::class)->number($data['qty'], 'qty', 4);
            $numbers = app(DocumentNumberService::class); $reservation = $numbers->reserve('damage', $context, $data['business_date'], $actor);
            $details = array_intersect_key($data, array_flip(['variant_id', 'product_batch_id', 'serials', 'stock_identity_id', 'uom_id']));
            $loss = DamageStock::forceCreate(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
                'financial_year_id' => $context->financialYearId, 'reference_no' => $reservation->formatted_number,
                'product_id' => $product->id, 'warehouse_id' => $data['warehouse_id'], 'variant_id' => $data['variant_id'] ?? null,
                'qty' => $qty, 'unit_cost' => 0, 'total_loss' => 0, 'reason' => $data['disposition'], 'note' => $data['reason'],
                'user_id' => $actor, 'idempotency_key' => $key, 'request_hash' => $hash, 'stock_details_json' => $details, 'created_at' => $data['business_date']]);
            $numbers->assign($reservation, $loss);
            $movement = app(InventoryMovementService::class)->issue(new StockMovementCommand(
                date: $data['business_date'], lines: [StockLine::fromArray(['product_id' => $product->id, 'qty' => $qty] + $details)],
                warehouseId: $data['warehouse_id'], sourceType: 'damage', sourceId: $loss->id, sourceNo: $loss->reference_no,
                idempotencyKey: 'damage:'.$loss->id, reason: $data['reason'], userId: $actor, context: $context, purpose: 'disposal'));
            $value = abs(LedgerAmount::units((float) $movement->lines->sum('value')));
            $loss->forceFill(['unit_cost' => $value / 10000 / $qty, 'total_loss' => $value / 10000])->save();
            if ($value) {
                $accounts = app(PostingAccounts::class);
                app(AccountingService::class)->postJournalEntry(['entry_date' => $data['business_date'], 'reference_type' => 'damage',
                    'reference_id' => $loss->id, 'reference_no' => $loss->reference_no, 'posting_key' => 'damage:'.$loss->id.':v1',
                    'created_by' => $actor, 'description' => $data['reason']], [
                    ['chart_of_account_id' => $accounts->account(in_array($data['disposition'], ['sample', 'internal_consumption'], true) ? 'operating_expense' : 'damage', $context, $actor), 'debit' => LedgerAmount::decimal($value), 'credit' => 0],
                    ['chart_of_account_id' => $accounts->account('inventory', $context, $actor), 'debit' => 0, 'credit' => LedgerAmount::decimal($value)],
                ], $context);
            }
            $loss->forceFill(['posted_at' => now()])->save();
            DB::table('commercial_audit_events')->insert(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
                'user_id' => $actor, 'event' => 'stock_loss', 'source_type' => 'damage', 'source_id' => $loss->id,
                'details_json' => json_encode(['movement_id' => $movement->id, 'reason' => $data['reason']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            return $loss;
        });
    }
}
