<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Purchase;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
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

    private function queryFor(CompanyContext $context): Builder
    {
        return Purchase::visibleIn($context)->with([
            'supplier' => fn ($q) => $q->forCompany($context),
            'warehouse' => fn ($q) => $q->forCompany($context),
            'payments' => fn ($q) => $q->visibleIn($context),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->queryFor($this->companyContext($request));

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

    public function show(Request $request, int $id): JsonResponse
    {
        $context = $this->companyContext($request);
        $purchase = $this->queryFor($context)->with([
            'productPurchases' => fn ($q) => $q->forCompany($context)
                ->whereHas('product', fn ($q) => $q->forCompany($context))
                ->with(['product' => fn ($q) => $q->catalogFor($context)]),
        ])->find($id);

        if (!$purchase) {
            return $this->sendError('Purchase not found', [], 404);
        }

        $journalEntry = JournalEntry::withOwnedItems($context)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        $data = $purchase->toArray();
        $data['journal_entry'] = $journalEntry;

        return $this->sendResponse($data, 'Purchase retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        if (config('commercial.enabled')) {
            return app(\App\Http\Middleware\RequireSharedCommercial::class)->handle($request,
                fn ($request) => app(\App\Http\Controllers\CommercialController::class)->store($request, 'purchase'));
        }
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'status' => 'sometimes|integer|in:1,2,3,4',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.net_unit_cost' => 'required|numeric|min:0',
            'items.*.received_qty' => 'required_if:status,2|numeric|min:0|lte:items.*.qty',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $purchase = $this->purchaseService->createPurchase($request->all(), $request->user()?->id, $this->companyContext($request));
            return $this->sendResponse($purchase, 'Purchase created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    public function addPayment(Request $request, int $id): JsonResponse
    {
        if (config('commercial.enabled')) {
            return app(\App\Http\Middleware\RequireSharedCommercial::class)->handle($request,
                fn ($request) => app(\App\Http\Controllers\CommercialController::class)->payment($request, 'purchase', $id));
        }
        $context = $this->companyContext($request);
        $purchase = Purchase::visibleIn($context)->find($id);
        if (!$purchase) {
            return $this->sendError('Purchase not found', [], 404);
        }
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paying_method' => 'required|string',
            'account_id' => 'nullable|integer|min:1',
        ]);
        try {
            $payment = $this->purchaseService->addPayment($purchase, $request->all(), $request->user()->id, $context);
            return $this->sendResponse($payment, 'Supplier payment recorded and posted successfully', 201);
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
