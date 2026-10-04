<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductApiController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'search' => 'nullable|string|max:100']);
        $query = Product::catalogFor($this->companyContext($request));

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('code', 'LIKE', "%{$term}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $products = $query->where('is_active', true)->paginate($perPage);

        return $this->sendResponse(\App\Http\Resources\ProductResource::collection($products->getCollection())->resolve($request), 'Products retrieved successfully', 200, [
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $context = $this->companyContext($request);
        $product = Product::catalogFor($context)->with([
            'productWarehouse' => fn ($q) => $q->visibleIn($context)->with([
                'warehouse' => fn ($w) => $w->forCompany($context)->where('branch_id', $context->branchId),
            ]),
        ])->find($id);

        if (!$product) {
            return $this->sendError('Product not found', [], 404);
        }

        return $this->sendResponse((new \App\Http\Resources\ProductResource($product))->resolve($request), 'Product retrieved successfully');
    }

    public function search(Request $request, string $term): JsonResponse
    {
        validator(['term' => $term], ['term' => 'required|string|max:100'])->validate();
        $products = Product::catalogFor($this->companyContext($request), ['products.id', 'products.name', 'products.code', 'products.price', 'products.cost', 'products.image'])
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('code', 'LIKE', "%{$term}%");
            })
            ->limit(20)
            ->get();

        return $this->sendResponse(\App\Http\Resources\ProductResource::collection($products)->resolve($request), 'Search results');
    }
}
