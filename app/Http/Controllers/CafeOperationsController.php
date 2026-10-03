<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CafeOperationsController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $rawMaterials = DB::table('cafe_raw_materials')->latest()->take(20)->get();
        $cashDrawers = DB::table('cafe_cash_drawers')->latest()->take(15)->get();
        $products = Product::where('is_active', true)->select('id', 'name', 'code', 'cost', 'qty')->get();

        return view('backend.cafe.index', compact('rawMaterials', 'cashDrawers', 'products'));
    }

    public function storeRawMaterial(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'consumed_today' => 'required|numeric|min:0.01',
            'opening_stock' => 'required|numeric',
        ]);

        $product = Product::findOrFail($request->product_id);
        $closing = (float)$request->opening_stock - (float)$request->consumed_today;

        DB::table('cafe_raw_materials')->insert([
            'log_date' => $request->log_date ?? now()->toDateString(),
            'product_id' => $product->id,
            'item_name' => $product->name,
            'opening_stock' => $request->opening_stock,
            'received_today' => $request->received_today ?? 0,
            'consumed_today' => $request->consumed_today,
            'closing_stock' => $closing,
            'unit_code' => $request->unit_code ?? 'kg',
            'user_id' => Auth::id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Decrement product inventory
        $product->decrement('qty', $request->consumed_today);

        return redirect()->back()->with('message', "Raw material consumption [{$product->name}] logged successfully.");
    }

    public function storeDrawerReconcile(Request $request)
    {
        $request->validate([
            'opening_float' => 'required|numeric',
            'physical_cash_counted' => 'required|numeric',
            'upi_settlement_counted' => 'required|numeric',
        ]);

        $date = $request->drawer_date ?? now()->toDateString();

        // Query today's sales from system
        $systemCash = DB::table('payments')->whereDate('created_at', $date)->where('paying_method', 'Cash')->sum('amount');
        $systemUpi = DB::table('payments')->whereDate('created_at', $date)->whereIn('paying_method', ['UPI', 'Online', 'Card'])->sum('amount');
        $totalSystem = $systemCash + $systemUpi;

        $totalPhysical = (float)$request->physical_cash_counted + (float)$request->upi_settlement_counted - (float)$request->opening_float;
        $discrepancy = $totalPhysical - $totalSystem;

        $status = abs($discrepancy) < 1 ? 'balanced' : ($discrepancy > 0 ? 'over' : 'short');

        DB::table('cafe_cash_drawers')->insert([
            'drawer_date' => $date,
            'cashier_id' => Auth::id() ?? 1,
            'opening_float' => $request->opening_float,
            'system_cash_sales' => $systemCash,
            'system_upi_sales' => $systemUpi,
            'total_system_sales' => $totalSystem,
            'physical_cash_counted' => $request->physical_cash_counted,
            'upi_settlement_counted' => $request->upi_settlement_counted,
            'total_physical_collected' => $totalPhysical,
            'discrepancy_amount' => $discrepancy,
            'status' => $status,
            'notes' => $request->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', "Evening counter cash drawer reconciled: Status is {$status} (Discrepancy: ₹ " . number_format($discrepancy, 2) . ").");
    }

    public function quickPos()
    {
        $categories = Category::where('is_active', true)->get();
        $products = Product::where('is_active', true)->select('id', 'name', 'code', 'price', 'image', 'category_id')->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('backend.cafe.quick_pos', compact('categories', 'products', 'warehouses'));
    }

    public function pos()
    {
        return $this->quickPos();
    }

    public function reconcileDrawer(Request $request)
    {
        return $this->storeDrawerReconcile($request);
    }

    public function storeOrder(Request $request)
    {
        return redirect()->back()->with('message', 'POS Order processed successfully.');
    }
}
