<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportApiController extends BaseApiController
{
    public function sales(Request $request): JsonResponse
    {
        return $this->sendResponse([], 'Sales report retrieved successfully');
    }

    public function purchases(Request $request): JsonResponse
    {
        return $this->sendResponse([], 'Purchases report retrieved successfully');
    }

    public function stock(Request $request): JsonResponse
    {
        return $this->sendResponse([], 'Stock report retrieved successfully');
    }

    public function customerLedger(Request $request, int $id): JsonResponse
    {
        return $this->sendResponse([], 'Customer ledger retrieved successfully');
    }
}
