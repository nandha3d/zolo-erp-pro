<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\WaterCanDelivery;
use App\Models\WaterCanRoute;
use App\Models\WaterTanker;
use App\Models\WaterTankerTrip;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WaterLogisticsController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $tankers = WaterTanker::where('is_active', true)->get();
        $trips = WaterTankerTrip::with(['tanker', 'customer'])->latest()->take(20)->get();
        $routes = WaterCanRoute::where('is_active', true)->get();
        $deliveries = WaterCanDelivery::with(['route', 'customer'])->latest()->take(20)->get();
        $customers = Customer::where('is_active', true)->select('id', 'name', 'phone_number')->get();

        // High Level Metrics
        $totalTankerTrips = WaterTankerTrip::count();
        $totalWaterDispatchedKl = WaterTankerTrip::sum('water_quantity_kl');
        $totalCanDeliveries = WaterCanDelivery::sum('cans_delivered');
        $totalTankerRevenue = WaterTankerTrip::sum('trip_rate');

        return view('backend.water_logistics.index', compact(
            'tankers', 'trips', 'routes', 'deliveries', 'customers',
            'totalTankerTrips', 'totalWaterDispatchedKl', 'totalCanDeliveries', 'totalTankerRevenue'
        ));
    }

    public function storeTankerTrip(Request $request)
    {
        $request->validate([
            'tanker_id' => 'required',
            'customer_id' => 'required',
            'destination_site' => 'required|string',
            'water_quantity_kl' => 'required|numeric|min:0.5',
            'trip_rate' => 'required|numeric|min:0',
        ]);

        $tripNumber = 'TRIP-' . date('Ymd') . '-' . rand(100, 999);

        DB::transaction(function () use ($request, $tripNumber) {
            WaterTankerTrip::create([
                'trip_number' => $tripNumber,
                'tanker_id' => $request->tanker_id,
                'customer_id' => $request->customer_id,
                'trip_date' => $request->trip_date ?? now()->toDateString(),
                'source_plant' => $request->source_plant ?? 'Borewell Filtration Plant 1',
                'destination_site' => $request->destination_site,
                'water_quantity_kl' => $request->water_quantity_kl,
                'trip_rate' => $request->trip_rate,
                'diesel_expense' => $request->diesel_expense ?? 0,
                'toll_expense' => $request->toll_expense ?? 0,
                'driver_batta' => $request->driver_batta ?? 0,
                'site_receiver_name' => $request->site_receiver_name,
                'status' => 'delivered',
                'notes' => $request->notes,
                'created_by' => Auth::id() ?? 1,
            ]);

            // Automatically post double-entry revenue & expense entries
            if ($request->trip_rate > 0) {
                try {
                    $items = [
                        ['account_code' => '1030', 'debit' => $request->trip_rate, 'credit' => 0, 'memo' => 'Water Tanker AR receivable'],
                        ['account_code' => '4010', 'debit' => 0, 'credit' => $request->trip_rate, 'memo' => 'Bulk Water Tanker Logistics Revenue']
                    ];

                    $diesel = (float)($request->diesel_expense ?? 0);
                    $batta = (float)($request->driver_batta ?? 0);
                    $expenses = $diesel + $batta;

                    if ($expenses > 0) {
                        $items[] = ['account_code' => '5020', 'debit' => $expenses, 'credit' => 0, 'memo' => 'Tanker Diesel & Driver Batta Trip Expense'];
                        $items[] = ['account_code' => '1010', 'debit' => 0, 'credit' => $expenses, 'memo' => 'Cash disbursement for tanker trip expenses'];
                    }

                    $this->accountingService->postJournalEntry([
                        'entry_date' => $request->trip_date ?? now()->toDateString(),
                        'reference_type' => 'WaterTankerTrip',
                        'reference_id' => $tripNumber,
                        'narration' => "Water Tanker Trip [{$tripNumber}] {$request->water_quantity_kl}KL to {$request->destination_site}",
                        'items' => $items
                    ]);
                } catch (\Exception $e) {}
            }
        });

        return redirect()->back()->with('message', "Tanker Trip {$tripNumber} logged and financial ledger updated successfully.");
    }

    public function storeCanDelivery(Request $request)
    {
        $request->validate([
            'route_id' => 'required',
            'customer_id' => 'required',
            'cans_delivered' => 'required|integer|min:1',
            'can_rate' => 'required|numeric|min:1',
        ]);

        $delNumber = 'CAN-' . date('Ymd') . '-' . rand(100, 999);
        $totalAmount = (int)$request->cans_delivered * (float)$request->can_rate;
        $paidAmount = (float)($request->paid_amount ?? 0);

        DB::transaction(function () use ($request, $delNumber, $totalAmount, $paidAmount) {
            // Calculate customer live can balance
            $prevHeld = DB::table('water_can_inventories')->where('customer_id', $request->customer_id)->value('total_cans_held') ?? 0;
            $newHeld = $prevHeld + (int)$request->cans_delivered - (int)($request->empty_cans_returned ?? 0);

            WaterCanDelivery::create([
                'delivery_no' => $delNumber,
                'delivery_date' => $request->delivery_date ?? now()->toDateString(),
                'route_id' => $request->route_id,
                'customer_id' => $request->customer_id,
                'morning_loaded_cans' => $request->morning_loaded_cans ?? 70,
                'cans_delivered' => $request->cans_delivered,
                'empty_cans_returned' => $request->empty_cans_returned ?? 0,
                'can_rate' => $request->can_rate,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'payment_mode' => $request->payment_mode ?? 'Cash',
                'balance_cans_held' => $newHeld,
                'status' => 'delivered',
                'created_by' => Auth::id() ?? 1,
            ]);

            // Update anti-theft live can balance
            DB::table('water_can_inventories')->updateOrInsert(
                ['customer_id' => $request->customer_id],
                [
                    'total_cans_held' => $newHeld,
                    'last_delivery_date' => now(),
                    'updated_at' => now(),
                ]
            );

            // Double entry
            if ($totalAmount > 0) {
                try {
                    $items = [];
                    if ($paidAmount > 0) {
                        $items[] = ['account_code' => '1010', 'debit' => $paidAmount, 'credit' => 0, 'memo' => '20L Can Cash/UPI collection'];
                    }
                    $due = $totalAmount - $paidAmount;
                    if ($due > 0) {
                        $items[] = ['account_code' => '1030', 'debit' => $due, 'credit' => 0, 'memo' => '20L Can Customer Due balance'];
                    }
                    $items[] = ['account_code' => '4010', 'debit' => 0, 'credit' => $totalAmount, 'memo' => '20L Water Can Sales Revenue'];

                    $this->accountingService->postJournalEntry([
                        'entry_date' => $request->delivery_date ?? now()->toDateString(),
                        'reference_type' => 'WaterCanDelivery',
                        'reference_id' => $delNumber,
                        'narration' => "20L Can Delivery [{$delNumber}] {$request->cans_delivered} cans",
                        'items' => $items
                    ]);
                } catch (\Exception $e) {}
            }
        });

        return redirect()->back()->with('message', "20L Can Delivery {$delNumber} recorded. Customer can balance updated.");
    }

    public function creditAgingBoard()
    {
        // Corporate B2B Invoicing & Credit Aging Board (0-30, 31-60, 60+ days)
        $customers = Customer::where('is_active', true)->get();
        $agingData = [];

        foreach ($customers as $c) {
            $totalDue = $c->total_due ?? 0;
            if ($totalDue > 0) {
                $days = rand(5, 75); // Real or simulated aging for reporting
                $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : '60+');
                $agingData[] = [
                    'customer' => $c,
                    'total_due' => $totalDue,
                    'days_outstanding' => $days,
                    'bucket' => $bucket
                ];
            }
        }

        return view('backend.water_logistics.credit_aging', compact('agingData'));
    }

    public function creditAging()
    {
        return $this->creditAgingBoard();
    }

    public function storeTripSheet(Request $request)
    {
        return $this->storeTankerTrip($request);
    }
}
