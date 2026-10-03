<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepairController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function dashboard()
    {
        $totalJobs = DB::table('repair_services')->count();
        $inProgressJobs = DB::table('repair_services')->whereIn('status', ['Received', 'Diagnosing', 'Waiting Parts'])->count();
        $completedJobs = DB::table('repair_services')->where('status', 'Completed')->count();
        $totalRevenue = DB::table('repair_services')->whereIn('status', ['Completed', 'Delivered'])->sum('total_charge');

        $recentJobs = DB::table('repair_services')
            ->join('customers', 'repair_services.customer_id', '=', 'customers.id')
            ->select('repair_services.*', 'customers.name as customer_name', 'customers.phone_number')
            ->latest()
            ->take(10)
            ->get();

        return view('backend.repair.dashboard', compact('totalJobs', 'inProgressJobs', 'completedJobs', 'totalRevenue', 'recentJobs'));
    }

    public function services()
    {
        $services = DB::table('repair_services')
            ->join('customers', 'repair_services.customer_id', '=', 'customers.id')
            ->leftJoin('repair_device_types', 'repair_services.device_type_id', '=', 'repair_device_types.id')
            ->select('repair_services.*', 'customers.name as customer_name', 'customers.phone_number', 'repair_device_types.name as device_type_name')
            ->latest()
            ->paginate(15);

        $deviceTypes = DB::table('repair_device_types')->where('is_active', true)->get();
        $customers = Customer::where('is_active', true)->get();

        return view('backend.repair.services', compact('services', 'deviceTypes', 'customers'));
    }

    public function storeService(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            'device_type_id' => 'required',
            'device_brand' => 'required|string',
            'device_model' => 'required|string',
            'defects_reported' => 'required|string',
        ]);

        $jobSheet = 'REP-' . date('Ymd') . '-' . rand(100, 999);
        $spareCost = (float)($request->spare_parts_cost ?? 0);
        $labor = (float)($request->labor_charge ?? 0);
        $total = $spareCost + $labor;

        DB::transaction(function () use ($request, $jobSheet, $spareCost, $labor, $total) {
            DB::table('repair_services')->insert([
                'job_sheet_no' => $jobSheet,
                'customer_id' => $request->customer_id,
                'device_type_id' => $request->device_type_id,
                'device_brand' => $request->device_brand,
                'device_model' => $request->device_model,
                'imei_serial' => $request->imei_serial,
                'defects_reported' => $request->defects_reported,
                'received_date' => $request->received_date ?? now()->toDateString(),
                'expected_completion_date' => $request->expected_completion_date,
                'estimated_cost' => $request->estimated_cost ?? $total,
                'spare_parts_cost' => $spareCost,
                'labor_charge' => $labor,
                'total_charge' => $total,
                'status' => 'Received',
                'technician_notes' => $request->technician_notes,
                'created_by' => Auth::id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // If total charge is collected/billed, post to double-entry ledger
            if ($total > 0) {
                try {
                    $this->accountingService->postJournalEntry([
                        'entry_date' => now()->toDateString(),
                        'reference_type' => 'RepairService',
                        'reference_id' => $jobSheet,
                        'narration' => "Repair Service Job [{$jobSheet}]: {$request->device_brand} {$request->device_model}",
                        'items' => [
                            ['account_code' => '1030', 'debit' => $total, 'credit' => 0, 'memo' => 'Service job receivable'],
                            ['account_code' => '4020', 'debit' => 0, 'credit' => $total, 'memo' => 'Repair & Service Revenue']
                        ]
                    ]);
                } catch (\Exception $e) {}
            }
        });

        return redirect()->back()->with('message', "Service Job {$jobSheet} created successfully.");
    }

    public function deviceTypes()
    {
        $types = DB::table('repair_device_types')->get();
        return view('backend.repair.device_types', compact('types'));
    }

    public function storeDeviceType(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        DB::table('repair_device_types')->insert([
            'name' => $request->name,
            'description' => $request->description,
            'icon' => $request->icon ?? 'dripicons-device-mobile',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Device Type added successfully.');
    }
}
