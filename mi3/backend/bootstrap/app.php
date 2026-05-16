<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'worker' => \App\Http\Middleware\EnsureIsWorker::class,
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
        ]);

        // Extract Sanctum token from httpOnly cookie if not in Authorization header
        $middleware->prepend(\App\Http\Middleware\ExtractTokenFromCookie::class);

        // API-only: return JSON 401 instead of redirecting to login route
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for all API exceptions
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Add CORS headers to exception responses
        // (HandleCors middleware doesn't apply to exception handler responses)
        $exceptions->respond(function ($response) {
            $origin = request()->header('Origin');
            $allowedOrigins = ['https://mi.laruta11.cl', 'https://app.laruta11.cl', 'https://caja.laruta11.cl'];
            if ($origin && in_array($origin, $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Allow-Methods', '*');
                $response->headers->set('Access-Control-Allow-Headers', '*');
            }
            return $response;
        });
    })->create();
