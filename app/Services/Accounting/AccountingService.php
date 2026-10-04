<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Returns;
use App\Models\ReturnPurchase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
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
        $guard = app(CompanyWriteGuard::class);
        $actor = auth()->id() ?: ($header['created_by'] ?? null);
        $context = $guard->context($context, $actor);
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $sanitizedItems = [];
        foreach ($items as $item) {
            $debit = round((float) ($item['debit'] ?? 0), 4);
            $credit = round((float) ($item['credit'] ?? 0), 4);

            if (!is_finite($debit) || !is_finite($credit) || $debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new InvalidArgumentException('Journal amounts must be finite, nonnegative and on one side per line.');
            }

            // Ignore line if both debit and credit are 0
            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $sanitizedItems[] = [
                'chart_of_account_id' => $item['chart_of_account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $item['memo'] ?? null,
                'partner_type' => $item['partner_type'] ?? null,
                'partner_id' => $item['partner_id'] ?? null,
            ];
        }

        // Strict validation: Debits must equal Credits
        if (round($totalDebit - $totalCredit, 4) != 0.0) {
            throw new InvalidArgumentException(
                sprintf("Double-entry unbalanced: Total Debits (%.4f) must equal Total Credits (%.4f)", $totalDebit, $totalCredit)
            );
        }

        if (count($sanitizedItems) < 2) {
            throw new InvalidArgumentException("A valid journal entry requires at least two lines (one debit and one credit).");
        }

        return DB::transaction(function () use ($header, $sanitizedItems, $totalDebit, $totalCredit, $context, $guard, $actor) {
            $date = $guard->begin($context, $header['entry_date'] ?? null);
            $this->validateReference($header, $context, $guard);
            $accounts = [];
            foreach ($sanitizedItems as $line) {
                $account = $guard->owned(ChartOfAccount::class, $line['chart_of_account_id'], $context, 'items.chart_of_account_id');
                if (!$account->is_active) {
                    throw new InvalidArgumentException('Journal accounts must be active.');
                }
                $accounts[$account->id] = $account;
                if (!empty($line['partner_id'])) {
                    $model = match ($line['partner_type']) {
                        'customer' => Customer::class,
                        'supplier' => Supplier::class,
                        default => throw new InvalidArgumentException('Partner type requires a company-owned posting path.'),
                    };
                    $guard->owned($model, $line['partner_id'], $context, 'items.partner_id');
                } elseif (!empty($line['partner_type'])) {
                    throw new InvalidArgumentException('Journal partner type and ID must be supplied together.');
                }
            }
            $numbers = app(\App\Services\Platform\DocumentNumberService::class);
            $reservation = $numbers->reserve('journal', $context, $date, $actor);
            $entryNumber = $reservation->formatted_number;

            $entry = (new JournalEntry)->forceFill([
                'company_id' => $context->companyId,
                'entry_number' => $entryNumber,
                'entry_date' => $date,
                'reference_type' => $header['reference_type'] ?? 'manual',
                'reference_id' => $header['reference_id'] ?? null,
                'reference_no' => $header['reference_no'] ?? null,
                'description' => $header['description'] ?? 'Journal Entry',
                'status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => $actor,
            ]);
            $entry->save();
            $numbers->assign($reservation, $entry);

            foreach ($sanitizedItems as $line) {
                $line['journal_entry_id'] = $entry->id;
                (new JournalItem)->forceFill($line + ['company_id' => $context->companyId])->save();

                // Update account balance
                $account = $accounts[$line['chart_of_account_id']];
                if ($account) {
                    $change = $account->isDebitNormal()
                        ? ($line['debit'] - $line['credit'])
                        : ($line['credit'] - $line['debit']);
                    $account->increment('current_balance', $change);
                }
            }

            return $entry;
        });
    }

    /**
     * Find standard account by system code or sub_type
     */
    public function getAccount(string $codeOrSubType, ?CompanyContext $context = null, ?int $actor = null): ?ChartOfAccount
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor ?? auth()->id());
        $accounts = ChartOfAccount::forCompany($context)->where('is_active', true);
        $exact = (clone $accounts)->where('code', $codeOrSubType)->first();
        if ($exact) {
            return $exact;
        }
        $subType = [
            '1010' => 'cash', '1020' => 'bank', '1100' => 'accounts_receivable',
            '1200' => 'inventory', '2010' => 'accounts_payable', '2020' => 'tax_payable',
            '4010' => 'sales_revenue', '4020' => 'sales_discount', '4030' => 'shipping_income',
            '5010' => 'cogs', '6090' => 'operating_expense',
        ][$codeOrSubType] ?? $codeOrSubType;
        $matches = $accounts->where('sub_type', $subType)->get();
        if ($matches->count() > 1) {
            throw new InvalidArgumentException('Accounting role is ambiguous; configure its company mapping.');
        }
        return $matches->first();
    }

    private function validateReference(array $header, CompanyContext $context, CompanyWriteGuard $guard): void
    {
        if (empty($header['reference_id'])) {
            return;
        }
        $model = match ($header['reference_type'] ?? 'manual') {
            'sale' => Sale::class, 'purchase' => Purchase::class, 'payment' => Payment::class,
            'expense' => Expense::class,
            default => throw new InvalidArgumentException('This source requires its company-owned accounting path.'),
        };
        if ($model === Expense::class) {
            $source = $guard->owned($model, $header['reference_id'], $context, 'reference_id');
            if ($source->warehouse_id) {
                $guard->warehouse($source->warehouse_id, $context, (int) auth()->id());
            }
        } else {
            $model::visibleIn($context)->whereKey($header['reference_id'])->lockForUpdate()->firstOrFail();
        }
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
        $cashAccount = $this->getAccount('1010', $context, $actor); // Cash on Hand
        $bankAccount = $this->getAccount('1020', $context, $actor); // Bank
        $arAccount   = $this->getAccount('1100', $context, $actor); // Accounts Receivable
        $revAccount  = $this->getAccount('4010', $context, $actor); // Sales Revenue
        $taxAccount  = $this->getAccount('2020', $context, $actor); // Tax Payable
        $discAccount = $this->getAccount('4020', $context, $actor); // Sales Discounts
        $shipAccount = $this->getAccount('4030', $context, $actor); // Shipping Income
        $cogsAccount = $this->getAccount('5010', $context, $actor); // COGS
        $invAccount  = $this->getAccount('1200', $context, $actor); // Inventory Asset

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
            $method = $sale->payments()->where('company_id', $context->companyId)->first()?->paying_method ?? 'Cash';
            $depositAccount = in_array($method, ['Bank', 'Deposit', 'Cheque', 'Credit Card'], true) ? $bankAccount : $cashAccount;
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
        $cashAccount = $this->getAccount('1010', $context, $actor);
        $bankAccount = $this->getAccount('1020', $context, $actor);
        $invAccount  = $this->getAccount('1200', $context, $actor); // Merchandise Inventory
        $apAccount   = $this->getAccount('2010', $context, $actor); // Accounts Payable

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
            $method = $purchase->payments()->where('company_id', $context->companyId)->first()?->paying_method ?? 'Cash';
            $paymentAccount = in_array($method, ['Bank', 'Cheque', 'Credit Card'], true)
                ? $bankAccount : $cashAccount;
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
    public function postPaymentJournal(Payment $payment, ?CompanyContext $context = null, ?int $actor = null): ?JournalEntry
    {
        $context = app(CompanyWriteGuard::class)->context($context, $actor ?? auth()->id());
        $cashAccount = $this->getAccount('1010', $context, $actor);
        $bankAccount = $this->getAccount('1020', $context, $actor);
        $arAccount   = $this->getAccount('1100', $context, $actor); // Accounts Receivable
        $apAccount   = $this->getAccount('2010', $context, $actor); // Accounts Payable

        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return null;
        }

        $payingAccount = ($payment->paying_method == 'Bank' || $payment->paying_method == 'Cheque' || $payment->paying_method == 'Credit Card')
            ? $bankAccount
            : $cashAccount;

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

        $header = [
            'entry_date' => $payment->payment_at?->toDateString() ?? $payment->created_at?->toDateString(),
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'reference_no' => $payment->payment_reference,
            'description' => "Double-entry payment settlement #{$payment->payment_reference}",
            'created_by' => $actor ?? auth()->id() ?? $payment->user_id,
        ];

        return $this->postJournalEntry($header, $items, $context);
    }

    /**
     * Automatically post double-entry journal for Expenses.
     */
    public function postExpenseJournal(Expense $expense, ?CompanyContext $context = null): ?JournalEntry
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $cashAccount = $this->getAccount('1010', $context);
        $bankAccount = $this->getAccount('1020', $context);
        $generalExp  = $this->getAccount('6090', $context) ?? $this->getAccount('operating_expense', $context);

        $amount = (float) $expense->amount;
        if ($amount <= 0 || !$generalExp) {
            return null;
        }

        $creditAccount = ($cashAccount ?? $bankAccount);

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

    /** Historical opening-balance semantics remain unchanged; every activity row and header is owned. */
    private function accountActivity(CompanyContext $context, ?string $startDate, ?string $endDate): Builder
    {
        return ChartOfAccount::forCompany($context)->with([
            'journalItems' => fn ($q) => $q->forCompany($context)
                ->whereHas('journalEntry', function ($q) use ($context, $startDate, $endDate) {
                    $q->forCompany($context)->where('status', 'posted');
                    if ($startDate) {
                        $q->whereDate('entry_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('entry_date', '<=', $endDate);
                    }
                }),
        ]);
    }

    private function reportDates(?string $startDate, ?string $endDate): void
    {
        \Illuminate\Support\Facades\Validator::make(
            ['start_date' => $startDate, 'end_date' => $endDate],
            ['start_date' => 'nullable|date_format:Y-m-d|after_or_equal:1000-01-01',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:1000-01-01'.($startDate ? '|after_or_equal:start_date' : '')],
        )->validate();
    }

    // ==========================================
    // FINANCIAL STATEMENTS & REPORT GENERATION
    // ==========================================

    /**
     * Generate Trial Balance.
     * Checks mathematical equality: Sum(Debit) == Sum(Credit).
     */
    public function getTrialBalance(?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $this->reportDates($startDate, $endDate);
        return DB::transaction(function () use ($startDate, $endDate, $context) {
            $accounts = $this->accountActivity($context, $startDate, $endDate)->where('is_active', true)->orderBy('code')->get();

            $rows = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($accounts as $account) {
                $debit = (float) $account->journalItems->sum('debit');
                $credit = (float) $account->journalItems->sum('credit');

                // Skip zero activity accounts if both debit & credit are zero and opening is zero
                if ($debit == 0 && $credit == 0 && (float)$account->opening_balance == 0) {
                    continue;
                }

                $netDebit = 0.0;
                $netCredit = 0.0;

                if ($account->isDebitNormal()) {
                    $netBalance = (float) $account->opening_balance + ($debit - $credit);
                    if ($netBalance >= 0) {
                        $netDebit = $netBalance;
                    } else {
                        $netCredit = abs($netBalance);
                    }
                } else {
                    $netBalance = (float) $account->opening_balance + ($credit - $debit);
                    if ($netBalance >= 0) {
                        $netCredit = $netBalance;
                    } else {
                        $netDebit = abs($netBalance);
                    }
                }

                $totalDebit += $netDebit;
                $totalCredit += $netCredit;

                $rows[] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'sub_type' => $account->sub_type,
                    'debit' => $netDebit,
                    'credit' => $netCredit,
                ];
            }

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'accounts' => $rows,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
                'difference' => round(abs($totalDebit - $totalCredit), 2),
            ];
        });
    }

    /**
     * Generate Profit and Loss (Income Statement).
     * Net Income = Revenue - COGS - Operating Expenses
     */
    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $this->reportDates($startDate, $endDate);
        return DB::transaction(function () use ($startDate, $endDate, $context) {
            $accounts = $this->accountActivity($context, $startDate, $endDate)->whereIn('type', ['revenue', 'expense'])->where('is_active', true)->orderBy('code')->get();

            $revenues = [];
            $cogs = [];
            $expenses = [];

            $totalRevenue = 0.0;
            $totalCogs = 0.0;
            $totalOperatingExpense = 0.0;

            foreach ($accounts as $acc) {
                $debits = (float) $acc->journalItems->sum('debit');
                $credits = (float) $acc->journalItems->sum('credit');

                if ($acc->type === 'revenue') {
                    // Credit normal
                    $amount = ($credits - $debits);
                    if ($amount != 0) {
                        $revenues[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => round($amount, 2)];
                        $totalRevenue += $amount;
                    }
                } elseif ($acc->sub_type === 'cogs') {
                    // Debit normal
                    $amount = ($debits - $credits);
                    if ($amount != 0) {
                        $cogs[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => round($amount, 2)];
                        $totalCogs += $amount;
                    }
                } else {
                    // Operating expense
                    $amount = ($debits - $credits);
                    if ($amount != 0) {
                        $expenses[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => round($amount, 2)];
                        $totalOperatingExpense += $amount;
                    }
                }
            }

            $grossProfit = $totalRevenue - $totalCogs;
            $netIncome = $grossProfit - $totalOperatingExpense;

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'revenues' => $revenues,
                'total_revenue' => round($totalRevenue, 2),
                'cogs' => $cogs,
                'total_cogs' => round($totalCogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'operating_expenses' => $expenses,
                'total_operating_expenses' => round($totalOperatingExpense, 2),
                'net_income' => round($netIncome, 2),
            ];
        });
    }

    /**
     * Generate Balance Sheet.
     * Accounting equation: Assets = Liabilities + Equity (including current period Net Income)
     */
    public function getBalanceSheet(?string $asOfDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $this->reportDates(null, $asOfDate);
        return DB::transaction(function () use ($asOfDate, $context) {
            $asOfDate = $asOfDate ?: \Carbon\CarbonImmutable::now(\App\Models\Company::findOrFail($context->companyId)->timezone)->toDateString();

            $accounts = $this->accountActivity($context, null, $asOfDate)->whereIn('type', ['asset', 'liability', 'equity'])->where('is_active', true)->orderBy('code')->get();

            $assets = [];
            $liabilities = [];
            $equities = [];

            $totalAssets = 0.0;
            $totalLiabilities = 0.0;
            $totalEquity = 0.0;

            foreach ($accounts as $acc) {
                $debits = (float) $acc->journalItems->sum('debit');
                $credits = (float) $acc->journalItems->sum('credit');

                if ($acc->type === 'asset') {
                    $balance = (float) $acc->opening_balance + ($debits - $credits);
                    if ($balance != 0) {
                        $assets[] = ['code' => $acc->code, 'name' => $acc->name, 'sub_type' => $acc->sub_type, 'balance' => round($balance, 2)];
                        $totalAssets += $balance;
                    }
                } elseif ($acc->type === 'liability') {
                    $balance = (float) $acc->opening_balance + ($credits - $debits);
                    if ($balance != 0) {
                        $liabilities[] = ['code' => $acc->code, 'name' => $acc->name, 'sub_type' => $acc->sub_type, 'balance' => round($balance, 2)];
                        $totalLiabilities += $balance;
                    }
                } elseif ($acc->type === 'equity') {
                    $balance = (float) $acc->opening_balance + ($credits - $debits);
                    if ($balance != 0) {
                        $equities[] = ['code' => $acc->code, 'name' => $acc->name, 'sub_type' => $acc->sub_type, 'balance' => round($balance, 2)];
                        $totalEquity += $balance;
                    }
                }
            }

            // Add current period Net Income to Equity for dynamic balance
            $pnl = $this->getProfitAndLoss(null, $asOfDate, $context);
            $currentEarnings = $pnl['net_income'];

            $equities[] = [
                'code' => '9999',
                'name' => 'Current Year Earnings (Net Income)',
                'sub_type' => 'equity',
                'balance' => $currentEarnings,
            ];
            $totalEquity += $currentEarnings;

            $totalLiabEquity = $totalLiabilities + $totalEquity;
            $isBalanced = abs($totalAssets - $totalLiabEquity) < 0.01;

            return [
                'as_of_date' => $asOfDate,
                'assets' => $assets,
                'total_assets' => round($totalAssets, 2),
                'liabilities' => $liabilities,
                'total_liabilities' => round($totalLiabilities, 2),
                'equity' => $equities,
                'total_equity' => round($totalEquity, 2),
                'total_liabilities_and_equity' => round($totalLiabEquity, 2),
                'is_balanced' => $isBalanced,
                'difference' => round(abs($totalAssets - $totalLiabEquity), 2),
            ];
        });
    }

    /**
     * General Ledger Statement for a single Account.
     */
    public function getGeneralLedger(int $accountId, ?string $startDate = null, ?string $endDate = null, ?CompanyContext $context = null): array
    {
        $context = app(CompanyWriteGuard::class)->context($context, auth()->id());
        $this->reportDates($startDate, $endDate);
        return DB::transaction(function () use ($accountId, $startDate, $endDate, $context) {
            $account = ChartOfAccount::forCompany($context)->findOrFail($accountId);

            $query = JournalItem::forCompany($context)->with(['journalEntry' => fn ($q) => $q->forCompany($context)])
                ->where('chart_of_account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($context, $startDate, $endDate) {
                    $q->forCompany($context)->where('status', 'posted');
                    if ($startDate) {
                        $q->whereDate('entry_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->whereDate('entry_date', '<=', $endDate);
                    }
                });

            $items = $query->orderBy(
                JournalEntry::forCompany($context)->select('entry_date')->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
            )->get();

            $runningBalance = (float) $account->opening_balance;
            $rows = [];

            foreach ($items as $item) {
                $debit = (float) $item->debit;
                $credit = (float) $item->credit;

                if ($account->isDebitNormal()) {
                    $runningBalance += ($debit - $credit);
                } else {
                    $runningBalance += ($credit - $debit);
                }

                $rows[] = [
                    'date' => $item->journalEntry->entry_date->format('Y-m-d'),
                    'entry_number' => $item->journalEntry->entry_number,
                    'reference_type' => $item->journalEntry->reference_type,
                    'reference_no' => $item->journalEntry->reference_no,
                    'memo' => $item->memo ?: $item->journalEntry->description,
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                    'running_balance' => round($runningBalance, 2),
                ];
            }

            return [
                'account' => $account,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'opening_balance' => (float) $account->opening_balance,
                'closing_balance' => round($runningBalance, 2),
                'total_debit' => round((float)$items->sum('debit'), 2),
                'total_credit' => round((float)$items->sum('credit'), 2),
                'transactions' => $rows,
            ];
        });
    }
}
