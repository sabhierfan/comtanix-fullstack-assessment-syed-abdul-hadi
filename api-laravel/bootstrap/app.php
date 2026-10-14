<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every /api request answers in JSON, even if the client forgot the Accept header.
        $middleware->prependToGroup('api', ForceJsonResponse::class);

        // When the app runs behind a load balancer or proxy, set TRUSTED_PROXIES
        // (e.g. "10.0.0.0/8" or "*"). Then $request->ip() is the real visitor IP,
        // not the proxy's IP, and the per-IP rate limits work correctly.
        // Empty by default = trust nobody (safe for local development).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errors on /api routes (401, 403, 404, 422, 500...) are returned as JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
