<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Accounting\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RepairApiController extends BaseApiController
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get list of repair jobs
     */
    public function jobs(Request $request): JsonResponse
    {
        $query = DB::table('repair_services')
            ->join('customers', 'repair_services.customer_id', '=', 'customers.id')
            ->select('repair_services.*', 'customers.name as customer_name', 'customers.phone_number');

        if ($request->has('status')) {
            $query->where('repair_services.status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('repair_services.job_sheet_no', 'like', "%{$search}%")
                    ->orWhere('repair_services.device_brand', 'like', "%{$search}%")
                    ->orWhere('repair_services.device_model', 'like', "%{$search}%")
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.phone_number', 'like', "%{$search}%");
            });
        }

        $jobs = $query->latest()->paginate($request->per_page ?? 20);

        return $this->sendResponse($jobs, 'Repair jobs retrieved successfully');
    }

    /**
     * Create new repair job sheet
     */
    public function storeJob(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required',
            'device_type_id' => 'required',
            'device_brand' => 'required|string',
            'device_model' => 'required|string',
            'defects_reported' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        $jobSheet = 'REP-' . date('Ymd') . '-' . rand(100, 999);
        $spareCost = (float)($request->spare_parts_cost ?? 0);
        $labor = (float)($request->labor_charge ?? 0);
        $total = $spareCost + $labor;

        $jobId = DB::table('repair_services')->insertGetId([
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

        return $this->sendResponse([
            'id' => $jobId,
            'job_sheet_no' => $jobSheet,
            'status' => 'Received',
        ], 'Job sheet created successfully', 201);
    }

    /**
     * Update job status and notes
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Received,Diagnosing,Waiting Parts,Completed,Delivered,Cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        $job = DB::table('repair_services')->where('id', $id)->first();
        if (!$job) {
            return $this->sendError('Job sheet not found', [], 404);
        }

        $updates = [
            'status' => $request->status,
            'updated_at' => now(),
        ];

        if ($request->has('technician_notes')) {
            $updates['technician_notes'] = $request->technician_notes;
        }
        if ($request->has('spare_parts_cost')) {
            $updates['spare_parts_cost'] = (float)$request->spare_parts_cost;
        }
        if ($request->has('labor_charge')) {
            $updates['labor_charge'] = (float)$request->labor_charge;
        }
        if ($request->has('spare_parts_cost') || $request->has('labor_charge')) {
            $updates['total_charge'] = (float)($updates['spare_parts_cost'] ?? $job->spare_parts_cost) + (float)($updates['labor_charge'] ?? $job->labor_charge);
        }

        DB::table('repair_services')->where('id', $id)->update($updates);

        // When marked completed, post to accounting ledger
        if ($request->status === 'Completed' && ($job->total_charge > 0 || ($updates['total_charge'] ?? 0) > 0)) {
            $finalTotal = $updates['total_charge'] ?? $job->total_charge;
            try {
                $this->accountingService->postJournalEntry([
                    'entry_date' => now()->toDateString(),
                    'reference_type' => 'RepairService',
                    'reference_id' => $job->job_sheet_no,
                    'narration' => "Repair Service Job Completed [{$job->job_sheet_no}]",
                    'items' => [
                        ['account_code' => '1030', 'debit' => $finalTotal, 'credit' => 0, 'memo' => 'Service AR'],
                        ['account_code' => '4020', 'debit' => 0, 'credit' => $finalTotal, 'memo' => 'Repair Revenue']
                    ]
                ]);
            } catch (\Exception $e) {}
        }

        return $this->sendResponse([
            'id' => $id,
            'job_sheet_no' => $job->job_sheet_no,
            'status' => $request->status,
        ], 'Job status updated successfully');
    }
}
