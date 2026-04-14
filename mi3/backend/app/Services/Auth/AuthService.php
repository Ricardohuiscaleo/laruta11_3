<?php

namespace App\Services\Auth;

use App\Models\Personal;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Login with email and password.
     *
     * Supports both hashed passwords (password_verify) and legacy session_token.
     */
    public function loginWithEmail(string $email, string $password): array
    {
        $user = Usuario::where('email', $email)->first();

        if (!$user) {
            return ['success' => false, 'error' => 'Credenciales inválidas', 'status' => 401];
        }

        // Try hashed password first, then fall back to session_token comparison
        $passwordValid = false;

        if (!empty($user->password)) {
            $passwordValid = Hash::check($password, $user->password);
        }

        if (!$passwordValid && !empty($user->session_token)) {
            $passwordValid = $user->session_token === $password;
        }

        if (!$passwordValid) {
            return ['success' => false, 'error' => 'Credenciales inválidas', 'status' => 401];
        }

        return $this->authenticateUser($user);
    }

    /**
     * Login with Google OAuth token.
     */
    public function loginWithGoogle(string $googleToken): array
    {
        // Verify Google token via Google API
        $googleUser = $this->verifyGoogleToken($googleToken);

        if (!$googleUser) {
            return ['success' => false, 'error' => 'Token de Google inválido', 'status' => 401];
        }

        $user = Usuario::where('email', $googleUser['email'])->first();

        if (!$user) {
            return [
                'success' => false,
                'error' => 'No estás registrado como trabajador activo de La Ruta 11',
                'status' => 403,
            ];
        }

        return $this->authenticateUser($user);
    }

    /**
     * Get authenticated user data with personal info.
     */
    public function getAuthenticatedUser(Usuario $user): array
    {
        $personal = Personal::where('user_id', $user->id)
            ->where('activo', 1)
            ->first();

        $data = [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'email' => $user->email,
        ];

        if ($personal) {
            $data['personal_id'] = $personal->id;
            $data['rol'] = $personal->rol;
            $data['is_admin'] = $personal->isAdmin();
        }

        return $data;
    }

    /**
     * Authenticate a user: verify personal link, create Sanctum token.
     */
    private function authenticateUser(Usuario $user): array
    {
        $personal = Personal::where('user_id', $user->id)
            ->where('activo', 1)
            ->first();

        if (!$personal) {
            return [
                'success' => false,
                'error' => 'No estás registrado como trabajador activo de La Ruta 11',
                'status' => 403,
            ];
        }

        // Clean up old tokens (>30 days) but keep recent ones for other devices
        $user->tokens()->where('created_at', '<', now()->subDays(30))->delete();
        $token = $user->createToken('mi3-auth')->plainTextToken;

        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'personal_id' => $personal->id,
                'nombre' => $user->nombre ?? $personal->nombre,
                'email' => $user->email,
                'rol' => $personal->rol,
                'is_admin' => $personal->isAdmin(),
            ],
        ];
    }

    /**
     * Verify a Google OAuth ID token.
     */
    private function verifyGoogleToken(string $token): ?array
    {
        try {
            $response = file_get_contents(
                'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token)
            );

            if ($response === false) {
                return null;
            }

            $payload = json_decode($response, true);

            if (!isset($payload['email'])) {
                return null;
            }

            return [
                'email' => $payload['email'],
                'name' => $payload['name'] ?? null,
                'picture' => $payload['picture'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Login with Google OAuth authorization code (server-side flow).
     * Exchanges code for access token, gets user info, finds/creates user.
     */
    public function loginWithGoogleCode(string $code): array
    {
        $clientId = env('RUTA11_GOOGLE_CLIENT_ID');
        $clientSecret = env('RUTA11_GOOGLE_CLIENT_SECRET');
        $redirectUri = env('MI3_GOOGLE_REDIRECT_URI', 'https://api-mi3.laruta11.cl/api/v1/auth/google/callback');

        // Exchange code for token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]),
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $tokenResponse = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($tokenResponse, true);

        if (!isset($tokenData['access_token'])) {
            return ['success' => false, 'error' => 'Error obteniendo token de Google', 'status' => 401];
        }

        // Get user info
        $userResponse = file_get_contents(
            'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenData['access_token']
        );
        $googleUser = json_decode($userResponse, true);

        if (!$googleUser || !isset($googleUser['email'])) {
            return ['success' => false, 'error' => 'Error obteniendo datos de Google', 'status' => 401];
        }

        // Find or create user
        $user = Usuario::where('google_id', $googleUser['id'])->first();

        if (!$user) {
            $user = Usuario::where('email', $googleUser['email'])->first();
        }

        if (!$user) {
            return ['success' => false, 'error' => 'No estás registrado en La Ruta 11', 'status' => 403];
        }

        // Update google_id and photo if needed
        $user->update([
            'google_id' => $googleUser['id'],
            'foto_perfil' => $googleUser['picture'] ?? $user->foto_perfil,
        ]);

        return $this->authenticateUser($user);
    }
}
