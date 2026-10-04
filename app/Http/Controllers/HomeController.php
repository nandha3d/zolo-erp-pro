<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Returns;
use App\Models\ReturnPurchase;
use App\Models\ProductPurchase;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payroll;
use App\Models\Quotation;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Product_Sale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RewardPointSetting;
use App\Models\Product_Warehouse;
use App\Models\Unit;
use Cache;
use DB;
use Auth;
use Printing;
use Rawilk\Printing\Contracts\Printer;
use Spatie\Permission\Models\Role;
use App\Traits\AutoUpdateTrait;
use App\Traits\ENVFilePutContent;
use Illuminate\Support\Facades\Artisan;
use Exception;
use ZipArchive;
use Illuminate\Support\Facades\File;

class HomeController extends Controller
{
    use AutoUpdateTrait, ENVFilePutContent;

    private $versionUpgradeInfo = [];

	public function __construct()
    {
        if(!config('database.connections.zoloerp_landlord')) {
            $this->versionUpgradeInfo = $this->isUpdateAvailable();
        }
	}

    public function home()
    {
        return view('backend.home');
    }

    public function index()
    {
        return redirect('dashboard');
    }

    public function addonList()
    {
        if(!config('database.connections.zoloerp_landlord')) {
            $role = Role::find(Auth::user()->role_id);
            if(!$role->hasPermissionTo('addons')) {
                return redirect('dashboard')->with('not_permitted', __('db.Sorry! You are not allowed to access this module'));
            }
        }

        $general_setting = DB::table('general_settings')->first();
        $activeModules = array_filter(explode(',', $general_setting->modules ?? ''));

        $addons = [
            [
                'key' => 'water_logistics',
                'name' => 'Water Logistics & Tanker Fleet',
                'category' => 'Industry Solution',
                'description' => 'Bulk water tanker fleet trip sheets (9KL, 16KL, 24KL), diesel & toll expense vouchers, site receiver signatures, and Tata Ace 20L Can distribution with live inventory balancing and corporate 15/30-day B2B credit aging.',
                'icon' => 'dripicons-drop',
                'color' => '#0284c7',
                'route' => 'water-logistics.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'cafe_bakery',
                'name' => 'Bakery & Cafe POS Operations',
                'category' => 'Hospitality & Retail',
                'description' => 'Fast touch POS counter terminal, kitchen raw materials consumption tracking (~10 ingredients), morning float and evening cash & UPI drawer reconciliation directly linked to ERP general ledger.',
                'icon' => 'dripicons-store',
                'color' => '#ea580c',
                'route' => 'cafe.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'repair',
                'name' => 'Repair & RMA Service Center',
                'category' => 'Services & Hardware',
                'description' => 'Complete job sheet workflow for smartphones, laptops, electronics and water dispensers. Defect diagnosis, spare parts inventory deduction, technician notes, and revenue billing.',
                'icon' => 'dripicons-wrench',
                'color' => '#6366f1',
                'route' => 'repair.dashboard',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'project_management',
                'name' => 'Project & Task Management',
                'category' => 'Productivity & Delivery',
                'description' => 'Project milestones, task delegation, team assignments, budget tracking, and progress metrics connected with customer accounts.',
                'icon' => 'dripicons-briefcase',
                'color' => '#059669',
                'route' => 'project-management.projects',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'manufacturing',
                'name' => 'Manufacturing & Production BOM',
                'category' => 'Industrial & Assembly',
                'description' => 'Bill of Materials (BOM recipes), batch production runs, automated raw material stock deduction and finished goods creation with overhead costing.',
                'icon' => 'dripicons-industrial',
                'color' => '#7c3aed',
                'route' => 'productions.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'installment_plans',
                'name' => 'Consumer Financing & EMI Plans',
                'category' => 'Financial Services',
                'description' => 'Customer installment plans, down payments, EMI schedule generator, auto-interest calculations, late payment penalty accruals, and ledger integration.',
                'icon' => 'dripicons-calendar',
                'color' => '#d97706',
                'route' => 'installmentplan.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'catalogue_qr',
                'name' => 'Dynamic QR Digital Catalogue',
                'category' => 'Digital Commerce',
                'description' => 'Instant contactless digital menus and interactive product catalogues accessible via table/counter QR codes, with real-time price and stock sync from ERP.',
                'icon' => 'dripicons-view-thumb',
                'color' => '#2563eb',
                'route' => 'qr.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'damage_stock',
                'name' => 'Damage Stock & Shrinkage Audit',
                'category' => 'Warehouse & Inventory',
                'description' => 'Log damaged, expired, or spoiled merchandise with reason codes and automated journal entries posting from Inventory Asset to Damage Loss (5010).',
                'icon' => 'dripicons-trash',
                'color' => '#dc2626',
                'route' => 'damage-stock.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'exchange',
                'name' => 'Product Return & Exchange Counter',
                'category' => 'Retail POS',
                'description' => 'Counter product returns and exchanges, calculate value differentials, generate return credit notes or collect balance payments directly linked to ERP sales ledger.',
                'icon' => 'dripicons-retweet',
                'color' => '#0d9488',
                'route' => 'exchange.index',
                'installed' => true,
                'is_core_connected' => true,
            ],
            [
                'key' => 'api',
                'name' => 'RESTful API Engine (v1)',
                'category' => 'Developer & Mobile',
                'description' => 'High-performance Sanctum authenticated RESTful API layer for mobile driver apps, external POS hardware, automated webhooks, and third-party integrations.',
                'icon' => 'dripicons-code',
                'color' => '#0891b2',
                'route' => 'api.v1.auth.me',
                'installed' => true,
                'is_core_connected' => true,
            ],
        ];

        return view('backend.addonlist', compact('addons', 'activeModules', 'general_setting'));
    }

    public function addonToggle(Request $request)
    {
        $key = $request->key;
        $general_setting = DB::table('general_settings')->first();
        $current = array_filter(explode(',', $general_setting->modules ?? ''));

        if (in_array($key, $current)) {
            $updated = array_values(array_diff($current, [$key]));
            $status = false;
            $msg = "Addon deactivated.";
        } else {
            $current[] = $key;
            $updated = array_values(array_unique($current));
            $status = true;
            $msg = "Addon activated successfully.";
        }

        DB::table('general_settings')->where('id', $general_setting->id)->update([
            'modules' => implode(',', $updated),
        ]);

        return response()->json(['success' => true, 'status' => $status, 'message' => $msg]);
    }

    public function dashboard()
    {

        //Write your translatable strings in JSON format below
        // $jsonData = '{
        //     "Available Quantity": "Available Quantity",
        //     "Hide Total Due": "Hide Total Due"
        // }';

        // $translations = json_decode($jsonData, true);

        // $languages = DB::table('languages')->pluck('language');

        // foreach ($languages as $locale) {
        //     foreach ($translations as $key => $value) {
        //         // Optional: Check if this translation already exists to avoid duplication
        //         $exists = DB::table('translations')
        //             ->where('locale', $locale)
        //             ->where('key', $key)
        //             ->exists();

        //         if (!$exists) {
        //             DB::table('translations')->insert([
        //                 'locale' => $locale,
        //                 'group' => 'db',
        //                 'key' => $key,
        //                 'value' => $value,
        //                 'created_at' => now(),
        //                 'updated_at' => now(),
        //             ]);
        //         }
        //     }
        // };

        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();

        if(in_array('restaurant',explode(',',cache()->get('general_setting')->modules))){
            if(Auth::user()->role_id > 2 && isset(Auth::user()->kitchen_id)){

                $result = (new \Modules\Restaurant\Http\Controllers\KitchenController)->dashboard();

                return $result;
            }
        }

        if(Auth::user()->role_id == 5) {
            $customer = Customer::select('id', 'points')->where('user_id', Auth::id())->first();
            $lims_sale_data = Sale::with('warehouse')
                                ->whereNull('deleted_at')
                                ->where('customer_id', $customer->id)
                                ->where(function($q) {
                                    $q->where('sale_type', '!=', 'opening balance')
                                    ->orWhereNull('sale_type');
                                })
                                ->orderBy('created_at', 'desc')
                                ->get();
            $lims_payment_data = DB::table('payments')
                           ->join('sales', 'payments.sale_id', '=', 'sales.id')
                           ->whereNull('sales.deleted_at')
                           ->where('customer_id', $customer->id)
                           ->select('payments.*', 'sales.reference_no as sale_reference')
                           ->orderBy('payments.created_at', 'desc')
                           ->get();
            $lims_quotation_data = Quotation::with('biller', 'customer', 'supplier', 'user')->orderBy('id', 'desc')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();

            $lims_return_data = Returns::with('warehouse', 'customer', 'biller')->where('customer_id', $customer->id)->orderBy('created_at', 'desc')->get();
            $lims_reward_point_setting_data = RewardPointSetting::select('per_point_amount')->latest()->first();
            return view('backend.customer_index', compact('customer', 'lims_sale_data', 'lims_payment_data', 'lims_quotation_data', 'lims_return_data', 'lims_reward_point_setting_data'));
        }

        $start_date = date("Y").'-'.date("m").'-'.'01';
        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")));
        $yearly_sale_amount = [];

        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $product_sale_data = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, product_sales.sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.return_qty) as return_qty, sum(product_sales.total) as sold_amount'))
                ->whereNull('sales.deleted_at')
                ->where('sales.user_id', Auth::id())
                ->whereDate('sales.created_at', '>=' , $start_date)
                ->whereDate('sales.created_at', '<=' , $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id')
                ->get();

            $product_cost = $this->calculateAverageCOGS($product_sale_data);

            $sale_query = Sale::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');

            $revenue = $sale_query->sum(DB::raw('(grand_total - shipping_cost) / exchange_rate'));

            $total_sale = $sale_query->sum(DB::raw('(grand_total - shipping_cost) / exchange_rate'));

            $invoice_due = $sale_query->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));

            $return = Returns::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total / exchange_rate'));
            
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total / exchange_rate'));
            
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');
            
            $income = Income::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->sum('amount');

            $purchase_query = Purchase::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');
            
            $purchase = $purchase_query->sum(DB::raw('grand_total / exchange_rate'));
            
            $purchase_due = $purchase_query->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));
            
            $revenue = $revenue - $return + $income;
            
            $profit = $revenue + $purchase_return - $product_cost - $expense;
        }
        else
        {
            $product_sale_data = Product_Sale::join('sales', 'product_sales.sale_id', '=', 'sales.id')
                ->select(DB::raw('
                    product_sales.product_id,
                    product_sales.product_batch_id,
                    product_sales.sale_unit_id,
                    sum(product_sales.qty) as sold_qty,
                    sum(product_sales.return_qty) as return_qty,
                    sum(product_sales.total) as sold_total
                '))
                ->whereNull('sales.deleted_at')
                ->whereDate('sales.created_at', '>=', $start_date)
                ->whereDate('sales.created_at', '<=', $end_date)
                ->groupBy('product_sales.product_id', 'product_sales.product_batch_id', 'product_sales.sale_unit_id')
                ->get();

            $product_cost = $this->calculateAverageCOGS($product_sale_data);

            $sale_query = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');
            
            $revenue = $sale_query->sum(DB::raw('(grand_total - shipping_cost) / exchange_rate'));
            
            $total_sale = $sale_query->sum(DB::raw('(grand_total - shipping_cost) / exchange_rate'));
            
            $invoice_due = Sale::whereDate('created_at', '>=' , $start_date)->where('user_id', Auth::id())->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at')->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));
            
            $expense = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            
            $income = Income::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
           
            $return = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total / exchange_rate'));
            
            $purchase_return = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total / exchange_rate'));

            $purchase_query = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');
            
            $purchase = $purchase_query->sum(DB::raw('grand_total / exchange_rate'));
            
            $purchase_due = $purchase_query->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));
            
            $revenue = $revenue - $return + $income;
            
            $profit = $revenue + $purchase_return - $product_cost - $expense;
        }

        //cash flow of last 6 months
        $start = strtotime(date('Y-m-01', strtotime('-6 month', strtotime(date('Y-m-d') ))));
        $end = strtotime(date('Y-m-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")))));

        while($start < $end)
        {
            $start_date = date("Y-m", $start).'-'.'01';
            $end_date = date("Y-m", $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));

            if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own') {
                $recieved_amount = DB::table('payments')->whereNotNull('sale_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum(DB::raw('amount / exchange_rate'));
                $sent_amount = DB::table('payments')->whereNotNull('purchase_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum(DB::raw('amount / exchange_rate'));
                $return_amount = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum(DB::raw('grand_total / exchange_rate'));
                $purchase_return_amount = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum(DB::raw('grand_total / exchange_rate'));
                $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
                $payroll_amount = Payroll::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->sum('amount');
            }
            else {
                $recieved_amount = DB::table('payments')->whereNotNull('sale_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount / exchange_rate'));
                $sent_amount = DB::table('payments')->whereNotNull('purchase_id')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('amount / exchange_rate'));
                $return_amount = Returns::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total / exchange_rate'));
                $purchase_return_amount = ReturnPurchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum(DB::raw('grand_total / exchange_rate'));
                $expense_amount = Expense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
                $payroll_amount = Payroll::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
            }
            $sent_amount = $sent_amount + $return_amount + $expense_amount + $payroll_amount;

            $payment_recieved[] = number_format((float)($recieved_amount + $purchase_return_amount), config('decimal'), '.', '');
            $payment_sent[] = number_format((float)$sent_amount, config('decimal'), '.', '');
            $month[] = date("F", strtotime($start_date));
            $start = strtotime("+1 month", $start);
        }
        // yearly report
        $start = strtotime(date("Y") .'-01-01');
        $end = strtotime(date("Y") .'-12-31');
        while($start < $end)
        {
            $start_date = date("Y").'-'.date('m', $start).'-'.'01';
            $end_date = date("Y").'-'.date('m', $start).'-'.date('t', mktime(0, 0, 0, date("m", $start), 1, date("Y", $start)));
            if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own') {
                $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->whereNull('deleted_at')->sum(DB::raw('grand_total / exchange_rate'));
                $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('user_id', Auth::id())->whereNull('deleted_at')->sum(DB::raw('grand_total / exchange_rate'));
            }
            else{
                $sale_amount = Sale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at')->sum(DB::raw('grand_total / exchange_rate'));
                $purchase_amount = Purchase::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at')->sum(DB::raw('grand_total / exchange_rate'));
            }
            $yearly_sale_amount[] = number_format((float)$sale_amount, config('decimal'), '.', '');
            $yearly_purchase_amount[] = number_format((float)$purchase_amount, config('decimal'), '.', '');
            $start = strtotime("+1 month", $start);
        }
        //making strict mode true for this query
        config()->set('database.connections.mysql.strict', true);
        DB::reconnect();
        // Additional Business Insights & Liquidity Metrics
        $liquid_balance = Account::where('is_active', true)->sum('total_balance') ?? 0;
        $stock_valuation = DB::table('product_warehouse')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->sum(DB::raw('product_warehouse.qty * products.cost')) ?? 0;
        $total_products = Product::where('is_active', true)->count();
        $total_customers = Customer::where('is_active', true)->count();
        $total_suppliers = \App\Models\Supplier::where('is_active', true)->count();
        $low_stock_count = DB::table('product_warehouse')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->whereRaw('product_warehouse.qty <= products.alert_quantity')
            ->count();
        $low_stock_products = DB::table('product_warehouse')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->select('products.name', 'products.code', 'product_warehouse.qty', 'products.alert_quantity', 'warehouses.name as warehouse_name')
            ->whereRaw('product_warehouse.qty <= products.alert_quantity')
            ->limit(5)
            ->get();

        $currency_model = \App\Models\Currency::find(cache()->get('general_setting')->currency ?? 1);
        $currency_symbol = $currency_model ? ($currency_model->symbol ?: ($currency_model->code == 'INR' ? '₹' : ($currency_model->code ?: '$'))) : '$';

        $addon_stats = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('water_tankers')) {
            $addon_stats['water_tankers'] = DB::table('water_tankers')->where('is_active', 1)->count();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('water_tanker_trips')) {
            $addon_stats['water_trips_today'] = DB::table('water_tanker_trips')->whereDate('trip_date', date('Y-m-d'))->count();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('water_can_deliveries')) {
            $addon_stats['cans_delivered_today'] = DB::table('water_can_deliveries')->whereDate('delivery_date', date('Y-m-d'))->sum('cans_delivered') ?? 0;
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('cafe_cash_drawers')) {
            $addon_stats['open_drawers'] = DB::table('cafe_cash_drawers')->where('status', 'open')->count();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('repair_services')) {
            $addon_stats['repair_services_count'] = DB::table('repair_services')->count();
        }

        $versionUpgradeData = (!config('database.connections.zoloerp_landlord') && Auth::user()->role_id <= 2) ? ($this->versionUpgradeInfo ?? []) : [];

        return view('backend.index', compact(
            'revenue', 'purchase_due', 'total_sale', 'invoice_due', 'purchase', 'expense',
            'return', 'purchase_return', 'profit', 'payment_recieved', 'payment_sent',
            'month', 'yearly_sale_amount', 'yearly_purchase_amount', 'versionUpgradeData',
            'liquid_balance', 'stock_valuation', 'total_products', 'total_customers',
            'total_suppliers', 'low_stock_count', 'low_stock_products', 'currency_symbol',
            'addon_stats'
        ));
    }

    public function newVersionReleasePage()
    {
		// Below line is deprecated, this code is needed for the client version 1.5.1 and below
        $this->dataWriteInENVFile('APP_ENV', 'local');
		// Below line is deprecated, this code is needed for the client version 1.5.1 and below

        $versionUpgradeData = [];
        $versionUpgradeData = $this->versionUpgradeInfo;
        return view('version_upgrade.index', compact('versionUpgradeData'));
    }

    public function versionUpgrade(Request $request) {
        $versionUpgradeData = [];
        $versionUpgradeData = $this->versionUpgradeInfo;
        $version_upgrade_file_url = $this->versionUpgradeFileUrl($request->purchasecode);

        if (!$version_upgrade_file_url) {
            return redirect()->back()->with('not_permitted', 'Wrong Purchase Code !');
        }

        try {
            //Check file is exist
            $header_array = @get_headers($version_upgrade_file_url);
            if(!strpos($header_array[0], '200')) {
                throw new Exception("Something wrong. Please contact with support team.");
            }

            $this->fileTransferProcess($version_upgrade_file_url);

            if ($versionUpgradeData['latest_version_db_migrate_enable']==true){
                Artisan::call('migrate');
                Artisan::call('db:seed');
            }

            Artisan::call('optimize:clear');

            $this->dataWriteInENVFile('VERSION', $versionUpgradeData['demo_version']);

            return redirect()->back()->with('message', 'Version Upgraded Successfully !!!');
        }
        catch(Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function fileTransferProcess($version_upgrade_file_url)
    {
        $remote_file_name = pathinfo($version_upgrade_file_url)['basename'];
        $local_file = base_path('/'.$remote_file_name);
        $copy = copy($version_upgrade_file_url, $local_file);
        if ($copy) {
            // ****** Unzip ********
            $zip = new ZipArchive;
            $file = base_path($remote_file_name);
            $res = $zip->open($file);
            if ($res === TRUE) {
                $zip->extractTo(base_path());
                $zip->close();

                // ****** Delete Zip File ******
                File::delete(base_path($remote_file_name));
            }
        }
    }

    public function yearlyBestSellingPrice()
    {
        //making strict mode false for this query
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();
        $yearly_best_selling_price = Product_Sale::join('products', 'products.id', '=', 'product_sales.product_id')
        ->join('sales', 'sales.id', '=', 'product_sales.sale_id')
        ->whereNull('sales.deleted_at')
        ->select(DB::raw('products.name as product_name, products.code as product_code, products.image as product_images'),'sales.exchange_rate', DB::raw('sum(product_sales.total / sales.exchange_rate) as total_price'))
        ->whereYear('product_sales.created_at', date("Y")) 
        ->groupBy('products.code')
        ->orderBy('total_price', 'desc')
        ->take(5)
        ->get();

        return response()->json($yearly_best_selling_price);
    }

    public function yearlyBestSellingQty()
    {
        //making strict mode false for this query
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();
        $yearly_best_selling_qty = Product_Sale::join('products', 'products.id', '=', 'product_sales.product_id')
        ->select(DB::raw('products.name as product_name, products.code as product_code, products.image as product_images, sum(product_sales.qty) as sold_qty'))
        ->whereYear('product_sales.created_at', date("Y")) 
        ->groupBy('products.code')
        ->orderBy('sold_qty', 'desc')
        ->take(5)
        ->get();

        return response()->json($yearly_best_selling_qty);
    }

    public function monthlyBestSellingQty()
    {
        //making strict mode false for this query
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();

        $best_selling_qty = Product_Sale::join('products', 'products.id', '=', 'product_sales.product_id')
        ->select(DB::raw('products.name as product_name, products.code as product_code, products.image as product_images, sum(product_sales.qty) as sold_qty'))
        ->whereYear('product_sales.created_at', date("Y"))
        ->whereMonth('product_sales.created_at', date("m"))
        ->groupBy('products.code')
        ->orderBy('sold_qty', 'desc')
        ->take(5)
        ->get();

        return response()->json($best_selling_qty);
    }

    public function recentSale()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_sale = Sale::join('customers', 'customers.id', '=', 'sales.customer_id')
                            ->select('sales.id','sales.reference_no','sales.sale_status','sales.created_at','sales.grand_total','sales.exchange_rate','sales.user_id','customers.name')
                            ->orderBy('id', 'desc')
                            ->whereNull('sales.deleted_at')
                            ->where('sales.user_id', Auth::id())
                            ->where(function($q) {
                                $q->where('sales.sale_type', '!=', 'opening balance')
                                ->orWhereNull('sales.sale_type');
                            })
                            ->take(5)->get();
            return response()->json($recent_sale);
        }
        else
        {
            $recent_sale = Sale::join('customers', 'customers.id', '=', 'sales.customer_id')
                            ->select('sales.id','sales.reference_no','sales.sale_status','sales.created_at','sales.grand_total','sales.exchange_rate','customers.name')->orderBy('id', 'desc')
                            ->whereNull('sales.deleted_at')
                            ->where(function($q) {
                                $q->where('sales.sale_type', '!=', 'opening balance')
                                ->orWhereNull('sales.sale_type');
                            })
                            ->take(5)->get();
            return response()->json($recent_sale);
        }
    }

    public function recentPurchase()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_purchase = Purchase::leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')->select('purchases.id','purchases.reference_no','purchases.status','purchases.created_at','purchases.grand_total','purchases.exchange_rate','purchases.user_id','suppliers.name')->orderBy('id', 'desc')->where('purchases.user_id', Auth::id())->whereNull('purchases.deleted_at')->whereNULL('purchases.purchase_type')->take(5)->get();
            return response()->json($recent_purchase);
        }
        else
        {
            $recent_purchase = Purchase::leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')->select('purchases.id','purchases.reference_no','purchases.status','purchases.created_at','purchases.grand_total','purchases.exchange_rate','suppliers.name')->orderBy('id', 'desc')->whereNull('purchases.deleted_at')->whereNULL('purchases.purchase_type')->take(5)->get();
            return response()->json($recent_purchase);
        }
    }

    public function recentQuotation()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_quotation = Quotation::join('customers', 'customers.id', '=', 'quotations.customer_id')->select('quotations.id','quotations.reference_no','quotations.quotation_status','quotations.created_at','quotations.grand_total','quotations.user_id','customers.name')->orderBy('id', 'desc')->where('quotations.user_id', Auth::id())->take(5)->get();
            return response()->json($recent_quotation);
        }
        else
        {
            $recent_quotation = Quotation::join('customers', 'customers.id', '=', 'quotations.customer_id')->select('quotations.id','quotations.reference_no','quotations.quotation_status','quotations.created_at','quotations.grand_total','customers.name')->orderBy('id', 'desc')->take(5)->get();
            return response()->json($recent_quotation);
        }
    }

    public function recentPayment()
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own')
        {
            $recent_payment = Payment::select('id','payment_reference','amount','exchange_rate','paying_method','created_at','user_id')->orderBy('id', 'desc')->where('user_id', Auth::id())->take(5)->get();
            return response()->json($recent_payment);
        }
        else
        {
            $recent_payment = Payment::select('id','payment_reference','amount','exchange_rate','paying_method','created_at')->orderBy('id', 'desc')->take(5)->get();
            return response()->json($recent_payment);
        }
    }

    public function dashboardFilter($start_date, $end_date, $warehouse_id)
    {
        if(Auth::user()->role_id > 2 && cache()->get('general_setting')->staff_access == 'own') {
            config()->set('database.connections.mysql.strict', false);
            DB::reconnect();

            $q = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, product_sales.sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.return_qty) as return_qty, sum(product_sales.total) as sold_amount'))
                ->whereNull('sales.deleted_at')
                ->where('sales.user_id', Auth::id())
                ->whereDate('sales.created_at', '>=' , $start_date)
                ->whereDate('sales.created_at', '<=' , $end_date);

            if($warehouse_id != 0) {
                $q->where('sales.warehouse_id',$warehouse_id);
            }

            $product_sale_data = $q->groupBy('product_sales.product_id', 'product_sales.product_batch_id')->get();

            config()->set('database.connections.mysql.strict', true);
            DB::reconnect();

            $product_cost = $this->calculateAverageCOGS($product_sale_data);

            $total_sale_q = Sale::where('user_id', Auth::id())->whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');

            $purchase_q = Purchase::where('user_id', Auth::id())->whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');

            $return_q = Returns::where('user_id', Auth::id())->whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date);

            $purchase_return_q = ReturnPurchase::where('user_id', Auth::id())->whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date);

            if($warehouse_id != 0) {
                $total_sale_q->where('warehouse_id',$warehouse_id);
                $purchase_q->where('warehouse_id',$warehouse_id);
                $return_q->where('warehouse_id',$warehouse_id);
                $purchase_return_q->where('warehouse_id',$warehouse_id);
            }

            $total_sale = $total_sale_q->sum(DB::raw('(grand_total - shipping_cost) / exchange_rate'));
            $purchase = $purchase_q->sum(DB::raw('grand_total / exchange_rate'));
            $return = $return_q->sum(DB::raw('grand_total / exchange_rate'));
            $purchase_return = $purchase_return_q->sum(DB::raw('grand_total / exchange_rate'));

            $invoice_due = Sale::whereDate('created_at', '>=' , $start_date)
                ->whereNull('deleted_at')
                ->whereDate('created_at', '<=' , $end_date)
                ->where('user_id', Auth::id())
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));

            $purchase_due = Purchase::whereDate('created_at', '>=' , $start_date)
                ->whereNull('deleted_at')
                ->whereDate('created_at', '<=' , $end_date)
                ->where('user_id', Auth::id())
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));

            $expense = Expense::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)
                ->where('user_id', Auth::id())
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum('amount');


            $income = Income::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)
                ->where('user_id', Auth::id())
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum('amount');

            $revenue = $total_sale - $return + $income;
            $profit = $revenue + $purchase_return - $product_cost - $expense;

        } else {
            config()->set('database.connections.mysql.strict', false);
            DB::reconnect();

            $q = Sale::join('product_sales', 'sales.id','=', 'product_sales.sale_id')
                ->select(DB::raw('product_sales.product_id, product_sales.product_batch_id, product_sales.sale_unit_id, sum(product_sales.qty) as sold_qty, sum(product_sales.return_qty) as return_qty, sum(product_sales.total) as sold_amount'))
                ->whereNull('sales.deleted_at')
                ->whereDate('sales.created_at', '>=' , $start_date)
                ->whereDate('sales.created_at', '<=' , $end_date);

            if($warehouse_id != 0) {
                $q->where('sales.warehouse_id',$warehouse_id);
            }

            $product_sale_data = $q->groupBy('product_sales.product_id', 'product_sales.product_batch_id')->get();

            config()->set('database.connections.mysql.strict', true);
            DB::reconnect();

            $product_cost = $this->calculateAverageCOGS($product_sale_data);

            $total_sale_q = Sale::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');

            $purchase_q = Purchase::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)->whereNull('deleted_at');

            $return_q = Returns::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date);

            $purchase_return_q = ReturnPurchase::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date);

            if($warehouse_id != 0) {
                $total_sale_q->where('warehouse_id',$warehouse_id);
                $purchase_q->where('warehouse_id',$warehouse_id);
                $return_q->where('warehouse_id',$warehouse_id);
                $purchase_return_q->where('warehouse_id',$warehouse_id);
            }

            $total_sale = $total_sale_q->sum(DB::raw('(grand_total - shipping_cost) / exchange_rate'));
            $purchase = $purchase_q->sum(DB::raw('grand_total / exchange_rate'));
            $return = $return_q->sum(DB::raw('grand_total / exchange_rate'));
            $purchase_return = $purchase_return_q->sum(DB::raw('grand_total / exchange_rate'));

            $invoice_due = Sale::whereDate('created_at', '>=' , $start_date)
                ->whereNull('deleted_at')
                ->whereDate('created_at', '<=' , $end_date)
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));

            $purchase_due = Purchase::whereDate('created_at', '>=' , $start_date)
                ->whereNull('deleted_at')
                ->whereDate('created_at', '<=' , $end_date)
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum(DB::raw('(grand_total - paid_amount) / exchange_rate'));

            $expense = Expense::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum('amount');


            $income = Income::whereDate('created_at', '>=' , $start_date)
                ->whereDate('created_at', '<=' , $end_date)
                ->when($warehouse_id != 0, function ($q) use ($warehouse_id) {
                    $q->where('warehouse_id', $warehouse_id);
                })
                ->sum('amount');

            $revenue = $total_sale - $return + $income;
            $profit = $revenue + $purchase_return - $product_cost - $expense;
        }
        $data[0] = $revenue;
        $data[1] = $return;
        $data[2] = $profit;
        $data[3] = $purchase_return;
        $data[4] = $total_sale;
        $data[5] = $invoice_due ?? 0;
        $data[6] = $purchase - $purchase_return;
        $data[7] = $purchase_due ?? 0;
        $data[8] = $expense ?? 0;
        return $data;
    }

    public function calculateAverageCOGS($product_sale_data)
    {
        // Initialize total product cost
        $product_cost = 0;

        // Loop through each sold product entry
        foreach ($product_sale_data as $key => $product_sale) {

            // Fetch product details for the sold product
            $product_data = Product::select('type', 'product_list', 'variant_list', 'qty_list')
                ->find($product_sale->product_id);

            // If product is a combo (bundle of multiple products)
            if($product_data && $product_data->type == 'combo') {
                $product_list = explode(",", $product_data->product_list);

                // Handle variants if present
                if($product_data->variant_list)
                    $variant_list = explode(",", $product_data->variant_list);
                else
                    $variant_list = [];

                // Quantities of each product in the combo
                $qty_list = explode(",", $product_data->qty_list);

                // Loop through each product inside the combo
                foreach ($product_list as $index => $product_id) {

                    // If product has variants, fetch purchase data accordingly
                    if(count($variant_list) && $variant_list[$index]) {
                        $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.purchase_id', '=', 'purchases.id')
                        ->where([
                            ['product_purchases.product_id', $product_id],
                            ['product_purchases.variant_id', $variant_list[$index] ]
                        ])
                        ->whereNull('purchases.deleted_at')
                        ->select('purchases.exchange_rate', 'product_purchases.recieved', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                        ->get();
                    }
                    else {
                        // Fetch all purchases for this product
                        $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.purchase_id', '=', 'purchases.id')
                        ->where('product_purchases.product_id', $product_id)
                        ->whereNull('purchases.deleted_at')
                        ->select('purchases.exchange_rate', 'product_purchases.recieved', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                        ->get();
                    }

                    $total_received_qty = 0;
                    $total_purchased_amount = 0;

                    // Calculate sold quantity of this sub-product in the combo
                    $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty) * $qty_list[$index];

                    // Fetch all unit conversion data
                    $units = Unit::select('id', 'operator', 'operation_value')->get();

                    // Loop through all purchases for this product
                    foreach ($product_purchase_data as $key => $product_purchase) {
                        $purchase_unit_data = $units->where('id',$product_purchase->purchase_unit_id)->first();

                        // Convert received quantity into base unit
                        if($purchase_unit_data->operator == '*')
                            $total_received_qty += $product_purchase->recieved * $purchase_unit_data->operation_value;
                        else
                            $total_received_qty += $product_purchase->recieved / $purchase_unit_data->operation_value;

                        // Accumulate purchase cost
                        if(isset($product_purchase->exchange_rate))
                            $total_purchased_amount += $product_purchase->total/$product_purchase->exchange_rate;
                        else
                            $total_purchased_amount += $product_purchase->total;
                    }

                    // Compute average cost (purchase amount / total received qty)
                    if($total_received_qty)
                        $averageCost = $total_purchased_amount / $total_received_qty;
                    else
                        $averageCost = 0;

                    // Add to total product cost
                    $product_cost += $sold_qty * $averageCost;
                }
            }
            else {
                // For normal products (not combo)

                // Fetch purchase data depending on batch or variant
                if($product_sale->product_batch_id) {
                    $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.purchase_id', '=', 'purchases.id')
                        ->where([
                        ['product_purchases.product_id', $product_sale->product_id],
                        ['product_purchases.product_batch_id', $product_sale->product_batch_id]
                    ])
                    ->whereNull('purchases.deleted_at')
                    ->select('purchases.exchange_rate', 'product_purchases.recieved', 'product_purchases.purchase_unit_id', 'product_purchases.tax', 'product_purchases.total')
                    ->get();
                }
                elseif($product_sale->variant_id) {
                    $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.purchase_id', '=', 'purchases.id')
                        ->where([
                        ['product_purchases.product_id', $product_sale->product_id],
                        ['product_purchases.variant_id', $product_sale->variant_id]
                    ])
                    ->whereNull('purchases.deleted_at')
                    ->select('purchases.exchange_rate', 'product_purchases.recieved', 'product_purchases.purchase_unit_id', 'product_purchases.tax', 'product_purchases.total')
                    ->get();
                }
                else {
                    $product_purchase_data = ProductPurchase::join('purchases', 'product_purchases.purchase_id', '=', 'purchases.id')
                        ->where('product_id', $product_sale->product_id)
                        ->whereNull('purchases.deleted_at')
                    ->select('purchases.exchange_rate', 'product_purchases.recieved', 'product_purchases.purchase_unit_id', 'product_purchases.tax', 'product_purchases.total')
                    ->get();
                }

                $total_received_qty = 0;
                $total_purchased_amount = 0;

                // Fetch all unit conversion data
                $units = Unit::select('id', 'operator', 'operation_value')->get();

                // Convert sold quantity into base unit if sale unit is defined
                if($product_sale->sale_unit_id) {
                    $sale_unit_data = $units->where('id', $product_sale->sale_unit_id)->first();
                    if($sale_unit_data->operator == '*')
                        $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty) * $sale_unit_data->operation_value;
                    else
                        $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty) / $sale_unit_data->operation_value;
                }
                else {
                    // If no unit conversion, just take raw sold qty
                    $sold_qty = ($product_sale->sold_qty - $product_sale->return_qty);
                }

                // Loop through purchases to accumulate received qty and purchase amount
                foreach ($product_purchase_data as $key => $product_purchase) {
                    $purchase_unit_data = $units->where('id', $product_purchase->purchase_unit_id)->first();
                    if($purchase_unit_data) {
                        if($purchase_unit_data->operator == '*')
                            $total_received_qty += $product_purchase->recieved * $purchase_unit_data->operation_value;
                        else
                            $total_received_qty += $product_purchase->recieved / $purchase_unit_data->operation_value;

                        if(isset($product_purchase->exchange_rate))
                            $total_purchased_amount += $product_purchase->total/$product_purchase->exchange_rate;
                        else
                            $total_purchased_amount += $product_purchase->total;
                    }
                }

                // Calculate average cost for the product
                if($total_received_qty)
                    $averageCost = $total_purchased_amount / $total_received_qty;
                else
                    $averageCost = 0;

                // Add to total product cost
                $product_cost += $sold_qty * $averageCost;
            }
        }

        // Return the total calculated product cost (COGS)
        return $product_cost;
    }


    public function myTransaction($year, $month)
    {
        $start = 1;
        $number_of_day = date('t', mktime(0, 0, 0, $month, 1, $year));
        while($start <= $number_of_day)
        {
            if($start < 10)
                $date = $year.'-'.$month.'-0'.$start;
            else
                $date = $year.'-'.$month.'-'.$start;
            $sale_generated[$start] = Sale::whereDate('created_at', $date)->where('user_id', Auth::id())->whereNull('deleted_at')->count();
            $sale_grand_total[$start] = Sale::whereDate('created_at', $date)->where('user_id', Auth::id())->whereNull('deleted_at')->sum(DB::raw('grand_total / exchange_rate'));
            $purchase_generated[$start] = Purchase::whereDate('created_at', $date)->where('user_id', Auth::id())->whereNull('deleted_at')->count();
            $purchase_grand_total[$start] = Purchase::whereDate('created_at', $date)->where('user_id', Auth::id())->whereNull('deleted_at')->sum(DB::raw('grand_total / exchange_rate'));
            $quotation_generated[$start] = Quotation::whereDate('created_at', $date)->where('user_id', Auth::id())->count();
            $quotation_grand_total[$start] = Quotation::whereDate('created_at', $date)->where('user_id', Auth::id())->sum('grand_total');
            $start++;
        }
        $start_day = date('w', strtotime($year.'-'.$month.'-01')) + 1;
        $prev_year = date('Y', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
        $prev_month = date('m', strtotime('-1 month', strtotime($year.'-'.$month.'-01')));
        $next_year = date('Y', strtotime('+1 month', strtotime($year.'-'.$month.'-01')));
        $next_month = date('m', strtotime('+1 month', strtotime($year.'-'.$month.'-01')));
        return view('backend.user.my_transaction', compact('start_day', 'year', 'month', 'number_of_day', 'prev_year', 'prev_month', 'next_year', 'next_month', 'sale_generated', 'sale_grand_total','purchase_generated', 'purchase_grand_total','quotation_generated', 'quotation_grand_total'));
    }

    public function switchTheme($theme)
    {
        setcookie('theme', $theme, time() + (86400 * 365), "/");
    }

    public function sessionRenew(Request $request)
    {
        return response()->json('success');
    }
}
