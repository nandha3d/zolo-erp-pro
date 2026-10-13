<?php

namespace App\Services\JobWork;

use App\Models\Operations\JobWorkDispatch;
use App\Models\Operations\JobWorkOrder;
use App\Models\Operations\JobWorkReceipt;
use App\Models\Purchase;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A job worker's processing charge is part of the cost of what came back (AS 2 / Ind AS 2 conversion cost).
 * The charge is spread over the receipt's processed stock lines by value; the share still on hand raises stock value
 * through a value-only revaluation, and the share of output already consumed goes to cost of goods sold.
 */
class JobWorkCosting
{
    /** @return array{inventory:int, cogs:int} ledger units (1/10000) to debit instead of expense */
    public function capitalise(Purchase $purchase, int $amount, string $date, CompanyContext $context, int $actor): array
    {
        $receiptId = (int) ($purchase->attributes_json['job_work_receipt_id'] ?? 0);
        if ($amount <= 0 || !$receiptId) return ['inventory' => 0, 'cogs' => 0];
        $receipt = JobWorkReceipt::visibleIn($context)->lockForUpdate()->find($receiptId);
        $order = $receipt ? JobWorkOrder::visibleIn($context)->find(JobWorkDispatch::visibleIn($context)->find($receipt->dispatch_id)?->job_work_order_id) : null;
        if (!$receipt || !$order || $receipt->reversed_at || ($receipt->service_purchase_id && (int) $receipt->service_purchase_id !== $purchase->id)
            || (int) $order->job_worker_party_id !== (int) $purchase->supplier_id) {
            throw ValidationException::withMessages(['job_work_receipt_id' => 'Processing charges can only be billed by the job worker against an active, unbilled receipt.']);
        }
        $groups = $this->processedLines($receipt);
        if ($groups === []) throw ValidationException::withMessages(['job_work_receipt_id' => 'The receipt has no accepted stock to carry the processing charge.']);

        $weights = array_map(fn ($g) => $g['value'] > 0 ? $g['value'] : 0, $groups);
        if (array_sum($weights) <= 0) $weights = array_column($groups, 'qty');
        $shares = $this->spread($amount, $weights);
        // Only the part of the output still in stock can carry value; anything already sold or used goes to COGS.
        $onHand = [];
        foreach (array_unique(array_column($groups, 'product_id')) as $productId) {
            $processed = array_sum(array_map(fn ($g) => $g['product_id'] === $productId ? $g['qty'] : 0, $groups));
            $held = (float) DB::table('stock_movement_lines')->where('company_id', $context->companyId)->where('product_id', $productId)->sum('qty_base');
            $onHand[$productId] = $processed > 0 ? max(0, min(1, $held / $processed)) : 0;
        }
        $lines = []; $inventory = 0;
        foreach ($groups as $i => $group) {
            $share = (int) round($shares[$i] * $onHand[$group['product_id']]);
            if ($share <= 0) continue;
            $inventory += $share;
            $lines[] = ['product_id' => $group['product_id'], 'warehouse_id' => $group['warehouse_id'], 'batch_id' => $group['batch_id'],
                'variant_id' => $group['variant_id'], 'value' => $share / 10000,
                'attributes_json' => ['job_work_receipt_id' => $receipt->id, 'processing_charge' => true]];
        }
        if ($lines) {
            app(InventoryMovementService::class)->revalue($date, $lines, 'purchase', $purchase->id, $purchase->reference_no,
                'purchase:'.$purchase->id.':job-work-cost', $actor, $context, 'Job work processing cost '.$receipt->reference_no);
        }
        $qty = array_sum(array_column($groups, 'qty'));
        // Posted operation records are immutable models; the costing note is recorded beside them like the bill link.
        DB::table('job_work_receipts')->where('company_id', $context->companyId)->where('id', $receipt->id)->update(['details_json' => json_encode(($receipt->details_json ?? []) + ['processing_cost' => [
            'purchase_id' => $purchase->id, 'purchase_no' => $purchase->reference_no, 'amount' => $amount / 10000,
            'to_stock' => $inventory / 10000, 'to_cogs' => ($amount - $inventory) / 10000,
            'per_unit' => $qty > 0 ? round($amount / 10000 / $qty, 4) : null,
            'material_per_unit' => $qty > 0 ? round(array_sum(array_column($groups, 'value')) / 10000 / $qty, 4) : null,
        ]], JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        return ['inventory' => $inventory, 'cogs' => $amount - $inventory];
    }

    /** Stock that came back processed: conversion outputs, else the accepted material returned to the receiving warehouse. */
    private function processedLines(JobWorkReceipt $receipt): array
    {
        $movementId = $receipt->output_movement_id ?: $receipt->material_movement_id;
        if (!$movementId) return [];
        $groups = [];
        foreach (DB::table('stock_movement_lines')->where('stock_movement_id', $movementId)->where('qty_base', '>', 0)
            ->where('warehouse_id', $receipt->warehouse_id)->get() as $line) {
            $key = $line->product_id.'|'.$line->warehouse_id.'|'.$line->batch_id.'|'.$line->variant_id;
            $groups[$key] ??= ['product_id' => (int) $line->product_id, 'warehouse_id' => (int) $line->warehouse_id,
                'batch_id' => $line->batch_id ? (int) $line->batch_id : null, 'variant_id' => $line->variant_id ? (int) $line->variant_id : null, 'qty' => 0.0, 'value' => 0];
            $groups[$key]['qty'] += (float) $line->qty_base;
            $groups[$key]['value'] += LedgerAmount::units($line->value);
        }
        return array_values($groups);
    }

    /** @param array<int, float|int> $weights @return array<int, int> */
    private function spread(int $amount, array $weights): array
    {
        $sum = array_sum($weights); $result = array_fill(0, count($weights), 0);
        if ($sum <= 0) return $result;
        $remaining = $amount; $last = array_key_last(array_filter($weights, fn ($w) => $w > 0));
        foreach ($weights as $i => $weight) {
            if ($i === $last || $weight <= 0) continue;
            $result[$i] = (int) round($amount * $weight / $sum);
            $remaining -= $result[$i];
        }
        $result[$last] = $remaining;
        return $result;
    }
}
