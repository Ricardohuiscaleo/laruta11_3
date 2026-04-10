<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'worker' => \App\Http\Middleware\EnsureIsWorker::class,
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
        ]);

        // API-only: return JSON 401 instead of redirecting to login route
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for all API exceptions
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
