<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Services\Inventory\LegacyInventoryPosting;
use App\Services\Inventory\StockLine;

use App\Models\GeneralSetting;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Product_Warehouse;
use App\Models\Unit;
use Modules\Manufacturing\Entities\Production;
use Modules\Manufacturing\Entities\ProductProduction;
use App\Models\Tax;
use App\Models\Account;
use App\Models\PosSetting;
use Auth;
use App\Traits\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    use StaffAccess, \App\Traits\FileHandleTrait;

    public function index(Request $request)
    {
        if(in_array('manufacturing',explode(',',config('addons')))) {
            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            if($request->input('status'))
                $status = $request->input('status');
            else
                $status = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }

            $lims_pos_setting_data = PosSetting::select('stripe_public_key')->latest()->first();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_account_list = Account::where('is_active', true)->get();
            return view('manufacturing::production.index', compact('status', 'lims_account_list', 'lims_warehouse_list', 'lims_pos_setting_data', 'warehouse_id', 'starting_date', 'ending_date'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function productionData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
            4 => 'product_id',
            5 => 'warehouse_id',
            6 => 'total_qty',
            7 => 'grand_total',
        );

        $warehouse_id = $request->input('warehouse_id');
        $status = $request->input('status');

        $q = Production::whereDate('created_at', '>=' ,$request->input('starting_date'))->whereDate('created_at', '<=' ,$request->input('ending_date'));
        //check staff access
        $this->staffAccessCheck($q);
        if($warehouse_id)
            $q = $q->where('warehouse_id', $warehouse_id);
        if($status)
            $q = $q->where('status', $status);

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'productions.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if(empty($request->input('search.value'))) {
            $q = Production::with('user', 'warehouse')
                ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
            //check staff access
            $this->staffAccessCheck($q);
            if($warehouse_id)
                $q = $q->where('warehouse_id', $warehouse_id);
            if($status)
                $q = $q->where('status', $status);

            $productions = $q->get();
        }
        else
            {
                $search = $request->input('search.value');
                $searchDate = date('Y-m-d', strtotime(str_replace('/', '-', $search)));

                $q = Production::leftJoin('products', 'productions.product_id', '=', 'products.id')
                    ->with('warehouse', 'user')
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->where(function ($query) use ($search, $searchDate) {
                        // Search by created_at if input looks like a date
                        if ($searchDate && strtotime($search)) {
                            $query->whereDate('productions.created_at', '=', $searchDate);
                        }

                        // Search by reference_no or product name (optional product)
                        $query->orWhere('productions.reference_no', 'LIKE', "%{$search}%")
                            ->orWhere('products.name', 'LIKE', "%{$search}%");
                    });

                // Role based access control
                if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                    $q->where('productions.user_id', Auth::id());
                } elseif (Auth::user()->role_id > 2 && config('staff_access') == 'warehouse') {
                    $q->where('productions.warehouse_id', Auth::user()->warehouse_id);
                }

                // Get data and count
                $productions = $q->select('productions.*')->groupBy('productions.id')->get();
                $totalFiltered = $q->groupBy('productions.id')->count();
            }

        $data = array();
        if(!empty($productions))
        {
            foreach ($productions as $key=>$production)
            {
                //$nestedData['id'] = $production->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($production->created_at->toDateString()));
                $nestedData['reference_no'] = $production->reference_no;
                if($production->status == 1){
                    $nestedData['status'] = '<div class="badge badge-success">'.__('db.Completed').'</div>';
                    $status = __('db.Recieved');
                }

                $nestedData['total_cost'] = number_format($production->total_cost, config('decimal'));
                $nestedData['product'] = @$production->product->name;
                $nestedData['quantity'] = $production->total_qty;
                $nestedData['warehouse'] = @$production->warehouse->name;
                $nestedData['total_tax'] = number_format($production->total_tax, config('decimal'));
                $nestedData['shipping_cost'] = number_format($production->shipping_cost, config('decimal'));
                $nestedData['production_cost'] = number_format($production->production_cost, config('decimal'));
                $nestedData['grand_total'] = number_format($production->grand_total, config('decimal'));

                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.__("db.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> '.__('db.View').'</button>
                                </li>';

                $nestedData['options'] .= \Form::open(["route" => ["productions.destroy", $production->id], "method" => "DELETE"] ).'
                        <li>
                            <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.__("db.delete").'</button>
                        </li>'.\Form::close().'
                    </ul>
                </div>';

                // data for production details by one click
                $user = $production->user;
                $nestedData['production'] = array( '[ "'.date(config('date_format'), strtotime($production->created_at->toDateString())).'"', ' "'.$production->reference_no.'"', ' "'.$status.'"',  ' "'.$production->id.'"', ' "'.$production->warehouse->name.'"', ' "'.$production->total_tax.'"', ' "'.$production->total_cost.'"', ' "'.$production->shipping_cost.'"', ' "'.$production->shipping_cost.'"',' "'.$production->grand_total.'"', ' "'.preg_replace('/\s+/S', " ", $production->note).'"', ' "'.$user->name.'"', ' "'.$user->email.'"', ' "'.$production->document.'"]'
                );
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );
        echo json_encode($json_data);
    }


    public function create()
    {
        if(Auth::user()->role_id > 2) {
            $lims_warehouse_list = Warehouse::where([
                ['is_active', true],
                ['id', Auth::user()->warehouse_id]
            ])->get();
        }
        else {
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        }
        $lims_product_list = Product::where([
            ['is_recipe', 1],
            ['is_active', true]
        ])->get();
        $lims_tax_list = Tax::where('is_active', true)->get();
        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant = $this->productWithVariant();
        // return view('manufacturing::production-copy.create', compact('lims_product_list_with_variant','lims_product_list_without_variant', 'lims_warehouse_list', 'lims_product_list', 'lims_tax_list'));
        return view('manufacturing::production.create', compact('lims_product_list_with_variant','lims_product_list_without_variant', 'lims_warehouse_list', 'lims_product_list', 'lims_tax_list'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'product_id' => 'required|integer|exists:products,id', 'total_qty' => 'required|numeric|gt:0',
            'product_list' => 'required|array|min:1', 'product_list.*' => 'required|integer|exists:products,id',
            'product_qty' => 'required|array|size:'.count((array) $request->product_list), 'product_qty.*' => 'required|numeric|gt:0',
            'production_unit_ids' => 'required|array|size:'.count((array) $request->product_list),
            'production_unit_ids.*' => 'required|integer|exists:units,id',
        ]);
        try{
        DB::beginTransaction();
        $data = $request->except('document');
        $data['user_id'] = Auth::id();
        $data['reference_no'] = 'production-' . date("Ymd") . '-'. date("his");
        $product = Product::query()->findOrFail($request->product_id);
        $document = $request->document;
        if ($document) {
            $v = Validator::make(
                [
                    'extension' => strtolower($request->document->getClientOriginalExtension()),
                ],
                [
                    'extension' => 'in:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
                ]
            );
            if ($v->fails()) {
                DB::rollBack();
                return redirect()->back()->withErrors($v->errors());
            }

            $data['document'] = app(\App\Services\Documents\PrivateFileStorage::class)->store($document, 'production');
        }
        if(isset($data['created_at']))
            $data['created_at'] = date("Y-m-d H:i:s", strtotime($data['created_at']));
        else
            $data['created_at'] = date("Y-m-d H:i:s");
        $data['item'] = count($request->product_qty);
        $data['total_qty'] = $request->total_qty ?? 1;
        $data['product_list'] = implode(",", $data['product_list']);
        $data['qty_list'] = implode(",", $data['product_qty']);
        $data['price_list'] = implode(",", $data['unit_price']);
        $data['wastage_percent'] = implode(",", $data['wastage_percent']);
        $data['production_units_ids'] = implode(",", $data['production_unit_ids']);
        $data['total_tax'] = 0;
        $lims_production_data = Production::create($data);

        $posting = app(LegacyInventoryPosting::class);
        $ingredients = [];
        foreach ($request->product_list as $index => $productId) {
            $ingredients[] = StockLine::fromArray([
                'product_id' => $productId, 'qty' => $request->product_qty[$index],
                'uom_id' => $request->production_unit_ids[$index],
                'variant_id' => $request->variant_id[$index] ?? null,
                'product_batch_id' => $request->product_batch_id[$index] ?? null,
                'imei_number' => $request->imei_number[$index] ?? null,
            ]);
        }
        // Consume ingredients first; output cannot finance its own inputs under the negative-stock policy.
        $posting->post($lims_production_data, 'issue', $ingredients, (int) $request->warehouse_id);
        $posting->post($lims_production_data, 'receive', [StockLine::fromArray([
            'product_id' => $product->id, 'qty' => $data['total_qty'], 'unit_cost' => $product->cost,
            'variant_id' => $request->output_variant_id, 'product_batch_id' => $request->output_batch_id,
            'imei_number' => $request->output_serials,
        ])], (int) $request->warehouse_id);
        DB::commit();
        return redirect('manufacturing/productions')->with('message', __('db.Production created successfully'));
        }catch(\Throwable $e){
            DB::rollBack();
            report($e);
             return redirect('manufacturing/productions')->with('not_permitted', __('db.Something error please try again'));
        }

    }




    public function productProductionData($id)
    {
        try {
            $lims_product_production_data = Production::where('id', $id)->first();
            $proudction_units_ids = explode(',', $lims_product_production_data->production_units_ids);
            $product_list = explode(',', $lims_product_production_data->product_list);
            $wastage_percent = explode(',', $lims_product_production_data->wastage_percent);
            $qty_list = explode(',', $lims_product_production_data->qty_list);
            $variant_list = explode(',', $lims_product_production_data->variant_list);
            $price_list = explode(',', $lims_product_production_data->price_list);

            // production info
            $production_info = [];
            $production_info['shipping_cost']      = $lims_product_production_data->shipping_cost;
            $production_info['production_cost']    = $lims_product_production_data->production_cost ?? 0;
            $production_info['grand_total']        = $lims_product_production_data->grand_total;
            $production_info['total_qty']          = $lims_product_production_data->total_qty;
            $product_production = [];
            foreach ($product_list as $key => $id) {
                $product = Product::find($id);
                $variant_id = $variant_list[$key] ?? null;

                // Handle variant code
                if ($variant_id) {
                    $variant = ProductVariant::FindExactProduct($id, $variant_id)->first();
                    if ($variant) {
                        $product->code = $variant->item_code;
                    }
                }

                $unit = Unit::query()
                    ->where('id', $proudction_units_ids[$key])
                    ->first();
                    if($unit->operator == '*'){
                        $subtotal = $price_list[$key] * ($unit->operation_value * $qty_list[$key] ?? 1);
                    }elseif($unit->operator == '/'){
                        $subtotal = $price_list[$key] / $unit->operation_value;
                    }else{
                        $subtotal = $price_list[$key] * 1;
                    }

                $product_production[] = [
                    'id'                => $product->id,
                    'name'              => $product->name,
                    'code'              => $product->code,
                    'wastage_percent'   => $wastage_percent[$key] ?? 0,
                    'qty'               => $qty_list[$key] ?? 1,
                    'unit_cost'         => $product->cost,
                    'unit_price'        => $price_list[$key] ?? 0,
                    'subtotal'          => $subtotal,
                    'unit_name'         => $unit->unit_name,
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $product_production,
                'production_info' => $production_info
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something is wrong!',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

     public function backupproductProductionData($id)
    {
        try {
            $lims_product_production_data = ProductProduction::where('production_id', $id)->get();
            $product_production = [];
            foreach ($lims_product_production_data as $key => $product_production_data) {
                $product = Product::find($product_production_data->product_id);
                $unit = Unit::find($product_production_data->purchase_unit_id);
                $product_production[0][$key] = $product->name . ' [' . $product->code.']';
                $product_production[1][$key] = $product_production_data->qty;
                $product_production[2][$key] = $product_production_data->recieved;
                $product_production[3][$key] = $unit->unit_code;
                $product_production[4][$key] = $product_production_data->tax;
                $product_production[5][$key] = $product_production_data->tax_rate;
                $product_production[6][$key] = $product_production_data->total;
            }
            return $product_production;
        }
        catch (Exception $e) {
            return 'Something is wrong!';
        }

    }

    public function show($id)
    {
        return view('manufacturing::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('manufacturing::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $production = Production::whereKey($id)->lockForUpdate()->firstOrFail();
            $posting = app(LegacyInventoryPosting::class);
            $posting->reverse($production, function () use ($posting, $production) {
                if (!$production->product_id || !$production->product_list || !$production->production_units_ids) {
                    throw new \App\Services\Inventory\StockPolicyException('Historical production requires a reviewed ingredient snapshot before reversal.');
                }
                $posting->post($production, 'issue', [new StockLine((int) $production->product_id, (float) $production->total_qty)], (int) $production->warehouse_id);
                $quantities = explode(',', $production->qty_list);
                $units = explode(',', $production->production_units_ids);
                $costs = explode(',', $production->price_list);
                $lines = [];
                foreach (explode(',', $production->product_list) as $key => $productId) {
                    $lines[] = new StockLine((int) $productId, (float) $quantities[$key],
                        unitCost: isset($costs[$key]) ? (float) $costs[$key] : null, uomId: (int) $units[$key]);
                }
                $posting->post($production, 'receive', $lines, (int) $production->warehouse_id);
            });
            ProductProduction::where('production_id', $id)->delete();
            $production->delete();
            DB::afterCommit(fn () => app(\App\Services\Documents\PrivateFileStorage::class)->delete('production', $production->document));
            return redirect('manufacturing/productions')->with('not_permitted', __('db.Production deleted successfully'));
        });
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
            ->select('products.id', 'products.name', 'product_variants.item_code', 'product_variants.qty')
            ->orderBy('position')->get();
    }

    public function getIngredients(Request $request){
         $lims_product_data = Product::where('id', $request->product_id)->first();
        if($lims_product_data->variant_option) {
            $lims_product_data->variant_option = json_decode($lims_product_data->variant_option);
            $lims_product_data->variant_value = json_decode($lims_product_data->variant_value);
        }
        $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();
        if($request->recipe == true){
            $product = view('manufacturing::recipe.ingredients',compact('lims_product_data','lims_product_variant_data'))->render();
        }else{
            $warehouse_id = $request->warehouse_id;
            $product = view('manufacturing::production.ingredients',compact('lims_product_data','lims_product_variant_data','warehouse_id'))->render();
        }

        return response()->json(['ingredients'=> $product]);
    }
}
