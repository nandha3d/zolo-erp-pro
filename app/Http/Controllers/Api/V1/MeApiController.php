<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MeApiController extends BaseApiController
{
    public function me(Request $request): JsonResponse
    {
        return app(AuthController::class)->me($request);
    }
}

