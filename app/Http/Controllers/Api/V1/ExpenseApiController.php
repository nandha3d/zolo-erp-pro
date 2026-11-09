<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Expense;
use App\Models\ExpenseCategory;

class ExpenseApiController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::with('expenseCategory', 'warehouse')
            ->orderBy('id', 'desc')
            ->paginate($request->query('per_page', 25));

        return $this->sendResponse($expenses, 'Expenses retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_category_id' => 'required',
            'warehouse_id' => 'required',
            'amount' => 'required|numeric',
        ]);

        $expense = Expense::create($validated);
        return $this->sendResponse($expense, 'Expense created successfully', 201);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = ExpenseCategory::where('is_active', true)->get();
        return $this->sendResponse($categories, 'Expense categories retrieved successfully');
    }
}
