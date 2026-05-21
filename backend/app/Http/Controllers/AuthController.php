<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = auth('api')->user();

        if (!$user->is_active) {
            auth('api')->logout();
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        if (!$user->tenant->is_active) {
            auth('api')->logout();
            return response()->json(['message' => 'Tenant account is suspended.'], 403);
        }

        return $this->respondWithToken($token);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out.']);
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user()->load('tenant:id,name,slug');
        return response()->json(['user' => $this->formatUser($user)]);
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();
        return $this->respondWithToken($token);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        $user = auth('api')->user()->load('tenant:id,name,slug');

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    private function formatUser($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
            ] : null,
        ];
    }
}
