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

        // Revoke existing tokens and create a new one
        $user->tokens()->delete();
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
}
