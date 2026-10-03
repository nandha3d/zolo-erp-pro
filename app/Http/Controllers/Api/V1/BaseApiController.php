<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\Platform\CompanyContext;

class BaseApiController extends Controller
{
    protected function companyContext(Request $request): CompanyContext
    {
        $context = $request->attributes->get(CompanyContext::class);
        if (!$context instanceof CompanyContext) {
            throw new AuthorizationException('Authorized company context required.');
        }

        return $context;
    }

    /**
     * Standard success JSON response.
     */
    public function sendResponse(mixed $result, string $message = 'Success', int $code = 200, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data'    => $result,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Standard error JSON response.
     */
    public function sendError(string $error, mixed $errorMessages = [], int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
