<?php

namespace App\Http\Controllers;

use App\Models\DamageStock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Product_Warehouse;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DamageStockController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $damageStocks = DamageStock::with(['warehouse', 'product', 'user'])->latest()->paginate(15);
        $warehouses = Warehouse::where('is_active', true)->get();
        $products = Product::where('is_active', true)->select('id', 'name', 'code', 'cost', 'qty')->get();

        return view('backend.stock.damage_stock', compact('damageStocks', 'warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
            'product_id' => 'required',
            'qty' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
        ]);

        $product = Product::findOrFail($request->product_id);
        $unitCost = $product->cost ?? 0;
        $totalLoss = $unitCost * $request->qty;
        $refNo = 'DMG-' . date('Ymd') . '-' . rand(1000, 9999);

        DB::transaction(function () use ($request, $product, $unitCost, $totalLoss, $refNo) {
            DamageStock::create([
                'reference_no' => $refNo,
                'warehouse_id' => $request->warehouse_id,
                'product_id' => $product->id,
                'variant_id' => $request->variant_id,
                'qty' => $request->qty,
                'unit_cost' => $unitCost,
                'total_loss' => $totalLoss,
                'reason' => $request->reason,
                'note' => $request->note,
                'user_id' => Auth::id() ?? 1,
            ]);

            // Deduct stock from Product_Warehouse
            $pw = Product_Warehouse::where('warehouse_id', $request->warehouse_id)
                ->where('product_id', $product->id)
                ->first();

            if ($pw) {
                $pw->decrement('qty', $request->qty);
            }
            $product->decrement('qty', $request->qty);

            // Post double-entry journal if total loss > 0
            if ($totalLoss > 0) {
                try {
                    $this->accountingService->postJournalEntry([
                        'entry_date' => now()->toDateString(),
                        'reference_type' => 'DamageStock',
                        'reference_id' => $refNo,
                        'narration' => "Damaged Inventory Write-off [{$product->name} x {$request->qty}]: {$request->reason}",
                        'items' => [
                            [
                                'account_code' => '5010', // COGS / Inventory Loss
                                'debit' => $totalLoss,
                                'credit' => 0,
                                'memo' => "Damage Loss Write-off"
                            ],
                            [
                                'account_code' => '1040', // Inventory Asset
                                'debit' => 0,
                                'credit' => $totalLoss,
                                'memo' => "Inventory Asset reduction"
                            ]
                        ]
                    ]);
                } catch (\Exception $e) {
                    // Log but allow damage record
                }
            }
        });

        return redirect()->back()->with('message', 'Damaged stock recorded and inventory written off successfully.');
    }
}
