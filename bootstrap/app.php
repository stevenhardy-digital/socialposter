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
                // Determine appropriate status code
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($statusCode < 400 || $statusCode >= 600) {
                    $statusCode = 500;
                }

                // Get user-friendly error message based on exception type
                $message = match (get_class($e)) {
                    \Illuminate\Http\Exceptions\ThrottleRequestsException::class => 'Too many requests. Please wait a moment and try again.',
                    \Illuminate\Auth\AuthenticationException::class => 'Authentication required. Please log in.',
                    \Illuminate\Auth\Access\AuthorizationException::class => 'You are not authorized to perform this action.',
                    \Illuminate\Validation\ValidationException::class => 'Validation failed: ' . $e->getMessage(),
                    \Illuminate\Database\Eloquent\ModelNotFoundException::class => 'The requested resource was not found.',
                    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class => 'The requested endpoint was not found.',
                    \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class => 'Method not allowed for this endpoint.',
                    \Illuminate\Database\QueryException::class => 'Database error occurred. Please try again.',
                    default => app()->environment('production') ? 'An unexpected error occurred. Please try again.' : $e->getMessage()
                };

                $errorType = match (get_class($e)) {
                    \Illuminate\Http\Exceptions\ThrottleRequestsException::class => 'rate_limit_exceeded',
                    \Illuminate\Auth\AuthenticationException::class => 'authentication_required',
                    \Illuminate\Auth\Access\AuthorizationException::class => 'authorization_failed',
                    \Illuminate\Validation\ValidationException::class => 'validation_error',
                    \Illuminate\Database\Eloquent\ModelNotFoundException::class => 'resource_not_found',
                    \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class => 'endpoint_not_found',
                    \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class => 'method_not_allowed',
                    \Illuminate\Database\QueryException::class => 'database_error',
                    default => 'internal_error'
                };
                
                return response()->json([
                    'error' => $errorType,
                    'message' => $message,
                    'code' => $e->getCode(),
                ], $statusCode);
            }
        });
    })->create();
