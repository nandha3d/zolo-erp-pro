<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DeliveryChallan;
use App\Models\GoodsReceivedNote;
use App\Services\Commercial\MaterialDocumentService;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** GRN and DC move stock when saved; edits and cancellations undo that posting first. */
trait PostsMaterialDocuments
{
    private function materialContext(): ?CompanyContext
    {
        return request()->attributes->get(CompanyContext::class);
    }

    private function postMaterial(GoodsReceivedNote|DeliveryChallan $document): void
    {
        $service = app(MaterialDocumentService::class);
        if ($service->enabled() && ($context = $this->materialContext())) {
            $orders = app(\App\Services\Commercial\OrderLifecycleService::class);
            $kind = $document instanceof DeliveryChallan ? 'dc' : 'grn';
            $items = $document->items()->orderBy('id')->get()->map(fn ($line, $index) => [
                'product_id' => $line->product_id, 'qty' => $line->qty, 'unit_id' => $line->unit_id,
                'variant_id' => $line->variant_id,
                'product_batch_id' => $line->batch_no ? DB::table('product_batches')->where('product_id', $line->product_id)
                    ->where('company_id', $context->companyId)->where('batch_no', $line->batch_no)->value('id') : null,
                $kind === 'dc' ? 'sale_unit_id' : 'purchase_unit_id' => $line->unit_id,
                'batch' => $line->batch_no ? ['batch_no' => $line->batch_no] : null,
                'order_item_id' => request()->input('order_item_id.'.$index)])->all();
            $data = ['business_date' => ($kind === 'dc' ? $document->challan_date : $document->grn_date)->toDateString(),
                'warehouse_id' => $document->warehouse_id, 'items' => $items,
                $kind === 'dc' ? 'customer_id' : 'supplier_id' => $kind === 'dc' ? $document->customer_id : $document->supplier_id,
                $kind === 'dc' ? 'sales_order_id' : 'purchase_order_id' => request()->input($kind === 'dc' ? 'sales_order_id' : 'purchase_order_id')];
            $plan = $orders->prepareConsumer($kind, $data, $context, (int) Auth::id());
            $service->post($document, $context, (int) Auth::id());
            $orders->consume($document, $plan, $context);
        }
    }

    private function unpostMaterial(GoodsReceivedNote|DeliveryChallan $document, string $reason): void
    {
        if ($document->posted_at) {
            app(MaterialDocumentService::class)->unpost($document, $reason, $this->materialContext() ?? abort(409, 'Choose a company first.'), (int) Auth::id());
            app(\App\Services\Commercial\OrderLifecycleService::class)->release($document, $this->materialContext());
        }
    }

    /** Return unbilled goods: all of them, or item id => quantity in the item's unit. */
    private function returnMaterial(Request $request, string $model, int $id)
    {
        $request->validate(['business_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:3|max:500',
            'items' => 'nullable|array', 'items.*' => 'numeric|min:0', 'idempotency_key' => 'nullable|string|max:150']);
        $context = $this->materialContext() ?? abort(409, 'Choose a company first.');
        $document = $model::query()->where('company_id', $context->companyId)->findOrFail($id);
        DB::transaction(fn () => app(MaterialDocumentService::class)->returnOpen($document, $request->input('items'),
            $request->business_date, $request->reason, $context, (int) Auth::id(), $request->input('idempotency_key')));
        $message = 'Unbilled goods returned.';
        return $request->expectsJson() ? response()->json(['success' => true, 'message' => $message, 'status' => $document->fresh()->status])
            : redirect()->back()->with('message', $message);
    }

    /** Open (unbilled) quantity per line for the register and for loading the rest into a bill. */
    private function materialLine(object $item, array $open): array
    {
        $order = \Illuminate\Support\Facades\Schema::hasTable('commercial_order_allocations')
            ? DB::table('commercial_order_allocations as allocation')->join('commercial_order_items as item', 'item.id', '=', 'allocation.order_item_id')
                ->where('allocation.company_id', $item->company_id)->where('allocation.consumer_type', $item instanceof \App\Models\DeliveryChallanItem ? 'dc' : 'grn')
                ->where('allocation.consumer_item_id', $item->id)->where('allocation.stage', 'delivery')->whereNull('allocation.released_at')
                ->select('allocation.order_item_id', 'item.order_id')->first() : null;
        return ['batch_no' => $item->batch_no, 'variant_id' => $item->variant_id,
            'base_factor' => $item->qty_base !== null && $item->qty > 0 ? $item->qty_base / $item->qty : null,
            'open_qty' => $open[$item->id] ?? (float) $item->qty,
            'order_item_id' => $order?->order_item_id, 'order_id' => $order?->order_id,
            'product_batch_id' => $item->batch_no ? DB::table('product_batches')->where('product_id', $item->product_id)->where('batch_no', $item->batch_no)->value('id') : null,
            'billed_qty_base' => (float) $item->billed_qty_base, 'returned_qty_base' => (float) $item->returned_qty_base];
    }

    /** Product IDs are already company-scoped by the material catalog query. */
    private function materialVariants(array $productIds): \Illuminate\Support\Collection
    {
        if (!$productIds) return collect();
        return DB::table('product_variants')->join('variants', 'variants.id', '=', 'product_variants.variant_id')
            ->where('variants.company_id', $this->materialContext()?->companyId)->whereIn('product_variants.product_id', $productIds)
            ->orderBy('product_variants.position')->select('product_variants.product_id', 'product_variants.variant_id', 'product_variants.item_code', 'variants.name')
            ->get()->groupBy('product_id');
    }

    private function materialHistory(GoodsReceivedNote|DeliveryChallan $document): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('material_settlements')) return [];
        return DB::table('material_settlements')->where('company_id', $document->company_id)
            ->where('document_type', $document instanceof DeliveryChallan ? 'dc' : 'grn')->where('document_id', $document->id)
            ->orderBy('id')->get()->map(fn ($row) => [
                'type' => $row->settled_by_type, 'id' => $row->settled_by_id, 'qty_base' => $row->qty_base,
                'value' => $row->value, 'released_at' => $row->released_at,
                'reference_no' => in_array($row->settled_by_type, ['sale', 'purchase'], true)
                    ? DB::table($row->settled_by_type === 'sale' ? 'sales' : 'purchases')->where('company_id', $document->company_id)->where('id', $row->settled_by_id)->value('reference_no') : 'Return #'.$row->settled_by_id,
                'url' => in_array($row->settled_by_type, ['sale', 'purchase'], true) ? url(($row->settled_by_type === 'sale' ? 'sales' : 'purchases').'?show_bill='.$row->settled_by_id) : null])->all();
    }
}
