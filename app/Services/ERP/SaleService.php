<?php

namespace App\Services\ERP;

use App\Models\Sale;
use App\Models\Product_Sale;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Payment;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;

class SaleService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Create a complete ERP Sale, update inventory stock, and post double-entry journal entries.
     *
     * @param array $data Sale payload:
     *   - customer_id: int
     *   - warehouse_id: int
     *   - biller_id: int (optional)
     *   - items: array of [product_id, qty, net_unit_price, discount, tax_rate, tax, total]
     *   - order_tax_rate: float
     *   - order_tax: float
     *   - order_discount: float
     *   - shipping_cost: float
     *   - grand_total: float
     *   - paid_amount: float (optional)
     *   - paying_method: string (Cash, Bank, Cheque, etc.)
     *   - sale_status: int (1 = completed, 2 = pending)
     *   - payment_status: int (4 = paid, 2 = due, 3 = partial)
     *   - sale_note: string
     * @param int|null $userId
     * @return Sale
     */
    public function createSale(array $data, ?int $userId = null): Sale
    {
        $userId = $userId ?: (auth()->id() ?: 1);

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("Sale must contain at least one product item.");
        }

        return DB::transaction(function () use ($data, $userId) {
            // Generate Reference Number
            $referenceNo = 'posr-' . date("Ymd") . '-' . date("his");

            $itemQty = 0;
            $totalCost = 0.0;
            $totalQty = 0.0;
            $totalPrice = 0.0;
            $totalTax = 0.0;
            $totalDiscount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $itemQty += 1;
                $totalQty += $qty;
                $totalPrice += (float) ($item['total'] ?? ($qty * (float)$item['net_unit_price']));
                $totalTax += (float) ($item['tax'] ?? 0);
                $totalDiscount += (float) ($item['discount'] ?? 0);

                // Fetch product cost for COGS
                $product = Product::find($item['product_id']);
                if ($product) {
                    $totalCost += (float) ($product->cost ?? 0) * $qty;
                }
            }

            $orderTax = (float) ($data['order_tax'] ?? 0);
            $orderDiscount = (float) ($data['order_discount'] ?? 0);
            $shippingCost = (float) ($data['shipping_cost'] ?? 0);
            $grandTotal = (float) ($data['grand_total'] ?? ($totalPrice + $orderTax + $shippingCost - $orderDiscount));
            $paidAmount = (float) ($data['paid_amount'] ?? 0);

            $paymentStatus = 2; // Due
            if ($paidAmount >= $grandTotal) {
                $paymentStatus = 4; // Paid
            } elseif ($paidAmount > 0) {
                $paymentStatus = 3; // Partial
            }

            $sale = Sale::create([
                'reference_no' => $referenceNo,
                'user_id' => $userId,
                'cash_register_id' => $data['cash_register_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'biller_id' => $data['biller_id'] ?? 1,
                'item' => $itemQty,
                'total_qty' => $totalQty,
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTax,
                'total_price' => $totalPrice,
                'order_tax_rate' => $data['order_tax_rate'] ?? 0,
                'order_tax' => $orderTax,
                'order_discount_type' => $data['order_discount_type'] ?? 'Flat',
                'order_discount_value' => $data['order_discount_value'] ?? $orderDiscount,
                'order_discount' => $orderDiscount,
                'coupon_id' => $data['coupon_id'] ?? null,
                'coupon_discount' => $data['coupon_discount'] ?? null,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'currency_id' => $data['currency_id'] ?? null,
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'sale_status' => $data['sale_status'] ?? 1,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'sale_note' => $data['sale_note'] ?? null,
                'staff_note' => $data['staff_note'] ?? null,
            ]);

            // Save line items and decrement warehouse inventory
            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['qty'];
                $unitPrice = (float) $item['net_unit_price'];

                Product_Sale::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'product_batch_id' => $item['product_batch_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'imei_number' => $item['imei_number'] ?? null,
                    'qty' => $qty,
                    'sale_unit_id' => $item['sale_unit_id'] ?? 1,
                    'net_unit_price' => $unitPrice,
                    'discount' => (float) ($item['discount'] ?? 0),
                    'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    'tax' => (float) ($item['tax'] ?? 0),
                    'total' => (float) ($item['total'] ?? ($qty * $unitPrice)),
                ]);

                // Deduct stock if sale is completed
                if (($data['sale_status'] ?? 1) == 1) {
                    $product = Product::find($productId);
                    if ($product) {
                        $product->decrement('qty', $qty);
                    }

                    $pw = Product_Warehouse::where('product_id', $productId)
                        ->where('warehouse_id', $data['warehouse_id'])
                        ->first();
                    if ($pw) {
                        $pw->decrement('qty', $qty);
                    }
                }
            }

            // Create Payment record if paid amount > 0
            if ($paidAmount > 0) {
                Payment::create([
                    'payment_reference' => 'spr-' . date("Ymd") . '-' . date("his"),
                    'user_id' => $userId,
                    'sale_id' => $sale->id,
                    'account_id' => $data['account_id'] ?? 1,
                    'amount' => $paidAmount,
                    'change' => (float) ($data['paying_amount'] ?? $paidAmount) - $paidAmount,
                    'paying_method' => $data['paying_method'] ?? 'Cash',
                    'payment_note' => $data['payment_note'] ?? null,
                ]);
            }

            // ATOMIC DOUBLE-ENTRY JOURNAL POSTING
            // Dr. Cash/Bank + Dr. AR + Dr. Discount = Cr. Revenue + Cr. Tax + Cr. Shipping
            // Dr. COGS = Cr. Inventory Asset
            $this->accountingService->postSaleJournal($sale, $totalCost);

            return $sale->load(['customer', 'warehouse', 'productSales']);
        });
    }

    /**
     * Add a payment against an existing sale.
     */
    public function addPayment(Sale $sale, array $paymentData, ?int $userId = null): Payment
    {
        $userId = $userId ?: (auth()->id() ?: 1);
        $amount = (float) $paymentData['amount'];

        if ($amount <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        return DB::transaction(function () use ($sale, $paymentData, $amount, $userId) {
            $payment = Payment::create([
                'payment_reference' => 'spr-' . date("Ymd") . '-' . date("his"),
                'user_id' => $userId,
                'sale_id' => $sale->id,
                'account_id' => $paymentData['account_id'] ?? 1,
                'amount' => $amount,
                'change' => 0,
                'paying_method' => $paymentData['paying_method'] ?? 'Cash',
                'payment_note' => $paymentData['payment_note'] ?? null,
            ]);

            $newPaidAmount = (float)$sale->paid_amount + $amount;
            $paymentStatus = 3; // Partial
            if ($newPaidAmount >= (float)$sale->grand_total) {
                $paymentStatus = 4; // Paid
            }

            $sale->update([
                'paid_amount' => $newPaidAmount,
                'payment_status' => $paymentStatus,
            ]);

            // Post double-entry journal (Dr. Cash/Bank, Cr. AR)
            $this->accountingService->postPaymentJournal($payment);

            return $payment;
        });
    }
}
