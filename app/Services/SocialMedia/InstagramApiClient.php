<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramApiClient extends BaseSocialMediaClient
{
    protected string $baseUrl = 'https://graph.facebook.com/v18.0';
    
    /**
     * Get Instagram business account information
     */
    public function getAccountInfo(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/me", [
                'fields' => 'id,username,account_type,media_count,followers_count',
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get Instagram account info');
        } catch (Exception $e) {
            return $this->handleException($e, 'Instagram account info request failed');
        }
    }

    /**
     * Create Instagram media container (first step of publishing)
     */
    public function createMediaContainer(SocialAccount $account, array $postData): array
    {
        try {
            $params = [
                'image_url' => $postData['image_url'] ?? null,
                'caption' => $postData['content'],
                'access_token' => $account->access_token
            ];

            // Remove null values
            $params = array_filter($params, fn($value) => $value !== null);

            $response = Http::timeout(30)->post("{$this->baseUrl}/{$account->platform_user_id}/media", $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to create Instagram media container');
        } catch (Exception $e) {
            return $this->handleException($e, 'Instagram media container creation failed');
        }
    }

    /**
     * Publish Instagram media container (second step of publishing)
     */
    public function publishMedia(SocialAccount $account, string $creationId): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/{$account->platform_user_id}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to publish Instagram media');
        } catch (Exception $e) {
            return $this->handleException($e, 'Instagram media publishing failed');
        }
    }

    /**
     * Get Instagram post metrics
     */
    public function getPostMetrics(SocialAccount $account, string $mediaId): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/{$mediaId}/insights", [
                'metric' => 'engagement,impressions,reach,saved',
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                $insights = $response->json();
                $metrics = [];
                
                foreach ($insights['data'] as $insight) {
                    $metrics[$insight['name']] = $insight['values'][0]['value'] ?? 0;
                }

                // Get basic engagement data
                $basicResponse = Http::timeout(30)->get("{$this->baseUrl}/{$mediaId}", [
                    'fields' => 'like_count,comments_count',
                    'access_token' => $account->access_token
                ]);

                if ($basicResponse->successful()) {
                    $basicData = $basicResponse->json();
                    $metrics['likes_count'] = $basicData['like_count'] ?? 0;
                    $metrics['comments_count'] = $basicData['comments_count'] ?? 0;
                }

                return [
                    'success' => true,
                    'data' => [
                        'likes_count' => $metrics['likes_count'] ?? 0,
                        'comments_count' => $metrics['comments_count'] ?? 0,
                        'shares_count' => 0, // Instagram doesn't provide shares
                        'reach' => $metrics['reach'] ?? 0,
                        'impressions' => $metrics['impressions'] ?? 0,
                        'engagement' => $metrics['engagement'] ?? 0,
                        'saved' => $metrics['saved'] ?? 0,
                    ]
                ];
            }

            return $this->handleApiError($response, 'Failed to get Instagram post metrics');
        } catch (Exception $e) {
            return $this->handleException($e, 'Instagram metrics request failed');
        }
    }

    /**
     * Get Instagram user media
     */
    public function getUserMedia(SocialAccount $account, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/{$account->platform_user_id}/media", [
                'fields' => 'id,caption,media_type,media_url,permalink,timestamp',
                'limit' => $limit,
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get Instagram user media');
        } catch (Exception $e) {
            return $this->handleException($e, 'Instagram user media request failed');
        }
    }

    /**
     * Refresh Instagram access token
     */
    public function refreshAccessToken(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/oauth/access_token", [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => [
                        'access_token' => $data['access_token'],
                        'expires_in' => $data['expires_in'] ?? 5184000 // 60 days default
                    ]
                ];
            }

            return $this->handleApiError($response, 'Failed to refresh Instagram access token');
        } catch (Exception $e) {
            return $this->handleException($e, 'Instagram token refresh failed');
        }
    }

    /**
     * Validate Instagram webhook signature
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, config('services.instagram.webhook_secret'));
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process Instagram webhook data
     */
    public function processWebhookData(array $data): array
    {
        $processedUpdates = [];

        foreach ($data['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if ($change['field'] === 'comments' || $change['field'] === 'likes') {
                    $processedUpdates[] = [
                        'media_id' => $change['value']['media_id'] ?? null,
                        'field' => $change['field'],
                        'value' => $change['value'],
                        'timestamp' => now()
                    ];
                }
            }
        }

        return $processedUpdates;
    }

    /**
     * Check Instagram API status
     */
    public function checkApiStatus(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/me", [
                'access_token' => config('services.facebook.app_id') . '|' . config('services.facebook.app_secret'),
                'fields' => 'id'
            ]);

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'message' => 'Instagram API is accessible'
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'Instagram API returned non-success status: ' . $response->status()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Instagram API check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test basic connectivity to Instagram API
     */
    public function testConnectivity(): array
    {
        try {
            $startTime = microtime(true);
            $response = Http::timeout(10)->get($this->baseUrl);
            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'success' => true,
                'response_time_ms' => round($responseTime, 2),
                'status_code' => $response->status()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Instagram API rate limit status
     */
    public function getRateLimitStatus(): array
    {
        // Instagram uses Facebook's rate limiting
        // Rate limits are typically returned in response headers
        return [
            'platform' => 'instagram',
            'rate_limit_type' => 'facebook_graph_api',
            'note' => 'Instagram rate limits are managed through Facebook Graph API'
        ];
    }
}