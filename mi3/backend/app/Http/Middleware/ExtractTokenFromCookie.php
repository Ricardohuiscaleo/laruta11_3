<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extracts the Sanctum token from the httpOnly cookie
 * and injects it into the Authorization header.
 *
 * This allows the frontend to authenticate via cookies
 * (set by the backend on login/OAuth) without exposing
 * the token to JavaScript.
 */
class ExtractTokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only inject if no Authorization header is present
        if (!$request->hasHeader('Authorization')) {
            $token = $request->cookie('mi3_token');
            if ($token) {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
