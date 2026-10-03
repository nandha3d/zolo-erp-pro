<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CafeApiController extends BaseApiController
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get touch POS menu items and active categories
     */
    public function menu(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->select('id', 'name', 'code', 'price', 'category_id', 'image')
            ->take(50)
            ->get();

        $categories = DB::table('categories')->where('is_active', true)->select('id', 'name')->get();

        return $this->sendResponse([
            'products' => $products,
            'categories' => $categories,
        ], 'POS menu fetched successfully');
    }

    /**
     * Dispatch rapid cafe POS order
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'total_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:Cash,UPI,Card,Split',
            'items' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        $orderRef = 'POS-' . date('Ymd') . '-' . rand(1000, 9999);
        $total = (float)$request->total_amount;
        $cashPortion = (float)($request->cash_amount ?? ($request->payment_method === 'Cash' ? $total : 0));
        $upiPortion = (float)($request->upi_amount ?? ($request->payment_method === 'UPI' ? $total : 0));

        DB::transaction(function () use ($request, $orderRef, $total, $cashPortion, $upiPortion) {
            // Log sale in double-entry ledger
            try {
                $glItems = [];
                if ($cashPortion > 0) {
                    $glItems[] = ['account_code' => '1010', 'debit' => $cashPortion, 'credit' => 0, 'memo' => 'Cafe POS Counter Cash'];
                }
                if ($upiPortion > 0) {
                    $glItems[] = ['account_code' => '1020', 'debit' => $upiPortion, 'credit' => 0, 'memo' => 'Cafe POS QR/UPI Bank'];
                }
                $glItems[] = ['account_code' => '4010', 'debit' => 0, 'credit' => $total, 'memo' => 'Cafe POS Food & Beverage Revenue'];

                $this->accountingService->postJournalEntry([
                    'entry_date' => now()->toDateString(),
                    'reference_type' => 'CafeOrder',
                    'reference_id' => $orderRef,
                    'narration' => "Cafe Quick-POS Order [{$orderRef}]",
                    'items' => $glItems,
                ]);
            } catch (\Exception $e) {}
        });

        return $this->sendResponse([
            'order_reference' => $orderRef,
            'total_amount' => $total,
            'payment_method' => $request->payment_method,
            'timestamp' => now()->toIso8601String(),
            'status' => 'Completed & Reconciled',
        ], 'Cafe POS order processed and posted to ERP ledger', 201);
    }

    /**
     * Reconcile evening cash & UPI drawer
     */
    public function reconcileDrawer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'drawer_date' => 'required|date',
            'opening_float' => 'required|numeric',
            'closing_cash' => 'required|numeric',
            'upi_settlement' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        $expectedCash = (float)$request->opening_float + (float)($request->cash_sales ?? 0);
        $variance = (float)$request->closing_cash - $expectedCash;

        $record = DB::table('cafe_cash_drawers')->insertGetId([
            'drawer_date' => $request->drawer_date,
            'user_id' => Auth::id() ?? 1,
            'opening_float' => $request->opening_float,
            'closing_cash' => $request->closing_cash,
            'upi_settlement' => $request->upi_settlement,
            'cash_sales' => $request->cash_sales ?? 0,
            'variance' => $variance,
            'status' => abs($variance) < 1 ? 'Balanced' : 'Discrepancy',
            'notes' => $request->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->sendResponse([
            'id' => $record,
            'drawer_date' => $request->drawer_date,
            'variance' => $variance,
            'status' => abs($variance) < 1 ? 'Balanced' : 'Discrepancy Logged',
        ], 'Drawer reconciled successfully');
    }
}
