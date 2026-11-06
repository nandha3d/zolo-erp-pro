<?php

namespace App\Services\Platform;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyInsightService
{
    /**
     * Compute operational and financial insights for a single company.
     *
     * @param int $companyId
     * @return array
     */
    public function companyInsight(int $companyId): array
    {
        try {
            $hasSales = Schema::hasTable('sales');
            $hasReturns = Schema::hasTable('returns');
            $hasIncomes = Schema::hasTable('incomes');
            $hasExpenses = Schema::hasTable('expenses');
            $hasPurchases = Schema::hasTable('purchases');
            $hasReturnPurchases = Schema::hasTable('return_purchases');
            $hasProductSales = Schema::hasTable('product_sales');
            $hasProductPurchases = Schema::hasTable('product_purchases');
            $hasOpenItems = Schema::hasTable('account_open_items');

            // 1. Sales & Invoices
            $grossSales = 0.0;
            $salesCount = 0;
            if ($hasSales) {
                $saleQuery = DB::table('sales')->where('company_id', $companyId)->whereNull('deleted_at');
                $grossSales = (float) (clone $saleQuery)->sum('grand_total');
                $salesCount = (int) (clone $saleQuery)->count();
            }

            // 2. Returns
            $salesReturns = $hasReturns
                ? (float) DB::table('returns')->where('company_id', $companyId)->sum('grand_total')
                : 0.0;

            // 3. Incomes & Expenses
            $income = $hasIncomes
                ? (float) DB::table('incomes')->where('company_id', $companyId)->sum('amount')
                : 0.0;
            $expense = $hasExpenses
                ? (float) DB::table('expenses')->where('company_id', $companyId)->sum('amount')
                : 0.0;

            // Net Revenue = Gross Sales - Sales Returns + Income
            $netRevenue = max(0, $grossSales - $salesReturns + $income);

            // 4. Purchases
            $purchases = 0.0;
            $purchasesCount = 0;
            if ($hasPurchases) {
                $purchaseQuery = DB::table('purchases')->where('company_id', $companyId)->whereNull('deleted_at');
                $purchases = (float) (clone $purchaseQuery)->sum('grand_total');
                $purchasesCount = (int) (clone $purchaseQuery)->count();
            }

            $purchaseReturns = $hasReturnPurchases
                ? (float) DB::table('return_purchases')->where('company_id', $companyId)->sum('grand_total')
                : 0.0;

            // 5. COGS (Cost of Goods Sold for items sold by this company)
            $cogs = 0.0;
            if ($hasProductSales && $hasSales) {
                try {
                    $hasReturnQty = Schema::hasColumn('product_sales', 'return_qty');
                    $returnQtySelect = $hasReturnQty ? 'SUM(product_sales.return_qty)' : '0';
                    $soldItems = DB::table('product_sales')
                        ->join('sales', 'product_sales.sale_id', '=', 'sales.id')
                        ->where('sales.company_id', $companyId)
                        ->whereNull('sales.deleted_at')
                        ->select(
                            'product_sales.product_id',
                            'product_sales.product_batch_id',
                            DB::raw('SUM(product_sales.qty) as sold_qty'),
                            DB::raw($returnQtySelect . ' as returned_qty')
                        )
                        ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                        ->get();

                    foreach ($soldItems as $item) {
                        $netQty = max(0, (float) $item->sold_qty - (float) $item->returned_qty);
                        if ($netQty <= 0) {
                            continue;
                        }

                        $avgCost = 0.0;
                        if ($hasProductPurchases && $hasPurchases) {
                            $avgCost = DB::table('product_purchases')
                                ->join('purchases', 'product_purchases.purchase_id', '=', 'purchases.id')
                                ->where('purchases.company_id', $companyId)
                                ->where('product_purchases.product_id', $item->product_id)
                                ->when($item->product_batch_id, fn ($q) => $q->where('product_purchases.product_batch_id', $item->product_batch_id))
                                ->avg('product_purchases.net_unit_cost');
                        }

                        if (!$avgCost || $avgCost <= 0) {
                            $avgCost = Schema::hasTable('products')
                                ? (float) DB::table('products')->where('id', $item->product_id)->value('cost') ?: 0.0
                                : 0.0;
                        }
                        $cogs += $netQty * (float) $avgCost;
                    }
                } catch (\Throwable $e) {
                    $cogs = 0.0;
                }
            }

            // Net Profit = Net Revenue + Purchase Returns - COGS - Expenses
            $profit = round($netRevenue + $purchaseReturns - $cogs - $expense, 2);
            $isLoss = $profit < 0;
            $marginPercent = $netRevenue > 0 ? round(($profit / $netRevenue) * 100, 1) : ($profit < 0 ? -100.0 : 0.0);

            // 6. Outstanding Receivables (Customer Dues) & Payables (Supplier Dues)
            $receivables = $hasOpenItems
                ? (float) DB::table('account_open_items')->where('company_id', $companyId)->where('party_type', 'customer')->sum('open_amount')
                : 0.0;

            $payables = $hasOpenItems
                ? (float) DB::table('account_open_items')->where('company_id', $companyId)->where('party_type', 'supplier')->sum('open_amount')
                : 0.0;

            // 7. Last 6 Months Sales Trajectory for Mini Chart
            $monthlyTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $ym = $month->format('Y-m');
                $salesVal = $hasSales
                    ? (float) DB::table('sales')->where('company_id', $companyId)->whereNull('deleted_at')->where('created_at', 'like', "{$ym}%")->sum('grand_total')
                    : 0.0;

                $monthlyTrend[] = [
                    'month' => $month->format('M'),
                    'year_month' => $ym,
                    'amount' => round($salesVal, 2),
                ];
            }

            return [
                'company_id' => $companyId,
                'revenue' => round($netRevenue, 2),
                'gross_sales' => round($grossSales, 2),
                'sales_count' => $salesCount,
                'purchases' => round($purchases, 2),
                'purchases_count' => $purchasesCount,
                'cogs' => round($cogs, 2),
                'expenses' => round($expense, 2),
                'profit' => $profit,
                'margin_percent' => $marginPercent,
                'is_loss' => $isLoss,
                'receivables' => round($receivables, 2),
                'payables' => round($payables, 2),
                'monthly_trend' => $monthlyTrend,
            ];
        } catch (\Throwable $e) {
            return [
                'company_id' => $companyId,
                'revenue' => 0.0,
                'gross_sales' => 0.0,
                'sales_count' => 0,
                'purchases' => 0.0,
                'purchases_count' => 0,
                'cogs' => 0.0,
                'expenses' => 0.0,
                'profit' => 0.0,
                'margin_percent' => 0.0,
                'is_loss' => false,
                'receivables' => 0.0,
                'payables' => 0.0,
                'monthly_trend' => [],
            ];
        }
    }

    /**
     * Compute directory-wide consolidated insights for groups and companies.
     *
     * @param array $directory
     * @return array
     */
    public function directoryInsights(array $directory): array
    {
        $companyInsights = [];
        $groupRollups = [];

        // 1. Process all groups and their member companies
        foreach ($directory['groups'] ?? [] as $group) {
            $groupId = $group['id'];
            $companies = $group['companies'] ?? [];
            $groupCompanyIds = array_column($companies, 'id');

            $groupRevenue = 0.0;
            $groupGrossSales = 0.0;
            $groupPurchases = 0.0;
            $groupExpenses = 0.0;
            $groupCogs = 0.0;
            $groupProfit = 0.0;
            $groupReceivables = 0.0;
            $groupPayables = 0.0;
            $groupSalesCount = 0;
            $groupPurchasesCount = 0;

            $lossCompanies = [];
            $profitableCompanies = [];
            $companyBreakdowns = [];

            foreach ($companies as $comp) {
                $cId = $comp['id'];
                $insight = $this->companyInsight($cId);
                $insight['name'] = $comp['name'];
                $insight['code'] = $comp['code'];
                $insight['industry'] = $comp['industry'] ?? 'general_trading';

                $companyInsights[$cId] = $insight;
                $companyBreakdowns[$cId] = $insight;

                $groupRevenue += $insight['revenue'];
                $groupGrossSales += $insight['gross_sales'];
                $groupPurchases += $insight['purchases'];
                $groupExpenses += $insight['expenses'];
                $groupCogs += $insight['cogs'];
                $groupProfit += $insight['profit'];
                $groupReceivables += $insight['receivables'];
                $groupPayables += $insight['payables'];
                $groupSalesCount += $insight['sales_count'];
                $groupPurchasesCount += $insight['purchases_count'];

                if ($insight['is_loss']) {
                    $lossCompanies[] = [
                        'id' => $cId,
                        'name' => $comp['name'],
                        'code' => $comp['code'],
                        'loss' => abs($insight['profit']),
                        'margin_percent' => $insight['margin_percent'],
                    ];
                } else {
                    $profitableCompanies[] = [
                        'id' => $cId,
                        'name' => $comp['name'],
                        'code' => $comp['code'],
                        'profit' => $insight['profit'],
                        'margin_percent' => $insight['margin_percent'],
                    ];
                }
            }

            $groupMarginPercent = $groupRevenue > 0
                ? round(($groupProfit / $groupRevenue) * 100, 1)
                : ($groupProfit < 0 ? -100.0 : 0.0);

            // Compute monthly trend combined for the group
            $groupMonthlyTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $ym = $month->format('Y-m');
                $monthTotal = 0.0;
                foreach ($groupCompanyIds as $cid) {
                    if (isset($companyInsights[$cid])) {
                        foreach ($companyInsights[$cid]['monthly_trend'] as $mt) {
                            if ($mt['year_month'] === $ym) {
                                $monthTotal += $mt['amount'];
                            }
                        }
                    }
                }
                $groupMonthlyTrend[] = [
                    'month' => $month->format('M'),
                    'year_month' => $ym,
                    'amount' => round($monthTotal, 2),
                ];
            }

            $groupRollups[$groupId] = [
                'group_id' => $groupId,
                'name' => $group['name'],
                'code' => $group['code'],
                'company_count' => count($companies),
                'total_revenue' => round($groupRevenue, 2),
                'total_gross_sales' => round($groupGrossSales, 2),
                'total_purchases' => round($groupPurchases, 2),
                'total_expenses' => round($groupExpenses, 2),
                'total_cogs' => round($groupCogs, 2),
                'net_profit' => round($groupProfit, 2),
                'margin_percent' => $groupMarginPercent,
                'is_loss' => $groupProfit < 0,
                'total_receivables' => round($groupReceivables, 2),
                'total_payables' => round($groupPayables, 2),
                'total_sales_count' => $groupSalesCount,
                'total_purchases_count' => $groupPurchasesCount,
                'loss_count' => count($lossCompanies),
                'loss_companies' => $lossCompanies,
                'profitable_count' => count($profitableCompanies),
                'profitable_companies' => $profitableCompanies,
                'company_breakdowns' => $companyBreakdowns,
                'monthly_trend' => $groupMonthlyTrend,
            ];
        }

        // 2. Process ungrouped companies
        foreach ($directory['ungrouped'] ?? [] as $comp) {
            $cId = $comp['id'];
            $insight = $this->companyInsight($cId);
            $insight['name'] = $comp['name'];
            $insight['code'] = $comp['code'];
            $insight['industry'] = $comp['industry'] ?? 'general_trading';
            $companyInsights[$cId] = $insight;
        }

        return [
            'companies' => $companyInsights,
            'groups' => $groupRollups,
        ];
    }
}
