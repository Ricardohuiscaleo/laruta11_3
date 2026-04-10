<?php

namespace App\Http\Middleware;

use App\Models\Personal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'No autenticado',
            ], 401);
        }

        $personal = Personal::where('user_id', $user->id)
            ->where('activo', 1)
            ->first();

        if (!$personal) {
            return response()->json([
                'success' => false,
                'error' => 'No registrado como trabajador activo',
            ], 403);
        }

        $request->merge(['personal' => $personal]);

        return $next($request);
    }
}
