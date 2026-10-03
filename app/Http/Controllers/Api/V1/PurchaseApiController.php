<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Purchase;
use App\Services\ERP\PurchaseService;
use App\Models\Accounting\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class PurchaseApiController extends BaseApiController
{
    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'warehouse', 'payments']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $purchases = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->sendResponse($purchases->items(), 'Purchases retrieved successfully', 200, [
            'pagination' => [
                'total' => $purchases->total(),
                'per_page' => $purchases->perPage(),
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
            ]
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $purchase = Purchase::with(['supplier', 'warehouse', 'productPurchases.product', 'payments'])->find($id);

        if (!$purchase) {
            return $this->sendError('Purchase not found', [], 404);
        }

        $journalEntry = JournalEntry::with('items.account')
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        $data = $purchase->toArray();
        $data['journal_entry'] = $journalEntry;

        return $this->sendResponse($data, 'Purchase retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.net_unit_cost' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $purchase = $this->purchaseService->createPurchase($request->all(), $request->user()?->id);
            return $this->sendResponse($purchase, 'Purchase created and double-entry posted successfully', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
