<?php

namespace App\Http\Controllers;

use App\Services\Inventory\LegacyInventoryPosting;
use App\Services\Inventory\StockLine;

use Illuminate\Http\Request;
use App\Models\Warehouse;
use App\Models\Product_Warehouse;
use App\Models\Product;
use App\Models\Adjustment;
use App\Models\ProductAdjustment;
use Illuminate\Support\Facades\DB;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\StockCount;
use App\Models\ProductVariant;
use App\Models\ProductPurchase;
use Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdjustmentController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if( $role->hasPermissionTo('adjustment') ) {
            /*if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $lims_adjustment_all = Adjustment::orderBy('id', 'desc')->where('user_id', Auth::id())->get();
            else*/
                $lims_adjustment_all = Adjustment::orderBy('id', 'desc')->get();
            return view('backend.adjustment.index', compact('lims_adjustment_all'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function getProduct($id)
    {
        $lims_product_warehouse_data = DB::table('products')
                                    ->join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
                                    ->whereNull('products.is_variant')
                                    ->where([
                                        ['products.is_active', true],
                                        ['product_warehouse.warehouse_id', $id]
                                    ])
                                    ->select('product_warehouse.qty', 'products.code', 'products.name', 'product_warehouse.product_id', 'products.cost')
                                    ->get();
        $lims_product_withVariant_warehouse_data = DB::table('products')
                                    ->join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
                                    ->whereNotNull('products.is_variant')
                                    ->where([
                                        ['products.is_active', true],
                                        ['product_warehouse.warehouse_id', $id]
                                    ])
                                    ->select('products.name', 'product_warehouse.qty', 'product_warehouse.product_id', 'product_warehouse.variant_id', 'products.cost')
                                    ->get();
        $product_code = [];
        $product_name = [];
        $product_qty = [];
        $product_cost = [];
        $product_data = [];
        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_code[] =  $product_warehouse->code;
            $product_name[] = $product_warehouse->name;
            $query = array(
                    'SUM(qty) AS total_qty',
                    'SUM(total) AS total_cost'
                );
            $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.product_id', '=', 'purchases.id')
                                    ->where([
                                        ['product_id', $product_warehouse->product_id],
                                        ['warehouse_id', $id]
                                    ])->whereNull('purchases.deleted_at')
                                    ->selectRaw(implode(',', $query))->get();
            if(count($product_purchase_data) && $product_purchase_data[0]->total_qty > 0)
                $product_cost[] = $product_purchase_data[0]->total_cost / $product_purchase_data[0]->total_qty;
            else
                $product_cost[] = $product_warehouse->cost;
        }

        foreach ($lims_product_withVariant_warehouse_data as $product_warehouse)
        {
            $product_variant = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
            if($product_variant) {
                $product_qty[] = $product_warehouse->qty;
                $product_code[] =  $product_variant->item_code;
                $product_name[] = $product_warehouse->name;
                $query = array(
                    'SUM(qty) AS total_qty',
                    'SUM(total) AS total_cost'
                );
                $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.product_id', '=', 'purchases.id')
                                        ->where([
                                            ['product_id', $product_warehouse->product_id],
                                            ['variant_id', $product_warehouse->variant_id],
                                            ['warehouse_id', $id]
                                        ])->whereNull('purchases.deleted_at')
                                        ->selectRaw(implode(',', $query))->get();
                if(count($product_purchase_data) && $product_purchase_data[0]->total_qty > 0)
                    $product_cost[] = $product_purchase_data[0]->total_cost / $product_purchase_data[0]->total_qty;
                else
                    $product_cost[] = $product_warehouse->cost;
                }
        }

        $product_data[] = $product_code;
        $product_data[] = $product_name;
        $product_data[] = $product_qty;
        $product_data[] = $product_cost;
        return $product_data;
    }

    public function limsProductSearch(Request $request)
    {
        $product_code = explode("(", $request['data']);
        $product_info = explode("|", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");

        //return $product_info;
        $lims_product_data = Product::where([
            ['code', $product_code[0]],
            ['is_active', true]
        ])->first();
        if(!$lims_product_data) {
            $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.id', 'products.name', 'products.is_variant', 'product_variants.id as product_variant_id', 'product_variants.item_code')
                ->where([
                    ['product_variants.item_code', $product_code[0]],
                    ['products.is_active', true]
                ])->first();
        }

        $product[] = $lims_product_data->name;
        $product_variant_id = null;
        if($lims_product_data->is_variant) {
            $product[] = $lims_product_data->item_code;
            $product_variant_id = $lims_product_data->product_variant_id;
        }
        else
            $product[] = $lims_product_data->code;

        $product[] = $lims_product_data->id;
        $product[] = $product_variant_id;
        $product[] = $product_info[1];
        $quantity = explode("|", $request['data']);
        if (count($quantity) >= 3) {
            $product[] =  $quantity[2];
        }
        return $product;
    }

    // public function limsProductSearch(Request $request)
    // {
    //     $product_code = explode("|", $request['data']);
    //     $product_code[0] = rtrim($product_code[0], " ");
    //     $lims_product_data = Product::where([
    //                             ['code', $product_code[0]],
    //                             ['is_active', true]
    //                         ])
    //                         ->whereNull('is_variant')
    //                         ->first();
    //     if(!$lims_product_data) {
    //         $lims_product_data = Product::where([
    //                             ['name', $product_code[1]],
    //                             ['is_active', true]
    //                         ])
    //                         ->whereNotNull(['is_variant'])
    //                         ->first();
    //         $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
    //             ->where([
    //                 ['product_variants.item_code', $product_code[0]],
    //                 ['products.is_active', true]
    //             ])
    //             ->whereNotNull('is_variant')
    //             ->select('products.*', 'product_variants.item_code', 'product_variants.additional_cost')
    //             ->first();
    //         $lims_product_data->cost += $lims_product_data->additional_cost;
    //     }
    //     $product[] = $lims_product_data->name;
    //     if($lims_product_data->is_variant)
    //         $product[] = $lims_product_data->item_code;
    //     else
    //         $product[] = $lims_product_data->code;
    //     $product[] = $lims_product_data->cost;

    //     if ($lims_product_data->tax_id) {
    //         $lims_tax_data = Tax::find($lims_product_data->tax_id);
    //         $product[] = $lims_tax_data->rate;
    //         $product[] = $lims_tax_data->name;
    //     } else {
    //         $product[] = 0;
    //         $product[] = 'No Tax';
    //     }
    //     $product[] = $lims_product_data->tax_method;

    //     $units = Unit::where("base_unit", $lims_product_data->unit_id)
    //                 ->orWhere('id', $lims_product_data->unit_id)
    //                 ->get();
    //     $unit_name = array();
    //     $unit_operator = array();
    //     $unit_operation_value = array();
    //     foreach ($units as $unit) {
    //         if ($lims_product_data->purchase_unit_id == $unit->id) {
    //             array_unshift($unit_name, $unit->unit_name);
    //             array_unshift($unit_operator, $unit->operator);
    //             array_unshift($unit_operation_value, $unit->operation_value);
    //         } else {
    //             $unit_name[]  = $unit->unit_name;
    //             $unit_operator[] = $unit->operator;
    //             $unit_operation_value[] = $unit->operation_value;
    //         }
    //     }

    //     $product[] = implode(",", $unit_name) . ',';
    //     $product[] = implode(",", $unit_operator) . ',';
    //     $product[] = implode(",", $unit_operation_value) . ',';
    //     $product[] = $lims_product_data->id;
    //     $product[] = $lims_product_data->is_batch;
    //     $product[] = $lims_product_data->is_imei;
    //     // return dd($product);
    //     return $product;
    // }

    public function create()
    {
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant = $this->productWithVariant();
        return view('backend.adjustment.create', compact('lims_warehouse_list', 'lims_product_list_without_variant', 'lims_product_list_with_variant'));

    }

    public function productWithoutVariant()
    {
        return Product::ActiveStandard()->select('id', 'name', 'code')
                ->whereNull('is_variant')->get();
    }

    public function productWithVariant()
    {
        return Product::join('product_variants', 'products.id', 'product_variants.product_id')
            ->ActiveStandard()
            ->whereNotNull('is_variant')
            ->select('products.id', 'products.name', 'product_variants.item_code')
            ->orderBy('position')
            ->get();
    }

    public function store(Request $request)
    {
        $this->validateLines($request);
        return DB::transaction(function () use ($request) {
            $data = $request->except('document');
            $data['reference_no'] = 'adr-' . date('Ymd-His');
            if ($request->document) {
                $data['document'] = $request->document->getClientOriginalName();
                $request->document->move(public_path('documents/adjustment'), $data['document']);
            }
            if ($request->stock_count_id) {
                StockCount::findOrFail($request->stock_count_id)->update(['is_adjusted' => true]);
            }
            $adjustment = Adjustment::create($data);
            $this->persistLines($adjustment, $request);
            return redirect('qty_adjustment')->with('message', __('db.Data inserted successfully'));
        });
    }

    public function edit($id)
    {
        $lims_adjustment_data = Adjustment::find($id);
        $lims_product_adjustment_data = ProductAdjustment::where('adjustment_id', $id)->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        return view('backend.adjustment.edit', compact('lims_adjustment_data', 'lims_warehouse_list', 'lims_product_adjustment_data'));
    }

    public function update(Request $request, $id)
    {
        $this->validateLines($request);
        return DB::transaction(function () use ($request, $id) {
            $adjustment = Adjustment::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->reverseStock($adjustment);
            ProductAdjustment::where('adjustment_id', $id)->delete();
            $data = $request->except('document');
            if ($request->document) {
                $data['document'] = $request->document->getClientOriginalName();
                $request->document->move(public_path('documents/adjustment'), $data['document']);
            }
            $adjustment->update($data);
            $this->persistLines($adjustment, $request);
            return redirect('qty_adjustment')->with('message', __('db.Data updated successfully'));
        });
    }

    public function deleteBySelection(Request $request)
    {
        return DB::transaction(function () use ($request) {
            foreach (collect($request->adjustmentIdArray)->sort()->unique() as $id) {
                $this->deleteAdjustment($id);
            }
            return 'Data deleted successfully';
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $this->deleteAdjustment($id);
            return redirect('qty_adjustment')->with('not_permitted', __('db.Data deleted successfully'));
        });
    }

    private function validateLines(Request $request): void
    {
        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'product_id' => 'required|array|min:1', 'product_id.*' => 'required|integer|exists:products,id',
            'qty' => 'required|array|size:'.count((array) $request->product_id), 'qty.*' => 'required|numeric|gt:0',
            'action' => 'required|array|size:'.count((array) $request->product_id), 'action.*' => 'required|in:+,-',
            'unit_cost.*' => 'nullable|numeric|min:0',
        ]);
    }

    private function persistLines(Adjustment $adjustment, Request $request): void
    {
        $lines = [];
        foreach ($request->product_id as $key => $id) {
            $product = Product::findOrFail($id);
            $variantId = null;
            if ($product->is_variant) {
                $variant = ProductVariant::where('product_id', $id);
                if (!empty($request->product_variant_id[$key])) {
                    $variant->whereKey($request->product_variant_id[$key]);
                } else {
                    $variant->where('item_code', $request->product_code[$key] ?? null);
                }
                $variantId = $variant->firstOrFail()->variant_id;
            }
            $row = ProductAdjustment::create([
                'adjustment_id' => $adjustment->id, 'product_id' => $id, 'variant_id' => $variantId,
                'qty' => $request->qty[$key], 'action' => $request->action[$key],
                'unit_cost' => $request->unit_cost[$key] ?? $product->cost,
            ]);
            $lines[] = new StockLine(
                productId: (int) $id, qty: ($row->action === '-' ? -1 : 1) * (float) $row->qty,
                variantId: $variantId ? (int) $variantId : null, unitCost: (float) $row->unit_cost,
            );
        }
        app(LegacyInventoryPosting::class)->post($adjustment, 'adjust', $lines, (int) $adjustment->warehouse_id);
    }

    private function reverseStock(Adjustment $adjustment): void
    {
        $posting = app(LegacyInventoryPosting::class);
        $posting->reverse($adjustment, function () use ($posting, $adjustment) {
            $lines = ProductAdjustment::where('adjustment_id', $adjustment->id)->get()->map(fn ($line) => new StockLine(
                productId: (int) $line->product_id, qty: ($line->action === '-' ? 1 : -1) * (float) $line->qty,
                variantId: $line->variant_id ? (int) $line->variant_id : null, unitCost: (float) $line->unit_cost,
            ))->all();
            $posting->post($adjustment, 'adjust', $lines, (int) $adjustment->warehouse_id);
        });
    }

    private function deleteAdjustment($id): void
    {
        $adjustment = Adjustment::whereKey($id)->lockForUpdate()->firstOrFail();
        $this->reverseStock($adjustment);
        ProductAdjustment::where('adjustment_id', $id)->delete();
        $adjustment->delete();
        DB::afterCommit(fn () => $this->fileDelete(public_path('documents/adjustment/'), $adjustment->document));
    }
}
