<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\NumbersLegacyDocuments;
use App\Models\Agent;
use App\Models\BillSundry;
use App\Models\Currency;
use App\Models\DocumentSeries;
use App\Models\GeneralSetting;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\Product;
use App\Models\PurchaseType;
use App\Models\StandardRemark;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoodsReceivedNoteController extends Controller
{
    use NumbersLegacyDocuments;

    public function index(Request $request)
    {
        $role = DB::table('roles')->find(Auth::user()->role_id ?? 1);
        if ($role && $role->id > 2 && method_exists(Auth::user(), 'hasPermissionTo') && !Auth::user()->hasPermissionTo('grn-index')) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $general_setting = DB::table('general_settings')->latest()->first();
        $currency = $general_setting && $general_setting->currency ? Currency::find($general_setting->currency) : null;

        $recent_grns = GoodsReceivedNote::with(['supplier:id,name,company_name,phone_number', 'warehouse:id,name', 'purchaseType:id,name', 'agent:id,name'])
            ->latest('id')
            ->limit(50)
            ->get();

        $lims_supplier_list = Supplier::where('is_active', true)->select('id', 'name', 'company_name', 'phone_number', 'city', 'address')->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->select('id', 'name')->get();
        $lims_tax_list = Tax::where('is_active', true)->get();

        $purchaseTypes = PurchaseType::where('is_active', true)->orderBy('name')->get();
        $agents = Agent::where('is_active', true)->orderBy('name')->get();
        $billSundries = BillSundry::where('is_active', true)->orderBy('name')->get();
        $standardRemarks = StandardRemark::where('is_active', true)->orderBy('title')->get();
        $documentSeries = DocumentSeries::where('document_type', 'grn')->orderBy('code')->get();

        $lims_product_list_without_variant = Product::ActiveStandard()
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->select('products.id', 'products.name', 'products.code', 'products.price', 'products.cost', 'products.tax_id', 'products.unit_id', 'units.unit_name', 'units.unit_code')
            ->whereNull('products.is_variant')
            ->get();

        $lims_product_list_with_variant = Product::ActiveStandard()
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->select('products.id', 'products.name', 'products.code', 'products.price', 'products.cost', 'products.tax_id', 'products.unit_id', 'units.unit_name', 'units.unit_code')
            ->whereNotNull('products.is_variant')
            ->get();

        return view('backend.goods_received_note.index', compact(
            'recent_grns',
            'lims_supplier_list',
            'lims_warehouse_list',
            'lims_tax_list',
            'purchaseTypes',
            'agents',
            'billSundries',
            'standardRemarks',
            'documentSeries',
            'lims_product_list_without_variant',
            'lims_product_list_with_variant',
            'currency',
            'general_setting'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required',
            'warehouse_id' => 'required',
            'grn_date' => 'required|date',
            'product_id' => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $companyId = request()->attributes->get(\App\Services\Platform\CompanyContext::class)?->companyId ?? 1;
            $reservation = null;

            // Generate atomic document number or use provided
            $grnNo = $request->input('grn_no');
            if (empty($grnNo)) {
                try {
                    $reservation = $this->reserveNumber('grn', $request->input('grn_date'));
                    $grnNo = $reservation->formatted_number;
                } catch (\Throwable $e) {
                    $grnNo = 'GRN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                }
            }

            $grn = GoodsReceivedNote::create([
                'company_id' => $companyId,
                'grn_no' => $grnNo,
                'grn_date' => $request->input('grn_date'),
                'supplier_id' => (int)$request->input('supplier_id'),
                'warehouse_id' => (int)$request->input('warehouse_id'),
                'purchase_type_id' => $request->input('purchase_type_id') ? (int)$request->input('purchase_type_id') : null,
                'agent_id' => $request->input('agent_id') ? (int)$request->input('agent_id') : null,
                'transport_name' => $request->input('transport_name'),
                'lr_no' => $request->input('lr_no'),
                'lr_date' => $request->input('lr_date') ?: null,
                'order_no' => $request->input('order_no'),
                'total_qty' => (float)$request->input('total_qty', 0),
                'total_cost' => (float)$request->input('total_cost', 0),
                'total_tax' => (float)$request->input('total_tax', 0),
                'grand_total' => (float)$request->input('grand_total', 0),
                'sundries_json' => $request->input('sundries_json') ? (is_array($request->input('sundries_json')) ? $request->input('sundries_json') : json_decode($request->input('sundries_json'), true)) : null,
                'remarks' => $request->input('remarks'),
                'status' => 'pending',
                'created_by' => Auth::id() ?? 1,
            ]);

            if ($reservation) {
                $this->assignNumber($reservation, $grn);
            }

            // Save items
            $productIds = $request->input('product_id', []);
            $qtys = $request->input('qty', []);
            $costs = $request->input('cost', []);
            $amounts = $request->input('amount', []);
            $taxRates = $request->input('tax_rate', []);
            $taxAmounts = $request->input('tax_amount', []);
            $totals = $request->input('total', []);
            $unitIds = $request->input('unit_id', []);
            $itemRemarks = $request->input('item_remarks', []);

            foreach ($productIds as $idx => $prodId) {
                if (empty($prodId)) continue;
                GoodsReceivedNoteItem::create([
                    'company_id' => $companyId,
                    'goods_received_note_id' => $grn->id,
                    'product_id' => (int)$prodId,
                    'unit_id' => !empty($unitIds[$idx]) ? (int)$unitIds[$idx] : 1,
                    'qty' => (float)($qtys[$idx] ?? 1),
                    'cost' => (float)($costs[$idx] ?? 0),
                    'amount' => (float)($amounts[$idx] ?? 0),
                    'tax_rate' => (float)($taxRates[$idx] ?? 0),
                    'tax_amount' => (float)($taxAmounts[$idx] ?? 0),
                    'total' => (float)($totals[$idx] ?? 0),
                    'remarks' => $itemRemarks[$idx] ?? null,
                ]);
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Goods Received Note created successfully: ' . $grn->grn_no,
                    'grn' => $grn,
                ], 201);
            }

            return redirect()->route('goods-received-notes.index')->with('message', 'Goods Received Note created: ' . $grn->grn_no);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('not_permitted', 'Error creating GRN: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $grn = GoodsReceivedNote::with(['items.product', 'items.unit', 'supplier', 'warehouse', 'purchaseType', 'agent'])->findOrFail($id);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'grn' => $grn,
                'items' => $grn->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product ? $item->product->name : 'Item #' . $item->product_id,
                        'product_code' => $item->product ? $item->product->code : '',
                        'unit_id' => $item->unit_id,
                        'unit' => $item->unit ? ($item->unit->unit_code ?? $item->unit->unit_name) : 'Unit',
                        'qty' => (float)$item->qty,
                        'cost' => (float)$item->cost,
                        'amount' => (float)$item->amount,
                        'tax_rate' => (float)$item->tax_rate,
                        'tax_amount' => (float)$item->tax_amount,
                        'total' => (float)$item->total,
                        'remarks' => $item->remarks,
                    ];
                }),
            ]);
        }

        return view('backend.goods_received_note.show', compact('grn'));
    }

    public function update(Request $request, $id)
    {
        $grn = GoodsReceivedNote::findOrFail($id);

        $request->validate([
            'supplier_id' => 'required',
            'warehouse_id' => 'required',
            'grn_date' => 'required|date',
            'product_id' => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $companyId = request()->attributes->get(\App\Services\Platform\CompanyContext::class)?->companyId ?? 1;

            $grn->update([
                'grn_date' => $request->input('grn_date'),
                'supplier_id' => (int)$request->input('supplier_id'),
                'warehouse_id' => (int)$request->input('warehouse_id'),
                'purchase_type_id' => $request->input('purchase_type_id') ? (int)$request->input('purchase_type_id') : null,
                'agent_id' => $request->input('agent_id') ? (int)$request->input('agent_id') : null,
                'transport_name' => $request->input('transport_name'),
                'lr_no' => $request->input('lr_no'),
                'lr_date' => $request->input('lr_date') ?: null,
                'order_no' => $request->input('order_no'),
                'total_qty' => (float)$request->input('total_qty', 0),
                'total_cost' => (float)$request->input('total_cost', 0),
                'total_tax' => (float)$request->input('total_tax', 0),
                'grand_total' => (float)$request->input('grand_total', 0),
                'sundries_json' => $request->input('sundries_json') ? (is_array($request->input('sundries_json')) ? $request->input('sundries_json') : json_decode($request->input('sundries_json'), true)) : null,
                'remarks' => $request->input('remarks'),
            ]);

            GoodsReceivedNoteItem::where('goods_received_note_id', $grn->id)->delete();

            $productIds = $request->input('product_id', []);
            $qtys = $request->input('qty', []);
            $costs = $request->input('cost', []);
            $amounts = $request->input('amount', []);
            $taxRates = $request->input('tax_rate', []);
            $taxAmounts = $request->input('tax_amount', []);
            $totals = $request->input('total', []);
            $unitIds = $request->input('unit_id', []);
            $itemRemarks = $request->input('item_remarks', []);

            foreach ($productIds as $idx => $prodId) {
                if (empty($prodId)) continue;
                GoodsReceivedNoteItem::create([
                    'company_id' => $companyId,
                    'goods_received_note_id' => $grn->id,
                    'product_id' => (int)$prodId,
                    'unit_id' => !empty($unitIds[$idx]) ? (int)$unitIds[$idx] : 1,
                    'qty' => (float)($qtys[$idx] ?? 1),
                    'cost' => (float)($costs[$idx] ?? 0),
                    'amount' => (float)($amounts[$idx] ?? 0),
                    'tax_rate' => (float)($taxRates[$idx] ?? 0),
                    'tax_amount' => (float)($taxAmounts[$idx] ?? 0),
                    'total' => (float)($totals[$idx] ?? 0),
                    'remarks' => $itemRemarks[$idx] ?? null,
                ]);
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Goods Received Note updated: ' . $grn->grn_no,
                    'grn' => $grn,
                ]);
            }

            return redirect()->route('goods-received-notes.index')->with('message', 'GRN updated: ' . $grn->grn_no);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('not_permitted', 'Error updating GRN: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $grn = GoodsReceivedNote::findOrFail($id);
        $grn->update(['status' => 'cancelled']);
        GoodsReceivedNoteItem::where('goods_received_note_id', $grn->id)->delete();
        $grn->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'GRN cancelled/deleted.']);
        }

        return redirect()->route('goods-received-notes.index')->with('message', 'GRN cancelled/deleted.');
    }

    public function convertToPurchase(Request $request, $id)
    {
        $grn = GoodsReceivedNote::with(['items.product', 'items.unit'])->findOrFail($id);

        if ($grn->status === 'converted_to_purchase') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'GRN has already been converted to a Purchase Bill.'], 422);
            }
            return redirect()->back()->with('not_permitted', 'GRN has already been converted to a Purchase Bill.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'GRN ready for conversion.',
                'redirect_url' => route('purchases.index', ['from_grn' => $grn->id]),
                'grn' => $grn,
            ]);
        }

        return redirect()->route('purchases.index', ['from_grn' => $grn->id]);
    }
}
