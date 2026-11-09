<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HrmApiController extends BaseApiController
{
    public function checkIn(Request $request): JsonResponse
    {
        return $this->sendResponse(['status' => 'checked_in', 'time' => now()->toIso8601String()], 'Check-in recorded successfully');
    }

    public function checkOut(Request $request): JsonResponse
    {
        return $this->sendResponse(['status' => 'checked_out', 'time' => now()->toIso8601String()], 'Check-out recorded successfully');
    }

    public function leaves(Request $request): JsonResponse
    {
        return $this->sendResponse([], 'Leaves retrieved successfully');
    }

    public function applyLeave(Request $request): JsonResponse
    {
        return $this->sendResponse(['status' => 'pending'], 'Leave application submitted successfully', 201);
    }

    public function leaveTypes(Request $request): JsonResponse
    {
        return $this->sendResponse([], 'Leave types retrieved successfully');
    }
}
