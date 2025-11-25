<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookApiClient extends BaseSocialMediaClient
{
    protected string $baseUrl = 'https://graph.facebook.com/v18.0';
    
    /**
     * Get Facebook page information
     */
    public function getPageInfo(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/{$account->platform_user_id}", [
                'fields' => 'id,name,category,fan_count,access_token',
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get Facebook page info');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook page info request failed');
        }
    }

    /**
     * Get Facebook pages managed by the user
     */
    public function getManagedPages(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/me/accounts", [
                'fields' => 'id,name,category,access_token,perms',
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get Facebook managed pages');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook managed pages request failed');
        }
    }

    /**
     * Publish post to Facebook page
     */
    public function publishPost(SocialAccount $account, array $postData): array
    {
        try {
            $params = [
                'message' => $postData['content'],
                'access_token' => $account->access_token
            ];

            // Add media if provided
            if (!empty($postData['image_url'])) {
                $params['link'] = $postData['image_url'];
            }

            // Add scheduled publishing if provided
            if (!empty($postData['scheduled_publish_time'])) {
                $params['scheduled_publish_time'] = $postData['scheduled_publish_time'];
                $params['published'] = false;
            }

            $response = Http::timeout(30)->post("{$this->baseUrl}/{$account->platform_user_id}/feed", $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to publish Facebook post');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook post publishing failed');
        }
    }

    /**
     * Get Facebook post metrics
     */
    public function getPostMetrics(SocialAccount $account, string $postId): array
    {
        try {
            // Get basic post data
            $response = Http::timeout(30)->get("{$this->baseUrl}/{$postId}", [
                'fields' => 'reactions.summary(total_count),comments.summary(total_count),shares',
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Get insights data
                $insightsResponse = Http::timeout(30)->get("{$this->baseUrl}/{$postId}/insights", [
                    'metric' => 'post_impressions,post_reach,post_engaged_users',
                    'access_token' => $account->access_token
                ]);

                $insights = [];
                if ($insightsResponse->successful()) {
                    $insightsData = $insightsResponse->json();
                    foreach ($insightsData['data'] as $insight) {
                        $insights[$insight['name']] = $insight['values'][0]['value'] ?? 0;
                    }
                }

                return [
                    'success' => true,
                    'data' => [
                        'likes_count' => $data['reactions']['summary']['total_count'] ?? 0,
                        'comments_count' => $data['comments']['summary']['total_count'] ?? 0,
                        'shares_count' => $data['shares']['count'] ?? 0,
                        'reach' => $insights['post_reach'] ?? 0,
                        'impressions' => $insights['post_impressions'] ?? 0,
                        'engaged_users' => $insights['post_engaged_users'] ?? 0,
                    ]
                ];
            }

            return $this->handleApiError($response, 'Failed to get Facebook post metrics');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook metrics request failed');
        }
    }

    /**
     * Get Facebook page posts
     */
    public function getPagePosts(SocialAccount $account, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/{$account->platform_user_id}/posts", [
                'fields' => 'id,message,created_time,permalink_url,status_type',
                'limit' => $limit,
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get Facebook page posts');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook page posts request failed');
        }
    }

    /**
     * Delete Facebook post
     */
    public function deletePost(SocialAccount $account, string $postId): array
    {
        try {
            $response = Http::timeout(30)->delete("{$this->baseUrl}/{$postId}", [
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to delete Facebook post');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook post deletion failed');
        }
    }

    /**
     * Refresh Facebook page access token
     */
    public function refreshAccessToken(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'fb_exchange_token' => $account->access_token
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

            return $this->handleApiError($response, 'Failed to refresh Facebook access token');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook token refresh failed');
        }
    }

    /**
     * Validate Facebook webhook signature
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, config('services.facebook.webhook_secret'));
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process Facebook webhook data
     */
    public function processWebhookData(array $data): array
    {
        $processedUpdates = [];

        foreach ($data['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (in_array($change['field'], ['feed', 'reactions', 'comments'])) {
                    $processedUpdates[] = [
                        'post_id' => $change['value']['post_id'] ?? null,
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
     * Subscribe to Facebook page webhooks
     */
    public function subscribeToWebhooks(SocialAccount $account, array $fields = ['feed', 'reactions', 'comments']): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/{$account->platform_user_id}/subscribed_apps", [
                'subscribed_fields' => implode(',', $fields),
                'access_token' => $account->access_token
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to subscribe to Facebook webhooks');
        } catch (Exception $e) {
            return $this->handleException($e, 'Facebook webhook subscription failed');
        }
    }

    /**
     * Check Facebook API status
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
                    'message' => 'Facebook API is accessible'
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'Facebook API returned non-success status: ' . $response->status()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Facebook API check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test basic connectivity to Facebook API
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
     * Get Facebook API rate limit status
     */
    public function getRateLimitStatus(): array
    {
        return [
            'platform' => 'facebook',
            'rate_limit_type' => 'facebook_graph_api',
            'limits' => [
                'app_level' => '200 calls per user per hour',
                'page_level' => '4800 calls per page per hour'
            ]
        ];
    }
}