<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $scopes;

    public function __construct()
    {
        $this->clientId = config('services.linkedin.client_id');
        $this->clientSecret = config('services.linkedin.client_secret');
        $this->redirectUri = config('services.linkedin.redirect');
        
        // Validate credentials are set
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::error('LinkedIn OAuth credentials missing', [
                'client_id_set' => !empty($this->clientId),
                'client_secret_set' => !empty($this->clientSecret),
                'redirect_uri' => $this->redirectUri,
            ]);
            throw new \Exception('LinkedIn OAuth credentials not configured. Please set LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET in your .env file.');
        }
        
        // Debug log the configuration
        Log::info('LinkedIn OAuth Service initialized', [
            'client_id' => $this->clientId,
            'client_id_length' => strlen($this->clientId),
            'client_secret_length' => strlen($this->clientSecret),
            'redirect_uri' => $this->redirectUri,
            'app_url' => config('app.url'),
        ]);
        // LinkedIn scopes - comprehensive permissions for full platform functionality
        $this->scopes = [
            // Basic profile and authentication
            'r_basicprofile',                    // Access basic profile information including name, photo, headline, and public profile URL
            
            // Member permissions
            'r_member_postAnalytics',            // Access analytics data for member posts and content performance
            'r_organization_followers',          // Access follower count and demographics for organizations you manage
            'r_organization_social',             // Read organization's social content, posts, and engagement data
            'rw_organization_admin',             // Full administrative access to manage organization pages and settings
            'r_organization_social_feed',        // Read organization's social feed content and interactions
            
            // Organization permissions
            'w_member_social',                   // Create and manage social content on behalf of the member
            'r_member_profileAnalytics',         // Access member profile analytics including views and search appearances
            'w_organization_social',             // Create and manage social content for organizations you administer
            'w_organization_social_feed',        // Manage organization's social feed content and interactions
            'w_member_social_feed',              // Create and manage member's social feed content
            'r_1st_connections_size'             // Access the count of first-degree connections in your network
        ];
    }

    /**
     * Generate OAuth authorization URL
     */
    public function getAuthorizationUrl(array $userData = []): string
    {
        // Use the database state key if provided, otherwise create a simple state
        $state = $userData['state_key'] ?? Str::random(40);
        
        // Store in session as backup
        session(['linkedin_oauth_state' => $state]);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => implode(' ', $this->scopes),
        ];

        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback and exchange code for tokens
     */
    public function handleCallback(Request $request): array
    {
        // Get state parameter
        $state = $request->get('state');
        if (!$state) {
            throw new \Exception('OAuth state parameter missing');
        }

        // Try to get state data from database first
        $stateData = \App\Models\OAuthState::consumeState($state);
        
        if (!$stateData) {
            // Fallback to session verification for backward compatibility
            $sessionState = session('linkedin_oauth_state');
            if ($state !== $sessionState) {
                throw new \Exception('Invalid OAuth state parameter');
            }
            $stateData = []; // Empty state data for session-based flow
        }

        // Clear the state from session
        session()->forget('linkedin_oauth_state');

        // Check for OAuth errors
        if ($request->has('error')) {
            throw new \Exception($request->get('error_description', $request->get('error')));
        }

        $code = $request->get('code');
        if (!$code) {
            throw new \Exception('Authorization code not provided');
        }

        // Exchange code for access token
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        Log::info('LinkedIn token exchange attempt', [
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'code_length' => strlen($code),
            'code_preview' => substr($code, 0, 10) . '...',
        ]);

        $tokenResponse = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', $tokenParams);

        if (!$tokenResponse->successful()) {
            Log::error('LinkedIn token exchange failed', [
                'status' => $tokenResponse->status(),
                'response' => $tokenResponse->body(),
                'request_params' => array_merge($tokenParams, ['client_secret' => '[REDACTED]']),
            ]);
            throw new \Exception('Failed to exchange authorization code for access token: ' . $tokenResponse->body());
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'];

        // Try to get profile information with updated scopes
        $profileInfo = $this->tryGetProfileInfo($accessToken);
        $companyPages = $this->tryGetCompanyPages($accessToken);

        // Use profile info if available, otherwise generate fallback
        $userId = $profileInfo['id'] ?? 'linkedin_' . substr(hash('sha256', $accessToken), 0, 16);
        $name = $profileInfo['name'] ?? 'LinkedIn User';

        Log::info('LinkedIn OAuth complete with additional data', [
            'profile_info' => $profileInfo,
            'company_pages' => $companyPages,
            'access_token_preview' => substr($accessToken, 0, 20) . '...'
        ]);

        return [
            'id' => $userId,
            'name' => $name,
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in' => $tokenData['expires_in'] ?? 5184000, // 60 days default
            'state_data' => $stateData, // Include the decoded state data
            'profile_info' => $profileInfo,
            'company_pages' => $companyPages,
        ];
    }



    /**
     * Try to get profile information with available permissions
     */
    private function tryGetProfileInfo(string $accessToken): array
    {
        $profileInfo = [];
        
        // Try the /me endpoint (standard LinkedIn API)
        try {
            Log::info('Attempting LinkedIn /me endpoint');
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/me');

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn /me endpoint success', ['data' => $data]);
                
                $profileInfo = [
                    'id' => $data['id'] ?? null,
                    'name' => $this->formatLinkedInName($data),
                    'headline' => $data['headline'] ?? null,
                    'public_profile_url' => $data['publicProfileUrl'] ?? null,
                    'source' => 'me_endpoint'
                ];
                
                // Try to get additional profile analytics if available
                $analytics = $this->tryGetProfileAnalytics($accessToken);
                if ($analytics) {
                    $profileInfo['analytics'] = $analytics;
                }
                
                // Try to get connections count if available
                $connectionsCount = $this->tryGetConnectionsCount($accessToken);
                if ($connectionsCount !== null) {
                    $profileInfo['connections_count'] = $connectionsCount;
                }
                
                return $profileInfo;
            } else {
                Log::warning('LinkedIn /me endpoint failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LinkedIn /me endpoint exception', ['error' => $e->getMessage()]);
        }

        // Try basic profile endpoint with minimal fields as fallback
        try {
            Log::info('Attempting LinkedIn basic profile endpoint');
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/people/~', [
                'projection' => '(id,localizedFirstName,localizedLastName)'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn basic profile success', ['data' => $data]);
                
                return [
                    'id' => $data['id'] ?? null,
                    'name' => trim(($data['localizedFirstName'] ?? '') . ' ' . ($data['localizedLastName'] ?? '')),
                    'source' => 'basic_profile'
                ];
            } else {
                Log::warning('LinkedIn endpoint basic profile failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LinkedIn basic profile exception', ['error' => $e->getMessage()]);
        }

        // If all endpoints fail, return limited info
        Log::info('All LinkedIn profile endpoints failed - using fallback');
        
        return [
            'status' => 'limited_access',
            'message' => 'LinkedIn app needs appropriate products enabled (Sign In with LinkedIn or Marketing Developer Platform)',
            'required_products' => [
                'Sign In with LinkedIn using OpenID Connect',
                'Marketing Developer Platform'
            ],
            'current_capabilities' => ['Basic OAuth', 'Posting (w_member_social)'],
            'source' => 'fallback'
        ];
    }

    /**
     * Format LinkedIn name from API response
     */
    private function formatLinkedInName(array $data): string
    {
        // Try localized names first
        if (isset($data['localizedFirstName']) || isset($data['localizedLastName'])) {
            return trim(($data['localizedFirstName'] ?? '') . ' ' . ($data['localizedLastName'] ?? ''));
        }
        
        // Try firstName/lastName structure
        if (isset($data['firstName']['localized']) || isset($data['lastName']['localized'])) {
            $firstName = '';
            $lastName = '';
            
            if (isset($data['firstName']['localized'])) {
                $firstName = reset($data['firstName']['localized']);
            }
            
            if (isset($data['lastName']['localized'])) {
                $lastName = reset($data['lastName']['localized']);
            }
            
            return trim($firstName . ' ' . $lastName);
        }
        
        return 'LinkedIn User';
    }



    /**
     * Try to get profile analytics data
     */
    private function tryGetProfileAnalytics(string $accessToken): ?array
    {
        try {
            Log::info('Attempting to get LinkedIn profile analytics');
            
            // Try member profile analytics endpoint
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/networkSizes/urn:li:person:~');

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn profile analytics success', ['data' => $data]);
                
                return [
                    'network_sizes' => $data,
                    'source' => 'network_sizes_endpoint'
                ];
            }

            // Fallback to basic profile statistics
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/people/~', [
                'projection' => '(id,profileStatistics)'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn profile analytics fallback success', ['data' => $data]);
                
                return [
                    'profile_statistics' => $data['profileStatistics'] ?? null,
                    'source' => 'profile_statistics'
                ];
            } else {
                Log::warning('LinkedIn profile analytics failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LinkedIn profile analytics exception', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Try to get connections count
     */
    private function tryGetConnectionsCount(string $accessToken): ?int
    {
        try {
            Log::info('Attempting to get LinkedIn connections count');
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/people/~/network/network-sizes', [
                'edgeType' => 'FIRST_DEGREE'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn connections count success', ['data' => $data]);
                
                return $data['firstDegreeSize'] ?? null;
            } else {
                Log::warning('LinkedIn connections count failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LinkedIn connections count exception', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Try to get company pages the user manages
     */
    private function tryGetCompanyPages(string $accessToken): array
    {
        $companyPages = [];

        try {
            Log::info('Attempting to fetch LinkedIn company pages');

            // Try to get organizations the user administers with enhanced projection
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/organizationAcls', [
                'q' => 'roleAssignee',
                'projection' => '(elements*(organization~(id,name,localizedName,logoV2),roleAssignee,role))'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn company pages response', [
                    'data' => $data
                ]);

                if (isset($data['elements'])) {
                    foreach ($data['elements'] as $element) {
                        if (isset($element['organization~'])) {
                            $org = $element['organization~'];
                            $companyPages[] = [
                                'id' => $org['id'],
                                'name' => $org['localizedName'] ?? $org['name'] ?? 'Unknown Company',
                                'role' => $element['role'] ?? 'UNKNOWN',
                                'logo' => $org['logoV2'] ?? null,
                                'permissions' => $this->getOrganizationPermissions($element['role'] ?? 'UNKNOWN')
                            ];
                        }
                    }
                }
            } else {
                Log::warning('LinkedIn company pages fetch failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LinkedIn company pages exception', [
                'error' => $e->getMessage()
            ]);
        }

        return $companyPages;
    }

    /**
     * Get organization permissions based on role
     */
    private function getOrganizationPermissions(string $role): array
    {
        $permissions = [
            'ADMINISTRATOR' => [
                'can_post' => true,
                'can_manage' => true,
                'can_view_analytics' => true,
                'can_manage_followers' => true
            ],
            'CONTENT_ADMIN' => [
                'can_post' => true,
                'can_manage' => false,
                'can_view_analytics' => true,
                'can_manage_followers' => false
            ],
            'ORGANIC_POSTER' => [
                'can_post' => true,
                'can_manage' => false,
                'can_view_analytics' => false,
                'can_manage_followers' => false
            ],
            'UNKNOWN' => [
                'can_post' => false,
                'can_manage' => false,
                'can_view_analytics' => false,
                'can_manage_followers' => false
            ]
        ];

        return $permissions[$role] ?? $permissions['UNKNOWN'];
    }

    /**
     * Try to get member posts data
     */
    private function tryGetMemberPosts(string $accessToken): ?array
    {
        try {
            Log::info('Attempting to get LinkedIn member posts');
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/shares', [
                'q' => 'owners',
                'owners' => 'urn:li:person:~',
                'sortBy' => 'CREATED_TIME',
                'sharesPerOwner' => 10
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('LinkedIn member posts success', ['data' => $data]);
                
                return $data;
            } else {
                Log::warning('LinkedIn member posts failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LinkedIn member posts exception', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!$response->successful()) {
            Log::error('LinkedIn token refresh failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to refresh LinkedIn access token');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $refreshToken,
            'expires_in' => $data['expires_in'] ?? 5184000,
        ];
    }

    /**
     * Get comprehensive account information for a connected LinkedIn account
     */
    public function getAccountDetails(string $accessToken): array
    {
        Log::info('Getting LinkedIn account details', [
            'access_token_preview' => substr($accessToken, 0, 20) . '...'
        ]);

        $details = [
            'profile' => $this->tryGetProfileInfo($accessToken),
            'company_pages' => $this->tryGetCompanyPages($accessToken),
            'permissions' => $this->getTokenPermissions($accessToken),
        ];

        Log::info('LinkedIn account details retrieved', [
            'details' => $details
        ]);

        // Add comprehensive permission testing
        $details['scope_verification'] = $this->verifyAllScopes($accessToken);
        $details['member_posts'] = $this->tryGetMemberPosts($accessToken);

        return $details;
    }

    /**
     * Verify all requested scopes are working
     */
    private function verifyAllScopes(string $accessToken): array
    {
        $scopeStatus = [];
        
        // Map scopes to their verification methods
        $scopeVerification = [
            'r_basicprofile' => 'basic_profile',
            'w_member_social' => 'member_social',
            'w_member_social_feed' => 'member_social',
            'r_member_profile' => 'member_profile_analytics',
            'r_member_post' => 'member_posts',
            'r_1st_connections_size' => 'connections_size',
            'rw_organization_admin' => 'organizations',
            'w_organization_social' => 'organization_social',
            'w_organization_social_feed' => 'organization_social',
            'r_organization_social' => 'organization_social',
            'r_organization_social_feed' => 'organization_social',
            'r_organization_followers' => 'organizations'
        ];

        $permissions = $this->getTokenPermissions($accessToken);

        foreach ($this->scopes as $scope) {
            $testKey = $scopeVerification[$scope] ?? null;
            $scopeStatus[$scope] = [
                'requested' => true,
                'granted' => $testKey ? ($permissions[$testKey] ?? false) : false,
                'test_endpoint' => $testKey
            ];
        }

        return [
            'total_scopes' => count($this->scopes),
            'granted_scopes' => count(array_filter($scopeStatus, fn($s) => $s['granted'])),
            'scope_details' => $scopeStatus,
            'all_granted' => count(array_filter($scopeStatus, fn($s) => $s['granted'])) === count($this->scopes)
        ];
    }

    /**
     * Get the permissions/scopes for the current access token
     */
    private function getTokenPermissions(string $accessToken): array
    {
        $permissions = [];
        
        // Test endpoints for each scope to verify permissions
        $scopeTests = [
            'basic_profile' => [
                'url' => 'https://api.linkedin.com/v2/people/~',
                'params' => ['projection' => '(id,localizedFirstName,localizedLastName)']
            ],
            'member_profile_analytics' => [
                'url' => 'https://api.linkedin.com/v2/networkSizes/urn:li:person:~',
                'params' => []
            ],
            'member_posts' => [
                'url' => 'https://api.linkedin.com/v2/shares',
                'params' => ['q' => 'owners', 'owners' => 'urn:li:person:~', 'count' => 1]
            ],
            'member_social' => [
                'url' => 'https://api.linkedin.com/v2/people/~',
                'params' => ['projection' => '(id)']
            ],
            'organizations' => [
                'url' => 'https://api.linkedin.com/v2/organizationAcls',
                'params' => ['q' => 'roleAssignee', 'count' => 1]
            ],
            'organization_social' => [
                'url' => 'https://api.linkedin.com/v2/organizationalEntityShareStatistics',
                'params' => ['q' => 'organizationalEntity', 'count' => 1]
            ],
            'connections_size' => [
                'url' => 'https://api.linkedin.com/v2/people/~/network/network-sizes',
                'params' => ['edgeType' => 'FIRST_DEGREE']
            ]
        ];

        foreach ($scopeTests as $scope => $test) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])->get($test['url'], $test['params']);

                $permissions[$scope] = $response->successful();
                
                if ($response->successful()) {
                    Log::info("LinkedIn scope test passed: {$scope}");
                } else {
                    Log::warning("LinkedIn scope test failed: {$scope}", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                $permissions[$scope] = false;
                Log::warning("LinkedIn scope test exception: {$scope}", ['error' => $e->getMessage()]);
            }
        }

        return $permissions;
    }
}