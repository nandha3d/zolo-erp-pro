<?php

namespace App\Services\ERP;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\DocumentNumberService;
use App\Models\ProductPurchase;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Payment;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\StockLine;
use App\Services\Inventory\StockMovementCommand;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;

class PurchaseService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Create an ERP Purchase, update inventory quantities & costs, and post double-entry journal entries.
     */
    public function createPurchase(array $data, ?int $userId = null, ?CompanyContext $context = null): Purchase
    {
        $userId = $userId ?: auth()->id();
        $guard = app(CompanyWriteGuard::class);
        $context = $guard->context($context, $userId);

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("Purchase must contain at least one product item.");
        }
        $status = filter_var($data['status'] ?? 1, FILTER_VALIDATE_INT);
        if (!in_array($status, [1, 2, 3, 4], true)) {
            throw new InvalidArgumentException('Purchase status must be Received, Partial, Pending or Ordered.');
        }
        $data['status'] = $status;
        foreach ($data['items'] as &$item) {
            $qty = $item['qty'] ?? null;
            $cost = $item['net_unit_cost'] ?? null;
            if (!is_numeric($qty) || !is_finite((float) $qty) || (float) $qty <= 0
                || !is_numeric($cost) || !is_finite((float) $cost) || (float) $cost < 0) {
                throw new InvalidArgumentException('Purchase quantity must be positive and unit cost must be nonnegative.');
            }
            $received = $status === 1 ? $qty : ($status === 2 ? ($item['received_qty'] ?? null) : 0);
            if (!is_numeric($received) || !is_finite((float) $received)
                || (float) $received < 0 || (float) $received > (float) $qty) {
                throw new InvalidArgumentException('Partial purchases require received_qty between zero and ordered quantity for every line.');
            }
            if (isset($item['received_qty']) && (!is_numeric($item['received_qty'])
                || (float) $item['received_qty'] !== (float) $received)) {
                throw new InvalidArgumentException('received_qty contradicts the purchase status.');
            }
            $item['received_qty'] = (float) $received;
        }
        unset($item);

        return DB::transaction(function () use ($data, $userId, $context, $guard) {
            $date = $guard->begin($context, $guard->businessDate($data));
            $guard->rejectUnscopedReferences($data);
            $guard->warehouse($data['warehouse_id'] ?? null, $context, $userId);
            if (!empty($data['supplier_id'])) {
                $guard->owned(Supplier::class, $data['supplier_id'], $context, 'supplier_id');
            }
            foreach ($data['items'] as &$line) {
                $guard->product($line, $context, 'purchase_unit_id');
            }
            unset($line);
            $numbers = app(DocumentNumberService::class);
            $reservation = $numbers->reserve('purchase', $context, $date, $userId);
            $referenceNo = $reservation->formatted_number;

            $itemCount = 0;
            $totalQty = 0.0;
            $totalCost = 0.0;
            $totalTax = 0.0;
            $totalDiscount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $unitCost = (float) $item['net_unit_cost'];
                $itemCount += 1;
                $totalQty += $qty;
                $totalCost += (float) ($item['total'] ?? ($qty * $unitCost));
                $totalTax += (float) ($item['tax'] ?? 0);
                $totalDiscount += (float) ($item['discount'] ?? 0);
            }

            $orderTax = (float) ($data['order_tax'] ?? 0);
            $orderDiscount = (float) ($data['order_discount'] ?? 0);
            $shippingCost = (float) ($data['shipping_cost'] ?? 0);
            $grandTotal = (float) ($data['grand_total'] ?? ($totalCost + $orderTax + $shippingCost - $orderDiscount));
            $paidAmount = (float) ($data['paid_amount'] ?? 0);
            if (!is_finite($grandTotal) || $grandTotal < 0 || !is_finite($paidAmount)
                || $paidAmount < 0 || $paidAmount > $grandTotal || ($data['status'] === 4 && $paidAmount > 0)) {
                throw new InvalidArgumentException('Payment must be within the bill total; an unbilled order cannot receive payment here.');
            }

            $paymentStatus = 2; // Due
            if ($paidAmount >= $grandTotal) {
                $paymentStatus = 4; // Paid
            } elseif ($paidAmount > 0) {
                $paymentStatus = 3; // Partial
            }

            if ($paidAmount > 0) {
                $data['account_id'] = $guard->paymentAccount($data, $context);
            }
            $purchase = (new Purchase)->forceFill([
                'company_id' => $context->companyId,
                'created_at' => $date,
                'reference_no' => $referenceNo,
                'user_id' => $userId,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'item' => $itemCount,
                'total_qty' => $totalQty,
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTax,
                'total_cost' => $totalCost,
                'order_tax_rate' => $data['order_tax_rate'] ?? 0,
                'order_tax' => $orderTax,
                'order_discount' => $orderDiscount,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'status' => $data['status'] ?? 1, // 1 = Received
                'payment_status' => $paymentStatus,
                'note' => $data['note'] ?? null,
            ]);
            $purchase->save();
            $numbers->assign($reservation, $purchase);

            // Save items & increment warehouse stock
            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['qty'];
                $unitCost = (float) $item['net_unit_cost'];

                (new ProductPurchase)->forceFill([
                    'company_id' => $context->companyId,
                    'purchase_id' => $purchase->id,
                    'product_id' => $productId,
                    'product_batch_id' => $item['product_batch_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'imei_number' => $item['imei_number'] ?? null,
                    'qty' => $qty,
                    'recieved' => $item['received_qty'],
                    'purchase_unit_id' => $item['purchase_unit_id'] ?? 1,
                    'net_unit_cost' => $unitCost,
                    'discount' => (float) ($item['discount'] ?? 0),
                    'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    'tax' => (float) ($item['tax'] ?? 0),
                    'total' => (float) ($item['total'] ?? ($qty * $unitCost)),
                ])->save();

                if ($item['received_qty'] > 0) {
                    Product::forCompany($context)->findOrFail($productId)->update(['cost' => $unitCost]);
                }
            }

            // Receive only the validated physical quantity, including partial receipts, through the ledger.
            $received = array_values(array_filter($data['items'], fn ($item) => $item['received_qty'] > 0));
            if ($received !== []) {
                app(InventoryMovementService::class)->receive(new StockMovementCommand(
                    date: $date,
                    lines: array_map(fn ($item) => StockLine::fromArray([
                        'qty' => $item['received_qty'],
                        'unit_cost' => $item['net_unit_cost'],
                        'uom_id' => $item['purchase_unit_id'] ?? null,
                    ] + $item), $received),
                    warehouseId: (int) $data['warehouse_id'],
                    sourceType: 'purchase',
                    sourceId: $purchase->id,
                    sourceNo: $referenceNo,
                    idempotencyKey: 'purchase:'.$purchase->id,
                    userId: $userId,
                    context: $context,
                ));
            }

            // Record payment if paid amount > 0
            if ($paidAmount > 0) {
                $paymentReservation = $numbers->reserve('purchase_payment', $context, $date, $userId);
                $payment = (new Payment)->forceFill([
                    'company_id' => $context->companyId,
                    'created_at' => $date,
                    'payment_at' => $date,
                    'payment_reference' => $paymentReservation->formatted_number,
                    'user_id' => $userId,
                    'purchase_id' => $purchase->id,
                    'account_id' => $data['account_id'] ?? 1,
                    'amount' => $paidAmount,
                    'change' => 0,
                    'paying_method' => $data['paying_method'] ?? 'Cash',
                    'payment_note' => $data['payment_note'] ?? null,
                ]);
                $payment->save();
                $numbers->assign($paymentReservation, $payment);
            }

            // Supplier-bill recognition: received inventory + goods-in-transit = payment + AP.
            // Ordered is an unbilled PO; it has no stock or financial posting.
            $this->accountingService->postPurchaseJournal($purchase, $context, $userId);

            return $purchase->load(['supplier', 'warehouse', 'productPurchases']);
        });
    }

    public function addPayment(Purchase $purchase, array $paymentData, ?int $userId = null, ?CompanyContext $context = null): Payment
    {
        return app(PaymentService::class)->addPayment($purchase, $paymentData, $userId, $context);
    }
}
