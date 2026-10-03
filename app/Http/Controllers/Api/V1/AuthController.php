<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseApiController
{
    /**
     * Authenticate user and issue Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required_without:email|string',
            'email' => 'required_without:name|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        $user = null;
        if ($request->filled('name')) {
            $user = User::where('name', $request->name)->where('is_active', true)->where('is_deleted', false)->first();
        } elseif ($request->filled('email')) {
            $user = User::where('email', $request->email)->where('is_active', true)->where('is_deleted', false)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->sendError('Unauthorized', ['credentials' => 'Invalid username/email or password.'], 401);
        }

        // Create personal access token with Sanctum
        $tokenName = $request->input('device_name', 'ERP-API-Client');
        $token = $user->createToken($tokenName)->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role_id' => $user->role_id,
                'warehouse_id' => $user->warehouse_id,
                'biller_id' => $user->biller_id,
            ]
        ], 'Authenticated successfully');
    }

    /**
     * Get profile of authenticated API user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return $this->sendResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'role_id' => $user->role_id,
            'warehouse_id' => $user->warehouse_id,
            'biller_id' => $user->biller_id,
        ], 'User profile retrieved');
    }

    /**
     * Revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->sendResponse([], 'Logged out successfully');
    }
}
