<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * POST /api/v1/auth/login
     *
     * Accepts email+password or google_token.
     */
    public function login(Request $request): JsonResponse
    {
        // Google OAuth login
        if ($request->filled('google_token')) {
            $result = $this->authService->loginWithGoogle($request->input('google_token'));

            return response()->json(
                $result['success']
                    ? ['success' => true, 'token' => $result['token'], 'user' => $result['user']]
                    : ['success' => false, 'error' => $result['error']],
                $result['status'] ?? 200,
            );
        }

        // Email + password login
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->loginWithEmail(
            $request->input('email'),
            $request->input('password'),
        );

        return response()->json(
            $result['success']
                ? ['success' => true, 'token' => $result['token'], 'user' => $result['user']]
                : ['success' => false, 'error' => $result['error']],
            $result['status'] ?? 200,
        );
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->authService->getAuthenticatedUser($user);

        return response()->json(['success' => true, 'user' => $data]);
    }
}
