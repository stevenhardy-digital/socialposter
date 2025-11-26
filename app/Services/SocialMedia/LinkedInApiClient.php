<?php

namespace App\Services\SocialMedia;

use App\Models\SocialAccount;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInApiClient extends BaseSocialMediaClient
{
    protected string $baseUrl = 'https://api.linkedin.com/v2';
    
    /**
     * Get LinkedIn profile information using OpenID Connect
     */
    public function getProfileInfo(SocialAccount $account): array
    {
        try {
            // Try userinfo endpoint first (most reliable with OpenID Connect)
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
            ])->get('https://api.linkedin.com/v2/userinfo');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            // Fallback to basic profile with minimal fields
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/people/~", [
                'projection' => '(id,localizedFirstName,localizedLastName)'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get LinkedIn profile info');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn profile info request failed');
        }
    }

    /**
     * Get profile analytics (requires r_member_profileAnalytics scope)
     */
    public function getProfileAnalytics(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/networkSizes/{$account->platform_user_id}", [
                'edgeType' => 'CompanyFollowedByMember'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get LinkedIn profile analytics');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn profile analytics request failed');
        }
    }

    /**
     * Get connection count (requires r_1st_connections_size scope)
     */
    public function getConnectionCount(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/people/(id:{$account->platform_user_id})/network/network-sizes", [
                'edgeType' => 'FIRST_DEGREE'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get LinkedIn connection count');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn connection count request failed');
        }
    }

    /**
     * Get LinkedIn company pages managed by the user
     */
    public function getManagedCompanies(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/organizationAcls", [
                'q' => 'roleAssignee',
                'projection' => '(elements*(organization~(id,name)))'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get LinkedIn managed companies');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn managed companies request failed');
        }
    }

    /**
     * Get organization followers (requires r_organization_followers scope)
     */
    public function getOrganizationFollowers(SocialAccount $account, string $organizationId): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/networkSizes/{$organizationId}", [
                'edgeType' => 'CompanyFollowedByMember'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get organization followers');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn organization followers request failed');
        }
    }

    /**
     * Get organization post analytics (requires r_organization_social scope)
     */
    public function getOrganizationPostAnalytics(SocialAccount $account, string $organizationId, array $postIds = []): array
    {
        try {
            $params = [
                'q' => 'organizationalEntity',
                'organizationalEntity' => $organizationId,
            ];

            if (!empty($postIds)) {
                $params['shares'] = $postIds;
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/organizationalEntityShareStatistics", $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get organization post analytics');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn organization post analytics request failed');
        }
    }

    /**
     * Publish post to LinkedIn
     */
    public function publishPost(SocialAccount $account, array $postData): array
    {
        try {
            $author = $this->determineAuthor($account, $postData);
            
            $postBody = [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $postData['content']
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            // Add media if provided
            if (!empty($postData['image_url'])) {
                $postBody['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                $postBody['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                    [
                        'status' => 'READY',
                        'description' => [
                            'text' => 'Shared image'
                        ],
                        'media' => $postData['image_url'],
                        'title' => [
                            'text' => 'Image'
                        ]
                    ]
                ];
            }

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->post("{$this->baseUrl}/ugcPosts", $postBody);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to publish LinkedIn post');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn post publishing failed');
        }
    }

    /**
     * Get LinkedIn post metrics
     */
    public function getPostMetrics(SocialAccount $account, string $postId): array
    {
        try {
            // Get social actions (likes, comments, shares)
            $socialResponse = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/socialActions/{$postId}");

            $socialData = [];
            if ($socialResponse->successful()) {
                $socialData = $socialResponse->json();
            }

            // Get post statistics if available
            $statsResponse = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/organizationalEntityShareStatistics", [
                'q' => 'organizationalEntity',
                'organizationalEntity' => $account->platform_user_id,
                'shares' => [$postId]
            ]);

            $statsData = [];
            if ($statsResponse->successful()) {
                $statsData = $statsResponse->json();
            }

            return [
                'success' => true,
                'data' => [
                    'likes_count' => $socialData['likesSummary']['totalLikes'] ?? 0,
                    'comments_count' => $socialData['commentsSummary']['totalComments'] ?? 0,
                    'shares_count' => $socialData['sharesSummary']['totalShares'] ?? 0,
                    'reach' => $statsData['elements'][0]['totalShareStatistics']['uniqueImpressionsCount'] ?? 0,
                    'impressions' => $statsData['elements'][0]['totalShareStatistics']['impressionCount'] ?? 0,
                    'clicks' => $statsData['elements'][0]['totalShareStatistics']['clickCount'] ?? 0,
                ]
            ];
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn metrics request failed');
        }
    }

    /**
     * Get LinkedIn user posts
     */
    public function getUserPosts(SocialAccount $account, int $count = 25): array
    {
        try {
            $author = $this->determineAuthor($account);
            
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/ugcPosts", [
                'q' => 'authors',
                'authors' => $author,
                'count' => $count,
                'projection' => '(elements*(id,specificContent,created,lifecycleState))'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return $this->handleApiError($response, 'Failed to get LinkedIn user posts');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn user posts request failed');
        }
    }

    /**
     * Delete LinkedIn post
     */
    public function deletePost(SocialAccount $account, string $postId): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->delete("{$this->baseUrl}/ugcPosts/{$postId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => ['deleted' => true]
                ];
            }

            return $this->handleApiError($response, 'Failed to delete LinkedIn post');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn post deletion failed');
        }
    }

    /**
     * Refresh LinkedIn access token
     */
    public function refreshAccessToken(SocialAccount $account): array
    {
        try {
            $response = Http::timeout(30)->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_id' => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret')
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => [
                        'access_token' => $data['access_token'],
                        'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                        'expires_in' => $data['expires_in'] ?? 5184000 // 60 days default
                    ]
                ];
            }

            return $this->handleApiError($response, 'Failed to refresh LinkedIn access token');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn token refresh failed');
        }
    }

    /**
     * Upload media to LinkedIn
     */
    public function uploadMedia(SocialAccount $account, string $mediaUrl, string $mediaType = 'image'): array
    {
        try {
            $author = $this->determineAuthor($account);
            
            // Register upload
            $registerResponse = Http::timeout(30)->withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->post("{$this->baseUrl}/assets", [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $author,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent'
                        ]
                    ]
                ]
            ]);

            if ($registerResponse->successful()) {
                $registerData = $registerResponse->json();
                $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
                $asset = $registerData['value']['asset'];

                // Upload the actual media
                $mediaContent = file_get_contents($mediaUrl);
                $uploadResponse = Http::timeout(60)->withHeaders([
                    'Authorization' => "Bearer {$account->access_token}"
                ])->withBody($mediaContent, 'application/octet-stream')->put($uploadUrl);

                if ($uploadResponse->successful()) {
                    return [
                        'success' => true,
                        'data' => [
                            'asset' => $asset,
                            'upload_url' => $uploadUrl
                        ]
                    ];
                }
            }

            return $this->handleApiError($registerResponse, 'Failed to upload media to LinkedIn');
        } catch (Exception $e) {
            return $this->handleException($e, 'LinkedIn media upload failed');
        }
    }

    /**
     * Determine the author URN based on account type
     */
    private function determineAuthor(SocialAccount $account, array $postData = []): string
    {
        // If posting to a company page
        if (!empty($postData['company_id'])) {
            return "urn:li:organization:{$postData['company_id']}";
        }
        
        // Default to person
        return "urn:li:person:{$account->platform_user_id}";
    }

    /**
     * Validate LinkedIn webhook signature (if webhooks are implemented)
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        // LinkedIn doesn't currently support webhooks for UGC posts
        // This is a placeholder for future implementation
        return true;
    }

    /**
     * Process LinkedIn webhook data (placeholder)
     */
    public function processWebhookData(array $data): array
    {
        // LinkedIn doesn't currently support webhooks for UGC posts
        // This is a placeholder for future implementation
        return [];
    }

    /**
     * Check LinkedIn API status
     */
    public function checkApiStatus(): array
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get("{$this->baseUrl}/people/(id:~)");

            if ($response->status() === 401) {
                // 401 is expected without auth token, means API is accessible
                return [
                    'status' => 'healthy',
                    'message' => 'LinkedIn API is accessible'
                ];
            }

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'message' => 'LinkedIn API is accessible'
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'LinkedIn API returned non-success status: ' . $response->status()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'LinkedIn API check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test basic connectivity to LinkedIn API
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
     * Get LinkedIn API rate limit status
     */
    public function getRateLimitStatus(): array
    {
        return [
            'platform' => 'linkedin',
            'rate_limit_type' => 'linkedin_api',
            'limits' => [
                'default' => '500 calls per user per day',
                'marketing_api' => '100,000 calls per day per application'
            ]
        ];
    }
}