<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Product_Warehouse;
use App\Models\Warehouse;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Http\Request;

class PeriodicInventoryCloseController extends Controller
{
    public function index(Request $request)
    {
        $context = app(CompanyContextResolver::class)->forActor();
        $warehouseId = $request->input('warehouse_id');
        $warehouses = Warehouse::forCompany($context)->where('branch_id', $context->branchId)->where('is_active', true)->get();
        if ($warehouseId) {
            abort_unless($warehouses->contains('id', $warehouseId), 422, 'Select a warehouse in the authorized branch.');
        }
        $query = Product_Warehouse::visibleIn($context)->with([
            'product' => fn ($q) => $q->forCompany($context),
            'warehouse' => fn ($q) => $q->forCompany($context)->where('branch_id', $context->branchId),
        ]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        $stockLines = $query->get();
        $totalQty = 0;
        $totalValuation = 0;
        foreach ($stockLines as $line) {
            $line->valuation = (float) $line->qty * (float) $line->product->cost;
            $totalQty += (float) $line->qty;
            $totalValuation += $line->valuation;
        }

        return view('backend.accounting.inventory_close', compact('warehouseId', 'warehouses', 'stockLines', 'totalQty', 'totalValuation'));
    }

    public function postClose(Request $request)
    {
        app(CompanyContextResolver::class)->forActor();
        abort(409, 'Inventory-close posting is blocked until the inventory ledger supports dated valuation and company-wide reconciliation.');
    }
}
