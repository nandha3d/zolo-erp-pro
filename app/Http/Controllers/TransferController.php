<?php

namespace App\Http\Controllers;

use App\Services\Inventory\LegacyInventoryPosting;
use App\Services\Inventory\StockLine;

use DB;
use Auth;
use Carbon\Carbon;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\Warehouse;
use App\Models\MailSetting;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use App\Mail\TransferDetails;
use App\Models\ProductVariant;
use App\Models\ProductPurchase;
use App\Models\ProductTransfer;
use App\Models\Product_Warehouse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

use App\Helpers\DateHelper;

class TransferController extends Controller
{
    use \App\Traits\MailInfo;

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('transfers-index')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            if($request->input('from_warehouse_id'))
                $from_warehouse_id = $request->input('from_warehouse_id');
            else
                $from_warehouse_id = 0;

            if($request->input('to_warehouse_id'))
                $to_warehouse_id = $request->input('to_warehouse_id');
            else
                $to_warehouse_id = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }

            $lims_warehouse_list = Warehouse::select('name', 'id')->where('is_active', true)->get();
            return view('backend.transfer.index',compact('starting_date', 'ending_date', 'from_warehouse_id', 'to_warehouse_id', 'all_permission', 'lims_warehouse_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    private function findImeis(string $product_id, string $variant_id = '0')
    {
        $imei_numbers = [];
        $purchases = [];
        if ($variant_id === '0') {
            $purchases = ProductPurchase::where('product_id', $product_id)
                ->whereNotNull('imei_number')
                ->select('imei_number')->get();
        } else {
            $purchases = ProductPurchase::where('product_id', $product_id)
                ->where('variant_id', '=', $variant_id)
                ->whereNotNull('imei_number')
                ->select('imei_number')->get();
        }

        foreach ($purchases as $purchase) {
            $imei_numbers[] = explode(',', $purchase->imei_number);
        }
        $imeis = [];
        foreach ($imei_numbers as $imei_number) {
            foreach ($imei_number as $imei) {
                $imeis[] = $imei;
            }
        }

        $convert_to_string = '';
        foreach ($imeis as $key => $value) {
            $convert_to_string .= $value;
            if (count($imeis)-1 > $key) {
                $convert_to_string .= ',';
            }
        }

        if (!count($imeis)) {
            return 'N/A';
        }
        return $convert_to_string;
    }

    public function transferData(Request $request)
    {
        $columns = array(
            1 => 'created_at',
            2 => 'reference_no',
        );

        $from_warehouse_id = $request->input('from_warehouse_id');
        $to_warehouse_id = $request->input('to_warehouse_id');
        $q = Transfer::whereDate('created_at', '>=' ,$request->input('starting_date'))
                     ->whereDate('created_at', '<=' ,$request->input('ending_date'));
        if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
            $q = $q->where('user_id', Auth::id());
        elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse')
            $q = $q->where('from_warehouse_id', Auth::user()->warehouse_id)->orWhere('to_warehouse_id', Auth::user()->warehouse_id);
        if($from_warehouse_id)
            $q = $q->where('from_warehouse_id', $from_warehouse_id);
        if($to_warehouse_id)
            $q = $q->where('to_warehouse_id', $to_warehouse_id);

        $totalData = $q->count();
        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'transfers.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        if(empty($request->input('search.value'))) {
            $q = Transfer::with('fromWarehouse', 'toWarehouse', 'user')
                ->whereDate('created_at', '>=' ,$request->input('starting_date'))
                ->whereDate('created_at', '<=' ,$request->input('ending_date'))
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir);
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $q = $q->where('user_id', Auth::id());
            elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse')
                $q = $q->where('from_warehouse_id', Auth::user()->warehouse_id)->orWhere('to_warehouse_id', Auth::user()->warehouse_id);
            if($from_warehouse_id)
                $q = $q->where('from_warehouse_id', $from_warehouse_id);
            if($to_warehouse_id)
                $q = $q->where('to_warehouse_id', $to_warehouse_id);
            $transfers = $q->get();
        }

        else
        {
            $search = $request->input('search.value');
            $q = Transfer::whereDate('transfers.created_at', '=' , date('Y-m-d', strtotime(str_replace('/', '-', $search))))
                ->offset($start)
                ->limit($limit)
                ->orderBy($order,$dir);
            if(Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $transfers =  $q->select('transfers.*')
                                ->with('fromWarehouse', 'toWarehouse', 'user')
                                ->where('transfers.user_id', Auth::id())
                                ->orwhere([
                                    ['reference_no', 'LIKE', "%{$search}%"],
                                    ['user_id', Auth::id()]
                                ])
                                ->get();
                $totalFiltered = $q->count();
            }
            elseif(Auth::user()->role_id > 2 && config('staff_access') == 'warehouse') {
                $transfers =  $q->select('transfers.*')
                                ->with('fromWarehouse', 'toWarehouse', 'user')
                                ->where('transfers.user_id', Auth::id())
                                ->orwhere([
                                    ['reference_no', 'LIKE', "%{$search}%"],
                                    ['from_warehouse_id', Auth::user()->warehouse_id]
                                ])
                                ->orwhere([
                                    ['reference_no', 'LIKE', "%{$search}%"],
                                    ['to_warehouse_id', Auth::user()->warehouse_id]
                                ])
                                ->get();
                $totalFiltered = $q->count();
            }
            else {
                $transfers =  $q->select('transfers.*')
                                ->with('fromWarehouse', 'toWarehouse', 'user')
                                ->orwhere('reference_no', 'LIKE', "%{$search}%")
                                ->get();

                $totalFiltered = $q->orwhere('transfers.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = array();
        if(!empty($transfers))
        {
            foreach ($transfers as $key=>$transfer)
            {
                $nestedData['id'] = $transfer->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($transfer->created_at->toDateString()));
                $nestedData['reference_no'] = $transfer->reference_no;
                $nestedData['from_warehouse'] = $transfer->fromWarehouse->name;
                $nestedData['to_warehouse'] = $transfer->toWarehouse->name;
                $nestedData['shipping_cost'] = number_format($transfer->shipping_cost, config('decimal'));
                $nestedData['grand_total'] = number_format($transfer->grand_total, config('decimal'));

                if($transfer->is_sent == 1) {
                    $nestedData['is_sent'] = '<div class="badge badge-success">'.__('db.Yes').'</div>';
                    $status = __('db.Yes');
                }else{
                    $nestedData['is_sent'] = '<div class="badge badge-danger">'.__('db.No').'</div>';
                    $status = __('db.No');
                }

                if($transfer->status == 1) {
                    $nestedData['status'] = '<div class="badge badge-success">'.__('db.Completed').'</div>';
                    $status = __('db.Completed');
                }
                elseif($transfer->status == 2) {
                    $nestedData['status'] = '<div class="badge badge-danger">'.__('db.Pending').'</div>';
                    $status = __('db.Pending');
                }
                elseif($transfer->status == 3) {
                    $nestedData['status'] = '<div class="badge badge-warning">'.__('db.Sent').'</div>';
                    $status = __('db.Sent');
                }

                $nestedData['options'] = '<div class="btn-group">
                    <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        ' . __("db.action") . '
                        <span class="caret"></span>
                        <span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                        <li>
                            <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> ' . __('db.View') . '</button>
                        </li>';

                if(in_array("transfers-edit", $request['all_permission'])) {
                    $nestedData['options'] .= '<li>
                        <a href="'.route('transfers.edit', $transfer->id).'" class="btn btn-link"><i class="dripicons-document-edit"></i> ' . __('db.edit') . '</a>
                    </li>';
                }

                if (auth()->user()->role_id < 3 && $transfer->status == 2) {
                    $nestedData['options'] .= '<li>
                        ' . \Form::open([
                            'route' => ['transfers.changeStatus', $transfer->id],
                            'method' => 'put',
                            'files' => true,
                            'id' => 'transfer-form'
                        ]) . '
                            <select name="status" type="hidden" class="form-control selectpicker d-none">
                                <option value="1" selected>' . __('db.Completed') . '</option>
                            </select>
                            <button type="submit" class="btn btn-link" onclick="return confirmApprove()">
                                <i class="dripicons-checkmark"></i> ' . __("Approved") . '
                            </button>
                        ' . \Form::close() . '
                    </li>';
                }

                if(in_array("transfers-delete", $request['all_permission'])) {
                    $nestedData['options'] .= '<li>
                        ' . \Form::open([
                            "route" => ["transfers.destroy", $transfer->id],
                            "method" => "DELETE"
                        ]) . '
                            <button type="submit" class="btn btn-link" onclick="return confirmDelete()">
                                <i class="dripicons-trash"></i> ' . __("db.delete") . '
                            </button>
                        ' . \Form::close() . '
                    </li>';
                }

                $nestedData['options'] .= '</ul></div>';
                // data for transfer details by one click

                $nestedData['transfer'] = array( '[ "'.date(config('date_format'), strtotime($transfer->created_at->toDateString())).'"', ' "'.$transfer->reference_no.'"', ' "'.$status.'"', ' "'.$transfer->id.'"', ' "'.$transfer->fromWarehouse->name.'"', ' "'.$transfer->fromWarehouse->phone.'"', ' "'.preg_replace('/\s+/S', " ", $transfer->fromWarehouse->address).'"', ' "'.$transfer->toWarehouse->name.'"', ' "'.$transfer->toWarehouse->phone.'"', ' "'.preg_replace('/\s+/S', " ", $transfer->toWarehouse->address).'"', ' "'.$transfer->total_tax.'"', ' "'.$transfer->total_cost.'"', ' "'.$transfer->shipping_cost.'"', ' "'.$transfer->grand_total.'"', ' "'.preg_replace('/[\n\r]/', "<br>", $transfer->note).'"', ' "'.$transfer->user->name.'"', ' "'.$transfer->user->email.'"]'
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
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('transfers-add')){
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('backend.transfer.create', compact('lims_warehouse_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $this->validateTransfer($request);
            $data = $request->except('document');

            $data['user_id'] = Auth::id();
            $data['reference_no'] = 'tr-' . date("Ymd") . '-'. date("his");

            if (isset($data['created_at'])) {
                $data['created_at'] = normalize_to_sql_datetime($data['created_at']);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
            }

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
                if ($v->fails())
                    return redirect()->back()->withErrors($v->errors());

                $documentName = $document->getClientOriginalName();
                $document->move(public_path('documents/transfer'), $documentName);
                $data['document'] = $documentName;
            }
            $lims_transfer_data = Transfer::create($data);

            $product_id = $data['product_id'];
            $imei_number = $data['imei_number'] ?? NULL;
            $product_batch_id = $data['product_batch_id'] ?? NULL;
            $product_code = $data['product_code'];
            $qty = $data['qty'];
            $purchase_unit = $data['purchase_unit'];
            $net_unit_cost = $data['net_unit_cost'];
            $tax_rate = $data['tax_rate'];
            $tax = $data['tax'];
            $total = $data['subtotal'];
            $product_transfer = [];

            foreach ($product_id as $i => $id) {
                $lims_purchase_unit_data  = Unit::where('unit_name', $purchase_unit[$i])->first();
                $product_transfer['variant_id'] = null;
                $product_transfer['product_batch_id'] = null;

                $product = Product::findOrFail($id);
                if ($product->is_variant) {
                    $product_transfer['variant_id'] = ProductVariant::FindExactProductWithCode($id, $product_code[$i])->firstOrFail()->variant_id;
                }
                $product_transfer['product_batch_id'] = $product_batch_id[$i] ?? null;

                $product_transfer['transfer_id'] = $lims_transfer_data->id ;
                $product_transfer['product_id'] = $id;
                $product_transfer['imei_number'] = $imei_number[$i] ?? null;
                $product_transfer['qty'] = $qty[$i];
                $product_transfer['purchase_unit_id'] = $lims_purchase_unit_data->id;
                $product_transfer['net_unit_cost'] = $net_unit_cost[$i];
                $product_transfer['tax_rate'] = $tax_rate[$i];
                $product_transfer['tax'] = $tax[$i];
                $product_transfer['total'] = $total[$i];
                ProductTransfer::create($product_transfer);
            }

            $this->postStock($lims_transfer_data);
            $message = 'Transfer created successfully';

            // Mail Send Start
            $mail_setting = MailSetting::latest()->first();
            $fromWareHouse = Warehouse::find($data['from_warehouse_id']);
            $toWareHouse = Warehouse::find($data['to_warehouse_id']);
            $mailData = [];

            //Data

            $mailData['date'] = date("Y-m-d", strtotime(str_replace("/", "-", $lims_transfer_data->created_at)));;
            $mailData['reference_no'] = $lims_transfer_data->reference_no;
            $mailData['status'] = $lims_transfer_data->status;
            $mailData['total_cost'] = $lims_transfer_data->total_cost;
            $mailData['shipping_cost'] = $lims_transfer_data->shipping_cost;
            $mailData['grand_total'] = $lims_transfer_data->grand_total;

            //From: Warehouse
            $mailData['from_warehouse'] = $fromWareHouse->name;
            $mailData['from_phone'] = $fromWareHouse->phone;
            $mailData['from_email'] = $fromWareHouse->email;
            $mailData['from_address'] = $fromWareHouse->address;

            //To: Warehouse
            $mailData['to_warehouse'] = $toWareHouse->name;
            $mailData['to_phone'] = $toWareHouse->phone;
            $mailData['to_email'] = $toWareHouse->email;
            $mailData['to_address'] = $toWareHouse->address;
            if($mail_setting && ($mailData['from_email'] || $mailData['to_email'])) {
                $this->setMailInfo($mail_setting);
                $productTransferData = $this->getProductTransferData($lims_transfer_data->id);
                $mailData['products'] = $productTransferData['products'];
                $mailData['qty'] = $productTransferData['qty'];
                $mailData['unit'] = $productTransferData['unit'];
                $mailData['tax'] = $productTransferData['tax'];
                $mailData['tax_rate'] = $productTransferData['tax_rate'];
                $mailData['total'] = $productTransferData['total'];
                $mailData['batch_no'] = $productTransferData['batch_no'];

                try{
                    if($mailData['to_email'])
                        Mail::to($mailData['to_email'])
                        ->send(new TransferDetails($mailData));
                    else
                        $message .= '.To warehouse email not found.';

                    if($mailData['from_email'])
                        Mail::to($mailData['from_email'])
                        ->send(new TransferDetails($mailData));
                    else
                        $message .= '.From warehouse email not found.';

                    $lims_transfer_data->update(['is_sent'=> true]);
                }
                catch(\Exception $e) {
                    // return $e;
                    $lims_transfer_data->update(['is_sent'=> false]);
                    $message .= '. Please Setup Your Mail Credentials to send Email.';
                }
            }
            return redirect('transfers')->with('message', $message);

        });
    }

    public function getProduct($id)
    {
        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');
        if(config('without_stock') == 'no') {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
                ['product_warehouse.qty', '>', 0]
            ]);
        }
        else {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id]
            ]);
        }

        $lims_product_warehouse_data = $query->whereNull('products.is_imei')
                                        ->whereNull('product_warehouse.variant_id')
                                        ->whereNull('product_warehouse.product_batch_id')
                                        ->select('product_warehouse.*', 'products.name', 'products.code', 'products.type', 'products.product_list', 'products.qty_list', 'products.is_embeded')
                                        ->get();
        //return $lims_product_warehouse_data;
        config()->set('database.connections.mysql.strict', false);
        \DB::reconnect(); //important as the existing connection if any would be in strict mode

        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');

        if(config('without_stock') == 'no') {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
                ['product_warehouse.qty', '>', 0]
            ]);
        }
        else {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id]
            ]);
        }

        $lims_product_with_batch_warehouse_data = $query->whereNull('product_warehouse.variant_id')
        ->whereNotNull('product_warehouse.product_batch_id')
        ->select('product_warehouse.*', 'products.name', 'products.code', 'products.type', 'products.product_list', 'products.qty_list', 'products.is_embeded')
        ->groupBy('product_warehouse.product_id')
        ->get();

        //now changing back the strict ON
        config()->set('database.connections.mysql.strict', true);
        \DB::reconnect();

        $query = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id');
        if(config('without_stock') == 'no') {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
                ['product_warehouse.qty', '>', 0]
            ]);
        }
        else {
            $query = $query->where([
                ['products.is_active', true],
                ['product_warehouse.warehouse_id', $id],
            ]);
        }
        $lims_product_with_variant_warehouse_data = $query->whereNotNull('product_warehouse.variant_id')
        ->select('product_warehouse.*', 'products.name', 'products.code', 'products.type', 'products.product_list', 'products.qty_list', 'products.is_embeded')
        ->get();

        $lims_product_with_imei_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['products.is_imei', true],
            ['product_warehouse.warehouse_id', $id],
            ['product_warehouse.qty', '>', 0]
        ])
        ->whereNull('product_warehouse.variant_id')
        ->whereNotNull('product_warehouse.imei_number')
        ->select('product_warehouse.*', 'products.is_embeded')
        ->groupBy('product_warehouse.product_id')
        ->get();

        $product_code = [];
        $product_name = [];
        $product_qty = [];
        $product_type = [];
        $product_id = [];
        $product_list = [];
        $qty_list = [];
        $product_price = [];
        $batch_no = [];
        $product_batch_id = [];
        $expired_date = [];
        $is_embeded = [];
        $imei_number = [];

        //product without variant
        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $product_code[] =  $product_warehouse->code;
            $product_name[] = htmlspecialchars($product_warehouse->name);
            $product_type[] = $product_warehouse->type;
            $product_id[] = $product_warehouse->product_id;
            $product_list[] = $product_warehouse->product_list;
            $qty_list[] = $product_warehouse->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $expired_date[] = null;
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;
            $imei_number[] = null;

        }
        //product with batches
        foreach ($lims_product_with_batch_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $product_code[] =  $product_warehouse->code;
            $product_name[] = htmlspecialchars($product_warehouse->name);
            $product_type[] = $product_warehouse->type;
            $product_id[] = $product_warehouse->product_id;
            $product_list[] = $product_warehouse->product_list;
            $qty_list[] = $product_warehouse->qty_list;
            $product_batch_data = ProductBatch::select('id', 'batch_no', 'expired_date')->find($product_warehouse->product_batch_id);
            $batch_no[] = $product_batch_data->batch_no;
            $product_batch_id[] = $product_batch_data->id;
            $expired_date[] = date(config('date_format'), strtotime($product_batch_data->expired_date));
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;

            $imei_number[] = null;
        }
        //product with variant
        foreach ($lims_product_with_variant_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
            if($lims_product_variant_data) {
                $product_code[] =  $lims_product_variant_data->item_code;
                $product_name[] = htmlspecialchars($product_warehouse->name);
                $product_type[] = $product_warehouse->type;
                $product_id[] = $product_warehouse->product_id;
                $product_list[] = $product_warehouse->product_list;
                $qty_list[] = $product_warehouse->qty_list;
                $batch_no[] = null;
                $product_batch_id[] = null;
                $expired_date[] = null;
                if($product_warehouse->is_embeded)
                    $is_embeded[] = $product_warehouse->is_embeded;
                else
                    $is_embeded[] = 0;

                $imei_number[] = null;

            }
        }

        //product with imei
        foreach ($lims_product_with_imei_warehouse_data as $product_warehouse)
        {
            $imei_numbers = explode(",", $product_warehouse->imei_number);
            foreach ($imei_numbers as $key => $number) {
                $product_qty[] = $product_warehouse->qty;
                $product_price[] = $product_warehouse->price;
                $lims_product_data = Product::find($product_warehouse->product_id);
                $product_code[] =  $lims_product_data->code;
                $product_name[] = htmlspecialchars($lims_product_data->name);
                $product_type[] = $lims_product_data->type;
                $product_id[] = $lims_product_data->id;
                $product_list[] = $lims_product_data->product_list;
                $qty_list[] = $lims_product_data->qty_list;
                $batch_no[] = null;
                $product_batch_id[] = null;
                $expired_date[] = null;
                $is_embeded[] = 0;
                $imei_number[] = $number;
            }
        }

        //retrieve product with type of digital, combo and service
        $lims_product_data = Product::whereNotIn('type', ['standard'])->where('is_active', true)->get();
        foreach ($lims_product_data as $product)
        {
            $product_qty[] = $product->qty;
            $product_code[] =  $product->code;
            $product_name[] = $product->name;
            $product_type[] = $product->type;
            $product_id[] = $product->id;
            $product_list[] = $product->product_list;
            $qty_list[] = $product->qty_list;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $expired_date[] = null;
            $is_embeded[] = 0;
            $imei_number[] = null;

        }
        $product_data = [$product_code, $product_name, $product_qty, $product_type, $product_id, $product_list, $qty_list, $product_price, $batch_no, $product_batch_id, $expired_date, $is_embeded, $imei_number];
        return $product_data;
    }

    public function limsProductSearch(Request $request)
    {
        $todayDate = date('Y-m-d');
        $product_data = explode("|", $request['data']);
        // $product_code = explode("(", $request['data']);
        $product_info = explode("|", $request['data']);

        $customer_id = $product_info[1];
        // if(strpos($request['data'], '|')) {
        //     $product_info = explode("|", $request['data']);
        //     $embeded_code = $product_code[0];
        //     $product_code[0] = substr($embeded_code, 0, 7);
        //     $qty = substr($embeded_code, 7, 5) / 1000;
        // }
        // else {
        //     $product_code[0] = rtrim($product_code[0], " ");
        //     $qty = $product_info[2];
        // }
        if($product_data[3][0]) {
            $product_info = explode("|", $request['data']);
            $embeded_code = $product_data[0];
            $product_data[0] = substr($embeded_code, 0, 7);
            $qty = substr($embeded_code, 7, 5) / 1000;
        }
        else {
            $qty = $product_info[3];
        }
        $product_variant_id = null;
        $all_discount = DB::table('discount_plan_customers')
                        ->join('discount_plans', 'discount_plans.id', '=', 'discount_plan_customers.discount_plan_id')
                        ->join('discount_plan_discounts', 'discount_plans.id', '=', 'discount_plan_discounts.discount_plan_id')
                        ->join('discounts', 'discounts.id', '=', 'discount_plan_discounts.discount_id')
                        ->where([
                            ['discount_plans.is_active', true],
                            ['discounts.is_active', true],
                            ['discount_plan_customers.customer_id', $customer_id]
                        ])
                        ->select('discounts.*')
                        ->get();
        // return $product_data[0];
        $lims_product_data = Product::where([
            ['code', $product_data[0]],
            ['is_active', true]
        ])->first();

        if(!$lims_product_data) {
            $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.id as product_variant_id', 'product_variants.item_code', 'product_variants.additional_cost')
                ->where([
                    ['product_variants.item_code', $product_data[0]],
                    ['products.is_active', true]
                ])->first();

            // return $lims_product_data;
            $product_variant_id = $lims_product_data->product_variant_id;
            $lims_product_data->code = $lims_product_data->item_code;
            $lims_product_data->cost += $lims_product_data->additional_cost;
        }

        $product[] = $lims_product_data->name;
        $product[] = $lims_product_data->code;
        $product[] = $lims_product_data->cost;

        // if($lims_product_data->is_variant){
        //     $product[] = $lims_product_data->item_code;
        //     $lims_product_data->price += $lims_product_data->additional_price;
        // }
        // else
        //     $product[] = $lims_product_data->code;

        // $no_discount = 1;
        // foreach ($all_discount as $key => $discount) {
        //     $product_list = explode(",", $discount->product_list);
        //     $days = explode(",", $discount->days);

        //     if( ( $discount->applicable_for == 'All' || in_array($lims_product_data->id, $product_list) ) && ( $todayDate >= $discount->valid_from && $todayDate <= $discount->valid_till && in_array(date('D'), $days) && $qty >= $discount->minimum_qty && $qty <= $discount->maximum_qty ) ) {
        //         if($discount->type == 'flat') {
        //             $product[] = $lims_product_data->price - $discount->value;
        //         }
        //         elseif($discount->type == 'percentage') {
        //             $product[] = $lims_product_data->price - ($lims_product_data->price * ($discount->value/100));
        //         }
        //         $no_discount = 0;
        //         break;
        //     }
        //     else {
        //         continue;
        //     }
        // }

        // if($lims_product_data->promotion && $todayDate <= $lims_product_data->last_date && $no_discount) {
        //     $product[] = $lims_product_data->promotion_price;
        // }
        // elseif($no_discount)
        //     $product[] = $lims_product_data->price;

        if($lims_product_data->tax_id) {
            $lims_tax_data = Tax::find($lims_product_data->tax_id);
            $product[] = $lims_tax_data->rate;
            $product[] = $lims_tax_data->name;
        }
        else{
            $product[] = 0;
            $product[] = 'No Tax';
        }
        $product[] = $lims_product_data->tax_method;
        if($lims_product_data->type == 'standard'){
            $units = Unit::where("base_unit", $lims_product_data->unit_id)
                    ->orWhere('id', $lims_product_data->unit_id)
                    ->get();
            $unit_name = array();
            $unit_operator = array();
            $unit_operation_value = array();
            foreach ($units as $unit) {
                if($lims_product_data->sale_unit_id == $unit->id) {
                    array_unshift($unit_name, $unit->unit_name);
                    array_unshift($unit_operator, $unit->operator);
                    array_unshift($unit_operation_value, $unit->operation_value);
                }
                else {
                    $unit_name[]  = $unit->unit_name;
                    $unit_operator[] = $unit->operator;
                    $unit_operation_value[] = $unit->operation_value;
                }
            }
            $product[] = implode(",",$unit_name) . ',';
            $product[] = implode(",",$unit_operator) . ',';
            $product[] = implode(",",$unit_operation_value) . ',';
        }
        else{
            $product[] = 'n/a'. ',';
            $product[] = 'n/a'. ',';
            $product[] = 'n/a'. ',';
        }

        $product[] = $lims_product_data->id;
        $product[] = $product_variant_id;
        $product[] = $lims_product_data->promotion;
        $product[] = $lims_product_data->is_batch; //12
        $product[] = $lims_product_data->is_imei;
        $product[] = $lims_product_data->is_variant;
        $product[] = $product_data[4];
        $product[] = $lims_product_data->wholesale_price;
        $product[] = $lims_product_data->cost;
        $product[] = $product_data[2];
        return $product;

    }

    public function productTransferData($id)
    {
        $lims_product_transfer_data = ProductTransfer::where('transfer_id', $id)->get();

        foreach ($lims_product_transfer_data as $key => $product_transfer_data) {
            $product = Product::find($product_transfer_data->product_id);
            $unit = Unit::find($product_transfer_data->purchase_unit_id);
            if($product_transfer_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_transfer_data->product_id, $product_transfer_data->variant_id)->first();
                $product->code = $lims_product_variant_data->item_code;
            }
            $product_transfer[0][$key] = $product->name . ' [' . $product->code. ']';

            $product_id = $product_transfer_data->product_id;
            $variant_id = $product_transfer_data->variant_id ?? 0;
            $imeis = $this->findImeis($product_id, $variant_id);
            if ($imeis != 'N/A') {
                $product_transfer[0][$key] .= '<br>IMEI or Serial Number: ' . $imeis;

            }

            $product_transfer[1][$key] = $product_transfer_data->qty;
            $product_transfer[2][$key] = $unit->unit_code;
            $product_transfer[3][$key] = $product_transfer_data->tax;
            $product_transfer[4][$key] = $product_transfer_data->tax_rate;
            $product_transfer[5][$key] = $product_transfer_data->total;
            if($product_transfer_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no')->find($product_transfer_data->product_batch_id);
                $product_transfer[6][$key] = $product_batch_data->batch_no;
            }
            else
                $product_transfer[6][$key] = 'N/A';
        }
        return $product_transfer;
    }
    public function getProductTransferData($id)
    {
        $lims_product_transfer_data = ProductTransfer::where('transfer_id', $id)->get();
        foreach ($lims_product_transfer_data as $key => $product_transfer_data) {
            $product = Product::find($product_transfer_data->product_id);
            $unit = Unit::find($product_transfer_data->purchase_unit_id);
            if($product_transfer_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_transfer_data->product_id, $product_transfer_data->variant_id)->first();
                $product->code = $lims_product_variant_data->item_code;
            }
            $product_transfer['products'][$key] = $product->name . ' [' . $product->code. ']';
            // if($product_transfer_data->imei_number)
            //     $product_transfer['imei_number'][$key] .= '<br>IMEI or Serial Number: ' . $product_transfer_data->imei_number;
            if (isset($product_transfer_data->imei_number)) {
                if (!isset($product_transfer['imei_number'][$key])) {
                    $product_transfer['imei_number'][$key] = '';  // Initialize the key if not already set
                }
                $product_transfer['imei_number'][$key] .= '<br>IMEI or Serial Number: ' . $product_transfer_data->imei_number;
            }
            $product_transfer['qty'][$key] = $product_transfer_data->qty;
            $product_transfer['unit'][$key] = $unit->unit_code;
            $product_transfer['tax'][$key] = $product_transfer_data->tax;
            $product_transfer['tax_rate'][$key] = $product_transfer_data->tax_rate;
            $product_transfer['total'][$key] = $product_transfer_data->total;
            if($product_transfer_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no')->find($product_transfer_data->product_batch_id);
                $product_transfer['batch_no'][$key] = $product_batch_data->batch_no;
            }
            else
                $product_transfer['batch_no'][$key] = 'N/A';
        }
        return $product_transfer;
    }
    public function transferByCsv()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('transfers-add')){
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('backend.transfer.import', compact('lims_warehouse_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function importTransfer(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $request->validate([
                'file' => 'required|file',
                'from_warehouse_id' => 'required|integer|exists:warehouses,id',
                'to_warehouse_id' => 'required|integer|exists:warehouses,id|different:from_warehouse_id',
                'status' => 'required|in:1,2,3',
            ]);
            //get the file
            $upload=$request->file('file');
            $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
            //checking if this is a CSV file
            if($ext != 'csv')
                return redirect()->back()->with('message', __('db.Please upload a CSV file'));

            $filePath=$upload->getRealPath();
            $file_handle = fopen($filePath, 'r');
            $i = 0;
            //validate the file
            while (!feof($file_handle) ) {
                $current_line = fgetcsv($file_handle);
                if($current_line && $i > 0){
                    $product_data[] = Product::where('code', $current_line[0])->first();
                    if(!$product_data[$i-1])
                        return redirect()->back()->with('message', __('db.Product does not exist!'));
                    $unit[] = Unit::where('unit_code', $current_line[2])->first();
                    if(!$unit[$i-1])
                        return redirect()->back()->with('message', __('db.Purchase unit does not exist!'));
                    if(strtolower($current_line[4]) != "no tax"){
                        $tax[] = Tax::where('name', $current_line[4])->first();
                        if(!$tax[$i-1])
                            return redirect()->back()->with('message', __('db.Tax name does not exist!'));
                    }
                    else
                        $tax[$i-1]['rate'] = 0;

                    $qty[] = $current_line[1];
                    $cost[] = $current_line[3];
                }
                $i++;
            }

            $data = $request->except('file');
            $data['reference_no'] = 'tr-' . date("Ymd") . '-'. date("his");
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
                if ($v->fails())
                    return redirect()->back()->withErrors($v->errors());

                $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
                $documentName = $data['reference_no'] . '.' . $ext;
                $document->move(public_path('documents/transfer'), $documentName);
                $data['document'] = $documentName;
            }
            $item = 0;
            $grand_total = $data['shipping_cost'];
            $data['user_id'] = Auth::id();
            $lims_transfer_data = Transfer::create($data);

            foreach ($product_data as $key => $product) {
                if($product['tax_method'] == 1){
                    $net_unit_cost = $cost[$key];
                    $product_tax = $net_unit_cost * ($tax[$key]['rate'] / 100) * $qty[$key];
                    $total = ($net_unit_cost * $qty[$key]) + $product_tax;
                }
                elseif($product['tax_method'] == 2){
                    $net_unit_cost = (100 / (100 + $tax[$key]['rate'])) * $cost[$key];
                    $product_tax = ($cost[$key] - $net_unit_cost) * $qty[$key];
                    $total = $cost[$key] * $qty[$key];
                }
                $product_transfer = new ProductTransfer();
                $product_transfer->transfer_id = $lims_transfer_data->id;
                $product_transfer->product_id = $product['id'];
                $product_transfer->qty = $qty[$key];
                $product_transfer->purchase_unit_id = $unit[$key]['id'];
                $product_transfer->net_unit_cost = number_format((float)$net_unit_cost, config('decimal'), '.', '');
                $product_transfer->tax_rate = $tax[$key]['rate'];
                $product_transfer->tax = number_format((float)$product_tax, config('decimal'), '.', '');
                $product_transfer->total = number_format((float)$total, config('decimal'), '.', '');
                $product_transfer->save();
                $lims_transfer_data->total_qty += $qty[$key];
                $lims_transfer_data->total_tax += number_format((float)$product_tax, config('decimal'), '.', '');
                $lims_transfer_data->total_cost += number_format((float)$total, config('decimal'), '.', '');
            }
            $lims_transfer_data->item = $key + 1;
            $lims_transfer_data->grand_total = $lims_transfer_data->total_cost + $lims_transfer_data->shipping_cost;
            $lims_transfer_data->save();
            $this->postStock($lims_transfer_data);
            return redirect('transfers')->with('message', __('db.Transfer imported successfully'));

        });
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('transfers-edit')){
            $lims_warehouse_list = Warehouse::where('is_active',true)->get();
            $lims_transfer_data = Transfer::find($id);
            $lims_product_transfer_data = ProductTransfer::where('transfer_id', $id)->get();
            return view('backend.transfer.edit', compact('lims_warehouse_list', 'lims_transfer_data', 'lims_product_transfer_data'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function update(Request $request, $id)
    {
        $this->validateTransfer($request);
        return DB::transaction(function () use ($request, $id) {
            $transfer = Transfer::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->reverseStock($transfer);
            ProductTransfer::where('transfer_id', $id)->delete();
            $data = $request->except('document', 'created_at', 'reference_no', 'user_id');
            if ($request->document) {
                $data['document'] = $request->document->getClientOriginalName();
                $request->document->move(public_path('documents/transfer'), $data['document']);
            }
            $transfer->update($data);
            foreach ($request->product_id as $key => $productId) {
                $product = Product::findOrFail($productId);
                $variantId = null;
                if ($product->is_variant) {
                    $variantId = ProductVariant::where('product_id', $productId)
                        ->whereKey($request->product_variant_id[$key] ?? null)->firstOrFail()->variant_id;
                }
                $unit = Unit::where('unit_name', $request->purchase_unit[$key])->firstOrFail();
                ProductTransfer::create([
                    'transfer_id' => $id, 'product_id' => $productId, 'variant_id' => $variantId,
                    'product_batch_id' => $request->product_batch_id[$key] ?? null,
                    'imei_number' => $request->imei_number[$key] ?? null,
                    'qty' => $request->qty[$key], 'purchase_unit_id' => $unit->id,
                    'net_unit_cost' => $request->net_unit_cost[$key], 'tax_rate' => $request->tax_rate[$key],
                    'tax' => $request->tax[$key], 'total' => $request->subtotal[$key],
                ]);
            }
            $this->postStock($transfer);
            return redirect('transfers')->with('message', __('db.Transfer updated successfully'));
        });
    }

    public function changeStatus(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $transfer = Transfer::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ((int) $transfer->status !== 2 || (int) $request->status !== 1) {
                return redirect('transfers')->with('not_permitted', __('Only Pending transfers can be completed'));
            }
            $transfer->update(['status' => 1]);
            $this->postStock($transfer);
            return redirect('transfers')->with('message', __('db.Transfer updated successfully'));
        });
    }

    public function deleteBySelection(Request $request)
    {
        return DB::transaction(function () use ($request) {
            foreach (collect($request->transferIdArray)->sort()->unique() as $id) {
                $this->deleteTransfer($id);
            }
            return 'Transfer deleted successfully!';
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $this->deleteTransfer($id);
            return redirect('transfers')->with('not_permitted', __('db.Transfer deleted successfully'));
        });
    }

    private function validateTransfer(Request $request): void
    {
        $request->validate([
            'from_warehouse_id' => 'required|integer|exists:warehouses,id',
            'to_warehouse_id' => 'required|integer|exists:warehouses,id|different:from_warehouse_id',
            'status' => 'required|in:1,2,3',
            'product_id' => 'required|array|min:1', 'product_id.*' => 'required|integer|exists:products,id',
            'qty' => 'required|array|size:'.count((array) $request->product_id), 'qty.*' => 'required|numeric|gt:0',
            'purchase_unit' => 'required|array|size:'.count((array) $request->product_id),
            'purchase_unit.*' => 'required|exists:units,unit_name',
            'net_unit_cost.*' => 'required|numeric|min:0',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,csv,docx,xlsx,txt',
        ]);
    }

    private function stockLines(Transfer $transfer): array
    {
        return ProductTransfer::where('transfer_id', $transfer->id)->get()->map(fn ($line) => StockLine::fromArray([
            'product_id' => $line->product_id, 'qty' => $line->qty, 'variant_id' => $line->variant_id,
            'product_batch_id' => $line->product_batch_id, 'imei_number' => $line->imei_number,
            'uom_id' => $line->purchase_unit_id, 'unit_cost' => $line->net_unit_cost,
        ]))->all();
    }

    private function postStock(Transfer $transfer): void
    {
        if ((int) $transfer->status === 2) {
            return;
        }
        app(LegacyInventoryPosting::class)->post($transfer, (int) $transfer->status === 1 ? 'transfer' : 'issue',
            $this->stockLines($transfer), (int) $transfer->from_warehouse_id, (int) $transfer->to_warehouse_id);
    }

    private function reverseStock(Transfer $transfer): void
    {
        $posting = app(LegacyInventoryPosting::class);
        $posting->reverse($transfer, function () use ($posting, $transfer) {
            if ((int) $transfer->status === 1) {
                $posting->post($transfer, 'transfer', $this->stockLines($transfer), (int) $transfer->to_warehouse_id, (int) $transfer->from_warehouse_id);
            } elseif ((int) $transfer->status === 3) {
                $posting->post($transfer, 'receive', $this->stockLines($transfer), (int) $transfer->from_warehouse_id);
            }
        });
    }

    private function deleteTransfer($id): void
    {
        $transfer = Transfer::whereKey($id)->lockForUpdate()->firstOrFail();
        $this->reverseStock($transfer);
        ProductTransfer::where('transfer_id', $id)->delete();
        $transfer->delete();
        DB::afterCommit(fn () => $this->fileDelete(public_path('documents/transfer/'), $transfer->document));
    }
}
