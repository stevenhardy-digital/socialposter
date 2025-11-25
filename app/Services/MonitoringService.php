<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringService
{
    /**
     * Log system event with context
     */
    public function logEvent(string $event, array $context = [], string $level = 'info'): void
    {
        $enrichedContext = array_merge($context, [
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-ID', uniqid()),
        ]);

        Log::channel('stack')->{$level}($event, $enrichedContext);
    }

    /**
     * Log API call with performance metrics
     */
    public function logApiCall(string $platform, string $endpoint, float $responseTime, bool $success, array $context = []): void
    {
        $this->logEvent("API call to {$platform}", array_merge($context, [
            'platform' => $platform,
            'endpoint' => $endpoint,
            'response_time_ms' => $responseTime,
            'success' => $success,
        ]), $success ? 'info' : 'warning');

        // Track API performance metrics
        $this->trackMetric("api.{$platform}.response_time", $responseTime);
        $this->trackMetric("api.{$platform}.calls", 1);
        
        if (!$success) {
            $this->trackMetric("api.{$platform}.failures", 1);
        }
    }

    /**
     * Log content generation event
     */
    public function logContentGeneration(int $userId, string $platform, bool $success, array $context = []): void
    {
        $this->logEvent('Content generation', array_merge($context, [
            'user_id' => $userId,
            'platform' => $platform,
            'success' => $success,
        ]), $success ? 'info' : 'error');

        $this->trackMetric('content_generation.total', 1);
        if ($success) {
            $this->trackMetric('content_generation.success', 1);
        } else {
            $this->trackMetric('content_generation.failures', 1);
        }
    }

    /**
     * Log post publication event
     */
    public function logPostPublication(int $postId, string $platform, bool $success, array $context = []): void
    {
        $this->logEvent('Post publication', array_merge($context, [
            'post_id' => $postId,
            'platform' => $platform,
            'success' => $success,
        ]), $success ? 'info' : 'error');

        $this->trackMetric('posts.published', 1);
        $this->trackMetric("posts.{$platform}.published", 1);
        
        if (!$success) {
            $this->trackMetric('posts.publication_failures', 1);
        }
    }

    /**
     * Log authentication event
     */
    public function logAuthentication(string $event, int $userId = null, array $context = []): void
    {
        $this->logEvent("Authentication: {$event}", array_merge($context, [
            'user_id' => $userId,
            'event' => $event,
        ]));

        $this->trackMetric("auth.{$event}", 1);
    }

    /**
     * Log OAuth event
     */
    public function logOAuthEvent(string $platform, string $event, int $userId, array $context = []): void
    {
        $this->logEvent("OAuth {$event} for {$platform}", array_merge($context, [
            'platform' => $platform,
            'event' => $event,
            'user_id' => $userId,
        ]));

        $this->trackMetric("oauth.{$platform}.{$event}", 1);
    }

    /**
     * Log error with full context
     */
    public function logError(\Throwable $exception, array $context = []): void
    {
        $this->logEvent('Application error', array_merge($context, [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]), 'error');

        $this->trackMetric('errors.total', 1);
        $this->trackMetric('errors.' . strtolower(class_basename($exception)), 1);
    }

    /**
     * Track performance metric
     */
    public function trackMetric(string $metric, float $value, array $tags = []): void
    {
        $key = "metrics.{$metric}." . date('Y-m-d-H');
        
        // Store hourly metrics
        Cache::increment($key, $value);
        Cache::expire($key, 86400 * 7); // Keep for 7 days

        // Store daily aggregates
        $dailyKey = "metrics.{$metric}." . date('Y-m-d');
        Cache::increment($dailyKey, $value);
        Cache::expire($dailyKey, 86400 * 30); // Keep for 30 days
    }

    /**
     * Get metric data for time range
     */
    public function getMetrics(string $metric, string $period = '24h'): array
    {
        $data = [];
        $now = now();

        switch ($period) {
            case '24h':
                for ($i = 23; $i >= 0; $i--) {
                    $hour = $now->copy()->subHours($i);
                    $key = "metrics.{$metric}." . $hour->format('Y-m-d-H');
                    $data[$hour->format('H:00')] = Cache::get($key, 0);
                }
                break;

            case '7d':
                for ($i = 6; $i >= 0; $i--) {
                    $day = $now->copy()->subDays($i);
                    $key = "metrics.{$metric}." . $day->format('Y-m-d');
                    $data[$day->format('M j')] = Cache::get($key, 0);
                }
                break;

            case '30d':
                for ($i = 29; $i >= 0; $i--) {
                    $day = $now->copy()->subDays($i);
                    $key = "metrics.{$metric}." . $day->format('Y-m-d');
                    $data[$day->format('M j')] = Cache::get($key, 0);
                }
                break;
        }

        return $data;
    }

    /**
     * Get system performance summary
     */
    public function getPerformanceSummary(): array
    {
        return [
            'api_calls' => [
                'total' => $this->getMetricSum('api.*.calls', '24h'),
                'failures' => $this->getMetricSum('api.*.failures', '24h'),
                'avg_response_time' => $this->getMetricAverage('api.*.response_time', '24h'),
            ],
            'content_generation' => [
                'total' => $this->getMetricSum('content_generation.total', '24h'),
                'success_rate' => $this->getSuccessRate('content_generation.success', 'content_generation.total', '24h'),
            ],
            'posts' => [
                'published' => $this->getMetricSum('posts.published', '24h'),
                'failures' => $this->getMetricSum('posts.publication_failures', '24h'),
            ],
            'errors' => [
                'total' => $this->getMetricSum('errors.total', '24h'),
                'by_type' => $this->getErrorBreakdown('24h'),
            ],
        ];
    }

    /**
     * Check for system alerts
     */
    public function getSystemAlerts(): array
    {
        $alerts = [];

        // High error rate alert
        $errorRate = $this->getMetricSum('errors.total', '1h');
        if ($errorRate > 10) {
            $alerts[] = [
                'type' => 'error',
                'message' => "High error rate: {$errorRate} errors in the last hour",
                'metric' => 'error_rate',
                'value' => $errorRate,
            ];
        }

        // API failure rate alert
        $apiFailures = $this->getMetricSum('api.*.failures', '1h');
        $apiCalls = $this->getMetricSum('api.*.calls', '1h');
        if ($apiCalls > 0 && ($apiFailures / $apiCalls) > 0.1) {
            $failureRate = round(($apiFailures / $apiCalls) * 100, 1);
            $alerts[] = [
                'type' => 'warning',
                'message' => "High API failure rate: {$failureRate}% in the last hour",
                'metric' => 'api_failure_rate',
                'value' => $failureRate,
            ];
        }

        // Queue backlog alert
        $queueSize = \Illuminate\Support\Facades\Queue::size();
        if ($queueSize > 100) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "Large queue backlog: {$queueSize} jobs pending",
                'metric' => 'queue_size',
                'value' => $queueSize,
            ];
        }

        return $alerts;
    }

    private function getMetricSum(string $pattern, string $period): float
    {
        // Handle wildcard patterns by summing multiple metrics
        if (strpos($pattern, '*') !== false) {
            $basePattern = str_replace('*', '', $pattern);
            $sum = 0;
            
            // For now, handle specific known patterns
            if ($basePattern === 'api..calls') {
                $platforms = ['instagram', 'facebook', 'linkedin'];
                foreach ($platforms as $platform) {
                    $metricName = str_replace('*', $platform, $pattern);
                    $metrics = $this->getMetrics($metricName, $period);
                    $sum += array_sum($metrics);
                }
            } elseif ($basePattern === 'api..failures') {
                $platforms = ['instagram', 'facebook', 'linkedin'];
                foreach ($platforms as $platform) {
                    $metricName = str_replace('*', $platform, $pattern);
                    $metrics = $this->getMetrics($metricName, $period);
                    $sum += array_sum($metrics);
                }
            } elseif ($basePattern === 'api..response_time') {
                $platforms = ['instagram', 'facebook', 'linkedin'];
                $count = 0;
                foreach ($platforms as $platform) {
                    $metricName = str_replace('*', $platform, $pattern);
                    $metrics = $this->getMetrics($metricName, $period);
                    $sum += array_sum($metrics);
                    $count += count(array_filter($metrics));
                }
                return $count > 0 ? $sum / $count : 0; // Return average for response time
            }
            
            return $sum;
        }
        
        $metrics = $this->getMetrics($pattern, $period);
        return array_sum($metrics);
    }

    private function getMetricAverage(string $pattern, string $period): float
    {
        $metrics = $this->getMetrics($pattern, $period);
        $values = array_filter($metrics);
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }

    private function getSuccessRate(string $successMetric, string $totalMetric, string $period): float
    {
        $success = $this->getMetricSum($successMetric, $period);
        $total = $this->getMetricSum($totalMetric, $period);
        return $total > 0 ? round(($success / $total) * 100, 1) : 0;
    }

    private function getErrorBreakdown(string $period): array
    {
        // This would need to be implemented based on specific error types
        // For now, return a placeholder
        return [
            'validation_errors' => $this->getMetricSum('errors.validationexception', $period),
            'api_errors' => $this->getMetricSum('errors.apiexception', $period),
            'database_errors' => $this->getMetricSum('errors.queryexception', $period),
        ];
    }
}