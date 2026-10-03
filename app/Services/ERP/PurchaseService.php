<?php

namespace App\Services\ERP;

use App\Models\Purchase;
use App\Models\ProductPurchase;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Payment;
use App\Services\Accounting\AccountingService;
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
    public function createPurchase(array $data, ?int $userId = null): Purchase
    {
        $userId = $userId ?: (auth()->id() ?: 1);

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("Purchase must contain at least one product item.");
        }

        return DB::transaction(function () use ($data, $userId) {
            $referenceNo = 'pr-' . date("Ymd") . '-' . date("his");

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

            $paymentStatus = 2; // Due
            if ($paidAmount >= $grandTotal) {
                $paymentStatus = 4; // Paid
            } elseif ($paidAmount > 0) {
                $paymentStatus = 3; // Partial
            }

            $purchase = Purchase::create([
                'reference_no' => $referenceNo,
                'user_id' => $userId,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
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

            // Save items & increment warehouse stock
            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];
                $qty = (float) $item['qty'];
                $unitCost = (float) $item['net_unit_cost'];

                ProductPurchase::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $productId,
                    'product_batch_id' => $item['product_batch_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'imei_number' => $item['imei_number'] ?? null,
                    'qty' => $qty,
                    'purchase_unit_id' => $item['purchase_unit_id'] ?? 1,
                    'net_unit_cost' => $unitCost,
                    'discount' => (float) ($item['discount'] ?? 0),
                    'tax_rate' => (float) ($item['tax_rate'] ?? 0),
                    'tax' => (float) ($item['tax'] ?? 0),
                    'total' => (float) ($item['total'] ?? ($qty * $unitCost)),
                ]);

                // Increment stock if purchase is Received
                if (($data['status'] ?? 1) == 1) {
                    $product = Product::find($productId);
                    if ($product) {
                        // Update product cost and quantity
                        $product->increment('qty', $qty);
                        $product->update(['cost' => $unitCost]);
                    }

                    $pw = Product_Warehouse::firstOrNew([
                        'product_id' => $productId,
                        'warehouse_id' => $data['warehouse_id'],
                    ]);
                    $pw->qty = (float)$pw->qty + $qty;
                    $pw->save();
                }
            }

            // Record payment if paid amount > 0
            if ($paidAmount > 0) {
                Payment::create([
                    'payment_reference' => 'ppr-' . date("Ymd") . '-' . date("his"),
                    'user_id' => $userId,
                    'purchase_id' => $purchase->id,
                    'account_id' => $data['account_id'] ?? 1,
                    'amount' => $paidAmount,
                    'change' => 0,
                    'paying_method' => $data['paying_method'] ?? 'Cash',
                    'payment_note' => $data['payment_note'] ?? null,
                ]);
            }

            // ATOMIC DOUBLE-ENTRY JOURNAL POSTING
            // Dr. Inventory Asset = Cr. Cash/Bank (paid) + Cr. Accounts Payable (due)
            $this->accountingService->postPurchaseJournal($purchase);

            return $purchase->load(['supplier', 'warehouse', 'productPurchases']);
        });
    }
}
