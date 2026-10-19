<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\Commercial\CommercialPermission;
use App\Services\Commercial\ProductAliasService;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductAliasController extends Controller
{
    public function index(Request $request)
    {
        $context = $request->attributes->get(CompanyContext::class);
        $permission = app(CommercialPermission::class);
        $permission->assert('products-index', $context, $request->user()->id);
        $canEdit = $permission->allows('products-edit', $context, $request->user()->id);
        $request->validate(['q' => 'nullable|string|max:150']);
        $query = ProductAlias::forCompany($context)->join('products', 'product_aliases.product_id', '=', 'products.id')
            ->where('products.company_id', $context->companyId)->select('product_aliases.*', 'products.name as product_name', 'products.code');
        if ($request->filled('q')) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], ProductAliasService::normalize($request->q));
            $query->whereRaw("product_aliases.normalized_name LIKE ? ESCAPE '!'", [$escaped.'%']);
        }
        $aliases = $query->orderByDesc('product_aliases.id')->paginate(50)->withQueryString();
        $products = Product::forCompany($context)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $customers = DB::table('customers')->where('company_id', $context->companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $suppliers = DB::table('suppliers')->where('company_id', $context->companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('backend.product.aliases', compact('aliases', 'products', 'customers', 'suppliers', 'canEdit'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['product_id' => 'required|integer|min:1', 'name' => 'required|string|max:150',
            'party_type' => 'required|in:company,customer,supplier', 'party_id' => 'nullable|integer|min:1']);
        $alias = app(ProductAliasService::class)->create($data, $request->attributes->get(CompanyContext::class), $request->user()->id);
        return $request->expectsJson() ? response()->json(['data' => $alias], 201) : redirect()->route('product-aliases.index')->with('message', 'Alternate name saved.');
    }

    public function destroy(Request $request, int $id)
    {
        $context = $request->attributes->get(CompanyContext::class);
        app(CommercialPermission::class)->assert('products-edit', $context, $request->user()->id);
        ProductAlias::forCompany($context)->findOrFail($id)->delete();
        return $request->expectsJson() ? response()->json(['success' => true]) : redirect()->route('product-aliases.index')->with('message', 'Alternate name removed.');
    }
}
