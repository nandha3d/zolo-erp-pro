<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PartnerApiController extends BaseApiController
{
    public function customers(Request $request): JsonResponse
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'search' => 'nullable|string|max:100']);
        $context = $this->companyContext($request);
        $query = Customer::forCompany($context)->with(['customerGroup' => fn ($q) => $q->forCompany($context)]);
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('phone_number', 'LIKE', "%{$term}%")
                  ->orWhere('email', 'LIKE', "%{$term}%");
            });
        }

        $customers = $query->where('is_active', true)->paginate($request->input('per_page', 20));
        return $this->sendResponse(\App\Http\Resources\PartyResource::collection($customers->getCollection())->resolve($request), 'Customers retrieved', 200, [
            'pagination' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
            ]
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'search' => 'nullable|string|max:100']);
        $query = Supplier::forCompany($this->companyContext($request));
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('company_name', 'LIKE', "%{$term}%")
                  ->orWhere('phone_number', 'LIKE', "%{$term}%");
            });
        }

        $suppliers = $query->where('is_active', true)->paginate($request->input('per_page', 20));
        return $this->sendResponse(\App\Http\Resources\PartyResource::collection($suppliers->getCollection())->resolve($request), 'Suppliers retrieved', 200, [
            'pagination' => [
                'total' => $suppliers->total(),
                'per_page' => $suppliers->perPage(),
                'current_page' => $suppliers->currentPage(),
            ]
        ]);
    }
}
