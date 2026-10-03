# Section 09: General Ledger, 12-Month Calendar Breakup & Financial Statements
## Technical Specification & Implementation Guide
### Based on Video Walkthrough: `ACCOUNTS 08.mp4`

---

## 1. Domain Overview & Optech Legacy Behavior

In Optech Express ERP:
- **Ctrl+F5 Ledger Shortcut:** Instantly launches the General Ledger screen from anywhere.
- **12-Month Calendar Matrix (April to March):**
  - Displays a 12-row calendar grid representing each month of the Indian fiscal year (April, May, June... March).
  - Columns: `Month`, `Monthly Debit Total`, `Monthly Credit Total`, and `Closing Balance`.
  - **Click-Through Drilldown:** Clicking on any month (e.g. *August 2026*) immediately expands the detailed transaction ledger for that specific month. Clicking any transaction row opens the original voucher or invoice!
- **Day Book & Cash Book:** Real-time daily timeline of all cash and bank movements with running cash-in-hand balance.
- **Financial Statements:** Instant Trial Balance, Profit & Loss, and Balance Sheet derived strictly from double-entry voucher lines.

---

## 2. Mathematical 12-Month Breakup Aggregator Engine

Located in: `Modules/OptechAccounting/Services/LedgerReportService.php`

```php
namespace Modules\OptechAccounting\Services;

use Illuminate\Support\Facades\DB;

class LedgerReportService
{
    public function get12MonthBreakup(int $accountId, int $financialYearId): array
    {
        $months = [
            ['name' => 'April',     'm' => 4],
            ['name' => 'May',       'm' => 5],
            ['name' => 'June',      'm' => 6],
            ['name' => 'July',      'm' => 7],
            ['name' => 'August',    'm' => 8],
            ['name' => 'September', 'm' => 9],
            ['name' => 'October',   'm' => 10],
            ['name' => 'November',  'm' => 11],
            ['name' => 'December',  'm' => 12],
            ['name' => 'January',   'm' => 1],
            ['name' => 'February',  'm' => 2],
            ['name' => 'March',     'm' => 3],
        ];

        // Fetch opening balance
        $openingBalance = $this->getOpeningBalance($accountId, $financialYearId);
        $runningBalance = $openingBalance;

        $results = [];
        foreach ($months as $mo) {
            $monthData = DB::table('optech_voucher_lines as vl')
                ->join('optech_vouchers as v', 'vl.voucher_id', '=', 'v.id')
                ->where('vl.account_id', $accountId)
                ->where('v.financial_year_id', $financialYearId)
                ->whereMonth('v.voucher_date', $mo['m'])
                ->selectRaw("
                    SUM(CASE WHEN vl.entry_type = 'DR' THEN vl.amount ELSE 0 END) as total_debit,
                    SUM(CASE WHEN vl.entry_type = 'CR' THEN vl.amount ELSE 0 END) as total_credit
                ")->first();

            $dr = (float)($monthData->total_debit ?? 0.00);
            $cr = (float)($monthData->total_credit ?? 0.00);
            $runningBalance += ($dr - $cr);

            $results[] = [
                'month_name' => $mo['name'],
                'month_num' => $mo['m'],
                'debit' => $dr,
                'credit' => $cr,
                'closing_balance' => $runningBalance,
                'balance_type' => $runningBalance >= 0 ? 'Dr' : 'Cr'
            ];
        }

        return [
            'account_id' => $accountId,
            'opening_balance' => $openingBalance,
            'monthly_rows' => $results,
            'final_balance' => $runningBalance
        ];
    }
}
```

---

## 3. Verification Checklist

- [ ] Sum of monthly debits and credits across all 12 months exactly equals the fiscal year total on Trial Balance.
- [ ] Clicking any month expands the Day Book entries for that month without full page reload.