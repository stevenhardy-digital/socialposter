<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware
        $middleware->append([
            \App\Http\Middleware\ForceHttpsMiddleware::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        // API middleware
        $middleware->api(append: [
            \App\Http\Middleware\RequestLoggingMiddleware::class,
        ]);

        // Web middleware
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            // Log all exceptions with context
            app(\App\Services\MonitoringService::class)->logError($e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);

            // Return JSON responses for API requests
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'An error occurred',
                    'message' => app()->environment('production') ? 'Internal server error' : $e->getMessage(),
                    'code' => $e->getCode(),
                ], 500);
            }
        });
    })->create();
