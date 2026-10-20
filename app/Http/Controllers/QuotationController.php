<?php

namespace App\Http\Controllers;

use App\Enums\CustomerTypeEnum;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Biller;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Tax;
use App\Models\Quotation;
use App\Models\Delivery;
use App\Models\PosSetting;
use App\Models\ProductQuotation;
use App\Models\Product_Warehouse;
use App\Models\ProductVariant;
use App\Models\ProductBatch;
use App\Models\Variant;
use DB;
use NumberToWords\NumberToWords;
use Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Mail\QuotationDetails;
use Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\MailSetting;
use App\Traits\MailInfo;
use App\Traits\StaffAccess;
use App\Traits\TenantInfo;

class QuotationController extends Controller
{
    use TenantInfo, MailInfo, StaffAccess;
    use \App\Http\Controllers\Concerns\NumbersLegacyDocuments;

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('quotes-index')){
            if($request->input('warehouse_id'))
                $warehouse_id = $request->input('warehouse_id');
            else
                $warehouse_id = 0;

            if($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date = $request->input('ending_date');
            }
            else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d') )))));
                $ending_date = date("Y-m-d");
            }

            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';

            $lims_warehouse_list = Warehouse::where('is_active', true)->get();

            /*if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $lims_quotation_all = Quotation::with('biller', 'customer', 'supplier', 'user')->orderBy('id', 'desc')->where('user_id', Auth::id())->get();
            else
                $lims_quotation_all = Quotation::with('biller', 'customer', 'supplier', 'user')->orderBy('id', 'desc')->get();*/
            return view('backend.quotation.index', compact('lims_warehouse_list', 'all_permission', 'warehouse_id', 'starting_date', 'ending_date'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function quotationData(Request $request)
    {
        $filters = $request->validate([
            'starting_date' => 'nullable|date_format:Y-m-d', 'ending_date' => 'nullable|date_format:Y-m-d|after_or_equal:starting_date',
            'warehouse_id' => 'nullable|integer|min:0', 'follow_up_from' => 'nullable|date_format:Y-m-d',
            'follow_up_to' => 'nullable|date_format:Y-m-d|after_or_equal:follow_up_from',
            'follow_up_due' => 'nullable|boolean', 'start' => 'nullable|integer|min:0', 'length' => 'nullable|integer|min:-1|max:500',
            'search.value' => 'nullable|string|max:200', 'order.0.column' => 'nullable|integer|min:0', 'order.0.dir' => 'nullable|in:asc,desc',
        ]);
        $base = Quotation::query()
            ->when($request->filled('starting_date'), fn ($q) => $q->whereDate('created_at', '>=', $filters['starting_date']))
            ->when($request->filled('ending_date'), fn ($q) => $q->whereDate('created_at', '<=', $filters['ending_date']))
            ->when((int) ($filters['warehouse_id'] ?? 0), fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']));
        if (Auth::user()->role_id > 2 && config('staff_access') === 'own') $base->where('user_id', Auth::id());
        elseif (Auth::user()->role_id > 2 && config('staff_access') === 'warehouse') $base->where('warehouse_id', Auth::user()->warehouse_id);
        $base->when($request->filled('follow_up_from'), fn ($q) => $q->whereDate('next_follow_up', '>=', $filters['follow_up_from']))
            ->when($request->filled('follow_up_to'), fn ($q) => $q->whereDate('next_follow_up', '<=', $filters['follow_up_to']))
            ->when($request->boolean('follow_up_due'), fn ($q) => $q->whereNotNull('next_follow_up')->whereDate('next_follow_up', '<=', today()->toDateString()));
        $totalData = (clone $base)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $base->where(function ($q) use ($pattern, $search) {
                $q->whereRaw("reference_no LIKE ? ESCAPE '!'", [$pattern]);
                foreach (['biller', 'customer', 'supplier'] as $relation) {
                    $q->orWhereHas($relation, fn ($party) => $party->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $search)) $q->orWhereDate('created_at', $search);
            });
        }
        $totalFiltered = (clone $base)->count();
        $columns = [1 => 'created_at', 2 => 'reference_no', 8 => 'grand_total', 9 => 'next_follow_up'];
        $order = $columns[(int) $request->input('order.0.column', 1)] ?? 'created_at';
        $dir = $request->input('order.0.dir', 'desc');
        $limit = (int) $request->input('length', 25);
        $quotations = $base->with('biller', 'customer', 'supplier', 'user', 'warehouse')
            ->offset((int) $request->input('start', 0))->limit($limit === -1 ? $totalFiltered : max(1, $limit))
            ->orderBy($order, $dir)->orderBy('id', $dir)->get();
        $data = array();
        if(!empty($quotations))
        {
            foreach ($quotations as $key => $quotation)
            {
                $nestedData['id'] = $quotation->id;
                $nestedData['key'] = $key;
                $nestedData['date'] = date(config('date_format'), strtotime($quotation->created_at->toDateString()));
                $nestedData['reference_no'] = e($quotation->reference_no);
                $nestedData['warehouse'] = e($quotation->warehouse->name);
                $nestedData['biller'] = e($quotation->biller->name);
                $nestedData['customer'] = e($quotation->customer->name);

                if($quotation->supplier_id) {
                    $supplier = $quotation->supplier;
                    $nestedData['supplier'] = e($supplier->name);
                }
                else {
                    $nestedData['supplier'] = 'N/A';
                }

                if ($quotation->converted_document_id) {
                    $status = 'Converted to '.ucfirst($quotation->converted_kind).' #'.$quotation->converted_document_id;
                    $nestedData['status'] = '<div class="badge badge-secondary">'.e($status).'</div>';
                } elseif ($quotation->conversion_review_required) {
                    $status = 'Sent / conversion review required';
                    $nestedData['status'] = '<div class="badge badge-warning">'.e($status).'</div>';
                } elseif($quotation->quotation_status == 1) {
                    $nestedData['status'] = '<div class="badge badge-danger">'.__('db.Pending').'</div>';
                    $status = __('db.Pending');
                }
                else{
                    $nestedData['status'] = '<div class="badge badge-success">'.__('db.Sent').'</div>';
                    $status = __('db.Sent');
                }

                $nestedData['next_follow_up'] = $quotation->next_follow_up?->toDateString() ?? '';
                $nestedData['grand_total'] = number_format($quotation->grand_total, config('decimal'));
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.__("db.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li>
                                    <button type="button" class="btn btn-link view"><i class="fa fa-eye"></i> '.__('db.View').'</button>
                                </li>';
                if(in_array("quotes-edit", $request['all_permission']))
                    $nestedData['options'] .= '<li>
                        <a href="'.route('quotations.edit', $quotation->id).'" class="btn btn-link"><i class="dripicons-document-edit"></i> '.__('db.edit').'</a>
                        </li>';
                if ($quotation->canConvert()) $nestedData['options'] .= '<li>
                        <a href="'.route('quotation.create_sale', $quotation->id).'" class="btn btn-link"><i class="fa fa-shopping-cart"></i> '.__('db.Create Sale').'</a>
                        </li>';
                if ($quotation->canConvert()) $nestedData['options'] .= '<li>
                        <a href="'.route('quotation.create_purchase', $quotation->id).'" class="btn btn-link"><i class="fa fa-shopping-basket"></i> '.__('db.Create Purchase').'</a>
                        </li>';
                if(in_array("quotes-delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["quotations.destroy", $quotation->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> '.__("db.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';

                // data for quotation details by one click

                $nestedData['quotation'] = array_map(static fn ($value) => is_string($value) ? e($value) : $value, [
                    date(config('date_format'), strtotime($quotation->created_at->toDateString())), $quotation->reference_no, $status,
                    $quotation->biller->name, $quotation->biller->company_name, $quotation->biller->email,
                    $quotation->biller->phone_number, $quotation->biller->address, $quotation->biller->city,
                    $quotation->customer->name, $quotation->customer->phone_number, $quotation->customer->address,
                    $quotation->customer->city, $quotation->id, $quotation->total_tax, $quotation->total_discount,
                    $quotation->total_price, $quotation->order_tax, $quotation->order_tax_rate, $quotation->order_discount,
                    $quotation->shipping_cost, $quotation->grand_total, $quotation->note ?? '',
                    $quotation->user->name, $quotation->user->email, $quotation->document,
                ]);
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        return response()->json($json_data);
    }

    public function create()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('quotes-add')){
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_customer_list = Customer::where([
                'is_active' => true,
                'type' => CustomerTypeEnum::REGULAR->value,
            ])->get();
            $lims_supplier_list = Supplier::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();

            return view('backend.quotation.create', compact('lims_biller_list', 'lims_warehouse_list', 'lims_customer_list', 'lims_supplier_list', 'lims_tax_list'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function store(Request $request)
    {
        $this->validateAmounts($request);
        $data = $request->except('document');
        //return dd($data);
        $data['user_id'] = Auth::id();
        $document = $request->document;
        if($document){
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
            $documentName = date("Ymdhis");
            if(!config('database.connections.zoloerp_landlord')) {
                $documentName = $documentName . '.' . $ext;
                $document->move(public_path('documents/quotation'), $documentName);
            }
            else {
                $documentName = $this->getTenantId() . '_' . $documentName . '.' . $ext;
                $document->move(public_path('documents/quotation'), $documentName);
            }
            $data['document'] = $documentName;
        }
        $lims_quotation_data = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $numberReservation = $this->reserveNumber('quotation');
            $quotation = Quotation::create($data + ['reference_no' => $numberReservation->formatted_number]);
            $this->assignNumber($numberReservation, $quotation);
            return $quotation;
        });
        $data['reference_no'] = $lims_quotation_data->reference_no;
        if($lims_quotation_data->quotation_status == 2){
            //collecting mail data
            $lims_customer_data = Customer::find($data['customer_id']);
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_quotation_data->reference_no;
            $mail_data['total_qty'] = $lims_quotation_data->total_qty;
            $mail_data['total_price'] = $lims_quotation_data->total_price;
            $mail_data['order_tax'] = $lims_quotation_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_quotation_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_quotation_data->order_discount;
            $mail_data['shipping_cost'] = $lims_quotation_data->shipping_cost;
            $mail_data['grand_total'] = $lims_quotation_data->grand_total;
        }
        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $product_code = $data['product_code'];
        $qty = $data['qty'];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];
        $product_quotation = [];
        $log_data['item_description'] = '';

        foreach ($product_id as $i => $id) {
            $lims_product_data = Product::find($id);

            if($sale_unit[$i] != 'n/a'){
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$i])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
            }
            else
                $sale_unit_id = 0;

            if($sale_unit_id) {
                $log_data['item_description'] .= $lims_product_data->name. '-'. $qty[$i].' '.$lims_sale_unit_data->unit_code.'<br>';
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            }
            else {
                $log_data['item_description'] .= $lims_product_data->name. '-'. $qty[$i].'<br>';
                $mail_data['unit'][$i] = '';
            }

            $lims_product_data = Product::find($id);
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('variant_id')->FindExactProductWithCode($id, $product_code[$i])->first();
                $product_quotation['variant_id'] = $lims_product_variant_data->variant_id;
            }
            else
                $product_quotation['variant_id'] = null;
            if($product_quotation['variant_id']){
                $variant_data = Variant::find($product_quotation['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' [' . $variant_data->name .']';
            }
            else
                $mail_data['products'][$i] = $lims_product_data->name;
            $product_quotation['quotation_id'] = $lims_quotation_data->id ;
            $product_quotation['product_id'] = $id;
            $product_quotation['product_batch_id'] = $product_batch_id[$i];
            $product_quotation['qty'] = $mail_data['qty'][$i] = $qty[$i];
            $product_quotation['sale_unit_id'] = $sale_unit_id;
            $product_quotation['net_unit_price'] = $net_unit_price[$i];
            $product_quotation['discount'] = $discount[$i];
            $product_quotation['tax_rate'] = $tax_rate[$i];
            $product_quotation['tax'] = $tax[$i];
            $product_quotation['total'] = $mail_data['total'][$i] = $total[$i];
            ProductQuotation::create($product_quotation);
        }
        $message = 'Quotation created successfully';

        //creating log
        $log_data['action'] = 'Quotation Created';
        $log_data['user_id'] = Auth::id();
        $log_data['reference_no'] = $lims_quotation_data->reference_no;
        $log_data['date'] = $lims_quotation_data->created_at->toDateString();            
        // $log_data['admin_email'] = config('admin_email');
        $log_data['admin_message'] = Auth::user()->name . ' has created a quotation. Reference No: ' .$lims_quotation_data->reference_no;
        $log_data['user_email'] = Auth::user()->email;
        $log_data['user_name'] = Auth::user()->name;
        $log_data['user_message'] = 'You just created a quotation. Reference No: ' .$lims_quotation_data->reference_no;
        // $log_data['mail_setting'] = $mail_setting = MailSetting::latest()->first();
        $this->createActivityLog($log_data);

        $mail_setting = MailSetting::latest()->first();
        if($lims_quotation_data->quotation_status == 2 && $mail_data['email'] && $mail_setting) {
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new QuotationDetails($mail_data));
            }
            catch(\Exception $e){
                $message = 'Quotation created successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        app(\App\Services\Commercial\CommercialDraftService::class)->forgetPosted('quotation', $request->integer('draft_id'), (int) Auth::id());
        return redirect('quotations')->with('message', $message);
    }

    public function sendMail(Request $request)
    {
        $data = $request->all();
        $lims_quotation_data = Quotation::find($data['quotation_id']);
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $data['quotation_id'])->get();
        $lims_customer_data = Customer::find($lims_quotation_data->customer_id);
        $mail_setting = MailSetting::latest()->first();

        if(!$mail_setting) {
            $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }else if(!$lims_customer_data->email) {
            $message = 'Customer doesnt have email!';
        }
        else if($lims_customer_data->email && $mail_setting) {
            //collecting male data
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_quotation_data->reference_no;
            $mail_data['total_qty'] = $lims_quotation_data->total_qty;
            $mail_data['total_price'] = $lims_quotation_data->total_price;
            $mail_data['order_tax'] = $lims_quotation_data->order_tax;
            $mail_data['order_tax_rate'] = $lims_quotation_data->order_tax_rate;
            $mail_data['order_discount'] = $lims_quotation_data->order_discount;
            $mail_data['shipping_cost'] = $lims_quotation_data->shipping_cost;
            $mail_data['grand_total'] = $lims_quotation_data->grand_total;

            foreach ($lims_product_quotation_data as $key => $product_quotation_data) {
                $lims_product_data = Product::find($product_quotation_data->product_id);
                if($product_quotation_data->variant_id) {
                    $variant_data = Variant::find($product_quotation_data->variant_id);
                    $mail_data['products'][$key] = $lims_product_data->name . ' [' . $variant_data->name . ']';
                }
                else
                    $mail_data['products'][$key] = $lims_product_data->name;
                if($product_quotation_data->sale_unit_id){
                    $lims_unit_data = Unit::find($product_quotation_data->sale_unit_id);
                    $mail_data['unit'][$key] = $lims_unit_data->unit_code;
                }
                else
                    $mail_data['unit'][$key] = '';

                $mail_data['qty'][$key] = $product_quotation_data->qty;
                $mail_data['total'][$key] = $product_quotation_data->total;
            }
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new QuotationDetails($mail_data));
                $message = 'Mail sent successfully';
            }
            catch(\Exception $e){
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }

        return redirect()->back()->with('message', $message);
    }

    public function getCustomerGroup($id)
    {
         $lims_customer_data = Customer::findOrFail($id);
         $lims_customer_group_data = CustomerGroup::find($lims_customer_data->customer_group_id);
         // A customer without a visible group gets no group discount rather than a broken price list.
         return $lims_customer_group_data->percentage ?? 0;
    }

    public function getProduct($id)
    {
        $product_code = [];
        $product_name = [];
        $product_qty = [];
        $product_price = [];
        $product_data = [];
        $product_type = [];
        $product_id = [];
        $product_list = [];
        $qty_list = [];
        $batch_no = [];
        $product_batch_id = [];
        $expired_date = [];
        $is_embeded = [];
        $imei_number = [];

        //retrieve data of product without variant
        $lims_product_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
        ])
        ->whereNull('product_warehouse.variant_id')
        ->whereNull('product_warehouse.product_batch_id')
        ->select('product_warehouse.*')
        ->get();

        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $product_code[] =  $lims_product_data->code;
            $product_name[] = $lims_product_data->name;
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = null;
            $qty_list[] = null;
            $batch_no[] = null;
            $product_batch_id[] = null;
            $expired_date[] = null;
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;
            $imei_number[] = null;
        }

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

        config()->set('database.connections.mysql.strict', false);
        \DB::reconnect(); //important as the existing connection if any would be in strict mode

        $lims_product_with_batch_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
        ])
        ->whereNull('product_warehouse.variant_id')
        ->whereNotNull('product_warehouse.product_batch_id')
        ->select('product_warehouse.*')
        ->groupBy('product_warehouse.product_id')
        ->get();

        //now changing back the strict ON
        config()->set('database.connections.mysql.strict', true);
        \DB::reconnect();

        foreach ($lims_product_with_batch_warehouse_data as $product_warehouse)
        {
            $product_qty[] = $product_warehouse->qty;
            $product_price[] = $product_warehouse->price;
            $lims_product_data = Product::find($product_warehouse->product_id);
            $product_code[] =  $lims_product_data->code;
            $product_name[] = $lims_product_data->name;
            $product_type[] = $lims_product_data->type;
            $product_id[] = $lims_product_data->id;
            $product_list[] = null;
            $qty_list[] = null;
            $product_batch_data = ProductBatch::select('id', 'batch_no')->find($product_warehouse->product_batch_id);
            $batch_no[] = $product_batch_data->batch_no;
            $product_batch_id[] = $product_batch_data->id;
            $expired_date[] = null;
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;
            $imei_number[] = null;
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

        //retrieve data of product with variant
        $lims_product_warehouse_data = Product::join('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')
        ->where([
            ['products.is_active', true],
            ['product_warehouse.warehouse_id', $id],
        ])->whereNotNull('product_warehouse.variant_id')->select('product_warehouse.*')->get();
        foreach ($lims_product_warehouse_data as $product_warehouse)
        {
            $lims_product_data = Product::find($product_warehouse->product_id);
            $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_warehouse->product_id, $product_warehouse->variant_id)->first();
            if($lims_product_variant_data) {
                $product_qty[] = $product_warehouse->qty;
                $product_code[] =  $lims_product_variant_data->item_code;
                $product_name[] = $lims_product_data->name;
                $product_type[] = $lims_product_data->type;
                $product_id[] = $lims_product_data->id;
                $product_list[] = null;
                $qty_list[] = null;
                $batch_no[] = null;
                $product_batch_id[] = null;
            }
            $expired_date[] = null;
            if($product_warehouse->is_embeded)
                $is_embeded[] = $product_warehouse->is_embeded;
            else
                $is_embeded[] = 0;
            $imei_number[] = null;
        }
        //retrieve product data of digital and combo
        $lims_product_data = Product::whereNotIn('type', ['standard'])->where('is_active', true)->get();
        foreach ($lims_product_data as $product)
        {
            $product_qty[] = $product->qty;
            $product_code[] =  $product->code;
            $product_name[] = $product->name;
            $product_type[] = $product->type;
            $product_id[] = $product->id;
            $product_list[] = $product->product_list;
            $lims_product_data = $product->id;
            $qty_list[] = $product->qty_list;
            $expired_date[] = null;
            $is_embeded[] = 0;
            $imei_number[] = null;
        }
        $product_data = [$product_code, $product_name, $product_qty, $product_type, $product_id, $product_list, $qty_list, $product_price, $batch_no, $product_batch_id, $expired_date, $is_embeded, $imei_number];
        return $product_data;
    }

    public function limsProductSearch(Request $request)
    {
        abort_unless($request->filled('data'), 404); // autocomplete endpoint: always called with the picked item
        //return $request['data'];
        $todayDate = date('Y-m-d');
        $product_data = explode("|", $request['data']);
        // $product_code = explode("(", $request['data']);
        $product_info = explode("?", $request['data']);
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
            $qty = $product_info[2];
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
                ->select('products.*', 'product_variants.id as product_variant_id', 'product_variants.item_code', 'product_variants.additional_price')
                ->where([
                    ['product_variants.item_code', $product_data[0]],
                    ['products.is_active', true]
                ])->first();

            return $lims_product_data;
            $product_variant_id = $lims_product_data->product_variant_id;
        }

        $product[] = $lims_product_data->name;
        if($lims_product_data->is_variant){
            $product[] = $lims_product_data->item_code;
            $lims_product_data->price += $lims_product_data->additional_price;
        }
        else
            $product[] = $lims_product_data->code;

        $no_discount = 1;
        foreach ($all_discount as $key => $discount) {
            $product_list = explode(",", $discount->product_list);
            $days = explode(",", $discount->days);

            if( ( $discount->applicable_for == 'All' || in_array($lims_product_data->id, $product_list) ) && ( $todayDate >= $discount->valid_from && $todayDate <= $discount->valid_till && in_array(date('D'), $days) && $qty >= $discount->minimum_qty && $qty <= $discount->maximum_qty ) ) {
                if($discount->type == 'flat') {
                    $product[] = $lims_product_data->price - $discount->value;
                }
                elseif($discount->type == 'percentage') {
                    $product[] = $lims_product_data->price - ($lims_product_data->price * ($discount->value/100));
                }
                $no_discount = 0;
                break;
            }
            else {
                continue;
            }
        }

        if($lims_product_data->promotion && $todayDate <= $lims_product_data->last_date && $no_discount) {
            $product[] = $lims_product_data->promotion_price;
        }
        elseif($no_discount)
            $product[] = $lims_product_data->price;

        // GST slab first (same rule as the sales search), then the legacy tax.
        if ($gst = app(\App\Services\Tax\GstSetupService::class)->rateFor($lims_product_data)) {
            $product[] = $gst['rate'];
            $product[] = $gst['name'];
        }
        elseif($lims_product_data->tax_id && ($lims_tax_data = Tax::find($lims_product_data->tax_id))) {
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
        $product[] = $lims_product_data->is_batch;
        $product[] = $lims_product_data->is_imei;
        $product[] = $lims_product_data->is_variant;
        $product[] = $qty;
        $product[] = $lims_product_data->wholesale_price;
        $product[] = $lims_product_data->cost;
        $product[] = $product_data[2];

        return $product;
    }

    public function productQuotationData($id)
    {
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        $product_quotation = []; // a document without lines answers an empty list
        foreach ($lims_product_quotation_data as $key => $product_quotation_data) {
            $product = Product::find($product_quotation_data->product_id);
            if($product_quotation_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('item_code')->FindExactProduct($product_quotation_data->product_id, $product_quotation_data->variant_id)->first();
                $product->code = $lims_product_variant_data->item_code;
            }
            if($product_quotation_data->sale_unit_id){
                $unit_data = Unit::find($product_quotation_data->sale_unit_id);
                $unit = $unit_data->unit_code;
            }
            else
                $unit = '';

            $product_quotation[0][$key] = e($product->name . ' [' . $product->code . ']');
            $product_quotation[1][$key] = $product_quotation_data->qty;
            $product_quotation[2][$key] = $unit;
            $product_quotation[3][$key] = $product_quotation_data->tax;
            $product_quotation[4][$key] = $product_quotation_data->tax_rate;
            $product_quotation[5][$key] = $product_quotation_data->discount;
            $product_quotation[6][$key] = $product_quotation_data->total;
            if($product_quotation_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no')->find($product_quotation_data->product_batch_id);
                $product_quotation[7][$key] = e($product_batch_data->batch_no);
            }
            else
                $product_quotation[7][$key] = 'N/A';
        }
        return $product_quotation;
    }

    public function edit($id)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('quotes-edit')){
            $lims_customer_list = Customer::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_biller_list = Biller::where('is_active', true)->get();
            $lims_supplier_list = Supplier::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_quotation_data = Quotation::findOrFail($id);
            $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
            $followUpHistory = \Illuminate\Support\Facades\Schema::hasTable('quotation_follow_ups')
                ? DB::table('quotation_follow_ups')->where('company_id', $lims_quotation_data->company_id)->where('quotation_id', $lims_quotation_data->id)->orderByDesc('id')->limit(20)->get()
                : collect();
            return view('backend.quotation.edit',compact('lims_customer_list', 'lims_warehouse_list', 'lims_biller_list', 'lims_tax_list', 'lims_quotation_data','lims_product_quotation_data', 'lims_supplier_list', 'followUpHistory'));
        }
        else
            return redirect()->back()->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
    }

    public function update(Request $request, $id)
    {
        $this->validateAmounts($request);
        $data = $request->except('document');
        //return dd($data);
        $document = $request->document;
        $lims_quotation_data = Quotation::findOrFail($id);

        if($document) {
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

            $this->fileDelete(public_path('documents/quotation/'), $lims_quotation_data->document);

            $ext = pathinfo($document->getClientOriginalName(), PATHINFO_EXTENSION);
            $documentName = date("Ymdhis");
            if(!config('database.connections.zoloerp_landlord')) {
                $documentName = $documentName . '.' . $ext;
                $document->move(public_path('documents/quotation'), $documentName);
            }
            else {
                $documentName = $this->getTenantId() . '_' . $documentName . '.' . $ext;
                $document->move(public_path('documents/quotation'), $documentName);
            }
            $data['document'] = $documentName;
        }
        $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
        //update quotation table
        $lims_quotation_data = $lims_quotation_data->revise($data);
        if($lims_quotation_data->quotation_status == 2){
            //collecting mail data
            $lims_customer_data = Customer::find($data['customer_id']);
            $mail_data['email'] = $lims_customer_data->email;
            $mail_data['reference_no'] = $lims_quotation_data->reference_no;
            $mail_data['total_qty'] = $data['total_qty'];
            $mail_data['total_price'] = $data['total_price'];
            $mail_data['order_tax'] = $data['order_tax'];
            $mail_data['order_tax_rate'] = $data['order_tax_rate'];
            $mail_data['order_discount'] = $data['order_discount'];
            $mail_data['shipping_cost'] = $data['shipping_cost'];
            $mail_data['grand_total'] = $data['grand_total'];
        }
        $product_id = $data['product_id'];
        $product_batch_id = $data['product_batch_id'];
        $product_variant_id = $data['product_variant_id'];
        $qty = $data['qty'];
        $sale_unit = $data['sale_unit'];
        $net_unit_price = $data['net_unit_price'];
        $discount = $data['discount'];
        $tax_rate = $data['tax_rate'];
        $tax = $data['tax'];
        $total = $data['subtotal'];

        foreach ($lims_product_quotation_data as $key => $product_quotation_data) {
            $old_product_id[] = $product_quotation_data->product_id;
            $lims_product_data = Product::select('id')->find($product_quotation_data->product_id);
            if($product_quotation_data->variant_id) {
                $lims_product_variant_data = ProductVariant::select('id')->FindExactProduct($product_quotation_data->product_id, $product_quotation_data->variant_id)->first();
                $old_product_variant_id[] = $lims_product_variant_data->id;
                if(!in_array($lims_product_variant_data->id, $product_variant_id))
                    $product_quotation_data->delete();
            }
            else {
                $old_product_variant_id[] = null;
                if(!in_array($product_quotation_data->product_id, $product_id))
                    $product_quotation_data->delete();
            }
        }

        $log_data['item_description'] = '';
        foreach ($product_id as $i => $pro_id) {
            if($sale_unit[$i] != 'n/a'){
                $lims_sale_unit_data = Unit::where('unit_name', $sale_unit[$i])->first();
                $sale_unit_id = $lims_sale_unit_data->id;
            }
            else
                $sale_unit_id = 0;
            $lims_product_data = Product::select('id', 'name', 'is_variant')->find($pro_id);
            if($sale_unit_id) {
                $log_data['item_description'] .= $lims_product_data->name. '-'. $qty[$i].' '.$lims_sale_unit_data->unit_code.'<br>';
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            }
            else {
                $log_data['item_description'] .= $lims_product_data->name. '-'. $qty[$i].'<br>';
                $mail_data['unit'][$i] = '';
            }
            
            $input['quotation_id'] = $id;
            $input['product_id'] = $pro_id;
            $input['product_batch_id'] = $product_batch_id[$i];
            $input['qty'] = $mail_data['qty'][$i] = $qty[$i];
            $input['sale_unit_id'] = $sale_unit_id;
            $input['net_unit_price'] = $net_unit_price[$i];
            $input['discount'] = $discount[$i];
            $input['tax_rate'] = $tax_rate[$i];
            $input['tax'] = $tax[$i];
            $input['total'] = $mail_data['total'][$i] = $total[$i];
            $flag = 1;
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('variant_id')->where('id', $product_variant_id[$i])->first();
                $input['variant_id'] = $lims_product_variant_data->variant_id;
                if(in_array($product_variant_id[$i], $old_product_variant_id)) {
                    ProductQuotation::where([
                        ['product_id', $pro_id],
                        ['variant_id', $input['variant_id']],
                        ['quotation_id', $id]
                    ])->update($input);
                }
                else {
                    ProductQuotation::create($input);
                }
                $variant_data = Variant::find($input['variant_id']);
                $mail_data['products'][$i] = $lims_product_data->name . ' [' . $variant_data->name . ']';
            }
            else {
                $input['variant_id'] = null;
                if(in_array($pro_id, $old_product_id)) {
                    ProductQuotation::where([
                        ['product_id', $pro_id],
                        ['quotation_id', $id]
                    ])->update($input);
                }
                else {
                    ProductQuotation::create($input);
                }
                $mail_data['products'][$i] = $lims_product_data->name;
            }
        }

        $message = 'Quotation updated successfully';

        //creating log
        $log_data['action'] = 'Quotation Updated';
        $log_data['user_id'] = Auth::id();
        $log_data['reference_no'] = $lims_quotation_data->reference_no;
        $log_data['date'] = $lims_quotation_data->created_at->toDateString();            
        // $log_data['admin_email'] = config('admin_email');
        $log_data['admin_message'] = Auth::user()->name . ' has updated a quotation. Reference No: ' .$lims_quotation_data->reference_no;
        $log_data['user_email'] = Auth::user()->email;
        $log_data['user_name'] = Auth::user()->name;
        $log_data['user_message'] = 'You just updated a quotation. Reference No: ' .$lims_quotation_data->reference_no;
        // $log_data['mail_setting'] = $mail_setting = MailSetting::latest()->first();
        $this->createActivityLog($log_data);

        $mail_setting = MailSetting::latest()->first();
        if($lims_quotation_data->quotation_status == 2 && $mail_data['email'] && $mail_setting) {
            $this->setMailInfo($mail_setting);
            try{
                Mail::to($mail_data['email'])->send(new QuotationDetails($mail_data));
            }
            catch(\Exception $e){
                $message = 'Quotation updated successfully. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return redirect('quotations')->with('message', $message);
    }

    /** A quotation becomes a bill on the normal Sales page (one entry engine); the bill marks the quotation converted. */
    public function createSale($id)
    {
        return $this->convert($id, 'sales.index');
    }

    public function createPurchase($id)
    {
        return $this->convert($id, 'purchases.index');
    }

    private function convert($id, string $route)
    {
        $quotation = Quotation::findOrFail($id);
        if (!$quotation->canConvert()) {
            return redirect('quotations')->with('not_permitted', 'Quotation '.$quotation->reference_no.' is already converted or requires historical conversion review.');
        }
        return redirect()->route($route, ['from_quotation' => $quotation->id]);
    }

    /** Header and lines that prefill the Sales / Purchase page for a conversion. */
    public function conversionData($id)
    {
        $quotation = Quotation::findOrFail($id);
        $units = Unit::pluck('unit_name', 'id');
        return response()->json(['quotation' => $quotation->only(['id', 'reference_no', 'customer_id', 'supplier_id', 'warehouse_id', 'biller_id', 'note', 'quotation_status']),
            'items' => ProductQuotation::where('quotation_id', $quotation->id)->get()->map(function ($line) use ($units) {
                $product = Product::find($line->product_id);
                return ['product_id' => $line->product_id, 'product_name' => $product->name ?? 'Item #'.$line->product_id, 'product_code' => $product->code ?? '',
                    'qty' => (float) $line->qty, 'price' => (float) $line->net_unit_price, 'cost' => (float) $line->net_unit_price, 'rate' => (float) $line->net_unit_price,
                    'tax_rate' => (float) $line->tax_rate, 'discount' => (float) $line->discount, 'product_batch_id' => $line->product_batch_id,
                    'unit' => $units[$line->sale_unit_id] ?? ($product ? ($units[$product->unit_id] ?? 'Unit') : 'Unit')];
            })->values()]);
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
                ->orderBy('position')->get();
    }

    public function deleteBySelection(Request $request)
    {
        $request->validate(['quotationIdArray' => 'required|array|min:1', 'quotationIdArray.*' => 'required|integer|distinct']);
        $quotations = DB::transaction(function () use ($request) {
            $quotations = Quotation::whereIn('id', $request->input('quotationIdArray'))->orderBy('id')->lockForUpdate()->get();
            abort_unless($quotations->count() === count($request->input('quotationIdArray')), 404);
            // Preflight every record before removing any header or line.
            foreach ($quotations as $quotation) $quotation->assertDeletable();
            foreach ($quotations as $quotation) {
                ProductQuotation::where('quotation_id', $quotation->id)->get()->each->delete();
                $quotation->delete();
            }
            return $quotations;
        });
        foreach ($quotations as $quotation) $this->fileDelete(public_path('documents/quotation/'), $quotation->document);
        return 'Quotation deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_quotation_data = DB::transaction(function () use ($id) {
            $lims_quotation_data = Quotation::lockForUpdate()->findOrFail($id);
            $lims_quotation_data->assertDeletable();
            $lims_product_quotation_data = ProductQuotation::where('quotation_id', $id)->get();
            $log_data['item_description'] = '';
            foreach ($lims_product_quotation_data as $product_quotation_data) {
                $lims_product_data = Product::select('id', 'name', 'is_variant')->find($product_quotation_data->product_id);

                if($product_quotation_data->sale_unit_id) {
                    $lims_sale_unit_data = Unit::find($product_quotation_data->sale_unit_id);
                    $log_data['item_description'] .= $lims_product_data->name. '-'. $product_quotation_data->qty.' '.$lims_sale_unit_data->unit_code.'<br>';
                }
                else {
                    $log_data['item_description'] .= $lims_product_data->name. '-'. $product_quotation_data->qty.'<br>';
                }
                $product_quotation_data->delete();
            }

            //creating log
            $log_data['action'] = 'Quotation Deleted';
            $log_data['user_id'] = Auth::id();
            $log_data['reference_no'] = $lims_quotation_data->reference_no;
            $log_data['date'] = $lims_quotation_data->created_at->toDateString();
            // $log_data['admin_email'] = config('admin_email');
            $log_data['admin_message'] = Auth::user()->name . ' has deleted a quotation. Reference No: ' .$lims_quotation_data->reference_no;
            $log_data['user_email'] = Auth::user()->email;
            $log_data['user_name'] = Auth::user()->name;
            $log_data['user_message'] = 'You just deleted a quotation. Reference No: ' .$lims_quotation_data->reference_no;
            // $log_data['mail_setting'] = MailSetting::latest()->first();
            $this->createActivityLog($log_data);

            $lims_quotation_data->delete();
            return $lims_quotation_data;
        });
        $this->fileDelete(public_path('documents/quotation/'), $lims_quotation_data->document);
        return redirect('quotations')->with('not_permitted', __('db.Quotation deleted successfully'));
    }

    /** Totals come from the browser; reject NaN/blank amounts instead of failing in the database. */
    private function validateAmounts(Request $request): void
    {
        $request->validate([
            'next_follow_up' => 'nullable|date_format:Y-m-d',
            'customer_id' => 'required|integer', 'warehouse_id' => 'required|integer',
            'product_id' => 'required|array|min:1', 'qty' => 'required|array', 'qty.*' => 'required|numeric|gt:0',
            'net_unit_price' => 'required|array', 'net_unit_price.*' => 'required|numeric|min:0',
            'tax.*' => 'nullable|numeric', 'subtotal.*' => 'nullable|numeric',
            'total_qty' => 'required|numeric', 'total_discount' => 'nullable|numeric', 'total_tax' => 'required|numeric',
            'total_price' => 'required|numeric', 'grand_total' => 'required|numeric|min:0', 'order_tax' => 'nullable|numeric',
            'order_discount' => 'nullable|numeric|min:0', 'shipping_cost' => 'nullable|numeric|min:0',
        ], ['*.numeric' => 'The quotation has an invalid amount. Re-enter the item prices and try again.']);
    }
}
