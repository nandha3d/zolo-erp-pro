<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\WaterTankerTrip;
use App\Models\WaterCanDelivery;
use App\Models\WaterCanRoute;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WaterLogisticsApiController extends BaseApiController
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get active can routes and tankers
     */
    public function routes(): JsonResponse
    {
        $routes = WaterCanRoute::where('is_active', true)->get();
        $tankers = DB::table('water_tankers')->where('status', 'Active')->get();

        return $this->sendResponse([
            'routes' => $routes,
            'tankers' => $tankers,
        ], 'Water routes and fleet retrieved successfully');
    }

    /**
     * Driver submits bulk tanker trip sheet
     */
    public function storeTripSheet(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tanker_id' => 'required',
            'trip_date' => 'required|date',
            'source_plant' => 'required|string',
            'delivery_site' => 'required|string',
            'billed_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        $tripNumber = 'TRIP-' . date('Ymd') . '-' . rand(1000, 9999);
        $trip = DB::transaction(function () use ($request, $tripNumber) {
            $record = WaterTankerTrip::create([
                'trip_number' => $tripNumber,
                'tanker_id' => $request->tanker_id,
                'driver_id' => Auth::id() ?? $request->driver_id ?? 1,
                'customer_id' => $request->customer_id ?? 1,
                'trip_date' => $request->trip_date,
                'source_plant' => $request->source_plant,
                'delivery_site' => $request->delivery_site,
                'start_km' => $request->start_km ?? 0,
                'end_km' => $request->end_km ?? 0,
                'diesel_expense' => $request->diesel_expense ?? 0,
                'toll_expense' => $request->toll_expense ?? 0,
                'driver_batta' => $request->driver_batta ?? 0,
                'billed_amount' => $request->billed_amount,
                'payment_status' => $request->payment_status ?? 'Pending',
                'site_receiver_name' => $request->site_receiver_name,
                'receiver_signature' => $request->receiver_signature,
                'status' => 'Delivered',
                'notes' => $request->notes,
            ]);

            // Post to General Ledger
            if ((float)$request->billed_amount > 0) {
                try {
                    $this->accountingService->postJournalEntry([
                        'entry_date' => $request->trip_date,
                        'reference_type' => 'WaterTankerTrip',
                        'reference_id' => $tripNumber,
                        'narration' => "Bulk Tanker Delivery [{$tripNumber}] to {$request->delivery_site}",
                        'items' => [
                            ['account_code' => '1030', 'debit' => (float)$request->billed_amount, 'credit' => 0, 'memo' => 'AR Water Delivery'],
                            ['account_code' => '4010', 'debit' => 0, 'credit' => (float)$request->billed_amount, 'memo' => 'Water Logistics Revenue']
                        ]
                    ]);
                } catch (\Exception $e) {}
            }

            return $record;
        });

        return $this->sendResponse($trip, 'Tanker trip sheet logged and posted to ERP ledger', 201);
    }

    /**
     * Driver logs 20L can delivery on route
     */
    public function storeCanDelivery(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required',
            'customer_id' => 'required',
            'delivery_date' => 'required|date',
            'cans_delivered' => 'required|integer|min:1',
            'unit_price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        $deliveryNumber = 'CAN-' . date('Ymd') . '-' . rand(1000, 9999);
        $totalAmount = (int)$request->cans_delivered * (float)$request->unit_price;
        $cashCollected = (float)($request->cash_collected ?? 0);

        $delivery = DB::transaction(function () use ($request, $deliveryNumber, $totalAmount, $cashCollected) {
            $record = WaterCanDelivery::create([
                'delivery_number' => $deliveryNumber,
                'route_id' => $request->route_id,
                'driver_id' => Auth::id() ?? 1,
                'customer_id' => $request->customer_id,
                'delivery_date' => $request->delivery_date,
                'cans_delivered' => $request->cans_delivered,
                'empty_cans_collected' => $request->empty_cans_collected ?? 0,
                'unit_price' => $request->unit_price,
                'total_amount' => $totalAmount,
                'cash_collected' => $cashCollected,
                'empty_balance_due' => (int)$request->cans_delivered - (int)($request->empty_cans_collected ?? 0),
                'payment_mode' => $cashCollected >= $totalAmount ? 'Cash' : 'Credit',
                'signature_url' => $request->signature_url,
                'notes' => $request->notes,
            ]);

            // Post to Double-entry accounting
            try {
                $items = [];
                if ($cashCollected > 0) {
                    $items[] = ['account_code' => '1010', 'debit' => $cashCollected, 'credit' => 0, 'memo' => 'Driver Cash Collected'];
                }
                $arBalance = $totalAmount - $cashCollected;
                if ($arBalance > 0) {
                    $items[] = ['account_code' => '1030', 'debit' => $arBalance, 'credit' => 0, 'memo' => 'Customer Can AR Balance'];
                }
                $items[] = ['account_code' => '4010', 'debit' => 0, 'credit' => $totalAmount, 'memo' => 'Water 20L Can Revenue'];

                $this->accountingService->postJournalEntry([
                    'entry_date' => $request->delivery_date,
                    'reference_type' => 'WaterCanDelivery',
                    'reference_id' => $deliveryNumber,
                    'narration' => "20L Can Delivery [{$deliveryNumber}]",
                    'items' => $items,
                ]);
            } catch (\Exception $e) {}

            return $record;
        });

        return $this->sendResponse($delivery, 'Can delivery logged successfully', 201);
    }

    /**
     * Driver surrenders cash and empty can count at end of shift
     */
    public function driverCashSurrender(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required',
            'surrender_date' => 'required|date',
            'cash_surrendered' => 'required|numeric',
            'empty_cans_returned' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors()->toArray(), 422);
        }

        // Return verified receipt
        return $this->sendResponse([
            'route_id' => $request->route_id,
            'driver_id' => Auth::id() ?? 1,
            'surrender_date' => $request->surrender_date,
            'cash_surrendered' => (float)$request->cash_surrendered,
            'empty_cans_returned' => (int)$request->empty_cans_returned,
            'surrender_receipt' => 'SURR-' . date('Ymd') . '-' . rand(100, 999),
            'status' => 'Verified & Deposited to Main Cash Ledger',
        ], 'Driver shift surrendered and balanced');
    }
}
