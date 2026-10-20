<?php

namespace App\Http\Controllers;

use App\Services\ERP\ReviewedProductImport;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewedProductImportController extends Controller
{
    public function index(Request $request, ReviewedProductImport $imports)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $imports->authorize($context, (int) auth()->id());
        $preview = $request->filled('preview') ? $imports->find((int) $request->preview, $context, (int) auth()->id()) : null;
        $masters = [];
        foreach (['categories', 'units', 'tax_categories'] as $table) {
            $masters[$table] = DB::table($table)->where('company_id', $context->companyId)->orderBy($table === 'units' ? 'unit_name' : 'name')->get();
        }
        return view('backend.product.import', compact('preview', 'masters'));
    }

    public function template(ReviewedProductImport $imports)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $imports->authorize($context, (int) auth()->id());
        return response()->streamDownload(function () {
            $stream = fopen('php://output', 'w'); fputcsv($stream, ReviewedProductImport::COLUMNS); fclose($stream);
        }, 'product-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, ReviewedProductImport $imports)
    {
        $request->validate(['file' => 'required|file|max:1024']);
        $record = $imports->preview($request->file('file'), app(CompanyContextResolver::class)->forActor(), (int) auth()->id());
        return $request->expectsJson() ? response()->json(['data' => $record])
            : redirect()->route('products.import.index', ['preview' => $record->id]);
    }

    public function commit(Request $request, int $id, ReviewedProductImport $imports)
    {
        $request->validate(['confirm' => 'accepted']);
        $results = $imports->commit($id, app(CompanyContextResolver::class)->forActor(), (int) auth()->id());
        return $request->expectsJson() ? response()->json(['data' => $results])
            : redirect()->route('products.import.index', ['preview' => $id])->with('message', count($results).' items created.');
    }
}
