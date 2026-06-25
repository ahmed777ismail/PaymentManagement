<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        $user = $registerUser->execute($request->validated());
        $token = auth('api')->login($user);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $this->tokenPayload($token),
            ],
        ], 201);
    }

    public function login(LoginRequest $request, LoginUserAction $loginUser): JsonResponse
    {
        $token = $loginUser->execute($request->validated());

        if ($token === null) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => new UserResource(auth('api')->user()),
                'token' => $this->tokenPayload($token),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user('api')),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    private function tokenPayload(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }
}
