<?php

namespace App\Services\JobWork;

use App\Models\Operations\JobWorkReceipt;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\Commercial\CommercialPermission;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use App\Services\Operations\OperationPosting;
use App\Services\Platform\CompanyContext;
use App\Support\LedgerAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Reclassifies only accepted stock, retaining the original GRN and the service bill's financial entries. */
class JobWorkStockNaming
{
    public function assertBillReversible(Purchase $bill, CompanyContext $context): void
    {
        if (empty($bill->attributes_json['job_work_receipt_id'])) return;
        $receipt = JobWorkReceipt::visibleIn($context)->where('service_purchase_id', $bill->id)->lockForUpdate()->first();
        $change = $receipt?->details_json['stock_name_change'] ?? null;
        if (!$change) return;
        if (DB::table('job_work_dispatches')->where('company_id', $context->companyId)->where('rework_of_receipt_id', $receipt->id)->whereNull('reversed_at')->exists()) {
            $this->reject('Reverse the rework DC before reversing this service bill.');
        }
        $lines = DB::table('stock_movement_lines')->where('company_id', $context->companyId)->where('stock_movement_id', $change['in_movement_id'])->get();
        foreach ($lines->groupBy(fn ($line) => $line->product_id.'|'.$line->batch_id) as $group) {
            $line = $group->first();
            $available = (float) DB::table('product_warehouse')->where('company_id', $context->companyId)->where('warehouse_id', $line->warehouse_id)
                ->where('product_id', $line->product_id)->where('product_batch_id', $line->batch_id)->whereNull('variant_id')->lockForUpdate()->sum('qty');
            if ($available + InventoryMovementService::EPSILON < $group->sum('qty_base')) {
                $this->reject('Returned stock was used. Reverse its sales or stock usage before reversing this service bill.');
            }
        }
    }

    public function apply(int $receiptId, array $data, string $key, CompanyContext $context, int $actor): JobWorkReceipt
    {
        validator($data, ['stock_names' => 'required|array|max:50', 'stock_names.*' => 'nullable|string|max:150'])->validate();
        if (!array_filter($data['stock_names'], fn ($name) => trim((string) $name) !== '')) $this->reject('Enter a returned stock name.');
        $data['receipt_id'] = $receiptId;
        return app(OperationPosting::class)->run('job_work_stock_name', JobWorkReceipt::class, 'operations.job_work', 'job_work.manage',
            $data, $key, $context, $actor, function ($data, $date) use ($receiptId, $context, $actor) {
                $receipt = JobWorkReceipt::visibleIn($context)->lockForUpdate()->findOrFail($receiptId);
                $bill = Purchase::visibleIn($context)->lockForUpdate()->findOrFail($receipt->service_purchase_id);
                return $this->forBill($receipt, $bill, $data['stock_names'] ?? [], $date, $context, $actor);
            });
    }

    public function forBill(JobWorkReceipt $receipt, Purchase $bill, array $names, string $date, CompanyContext $context, int $actor): JobWorkReceipt
    {
        validator(['stock_names' => $names], ['stock_names' => 'array|max:50', 'stock_names.*' => 'nullable|string|max:150'])->validate();
        $names = array_filter(array_map(fn ($name) => trim((string) $name), $names), fn ($name) => $name !== '');
        if (!$names) return $receipt->fresh();
        app(CommercialPermission::class)->assert('products-edit', $context, $actor);
        if ($receipt->reversed_at || $bill->reversed_at || $date < $receipt->business_date || $date < $bill->created_at->toDateString()
            || (int) ($bill->attributes_json['job_work_receipt_id'] ?? 0) !== $receipt->id) {
            $this->reject('Use an active service bill and a date on or after the bill and GRN.');
        }
        $details = $receipt->fresh()->details_json ?? [];
        if (!empty($details['stock_name_change'])) $this->reject('Returned stock already has its new name. Edit the service bill to correct it.');
        if (DB::table('job_work_dispatches')->where('company_id', $context->companyId)->where('rework_of_receipt_id', $receipt->id)->whereNull('reversed_at')->exists()) {
            $this->reject('Reverse the rework DC before changing its returned stock name.');
        }
        if ((float) ($details['processing_cost']['to_cogs'] ?? 0) > 0) $this->reject('Some returned stock was consumed before billing. Correct its stock usage before changing its name.');
        $sourceId = $receipt->output_movement_id ?: $receipt->material_movement_id;
        $source = DB::table('stock_movement_lines')->where('company_id', $context->companyId)->where('stock_movement_id', $sourceId)
            ->where('warehouse_id', $receipt->warehouse_id)->where('qty_base', '>', 0)->orderBy('id')->get();
        foreach (array_keys($names) as $id) if (!$source->contains('product_id', $id)) $this->reject('Name only accepted stock on this GRN.');
        $products = Product::forCompany($context)->whereIn('id', $source->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $out = []; $in = []; $items = []; $targets = []; $exact = [];
        foreach ($source as $line) {
            if (!isset($names[$line->product_id])) continue;
            $old = $products->get($line->product_id);
            $name = $names[$line->product_id];
            if (mb_strtolower($old->name) === mb_strtolower($name)) continue;
            if ($line->variant_id || $line->stock_identity_id || $old->is_variant || $old->is_imei) $this->reject('Tracked pieces, serials and variants need an explicit conversion GRN.');
            $target = $targets[$old->id] ??= $this->product($old, $name, $context, $actor);
            if ($target->unit_id != $old->unit_id || $target->type !== 'standard' || !$target->is_active || $target->is_variant || $target->is_imei) {
                $this->reject('The returned product must be active stock with the same base unit and compatible tracking.');
            }
            // An explicit lot is never borrowed from another product or replaced with unrelated available lots.
            $available = (float) DB::table('product_warehouse')->where('company_id', $context->companyId)->where('warehouse_id', $receipt->warehouse_id)
                ->where('product_id', $old->id)->where('product_batch_id', $line->batch_id)->whereNull('variant_id')->lockForUpdate()->sum('qty');
            $required = $source->where('product_id', $old->id)->where('batch_id', $line->batch_id)->sum('qty_base');
            if ($available + InventoryMovementService::EPSILON < $required) $this->reject('Not enough returned stock remains in its original warehouse and lot. Correct its stock usage first.');
            $batch = $line->batch_id ? DB::table('product_batches')->where('company_id', $context->companyId)->where('product_id', $old->id)->find($line->batch_id) : null;
            if (!$batch && $target->is_batch) $this->reject('The returned product requires a lot. Use an explicit conversion GRN.');
            $attributes = ['job_work_receipt_id' => $receipt->id, 'from_product_id' => $old->id, 'from_name' => $old->name,
                'to_product_id' => $target->id, 'to_name' => $target->name, 'source_line_id' => $line->id, 'from_batch_id' => $line->batch_id];
            $out[] = new StockLine(productId: $old->id, qty: (float) $line->qty_base, batchId: $line->batch_id, attributes: $attributes);
            $items[] = $attributes + ['qty' => (float) $line->qty_base, 'lot' => $batch->batch_no ?? null, 'warehouse_id' => $receipt->warehouse_id];
            $in[] = ['product' => $target, 'line' => $line, 'batch' => $batch, 'attributes' => $attributes];
            $group = $old->id.'|'.($line->batch_id ?? '');
            $exact[$group] ??= ['product_id' => $old->id, 'batch_id' => $line->batch_id, 'value' => 0];
            $exact[$group]['value'] += LedgerAmount::units($line->value);
        }
        if (!$out) return $receipt->fresh();
        // The already-posted processing revaluation belongs to the returned lot as well as its material cost.
        foreach ($exact as &$group) {
            $group['value'] += LedgerAmount::units(DB::table('stock_movement_lines as l')->join('stock_movements as m', 'm.id', '=', 'l.stock_movement_id')
                ->where('m.company_id', $context->companyId)->where('m.source_type', 'purchase')->where('m.source_id', $bill->id)
                ->where('m.movement_type', 'revaluation')->where('m.status', 'posted')->where('l.product_id', $group['product_id'])
                ->where('l.warehouse_id', $receipt->warehouse_id)->where('l.batch_id', $group['batch_id'])->sum('l.value'));
        }
        unset($group);
        $inventory = app(InventoryMovementService::class);
        $command = fn ($lines, $part) => new StockMovementCommand(date: $date, lines: $lines, warehouseId: $receipt->warehouse_id,
            sourceType: 'purchase', sourceId: $bill->id, sourceNo: $bill->reference_no,
            idempotencyKey: 'purchase:'.$bill->id.':stock-name:'.$part, reason: 'Returned stock name '.$receipt->reference_no, userId: $actor, context: $context);
        $issued = $inventory->issue($command($out, 'out'));
        $receives = []; $corrections = [];
        foreach ($in as $entry) {
            $line = $entry['line']; $target = $entry['product']; $batch = $entry['batch'];
            $group = $exact[$line->product_id.'|'.($line->batch_id ?? '')];
            $qty = $source->where('product_id', $line->product_id)->where('batch_id', $line->batch_id)->sum('qty_base');
            $cost = $group['value'] / 10000 / $qty;
            $receives[] = new StockLine(productId: $target->id, qty: (float) $line->qty_base, unitCost: $cost,
                batch: $batch ? array_filter(['batch_no' => $batch->batch_no, 'expired_date' => $batch->expired_date ?? null,
                    'mfg_date' => $batch->mfg_date ?? null, 'mrp' => $batch->mrp ?? null], fn ($value) => $value !== null) : null, attributes: $entry['attributes']);
        }
        $received = $inventory->receive($command($receives, 'in'));
        foreach ($exact as $group) {
            $issuedValue = -LedgerAmount::units($issued->lines->where('product_id', $group['product_id'])->where('batch_id', $group['batch_id'])->sum('value'));
            $targetId = $targets[$group['product_id']]->id;
            $newValue = LedgerAmount::units($received->lines->where('product_id', $targetId)->filter(fn ($l) => ($l->attributes_json['from_product_id'] ?? null) == $group['product_id']
                && ($l->attributes_json['from_batch_id'] ?? null) == $group['batch_id'])->sum('value'));
            if (abs($newValue - $group['value']) > 1) $this->reject('Returned cost exceeds stock precision.');
            $delta = $issuedValue - $newValue;
            if ($delta) $corrections[] = ['product_id' => $group['product_id'], 'warehouse_id' => $receipt->warehouse_id,
                'batch_id' => $group['batch_id'], 'value' => $delta / 10000, 'attributes_json' => ['job_work_receipt_id' => $receipt->id, 'returned_stock_name' => true]];
        }
        if ($corrections) $inventory->revalue($date, $corrections, 'purchase', $bill->id, $bill->reference_no,
            'purchase:'.$bill->id.':stock-name:cost', $actor, $context, 'Preserve returned material and processing cost '.$receipt->reference_no);
        foreach ($targets as $oldId => $target) {
            $balance = (float) DB::table('stock_movement_lines')->where('company_id', $context->companyId)->where('product_id', $oldId)->sum('value');
            $remaining = (float) DB::table('stock_movement_lines')->where('company_id', $context->companyId)->where('product_id', $oldId)->sum('qty_base');
            if ($balance < -0.0001 || (abs($remaining) < InventoryMovementService::EPSILON && abs($balance) > 0.0001)) {
                $this->reject('The original stock cannot carry a negative or stranded remaining value. Correct its stock usage first.');
            }
            $old = $products[$oldId];
            $note = 'Returned as '.$target->name.' ('.$receipt->reference_no.' / '.$bill->reference_no.').';
            if (array_key_exists('short_description', $old->getAttributes())) {
                $old->forceFill(['short_description' => trim(($old->short_description ?? '')."\n".$note)])->save();
            } elseif (array_key_exists('product_details', $old->getAttributes())) {
                $old->forceFill(['product_details' => ($old->product_details ?? '').'<p>'.e($note).'</p>'])->save();
            }
        }
        $details['stock_name_change'] = ['purchase_id' => $bill->id, 'purchase_no' => $bill->reference_no, 'date' => $date,
            'out_movement_id' => $issued->id, 'in_movement_id' => $received->id, 'items' => $items];
        DB::table('job_work_receipts')->where('id', $receipt->id)->update(['details_json' => json_encode($details, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        return $receipt->fresh();
    }

    private function product(Product $source, string $name, CompanyContext $context, int $actor): Product
    {
        $matches = Product::forCompany($context)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->lockForUpdate()->get();
        if ($matches->count() > 1) $this->reject('More than one product has this name. Give the returned product a unique name.');
        if ($matches->isNotEmpty()) return $matches->first();
        app(CommercialPermission::class)->assert('products-add', $context, $actor);
        $copy = array_intersect_key($source->getAttributes(), array_flip(['company_id', 'branch_id', 'financial_year_id', 'type', 'barcode_symbology',
            'brand_id', 'category_id', 'unit_id', 'purchase_unit_id', 'sale_unit_id', 'cost', 'price', 'wholesale_price', 'tax_id', 'tax_method',
            'hsn_code', 'tax_category_id', 'sales_account_id', 'purchase_account_id', 'is_batch', 'is_variant', 'is_imei',
            'track_inventory', 'negative_stock_policy', 'neg_stock_allowed']));
        $code = 'JW-'.$source->id.'-'.strtoupper(substr(hash('sha256', $context->companyId.'|'.$name), 0, 10));
        $attributes = $copy + ['name' => $name, 'print_name' => $name, 'code' => $code, 'slug' => Str::slug($name).'-'.strtolower($code),
            'qty' => 0, 'is_active' => true];
        return Product::forceCreate(array_intersect_key($attributes, array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('products'))));
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['stock_names' => $message]);
    }
}
