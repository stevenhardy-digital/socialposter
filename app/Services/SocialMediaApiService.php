<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Services\SocialMedia\FacebookApiClient;
use App\Services\SocialMedia\InstagramApiClient;
use App\Services\SocialMedia\LinkedInApiClient;
use Exception;
use Illuminate\Support\Facades\Log;

class SocialMediaApiService
{
    protected FacebookApiClient $facebookClient;
    protected InstagramApiClient $instagramClient;
    protected LinkedInApiClient $linkedinClient;

    public function __construct()
    {
        $this->facebookClient = new FacebookApiClient();
        $this->instagramClient = new InstagramApiClient();
        $this->linkedinClient = new LinkedInApiClient();
    }

    /**
     * Get the appropriate API client for a platform
     */
    public function getClient(string $platform)
    {
        return match($platform) {
            'facebook' => $this->facebookClient,
            'instagram' => $this->instagramClient,
            'linkedin' => $this->linkedinClient,
            default => throw new Exception("Unsupported platform: {$platform}")
        };
    }

    /**
     * Publish a post to the specified platform
     */
    public function publishPost(SocialAccount $account, array $postData): array
    {
        try {
            $client = $this->getClient($account->platform);
            
            // Validate access token
            if (!$account->access_token) {
                return [
                    'success' => false,
                    'error' => 'No access token available for this account',
                    'requires_reauth' => true
                ];
            }

            // Check if token is expired and refresh if needed
            if ($this->isTokenExpired($account)) {
                $refreshResult = $this->refreshAccessToken($account);
                if (!$refreshResult['success']) {
                    return [
                        'success' => false,
                        'error' => 'Access token expired and refresh failed',
                        'requires_reauth' => true
                    ];
                }
            }

            // Platform-specific publishing logic
            switch ($account->platform) {
                case 'instagram':
                    return $this->publishToInstagram($account, $postData);
                case 'facebook':
                    return $this->publishToFacebook($account, $postData);
                case 'linkedin':
                    return $this->publishToLinkedIn($account, $postData);
                default:
                    throw new Exception("Publishing not supported for platform: {$account->platform}");
            }
        } catch (Exception $e) {
            Log::error('Social media publishing failed', [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'requires_reauth' => false
            ];
        }
    }

    /**
     * Publish to Instagram (two-step process)
     */
    private function publishToInstagram(SocialAccount $account, array $postData): array
    {
        // Step 1: Create media container
        $containerResult = $this->instagramClient->createMediaContainer($account, $postData);
        
        if (!$containerResult['success']) {
            return $containerResult;
        }

        $creationId = $containerResult['data']['id'];

        // Step 2: Publish the media
        return $this->instagramClient->publishMedia($account, $creationId);
    }

    /**
     * Publish to Facebook
     */
    private function publishToFacebook(SocialAccount $account, array $postData): array
    {
        return $this->facebookClient->publishPost($account, $postData);
    }

    /**
     * Publish to LinkedIn
     */
    private function publishToLinkedIn(SocialAccount $account, array $postData): array
    {
        return $this->linkedinClient->publishPost($account, $postData);
    }

    /**
     * Collect engagement metrics for a post
     */
    public function collectEngagementMetrics(SocialAccount $account, string $platformPostId): array
    {
        try {
            $client = $this->getClient($account->platform);
            
            // Check if token is expired and refresh if needed
            if ($this->isTokenExpired($account)) {
                $refreshResult = $this->refreshAccessToken($account);
                if (!$refreshResult['success']) {
                    return [
                        'success' => false,
                        'error' => 'Access token expired and refresh failed',
                        'requires_reauth' => true
                    ];
                }
            }

            return $client->getPostMetrics($account, $platformPostId);
        } catch (Exception $e) {
            Log::error('Metrics collection failed', [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'post_id' => $platformPostId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Refresh access token for an account
     */
    public function refreshAccessToken(SocialAccount $account): array
    {
        try {
            $client = $this->getClient($account->platform);
            $result = $client->refreshAccessToken($account);

            if ($result['success']) {
                // Update the account with new token information
                $account->update([
                    'access_token' => $result['data']['access_token'],
                    'refresh_token' => $result['data']['refresh_token'] ?? $account->refresh_token,
                    'expires_at' => isset($result['data']['expires_in']) 
                        ? now()->addSeconds($result['data']['expires_in'])
                        : null
                ]);

                Log::info('Access token refreshed successfully', [
                    'account_id' => $account->id,
                    'platform' => $account->platform
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Token refresh failed', [
                'account_id' => $account->id,
                'platform' => $account->platform,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account information from the platform
     */
    public function getAccountInfo(SocialAccount $account): array
    {
        try {
            $client = $this->getClient($account->platform);
            
            switch ($account->platform) {
                case 'instagram':
                    return $client->getAccountInfo($account);
                case 'facebook':
                    return $client->getPageInfo($account);
                case 'linkedin':
                    return $client->getProfileInfo($account);
                default:
                    throw new Exception("Account info not supported for platform: {$account->platform}");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get posts from the platform
     */
    public function getPlatformPosts(SocialAccount $account, int $limit = 25): array
    {
        try {
            $client = $this->getClient($account->platform);
            
            switch ($account->platform) {
                case 'instagram':
                    return $client->getUserMedia($account, $limit);
                case 'facebook':
                    return $client->getPagePosts($account, $limit);
                case 'linkedin':
                    return $client->getUserPosts($account, $limit);
                default:
                    throw new Exception("Post retrieval not supported for platform: {$account->platform}");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a post from the platform
     */
    public function deletePost(SocialAccount $account, string $platformPostId): array
    {
        try {
            $client = $this->getClient($account->platform);
            
            switch ($account->platform) {
                case 'facebook':
                    return $client->deletePost($account, $platformPostId);
                case 'linkedin':
                    return $client->deletePost($account, $platformPostId);
                case 'instagram':
                    // Instagram doesn't support post deletion via API
                    return [
                        'success' => false,
                        'error' => 'Instagram does not support post deletion via API'
                    ];
                default:
                    throw new Exception("Post deletion not supported for platform: {$account->platform}");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if access token is expired
     */
    private function isTokenExpired(SocialAccount $account): bool
    {
        if (!$account->expires_at) {
            return false; // No expiration set, assume it's valid
        }

        // Consider token expired if it expires within the next 5 minutes
        return $account->expires_at->isBefore(now()->addMinutes(5));
    }

    /**
     * Validate webhook signature for a platform
     */
    public function validateWebhookSignature(string $platform, string $payload, string $signature): bool
    {
        try {
            $client = $this->getClient($platform);
            return $client->validateWebhookSignature($payload, $signature);
        } catch (Exception $e) {
            Log::error('Webhook signature validation failed', [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process webhook data for a platform
     */
    public function processWebhookData(string $platform, array $data): array
    {
        try {
            $client = $this->getClient($platform);
            return $client->processWebhookData($data);
        } catch (Exception $e) {
            Log::error('Webhook data processing failed', [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Subscribe to webhooks for a platform (where supported)
     */
    public function subscribeToWebhooks(SocialAccount $account, array $fields = []): array
    {
        try {
            switch ($account->platform) {
                case 'facebook':
                    return $this->facebookClient->subscribeToWebhooks($account, $fields);
                case 'instagram':
                    // Instagram webhooks are managed through Facebook
                    return [
                        'success' => true,
                        'message' => 'Instagram webhooks are managed through Facebook Graph API'
                    ];
                case 'linkedin':
                    return [
                        'success' => false,
                        'error' => 'LinkedIn does not currently support webhooks for UGC posts'
                    ];
                default:
                    throw new Exception("Webhook subscription not supported for platform: {$account->platform}");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check API status for a platform
     */
    public function checkApiStatus(string $platform): array
    {
        try {
            $client = $this->getClient($platform);
            $startTime = microtime(true);
            
            // Perform a lightweight API call to check status
            $status = $client->checkApiStatus();
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'status' => 'healthy',
                'platform' => $platform,
                'response_time_ms' => round($responseTime, 2),
                'last_checked' => now()->toISOString(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'platform' => $platform,
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get comprehensive API health status for all platforms
     */
    public function getApiHealthStatus(): array
    {
        $platforms = ['instagram', 'facebook', 'linkedin'];
        $statuses = [];
        
        foreach ($platforms as $platform) {
            $statuses[$platform] = $this->checkApiStatus($platform);
        }
        
        return $statuses;
    }

    /**
     * Test API connectivity and rate limits
     */
    public function testApiConnectivity(string $platform): array
    {
        try {
            $client = $this->getClient($platform);
            
            // Test basic connectivity
            $connectivityTest = $client->testConnectivity();
            
            // Check rate limit status
            $rateLimitStatus = $client->getRateLimitStatus();
            
            return [
                'platform' => $platform,
                'connectivity' => $connectivityTest,
                'rate_limits' => $rateLimitStatus,
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
            ];
        } catch (Exception $e) {
            return [
                'platform' => $platform,
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }
}