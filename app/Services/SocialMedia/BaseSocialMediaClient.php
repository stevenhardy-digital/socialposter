<?php

namespace App\Services\SocialMedia;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

abstract class BaseSocialMediaClient
{
    protected string $baseUrl;
    protected int $maxRetries = 3;
    protected array $retryDelays = [1, 3, 9]; // seconds
    
    /**
     * Handle API errors with proper error codes and messages
     */
    protected function handleApiError(Response $response, string $context): array
    {
        $statusCode = $response->status();
        $responseBody = $response->json() ?? ['error' => $response->body()];
        
        Log::error($context, [
            'status_code' => $statusCode,
            'response' => $responseBody,
            'url' => $response->effectiveUri()
        ]);

        // Determine if this is a rate limit error
        $isRateLimit = $this->isRateLimitError($statusCode, $responseBody);
        
        // Determine if this is a temporary error that should be retried
        $isRetryable = $this->isRetryableError($statusCode, $responseBody);

        return [
            'success' => false,
            'error' => $this->extractErrorMessage($responseBody),
            'error_code' => $statusCode,
            'is_rate_limit' => $isRateLimit,
            'is_retryable' => $isRetryable,
            'retry_after' => $this->extractRetryAfter($response, $responseBody)
        ];
    }

    /**
     * Handle exceptions with proper logging
     */
    protected function handleException(Exception $e, string $context): array
    {
        Log::error($context, [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'is_rate_limit' => false,
            'is_retryable' => $this->isRetryableException($e)
        ];
    }

    /**
     * Check if the error is a rate limit error
     */
    protected function isRateLimitError(int $statusCode, array $responseBody): bool
    {
        if ($statusCode === 429) {
            return true;
        }

        // Check for platform-specific rate limit indicators
        $errorMessage = strtolower($this->extractErrorMessage($responseBody));
        $rateLimitKeywords = ['rate limit', 'too many requests', 'quota exceeded', 'throttled'];
        
        foreach ($rateLimitKeywords as $keyword) {
            if (str_contains($errorMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the error is retryable
     */
    protected function isRetryableError(int $statusCode, array $responseBody): bool
    {
        // Retryable HTTP status codes
        $retryableStatusCodes = [429, 500, 502, 503, 504];
        
        if (in_array($statusCode, $retryableStatusCodes)) {
            return true;
        }

        // Check for platform-specific retryable errors
        $errorMessage = strtolower($this->extractErrorMessage($responseBody));
        $retryableKeywords = ['temporary', 'timeout', 'connection', 'network'];
        
        foreach ($retryableKeywords as $keyword) {
            if (str_contains($errorMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the exception is retryable
     */
    protected function isRetryableException(Exception $e): bool
    {
        $retryableExceptions = [
            'GuzzleHttp\Exception\ConnectException',
            'GuzzleHttp\Exception\RequestException',
            'Illuminate\Http\Client\ConnectionException'
        ];

        foreach ($retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract error message from response body
     */
    protected function extractErrorMessage(array $responseBody): string
    {
        // Try different common error message fields
        $errorFields = ['error_description', 'message', 'error', 'error_message', 'detail'];
        
        foreach ($errorFields as $field) {
            if (isset($responseBody[$field])) {
                if (is_string($responseBody[$field])) {
                    return $responseBody[$field];
                } elseif (is_array($responseBody[$field]) && isset($responseBody[$field]['message'])) {
                    return $responseBody[$field]['message'];
                }
            }
        }

        // If no standard error field found, return the whole response as JSON
        return json_encode($responseBody);
    }

    /**
     * Extract retry-after header or calculate delay
     */
    protected function extractRetryAfter(Response $response, array $responseBody): ?int
    {
        // Check for Retry-After header
        $retryAfter = $response->header('Retry-After');
        if ($retryAfter) {
            return (int) $retryAfter;
        }

        // Check for platform-specific retry delay in response body
        if (isset($responseBody['retry_after'])) {
            return (int) $responseBody['retry_after'];
        }

        // Default exponential backoff calculation
        return null;
    }

    /**
     * Execute API call with retry logic
     */
    protected function executeWithRetry(callable $apiCall, string $context = 'API call'): array
    {
        $lastResult = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $result = $apiCall();
                
                // If successful, return immediately
                if ($result['success'] ?? false) {
                    return $result;
                }
                
                $lastResult = $result;
                
                // If not retryable, break early
                if (!($result['is_retryable'] ?? false)) {
                    break;
                }
                
                // Calculate delay for next attempt
                if ($attempt < $this->maxRetries) {
                    $delay = $result['retry_after'] ?? $this->retryDelays[$attempt - 1] ?? 9;
                    
                    Log::info("Retrying {$context} in {$delay} seconds", [
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxRetries,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                    
                    sleep($delay);
                }
                
            } catch (Exception $e) {
                $lastResult = $this->handleException($e, $context);
                
                // If not retryable exception, break early
                if (!$this->isRetryableException($e)) {
                    break;
                }
                
                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt - 1] ?? 9;
                    Log::info("Retrying {$context} after exception in {$delay} seconds", [
                        'attempt' => $attempt,
                        'exception' => $e->getMessage()
                    ]);
                    sleep($delay);
                }
            }
        }
        
        // Log final failure
        Log::error("Failed {$context} after {$this->maxRetries} attempts", [
            'final_error' => $lastResult['error'] ?? 'Unknown error'
        ]);
        
        return $lastResult ?? [
            'success' => false,
            'error' => 'Maximum retry attempts exceeded',
            'is_retryable' => false
        ];
    }

    /**
     * Validate required parameters
     */
    protected function validateRequiredParams(array $params, array $required): void
    {
        foreach ($required as $param) {
            if (!isset($params[$param]) || empty($params[$param])) {
                throw new Exception("Required parameter '{$param}' is missing or empty");
            }
        }
    }

    /**
     * Sanitize and prepare post data
     */
    protected function sanitizePostData(array $postData): array
    {
        return [
            'content' => trim($postData['content'] ?? ''),
            'image_url' => filter_var($postData['image_url'] ?? '', FILTER_VALIDATE_URL) ?: null,
            'scheduled_publish_time' => $postData['scheduled_publish_time'] ?? null,
            'company_id' => $postData['company_id'] ?? null,
        ];
    }

    /**
     * Check API status with a lightweight call
     */
    abstract public function checkApiStatus(): array;

    /**
     * Test basic connectivity to the API
     */
    abstract public function testConnectivity(): array;

    /**
     * Get current rate limit status
     */
    abstract public function getRateLimitStatus(): array;

    /**
     * Validate webhook signature
     */
    abstract public function validateWebhookSignature(string $payload, string $signature): bool;

    /**
     * Process webhook data
     */
    abstract public function processWebhookData(array $data): array;
}