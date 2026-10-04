<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Models\Accounting\JournalEntry;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\Expense;
use Carbon\Carbon;
use InvalidArgumentException;

class AccountingService
{
    /**
     * Post an atomic, balanced double-entry Journal Entry.
     *
     * @param array $header [entry_date, reference_type, reference_id, reference_no, description, created_by]
     * @param array $items  Array of ['chart_of_account_id' => int, 'debit' => float, 'credit' => float, 'memo' => string, 'partner_type' => string, 'partner_id' => int]
     * @return JournalEntry
     * @throws InvalidArgumentException
     */
    public function postJournalEntry(array $header, array $items, ?CompanyContext $context = null): JournalEntry
    {
        return app(AccountingPostingService::class)->postJournalEntry($header, $items, $context);
    }
    /**
     * Resolve a company semantic posting role.
     */
    public function getAccount(string $role, ?CompanyContext $context = null, ?int $actor = null): ?ChartOfAccount
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor ?? auth()->id());
        return app(SemanticAccountResolver::class)->resolve($role, $context, false);
    }

    private function settlementAccount(Payment $payment, CompanyContext $context, ?int $actor = null): ?ChartOfAccount
    {
        $legacy = \App\Models\Account::forCompany($context)->findOrFail($payment->account_id);
        if ($legacy->chart_of_account_id) {
            $account = ChartOfAccount::forCompany($context)->where('is_active', true)->find($legacy->chart_of_account_id);
            $role = in_array($payment->paying_method, ['Bank', 'Cheque', 'Credit Card'], true) ? 'bank' : 'cash';
            if (!$account || $account->control_type !== $role) {
                throw new InvalidArgumentException('Payment account must link to an active company cash or bank account.');
            }
            return $account;
        }
        return $this->getAccount(in_array($payment->paying_method, ['Bank', 'Cheque', 'Credit Card'], true) ? 'bank' : 'cash', $context, $actor);
    }
    /**
     * Automatically post double-entry journal for a Sale.
     *
     * Accounting logic:
     * Dr. Cash / Bank (Paid Amount)
     * Dr. Accounts Receivable (Due Amount, if any)
     * Dr. Sales Discounts (Order discount, if any)
     * Cr. Sales Revenue (Subtotal / Net Sale)
     * Cr. Tax Payable (Tax Amount)
     * Cr. Shipping Income (Shipping Charge)
     * 
     * PLUS Inventory Valuation:
     * Dr. Cost of Goods Sold (COGS)
     * Cr. Inventory Asset
     */
    public function postSaleJournal(Sale $sale, ?float $cogsCost = null, ?CompanyContext $context = null, ?int $actor = null): ?JournalEntry
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor ?? auth()->id());
        $arAccount   = $this->getAccount('ar', $context, $actor); // Accounts Receivable
        $revAccount  = $this->getAccount('sales', $context, $actor); // Sales Revenue
        $taxAccount  = $this->getAccount('tax_output', $context, $actor); // Tax Payable
        $discAccount = $this->getAccount('discounts', $context, $actor); // Sales Discounts
        $shipAccount = $this->getAccount('freight', $context, $actor); // Shipping Income
        $cogsAccount = $this->getAccount('cogs', $context, $actor); // COGS
        $invAccount  = $this->getAccount('inventory', $context, $actor); // Inventory Asset

        if (!$revAccount || !$arAccount) {
            throw new InvalidArgumentException('Sale revenue and receivable accounts must be configured before posting.');
        }

        $grandTotal = (float) $sale->grand_total;
        $paidAmount = (float) ($sale->paid_amount ?? 0);
        $dueAmount  = max(0.0, $grandTotal - $paidAmount);
        $orderTax   = (float) ($sale->order_tax ?? 0);
        $orderDisc  = (float) ($sale->order_discount ?? 0);
        $shipping   = (float) ($sale->shipping_cost ?? 0);

        // Net revenue = Grand total - Tax - Shipping + Discount
        $netRevenue = max(0.0, $grandTotal - $orderTax - $shipping + $orderDisc);

        $items = [];

        // 1. Debit Payment received (Cash or Bank)
        if ($paidAmount > 0) {
            $payment = $sale->payments()->where('company_id', $context->companyId)->firstOrFail();
            $depositAccount = $this->settlementAccount($payment, $context, $actor);
            if (!$depositAccount) {
                throw new InvalidArgumentException('Sale payment account must be configured before posting.');
            }

            $items[] = [
                'chart_of_account_id' => $depositAccount->id,
                'debit' => $paidAmount,
                'credit' => 0,
                'memo' => "Payment received for Sale {$sale->reference_no}",
                'partner_type' => 'customer',
                'partner_id' => $sale->customer_id,
            ];
        }

        // 2. Debit Accounts Receivable (Due Amount)
        if ($dueAmount > 0) {
            $items[] = [
                'chart_of_account_id' => $arAccount->id,
                'debit' => $dueAmount,
                'credit' => 0,
                'memo' => "Receivable from Customer for Sale {$sale->reference_no}",
                'partner_type' => 'customer',
                'partner_id' => $sale->customer_id,
            ];
        }

        // 3. Debit Sales Discount (if any)
        if ($orderDisc > 0 && $discAccount) {
            $items[] = [
                'chart_of_account_id' => $discAccount->id,
                'debit' => $orderDisc,
                'credit' => 0,
                'memo' => "Discount allowed on Sale {$sale->reference_no}",
                'partner_type' => 'customer',
                'partner_id' => $sale->customer_id,
            ];
        }

        // 4. Credit Sales Revenue
        if ($netRevenue > 0) {
            $items[] = [
                'chart_of_account_id' => $revAccount->id,
                'debit' => 0,
                'credit' => $netRevenue,
                'memo' => "Sales revenue for {$sale->reference_no}",
                'partner_type' => 'customer',
                'partner_id' => $sale->customer_id,
            ];
        }

        // 5. Credit Tax Payable
        if ($orderTax > 0 && $taxAccount) {
            $items[] = [
                'chart_of_account_id' => $taxAccount->id,
                'debit' => 0,
                'credit' => $orderTax,
                'memo' => "Tax collected on Sale {$sale->reference_no}",
            ];
        }

        // 6. Credit Shipping Income
        if ($shipping > 0 && $shipAccount) {
            $items[] = [
                'chart_of_account_id' => $shipAccount->id,
                'debit' => 0,
                'credit' => $shipping,
                'memo' => "Shipping charges collected for Sale {$sale->reference_no}",
            ];
        }

        // 7. COGS & Inventory Asset entry
        if ($cogsCost !== null && $cogsCost > 0 && (!$cogsAccount || !$invAccount)) {
            throw new InvalidArgumentException('Sale COGS and inventory accounts must be configured before posting.');
        }
        if ($cogsCost !== null && $cogsCost > 0 && $cogsAccount && $invAccount) {
            $items[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit' => $cogsCost,
                'credit' => 0,
                'memo' => "Cost of goods sold for {$sale->reference_no}",
            ];
            $items[] = [
                'chart_of_account_id' => $invAccount->id,
                'debit' => 0,
                'credit' => $cogsCost,
                'memo' => "Inventory reduction for Sale {$sale->reference_no}",
            ];
        }

        $header = [
            'entry_date' => $sale->created_at ? $sale->created_at->toDateString() : Carbon::now()->toDateString(),
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
            'reference_no' => $sale->reference_no,
            'description' => "Double-entry posting for Invoice #{$sale->reference_no}",
            'created_by' => $actor ?? auth()->id() ?? $sale->user_id,
        ];

        return $this->postJournalEntry($header, $items, $context);
    }

    /**
     * Automatically post double-entry journal for a Purchase.
     *
     * Accounting logic:
     * Dr. Received inventory and supplier-billed goods awaiting receipt
     * Cr. Bank / Cash (Paid Amount)
     * Cr. Accounts Payable (Due Amount)
     */
    public function postPurchaseJournal(Purchase $purchase, ?CompanyContext $context = null, ?int $actor = null): ?JournalEntry
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor ?? auth()->id());
        if ((int) $purchase->status === 4) {
            return null; // An ordered purchase is not a supplier bill.
        }
        $invAccount  = $this->getAccount('inventory', $context, $actor); // Merchandise Inventory
        $apAccount   = $this->getAccount('ap', $context, $actor); // Accounts Payable

        if (!$invAccount || !$apAccount) {
            throw new InvalidArgumentException('Purchase inventory and payable accounts must be configured before posting.');
        }

        $grandTotal = (float) $purchase->grand_total;
        $paidAmount = (float) ($purchase->paid_amount ?? 0);
        $dueAmount  = max(0.0, $grandTotal - $paidAmount);
        if ($grandTotal == 0 && $paidAmount == 0) {
            return null; // Free goods have a quantity effect but no monetary journal.
        }

        $lines = $purchase->productPurchases()->forCompany($context)->get();
        $orderedValue = 0.0;
        $receivedValue = 0.0;
        $orderedQty = 0.0;
        $receivedQty = 0.0;
        foreach ($lines as $line) {
            $qty = (float) $line->qty;
            $received = (float) $line->recieved;
            if ($qty <= 0 || $received < 0 || $received > $qty || (float) $line->total < 0) {
                throw new InvalidArgumentException('Purchase receipt lines are invalid for posting.');
            }
            $orderedValue += (float) $line->total;
            $receivedValue += (float) $line->total * $received / $qty;
            $orderedQty += $qty;
            $receivedQty += $received;
        }
        if ($orderedQty <= 0) {
            throw new InvalidArgumentException('Purchase bill must contain receipt lines before posting.');
        }
        // Allocate bill-level charges/discounts proportionally; free lines use quantity.
        $receivedRatio = $orderedValue > 0 ? $receivedValue / $orderedValue : $receivedQty / $orderedQty;
        $inventoryValue = round($grandTotal * $receivedRatio, 4);
        $transitValue = round($grandTotal - $inventoryValue, 4);
        $transitAccount = $transitValue > 0 ? $this->getAccount('goods_in_transit', $context, $actor) : null;
        if ($transitValue > 0 && (!$transitAccount || !$transitAccount->is_active || $transitAccount->type !== 'asset')) {
            throw new InvalidArgumentException('Configure an active asset account with sub_type goods_in_transit before posting an unreceived bill.');
        }

        $items = [];

        // 1. Debit received inventory; unreceived supplier-billed value remains in transit.
        $items[] = [
            'chart_of_account_id' => $invAccount->id,
            'debit' => $inventoryValue,
            'credit' => 0,
            'memo' => "Inventory received from Purchase PO {$purchase->reference_no}",
            'partner_type' => $purchase->supplier_id ? 'supplier' : null,
            'partner_id' => $purchase->supplier_id,
        ];
        if ($transitValue > 0) {
            $items[] = [
                'chart_of_account_id' => $transitAccount->id,
                'debit' => $transitValue, 'credit' => 0,
                'memo' => "Supplier-billed goods awaiting receipt {$purchase->reference_no}",
                'partner_type' => $purchase->supplier_id ? 'supplier' : null, 'partner_id' => $purchase->supplier_id,
            ];
        }

        // 2. Credit Paid amount (Cash/Bank)
        if ($paidAmount > 0) {
            $payment = $purchase->payments()->where('company_id', $context->companyId)->firstOrFail();
            $paymentAccount = $this->settlementAccount($payment, $context, $actor);
            if (!$paymentAccount) {
                throw new InvalidArgumentException('Purchase payment account must be configured before posting.');
            }
            $items[] = [
                'chart_of_account_id' => $paymentAccount->id,
                'debit' => 0,
                'credit' => $paidAmount,
                'memo' => "Payment for Purchase PO {$purchase->reference_no}",
                'partner_type' => $purchase->supplier_id ? 'supplier' : null,
                'partner_id' => $purchase->supplier_id,
            ];
        }

        // 3. Credit Accounts Payable (Due Amount)
        if ($dueAmount > 0) {
            $items[] = [
                'chart_of_account_id' => $apAccount->id,
                'debit' => 0,
                'credit' => $dueAmount,
                'memo' => "Payable to Supplier for Purchase PO {$purchase->reference_no}",
                'partner_type' => $purchase->supplier_id ? 'supplier' : null,
                'partner_id' => $purchase->supplier_id,
            ];
        }

        $header = [
            'entry_date' => $purchase->created_at ? $purchase->created_at->toDateString() : Carbon::now()->toDateString(),
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
            'reference_no' => $purchase->reference_no,
            'description' => "Double-entry posting for Purchase PO #{$purchase->reference_no}",
            'created_by' => $actor ?? auth()->id() ?? $purchase->user_id,
        ];

        return $this->postJournalEntry($header, $items, $context);
    }

    /**
     * Automatically post double-entry journal for Payments (Customer payments or Supplier payments).
     */
    public function postPaymentJournal(Payment $payment, ?CompanyContext $context = null, ?int $actor = null, ?string $idempotencyKey = null): ?JournalEntry
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor ?? auth()->id());
        $arAccount   = $this->getAccount('ar', $context, $actor); // Accounts Receivable
        $apAccount   = $this->getAccount('ap', $context, $actor); // Accounts Payable

        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return null;
        }

        $payingAccount = $this->settlementAccount($payment, $context, $actor);

        if (!$payingAccount || (!empty($payment->sale_id) && !$arAccount) || (!empty($payment->purchase_id) && !$apAccount)) {
            throw new InvalidArgumentException('Payment settlement accounts must be configured before posting.');
        }
        $items = [];

        if (!empty($payment->sale_id)) {
            // Customer paying their account receivable
            // Dr. Cash / Bank
            // Cr. Accounts Receivable
            $items[] = [
                'chart_of_account_id' => $payingAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'memo' => "Payment received for Sale payment ref {$payment->payment_reference}",
            ];
            $items[] = [
                'chart_of_account_id' => $arAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'memo' => "Relief of Accounts Receivable for Sale payment ref {$payment->payment_reference}",
            ];
        } elseif (!empty($payment->purchase_id)) {
            // Supplier payment
            // Dr. Accounts Payable
            // Cr. Cash / Bank
            $items[] = [
                'chart_of_account_id' => $apAccount->id,
                'debit' => $amount,
                'credit' => 0,
                'memo' => "Payment made to Supplier ref {$payment->payment_reference}",
            ];
            $items[] = [
                'chart_of_account_id' => $payingAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'memo' => "Bank/Cash deduction for Supplier payment ref {$payment->payment_reference}",
            ];
        } else {
            return null;
        }

        $source = $payment->sale_id ? Sale::visibleIn($context)->findOrFail($payment->sale_id)
            : Purchase::visibleIn($context)->findOrFail($payment->purchase_id);
        foreach ($items as &$line) {
            $line['partner_type'] = $payment->sale_id ? 'customer' : ($source->supplier_id ? 'supplier' : null);
            $line['partner_id'] = $payment->sale_id ? $source->customer_id : ($source->supplier_id ?: null);
        }
        unset($line);

        $header = [
            'entry_date' => $payment->payment_at?->toDateString() ?? $payment->created_at?->toDateString(),
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'reference_no' => $payment->payment_reference,
            'description' => "Double-entry payment settlement #{$payment->payment_reference}",
            'created_by' => $actor ?? auth()->id() ?? $payment->user_id,
            'idempotency_key' => $idempotencyKey ? 'settlement:'.$idempotencyKey : null,
        ];

        return $this->postJournalEntry($header, $items, $context);
    }

    /**
     * Automatically post double-entry journal for Expenses.
     */
    public function postExpenseJournal(Expense $expense, ?CompanyContext $context = null): ?JournalEntry
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $cashAccount = $this->getAccount('cash', $context);
        $bankAccount = $this->getAccount('bank', $context);
        $generalExp  = $this->getAccount('expense', $context);

        $amount = (float) $expense->amount;
        if ($amount <= 0) {
            return null;
        }

        $creditAccount = ($cashAccount ?? $bankAccount);
        if (!$generalExp || !$creditAccount) {
            throw new InvalidArgumentException('Expense and settlement accounts must be configured before posting.');
        }

        $items = [
            [
                'chart_of_account_id' => $generalExp->id,
                'debit' => $amount,
                'credit' => 0,
                'memo' => "Expense ref: {$expense->reference_no}. Note: {$expense->note}",
            ],
            [
                'chart_of_account_id' => $creditAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'memo' => "Cash disbursement for Expense #{$expense->reference_no}",
            ],
        ];

        $header = [
            'entry_date' => $expense->created_at ? $expense->created_at->toDateString() : Carbon::now()->toDateString(),
            'reference_type' => 'expense',
            'reference_id' => $expense->id,
            'reference_no' => $expense->reference_no,
            'description' => "Operating expense #{$expense->reference_no}",
            'created_by' => auth()->id(),
        ];

        return $this->postJournalEntry($header, $items, $context);
    }

    public function getTrialBalance(?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        return app(FinancialReportService::class)->getTrialBalance($startDate, $endDate, $context);
    }

    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        return app(FinancialReportService::class)->getProfitAndLoss($startDate, $endDate, $context);
    }

    public function getBalanceSheet(?string $asOfDate = null, ?CompanyContext $context = null): array
    {
        return app(FinancialReportService::class)->getBalanceSheet($asOfDate, $context);
    }

    public function getGeneralLedger(int $accountId, ?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        return app(FinancialReportService::class)->getGeneralLedger($accountId, $startDate, $endDate, $context);
    }
}
