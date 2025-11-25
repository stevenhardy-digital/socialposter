<?php

namespace App\Http\Middleware;

use App\Services\MonitoringService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    public function __construct(
        private MonitoringService $monitoringService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid();
        
        // Add request ID to headers for tracing
        $request->headers->set('X-Request-ID', $requestId);

        // Log incoming request
        $this->logIncomingRequest($request, $requestId);

        $response = $next($request);

        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000;

        // Log outgoing response
        $this->logOutgoingResponse($request, $response, $responseTime, $requestId);

        // Track performance metrics
        $this->trackPerformanceMetrics($request, $response, $responseTime);

        return $response;
    }

    private function logIncomingRequest(Request $request, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
        ];

        // Log request body for non-GET requests (excluding sensitive data)
        if (!$request->isMethod('GET')) {
            $body = $request->all();
            
            // Remove sensitive fields
            $sensitiveFields = ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token'];
            foreach ($sensitiveFields as $field) {
                if (isset($body[$field])) {
                    $body[$field] = '[REDACTED]';
                }
            }
            
            $context['request_body'] = $body;
        }

        Log::info('Incoming request', $context);
    }

    private function logOutgoingResponse(Request $request, Response $response, float $responseTime, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime, 2),
            'user_id' => auth()->id(),
        ];

        $level = $response->getStatusCode() >= 400 ? 'warning' : 'info';
        
        // Log response body for errors
        if ($response->getStatusCode() >= 400) {
            $content = $response->getContent();
            if ($content && strlen($content) < 1000) { // Only log short error responses
                $context['response_body'] = $content;
            }
        }

        Log::log($level, 'Outgoing response', $context);
    }

    private function trackPerformanceMetrics(Request $request, Response $response, float $responseTime): void
    {
        // Track overall API performance
        $this->monitoringService->trackMetric('api.requests.total', 1);
        $this->monitoringService->trackMetric('api.requests.response_time', $responseTime);

        // Track by HTTP method
        $method = strtolower($request->method());
        $this->monitoringService->trackMetric("api.requests.{$method}", 1);

        // Track by status code
        $statusCode = $response->getStatusCode();
        $this->monitoringService->trackMetric("api.responses.{$statusCode}", 1);

        // Track by endpoint
        $route = $request->route();
        if ($route) {
            $routeName = $route->getName() ?: 'unnamed';
            $this->monitoringService->trackMetric("api.endpoints.{$routeName}", 1);
            $this->monitoringService->trackMetric("api.endpoints.{$routeName}.response_time", $responseTime);
        }

        // Track errors
        if ($statusCode >= 400) {
            $this->monitoringService->trackMetric('api.errors.total', 1);
            $this->monitoringService->trackMetric("api.errors.{$statusCode}", 1);
        }

        // Track slow requests
        if ($responseTime > 1000) { // Slower than 1 second
            $this->monitoringService->trackMetric('api.slow_requests', 1);
        }
    }
}