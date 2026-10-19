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
            'page' => 'nullable|integer|min:1', 'export' => 'nullable|in:csv']);
        $today = now(\App\Models\Company::findOrFail($context->companyId)->timezone ?: 'Asia/Kolkata');
        $filters['from'] = $filters['from'] ?? $today->copy()->startOfMonth()->toDateString();
        $filters['to'] = $filters['to'] ?? $today->toDateString();
        validator($filters, ['to' => 'after_or_equal:from'])->validate();
        $query = $queries->stockByLot($filters, $context, (int) auth()->id());
        if (($filters['export'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($query, $filters) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Product', 'Code', 'Lot', 'Warehouse', 'Base unit', 'Opening', 'Inward', 'Outward', 'Closing',
                    'Warehouse current', 'Customer custody current', 'GDNI current value', 'Owned current', 'From', 'To']);
                foreach ($query->cursor() as $row) {
                    $name = static fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value;
                    fputcsv($file, [$name($row->product), $name($row->product_code), $name($row->lot ?: 'Unbatched'), $name($row->warehouse),
                        $name($row->base_unit), $row->opening_qty, $row->inward_qty, $row->outward_qty, $row->closing_qty,
                        $row->current_qty, $row->custody_qty, $row->custody_value, $row->owned_current_qty, $filters['from'], $filters['to']]);
                }
                fclose($file);
            }, 'stock-by-lot.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        return view('backend.reports.stock-by-lot', [
            'rows' => $query->paginate(50)->withQueryString(), 'filters' => $filters,
            'products' => Product::forCompany($context)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::forCompany($context)->where('branch_id', $context->branchId)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
