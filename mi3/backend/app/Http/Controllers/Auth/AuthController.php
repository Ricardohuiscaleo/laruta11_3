<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
            return $this->respondWithAuth($result);
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

        return $this->respondWithAuth($result);
    }

    /**
     * Build JSON response with httpOnly auth cookies.
     */
    private function respondWithAuth(array $result): JsonResponse
    {
        if (!$result['success']) {
            return response()->json(
                ['success' => false, 'error' => $result['error']],
                $result['status'] ?? 401,
            );
        }

        $maxAge = 30 * 24 * 60 * 60 / 60; // 30 days in minutes
        $role = $result['user']['is_admin'] ? 'admin' : 'worker';

        return response()->json([
            'success' => true,
            'user' => $result['user'],
        ])
            ->cookie('mi3_token', $result['token'], $maxAge, '/', '.laruta11.cl', true, true, false, 'Lax')
            ->cookie('mi3_role', $role, $maxAge, '/', '.laruta11.cl', true, false, false, 'Lax')
            ->cookie('mi3_user', json_encode($result['user']), $maxAge, '/', '.laruta11.cl', true, false, false, 'Lax');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true])
            ->cookie('mi3_token', '', -1, '/', '.laruta11.cl', true, true, false, 'Lax')
            ->cookie('mi3_role', '', -1, '/', '.laruta11.cl', true, false, false, 'Lax')
            ->cookie('mi3_user', '', -1, '/', '.laruta11.cl', true, false, false, 'Lax');
    }

    /**
     * GET /api/v1/auth/google/redirect
     *
     * Redirects the user to Google OAuth consent screen.
     */
    public function googleRedirect(): RedirectResponse
    {
        $clientId = env('RUTA11_GOOGLE_CLIENT_ID');
        $redirectUri = env('MI3_GOOGLE_REDIRECT_URI', 'https://api-mi3.laruta11.cl/api/v1/auth/google/callback');

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'access_type' => 'online',
        ]);

        return redirect()->away($url);
    }

    /**
     * GET /api/v1/auth/google/callback
     *
     * Handles the Google OAuth callback, exchanges code for token,
     * finds/creates user, sets httpOnly cookies, and redirects to frontend.
     */
    public function googleCallback(Request $request)
    {
        $frontendUrl = env('MI3_FRONTEND_URL', 'https://mi.laruta11.cl');

        $code = $request->query('code');
        if (!$code) {
            return redirect()->away($frontendUrl . '/login?error=no_code');
        }

        $result = $this->authService->loginWithGoogleCode($code);

        if (!$result['success']) {
            return redirect()->away($frontendUrl . '/login?error=' . urlencode($result['error']));
        }

        $token = $result['token'];
        $user = $result['user'];
        $role = $user['is_admin'] ? 'admin' : 'worker';
        $maxAge = 30 * 24 * 60 * 60; // 30 days
        $redirectTo = $user['is_admin'] ? '/admin' : '/dashboard';

        // Set httpOnly cookies and redirect to frontend
        return redirect()->away($frontendUrl . $redirectTo)
            ->cookie('mi3_token', $token, $maxAge / 60, '/', '.laruta11.cl', true, true, false, 'Lax')
            ->cookie('mi3_role', $role, $maxAge / 60, '/', '.laruta11.cl', true, false, false, 'Lax')
            ->cookie('mi3_user', json_encode($user), $maxAge / 60, '/', '.laruta11.cl', true, false, false, 'Lax');
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
