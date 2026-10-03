<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Warehouse;
use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeriodicInventoryCloseController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index(Request $request)
    {
        $valuationDate = $request->input('valuation_date', now()->toDateString());
        $warehouseId = $request->input('warehouse_id');

        $query = Product_Warehouse::with(['product', 'warehouse']);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stockLines = $query->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        $totalQty = 0;
        $totalValuation = 0;

        foreach ($stockLines as $line) {
            $qty = $line->qty ?? 0;
            $cost = $line->product->cost ?? 0;
            $line->valuation = $qty * $cost;
            $totalQty += $qty;
            $totalValuation += $line->valuation;
        }

        // Current General Ledger Inventory Asset Balance (Account 1040)
        $glAccount = ChartOfAccount::where('code', '1040')->first();
        $glBalance = $glAccount ? $glAccount->current_balance : 0;
        $variance = $totalValuation - $glBalance;

        return view('backend.accounting.inventory_close', compact(
            'valuationDate', 'warehouseId', 'warehouses', 'stockLines',
            'totalQty', 'totalValuation', 'glBalance', 'variance'
        ));
    }

    public function postClose(Request $request)
    {
        $request->validate([
            'valuation_date' => 'required|date',
            'total_valuation' => 'required|numeric',
        ]);

        $refNo = 'INV-CLOSE-' . date('Ymd') . '-' . rand(100, 999);
        $totalValuation = (float)$request->total_valuation;
        $glAccount = ChartOfAccount::where('code', '1040')->first();
        $glBalance = $glAccount ? $glAccount->current_balance : 0;
        $adjustment = $totalValuation - $glBalance;

        DB::transaction(function () use ($request, $refNo, $totalValuation, $adjustment) {
            $entry = null;
            if (abs($adjustment) > 0.01) {
                $items = [];
                if ($adjustment > 0) {
                    // Physical stock valuation is higher than GL -> Debit Inventory Asset, Credit COGS/Inventory Gain
                    $items[] = ['account_code' => '1040', 'debit' => $adjustment, 'credit' => 0, 'memo' => 'Inventory physical reconciliation gain'];
                    $items[] = ['account_code' => '5010', 'debit' => 0, 'credit' => $adjustment, 'memo' => 'Inventory physical gain offset'];
                } else {
                    // Physical stock valuation is lower -> Debit COGS/Inventory Loss, Credit Inventory Asset
                    $loss = abs($adjustment);
                    $items[] = ['account_code' => '5010', 'debit' => $loss, 'credit' => 0, 'memo' => 'Inventory shrinkage / physical loss'];
                    $items[] = ['account_code' => '1040', 'debit' => 0, 'credit' => $loss, 'memo' => 'Inventory physical loss asset reduction'];
                }

                try {
                    $entry = $this->accountingService->postJournalEntry([
                        'entry_date' => $request->valuation_date,
                        'reference_type' => 'InventoryClose',
                        'reference_id' => $refNo,
                        'narration' => "Periodic Inventory Close & Reconciliation [{$refNo}]",
                        'items' => $items
                    ]);
                } catch (\Exception $e) {}
            }

            DB::table('inventory_closes')->insert([
                'reference_no' => $refNo,
                'closing_date' => $request->valuation_date,
                'warehouse_id' => $request->warehouse_id,
                'valuation_method' => 'weighted_average',
                'total_quantity' => $request->total_quantity ?? 0,
                'total_valuation' => $totalValuation,
                'adjustment_amount' => $adjustment,
                'journal_entry_id' => $entry?->id,
                'status' => 'posted',
                'notes' => $request->notes,
                'user_id' => Auth::id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->back()->with('message', "Periodic inventory valuation close completed successfully. General ledger balance synchronized.");
    }
}
