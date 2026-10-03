<?php

namespace App\Services\Accounting;

use App\Models\Accounting\ChartOfAccount;
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
    public function postJournalEntry(array $header, array $items): JournalEntry
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $sanitizedItems = [];
        foreach ($items as $item) {
            $debit = round((float) ($item['debit'] ?? 0), 4);
            $credit = round((float) ($item['credit'] ?? 0), 4);

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
        if (abs($totalDebit - $totalCredit) > 0.005) {
            throw new InvalidArgumentException(
                sprintf("Double-entry unbalanced: Total Debits (%.2f) must equal Total Credits (%.2f)", $totalDebit, $totalCredit)
            );
        }

        if (count($sanitizedItems) < 2) {
            throw new InvalidArgumentException("A valid journal entry requires at least two lines (one debit and one credit).");
        }

        return DB::transaction(function () use ($header, $sanitizedItems, $totalDebit, $totalCredit) {
            // Generate sequential entry number
            $date = $header['entry_date'] ?? Carbon::now()->toDateString();
            $datePrefix = Carbon::parse($date)->format('Ymd');
            $countToday = JournalEntry::whereDate('entry_date', $date)->count() + 1;
            $entryNumber = sprintf("JE-%s-%04d", $datePrefix, $countToday);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $date,
                'reference_type' => $header['reference_type'] ?? 'manual',
                'reference_id' => $header['reference_id'] ?? null,
                'reference_no' => $header['reference_no'] ?? null,
                'description' => $header['description'] ?? 'Journal Entry',
                'status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => $header['created_by'] ?? auth()->id() ?? 1,
            ]);

            foreach ($sanitizedItems as $line) {
                $line['journal_entry_id'] = $entry->id;
                JournalItem::create($line);

                // Update account balance
                $account = ChartOfAccount::find($line['chart_of_account_id']);
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
    public function getAccount(string $codeOrSubType): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', $codeOrSubType)
            ->orWhere('sub_type', $codeOrSubType)
            ->first();
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
    public function postSaleJournal(Sale $sale, ?float $cogsCost = null): ?JournalEntry
    {
        $cashAccount = $this->getAccount('1010'); // Cash on Hand
        $bankAccount = $this->getAccount('1020'); // Bank
        $arAccount   = $this->getAccount('1100'); // Accounts Receivable
        $revAccount  = $this->getAccount('4010'); // Sales Revenue
        $taxAccount  = $this->getAccount('2020'); // Tax Payable
        $discAccount = $this->getAccount('4020'); // Sales Discounts
        $shipAccount = $this->getAccount('4030'); // Shipping Income
        $cogsAccount = $this->getAccount('5010'); // COGS
        $invAccount  = $this->getAccount('1200'); // Inventory Asset

        if (!$revAccount || !$arAccount) {
            return null;
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
            $depositAccount = ($sale->paying_method == 'Deposit' || $sale->paying_method == 'Bank' || $sale->paying_method == 'Cheque')
                ? ($bankAccount ?? $cashAccount)
                : ($cashAccount ?? $bankAccount);

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
            'created_by' => $sale->user_id ?? auth()->id() ?? 1,
        ];

        return $this->postJournalEntry($header, $items);
    }

    /**
     * Automatically post double-entry journal for a Purchase.
     *
     * Accounting logic:
     * Dr. Merchandise Inventory Asset (Total Purchase Cost + Order Tax)
     * Cr. Bank / Cash (Paid Amount)
     * Cr. Accounts Payable (Due Amount)
     */
    public function postPurchaseJournal(Purchase $purchase): ?JournalEntry
    {
        $cashAccount = $this->getAccount('1010');
        $bankAccount = $this->getAccount('1020');
        $invAccount  = $this->getAccount('1200'); // Merchandise Inventory
        $apAccount   = $this->getAccount('2010'); // Accounts Payable

        if (!$invAccount || !$apAccount) {
            return null;
        }

        $grandTotal = (float) $purchase->grand_total;
        $paidAmount = (float) ($purchase->paid_amount ?? 0);
        $dueAmount  = max(0.0, $grandTotal - $paidAmount);

        $items = [];

        // 1. Debit Inventory Asset
        $items[] = [
            'chart_of_account_id' => $invAccount->id,
            'debit' => $grandTotal,
            'credit' => 0,
            'memo' => "Inventory received from Purchase PO {$purchase->reference_no}",
            'partner_type' => 'supplier',
            'partner_id' => $purchase->supplier_id,
        ];

        // 2. Credit Paid amount (Cash/Bank)
        if ($paidAmount > 0) {
            $paymentAccount = ($cashAccount ?? $bankAccount);
            $items[] = [
                'chart_of_account_id' => $paymentAccount->id,
                'debit' => 0,
                'credit' => $paidAmount,
                'memo' => "Payment for Purchase PO {$purchase->reference_no}",
                'partner_type' => 'supplier',
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
                'partner_type' => 'supplier',
                'partner_id' => $purchase->supplier_id,
            ];
        }

        $header = [
            'entry_date' => $purchase->created_at ? $purchase->created_at->toDateString() : Carbon::now()->toDateString(),
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
            'reference_no' => $purchase->reference_no,
            'description' => "Double-entry posting for Purchase PO #{$purchase->reference_no}",
            'created_by' => $purchase->user_id ?? auth()->id() ?? 1,
        ];

        return $this->postJournalEntry($header, $items);
    }

    /**
     * Automatically post double-entry journal for Payments (Customer payments or Supplier payments).
     */
    public function postPaymentJournal(Payment $payment): ?JournalEntry
    {
        $cashAccount = $this->getAccount('1010');
        $bankAccount = $this->getAccount('1020');
        $arAccount   = $this->getAccount('1100'); // Accounts Receivable
        $apAccount   = $this->getAccount('2010'); // Accounts Payable

        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return null;
        }

        $payingAccount = ($payment->paying_method == 'Bank' || $payment->paying_method == 'Cheque' || $payment->paying_method == 'Credit Card')
            ? ($bankAccount ?? $cashAccount)
            : ($cashAccount ?? $bankAccount);

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
            'entry_date' => $payment->created_at ? $payment->created_at->toDateString() : Carbon::now()->toDateString(),
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'reference_no' => $payment->payment_reference,
            'description' => "Double-entry payment settlement #{$payment->payment_reference}",
            'created_by' => $payment->user_id ?? auth()->id() ?? 1,
        ];

        return $this->postJournalEntry($header, $items);
    }

    /**
     * Automatically post double-entry journal for Expenses.
     */
    public function postExpenseJournal(Expense $expense): ?JournalEntry
    {
        $cashAccount = $this->getAccount('1010');
        $bankAccount = $this->getAccount('1020');
        $generalExp  = $this->getAccount('6090') ?? $this->getAccount('operating_expense');

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
            'created_by' => $expense->user_id ?? auth()->id() ?? 1,
        ];

        return $this->postJournalEntry($header, $items);
    }

    // ==========================================
    // FINANCIAL STATEMENTS & REPORT GENERATION
    // ==========================================

    /**
     * Generate Trial Balance.
     * Checks mathematical equality: Sum(Debit) == Sum(Credit).
     */
    public function getTrialBalance(?string $startDate = null, ?string $endDate = null): array
    {
        $accounts = ChartOfAccount::with(['journalItems' => function ($q) use ($startDate, $endDate) {
            $q->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'posted');
                if ($startDate) {
                    $query->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('entry_date', '<=', $endDate);
                }
            });
        }])->where('is_active', true)->orderBy('code')->get();

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
    }

    /**
     * Generate Profit and Loss (Income Statement).
     * Net Income = Revenue - COGS - Operating Expenses
     */
    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null): array
    {
        $accounts = ChartOfAccount::with(['journalItems' => function ($q) use ($startDate, $endDate) {
            $q->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->where('status', 'posted');
                if ($startDate) {
                    $query->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('entry_date', '<=', $endDate);
                }
            });
        }])->whereIn('type', ['revenue', 'expense'])->where('is_active', true)->orderBy('code')->get();

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
    }

    /**
     * Generate Balance Sheet.
     * Accounting equation: Assets = Liabilities + Equity (including current period Net Income)
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: Carbon::now()->toDateString();

        $accounts = ChartOfAccount::with(['journalItems' => function ($q) use ($asOfDate) {
            $q->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<=', $asOfDate);
            });
        }])->whereIn('type', ['asset', 'liability', 'equity'])->where('is_active', true)->orderBy('code')->get();

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
        $pnl = $this->getProfitAndLoss(null, $asOfDate);
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
    }

    /**
     * General Ledger Statement for a single Account.
     */
    public function getGeneralLedger(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $query = JournalItem::with('journalEntry')
            ->where('chart_of_account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'posted');
                if ($startDate) {
                    $q->where('entry_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('entry_date', '<=', $endDate);
                }
            });

        $items = $query->orderBy(
            JournalEntry::select('entry_date')->whereColumn('journal_entries.id', 'journal_items.journal_entry_id')
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
    }
}
