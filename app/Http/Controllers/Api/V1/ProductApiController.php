<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductApiController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'unit']);

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

        return $this->sendResponse($products->items(), 'Products retrieved successfully', 200, [
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'brand', 'unit', 'productWarehouse.warehouse'])->find($id);

        if (!$product) {
            return $this->sendError('Product not found', [], 404);
        }

        return $this->sendResponse($product, 'Product retrieved successfully');
    }

    public function search(string $term): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('code', 'LIKE', "%{$term}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'code', 'price', 'cost', 'qty', 'image']);

        return $this->sendResponse($products, 'Search results');
    }
}
