<?php

namespace App\Http\Controllers;

use App\Models\{Challan, Delivery, DeliveryChallan, PackingSlip, Sale};
use App\Services\Commercial\CommercialPermission;
use App\Services\Platform\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** A consolidated reader over existing dispatch documents; their workspaces still own writes. */
class DispatchRegisterController extends Controller
{
    public function index(Request $request)
    {
        $context = $request->attributes->get(CompanyContext::class);
        $definitions = [
            'dc' => ['Customer DCs', 'delivery-challans-index', DeliveryChallan::class, 'delivery-challans.index'],
            'challan' => ['Challan List', 'packing_slip_challan', Challan::class, 'challan.index'],
            'delivery' => ['Delivery List', 'delivery', Delivery::class, 'delivery.index'],
            'packing' => ['Packing Slips', 'packing_slip_challan', PackingSlip::class, 'packingSlip.index'],
        ];
        $allowed = app(CommercialPermission::class)->allowed(array_column($definitions, 1), $context, $request->user()->id);
        $tabs = array_filter($definitions, fn ($definition) => in_array($definition[1], $allowed, true));
        abort_unless($tabs, 403);
        $request->validate(['tab' => 'nullable|in:dc,challan,delivery,packing', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $tab = $request->input('tab') ?: array_key_first($tabs);
        abort_unless(isset($tabs[$tab]), 403);
        [$label, , $class, $route] = $tabs[$tab];
        $query = $class::query();
        $table = (new $class)->getTable();
        if (!Schema::hasTable($table)) return view('backend.commercial.dispatch-register', compact('tabs', 'tab', 'label', 'route') + ['rows' => null]);
        $sales = Sale::visibleIn($context)->select('sales.id');
        if ($tab === 'dc') {
            $query->forCompany($context)->where('branch_id', $context->branchId)->with('customer');
        } elseif ($tab === 'challan') {
            // Legacy courier challans contain comma-separated packing IDs and no company/branch key.
            // Admit a row only when every referenced packing slip belongs to the authorized branch.
            $owned = PackingSlip::whereIn('sale_id', $sales)->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
            $ids = [];
            foreach ((clone $query)->select('id', 'packing_slip_list')->cursor() as $row) {
                $parts = array_filter(explode(',', $row->packing_slip_list), fn ($part) => $part !== '');
                if ($parts && collect($parts)->every(fn ($id) => ctype_digit(trim($id)) && isset($owned[(int) $id]))) $ids[] = $row->id;
            }
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('sale_id', $sales);
        }
        $number = $tab === 'dc' ? 'challan_no' : 'reference_no';
        if ($request->filled('q')) $query->where($number, 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->q).'%');
        $rows = $query->orderByDesc('id')->paginate(50)->withQueryString();
        return view('backend.commercial.dispatch-register', compact('tabs', 'tab', 'label', 'route', 'rows'));
    }
}
