<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Sale;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use App\Services\ERP\SaleService;
use App\Models\Accounting\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class SaleApiController extends BaseApiController
{
    protected SaleService $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    private function queryFor(CompanyContext $context): Builder
    {
        return Sale::visibleIn($context)->with([
            'customer' => fn ($q) => $q->forCompany($context),
            'warehouse' => fn ($q) => $q->forCompany($context),
            'biller' => fn ($q) => $q->forCompany($context),
            'payments' => fn ($q) => $q->visibleIn($context),
        ]);
    }

    /** Get paginated sales with optional filters within the authorized context. */
    public function index(Request $request): JsonResponse
    {
        $query = $this->queryFor($this->companyContext($request));

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $perPage = (int) $request->input('per_page', 15);
        $sales = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->sendResponse($sales->items(), 'Sales retrieved successfully', 200, [
            'pagination' => [
                'total' => $sales->total(),
                'per_page' => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
            ]
        ]);
    }

    /**
     * Get detailed sale with line items, payments, and double-entry journal entry.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $context = $this->companyContext($request);
        $sale = $this->queryFor($context)->with([
            'productSales' => fn ($q) => $q->forCompany($context)
                ->whereHas('product', fn ($q) => $q->forCompany($context))
                ->with(['product' => fn ($q) => $q->catalogFor($context)]),
        ])->find($id);

        if (!$sale) {
            return $this->sendError('Sale not found', [], 404);
        }

        // Fetch associated double-entry journal entry
        $journalEntry = JournalEntry::withOwnedItems($context)
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->first();

        $data = $sale->toArray();
        $data['journal_entry'] = $journalEntry;

        return $this->sendResponse($data, 'Sale retrieved successfully');
    }

    /**
     * Create a new sale through the ERP engine.
     */
    public function store(Request $request): JsonResponse
    {
        if (config('commercial.enabled')) {
            return app(\App\Http\Middleware\RequireSharedCommercial::class)->handle($request,
                fn ($request) => app(\App\Http\Controllers\CommercialController::class)->store($request, 'sale'));
        }
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.net_unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $sale = $this->saleService->createSale($request->all(), $request->user()?->id, $this->companyContext($request));
            return $this->sendResponse($sale, 'Sale created and double-entry posted successfully', 201);
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    /**
     * Add a payment against an existing sale.
     */
    public function addPayment(Request $request, int $id): JsonResponse
    {
        if (config('commercial.enabled')) {
            return app(\App\Http\Middleware\RequireSharedCommercial::class)->handle($request,
                fn ($request) => app(\App\Http\Controllers\CommercialController::class)->payment($request, 'sale', $id));
        }
        $sale = Sale::visibleIn($this->companyContext($request))->find($id);
        if (!$sale) {
            return $this->sendError('Sale not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'paying_method' => 'required|string',
            'account_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $payment = $this->saleService->addPayment($sale, $request->all(), $request->user()?->id, $this->companyContext($request));
            return $this->sendResponse($payment, 'Payment recorded and posted successfully', 201);
        } catch (\Illuminate\Validation\ValidationException | \Illuminate\Auth\Access\AuthorizationException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }
}
