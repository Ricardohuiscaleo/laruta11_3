<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $personal = $request->get('personal');

        if (!$personal || !$personal->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acceso denegado',
            ], 403);
        }

        return $next($request);
    }
}
