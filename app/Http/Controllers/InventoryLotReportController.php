<?php

namespace App\Http\Controllers;

use App\Models\{Product, Warehouse};
use App\Services\Industry\IndustryStockQueries;
use App\Services\Platform\{CompanyContext, CompanyContextResolver};
use Illuminate\Http\Request;

class InventoryLotReportController extends Controller
{
    public function index(Request $request, IndustryStockQueries $queries)
    {
        $context = $request->attributes->get(CompanyContext::class) ?? app(CompanyContextResolver::class)->forActor();
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'product_id' => 'nullable|integer|min:1', 'warehouse_id' => 'nullable|integer|min:1', 'lot' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1', 'export' => 'nullable|in:csv', 'group_by' => 'nullable|in:product,lot']);
        $filters['group_by'] = $filters['group_by'] ?? 'product';
        $today = now(\App\Models\Company::findOrFail($context->companyId)->timezone ?: 'Asia/Kolkata');
        $filters['from'] = $filters['from'] ?? $today->copy()->startOfMonth()->toDateString();
        $filters['to'] = $filters['to'] ?? $today->toDateString();
        validator($filters, ['to' => 'after_or_equal:from'])->validate();
        $query = $queries->stockByLot($filters, $context, (int) auth()->id());
        if (($filters['export'] ?? null) === 'csv') {
            $stockNameNotes = $this->stockNameNotes($context, $filters);
            return response()->streamDownload(function () use ($query, $filters, $stockNameNotes) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Product', 'Code', 'Lot', 'Warehouse', 'Base unit', 'Opening', 'Inward', 'Outward', 'Closing',
                    'Warehouse current', 'Customer custody current', 'GDNI current value', 'Owned current', 'From', 'To', 'Returned stock link']);
                foreach ($query->cursor() as $row) {
                    $name = static fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value;
                    fputcsv($file, [$name($row->product), $name($row->product_code), $name($row->lot ?: 'Unbatched'), $name($row->warehouse),
                        $name($row->base_unit), $row->opening_qty, $row->inward_qty, $row->outward_qty, $row->closing_qty,
                        $row->current_qty, $row->custody_qty, $row->custody_value, $row->owned_current_qty, $filters['from'], $filters['to'],
                        $name(implode('; ', $stockNameNotes[$row->product_id.'|'.$row->warehouse_id.'|'.$row->lot] ?? []))]);
                }
                fclose($file);
            }, 'stock-by-lot.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $rows = $query->paginate(50)->withQueryString();
        return view('backend.reports.stock-by-lot', [
            'rows' => $rows, 'filters' => $filters, 'stockNameNotes' => $this->stockNameNotes($context, $filters),
            'products' => Product::forCompany($context)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::forCompany($context)->where('branch_id', $context->branchId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function stockNameNotes(CompanyContext $context, array $filters): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('job_work_receipts')) return [];
        $notes = [];
        $combined = ($filters['group_by'] ?? 'product') === 'product' && empty($filters['lot']);
        $receipts = \Illuminate\Support\Facades\DB::table('job_work_receipts')->where('company_id', $context->companyId)
            ->where('branch_id', $context->branchId)->whereNull('reversed_at')->whereNotNull('service_purchase_id')
            ->where('details_json', 'like', '%"stock_name_change"%')->get(['reference_no', 'details_json']);
        foreach ($receipts as $receipt) {
            $change = json_decode($receipt->details_json ?? '{}', true)['stock_name_change'] ?? null;
            if (!$change || $change['date'] > $filters['to']) continue;
            foreach ($change['items'] as $item) {
                $suffix = '|'.$item['warehouse_id'].'|'.($combined ? 'Combined lots' : ($item['lot'] ?? ''));
                $ref = ' ('.$receipt->reference_no.' / '.$change['purchase_no'].')';
                $qty = rtrim(rtrim(number_format($item['qty'], 4, '.', ''), '0'), '.');
                $notes[$item['from_product_id'].$suffix][] = $qty.' returned as '.$item['to_name'].$ref;
                $notes[$item['to_product_id'].$suffix][] = $qty.' received from '.$item['from_name'].$ref;
            }
        }
        return $notes;
    }
}
