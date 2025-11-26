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
        // Basic LinkedIn scopes that should work for most apps
        $this->scopes = [
            'r_basicprofile',                     // Basic profile access (current standard)
            'w_member_social',                   // Post to LinkedIn
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

        // Skip profile fetch due to LinkedIn API permissions issues
        // Generate a unique user ID from the access token for now
        Log::info('Skipping LinkedIn profile fetch due to API restrictions', [
            'access_token_preview' => substr($accessToken, 0, 20) . '...',
            'reason' => 'LinkedIn API returning ACCESS_DENIED for profile endpoints',
        ]);

        // Create a unique user ID from the access token hash
        $userId = 'linkedin_' . substr(hash('sha256', $accessToken), 0, 16);
        $name = 'LinkedIn User';

        // Try to get additional profile information if possible
        $profileInfo = $this->tryGetProfileInfo($accessToken);
        $companyPages = $this->tryGetCompanyPages($accessToken);

        return [
            'id' => $profileInfo['id'] ?? $userId,
            'name' => $profileInfo['name'] ?? $name,
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in' => $tokenData['expires_in'] ?? 5184000, // 60 days default
            'state_data' => $stateData, // Include the decoded state data
            'profile_info' => $profileInfo,
            'company_pages' => $companyPages,
        ];
    }



    /**
     * Try to get profile information with various endpoints
     */
    private function tryGetProfileInfo(string $accessToken): array
    {
        $profileInfo = [];

        // Try different LinkedIn API endpoints to get profile info
        $endpoints = [
            // Basic profile with minimal fields
            [
                'url' => 'https://api.linkedin.com/v2/people/~',
                'params' => ['projection' => '(id)'],
                'name' => 'basic_profile'
            ],
            // Try userinfo endpoint (OpenID Connect)
            [
                'url' => 'https://api.linkedin.com/v2/userinfo',
                'params' => [],
                'name' => 'userinfo'
            ],
            // Try profile with different projection
            [
                'url' => 'https://api.linkedin.com/v2/people/~',
                'params' => ['projection' => '(id,localizedFirstName,localizedLastName)'],
                'name' => 'localized_profile'
            ]
        ];

        foreach ($endpoints as $endpoint) {
            try {
                Log::info("Trying LinkedIn endpoint: {$endpoint['name']}", [
                    'url' => $endpoint['url'],
                    'params' => $endpoint['params']
                ]);

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])->get($endpoint['url'], $endpoint['params']);

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info("LinkedIn endpoint {$endpoint['name']} succeeded", [
                        'data' => $data
                    ]);

                    // Parse the response based on endpoint type
                    if ($endpoint['name'] === 'userinfo' && isset($data['sub'])) {
                        $profileInfo = [
                            'id' => $data['sub'],
                            'name' => $data['name'] ?? ($data['given_name'] . ' ' . $data['family_name']),
                            'email' => $data['email'] ?? null,
                            'source' => 'userinfo'
                        ];
                        break;
                    } elseif (isset($data['id'])) {
                        $name = 'LinkedIn User';
                        if (isset($data['localizedFirstName'], $data['localizedLastName'])) {
                            $name = trim($data['localizedFirstName'] . ' ' . $data['localizedLastName']);
                        }
                        $profileInfo = [
                            'id' => $data['id'],
                            'name' => $name,
                            'source' => $endpoint['name']
                        ];
                        break;
                    }
                } else {
                    Log::warning("LinkedIn endpoint {$endpoint['name']} failed", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("LinkedIn endpoint {$endpoint['name']} exception", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $profileInfo;
    }

    /**
     * Try to get company pages the user manages
     */
    private function tryGetCompanyPages(string $accessToken): array
    {
        $companyPages = [];

        try {
            Log::info('Attempting to fetch LinkedIn company pages');

            // Try to get organizations the user administers
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/organizationAcls', [
                'q' => 'roleAssignee',
                'projection' => '(elements*(organization~(id,name,logoV2)))'
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
                                'name' => $org['name'] ?? 'Unknown Company',
                                'logo' => $org['logoV2'] ?? null,
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
        $details = [
            'profile' => $this->tryGetProfileInfo($accessToken),
            'company_pages' => $this->tryGetCompanyPages($accessToken),
            'permissions' => $this->getTokenPermissions($accessToken),
        ];

        return $details;
    }

    /**
     * Get the permissions/scopes for the current access token
     */
    private function getTokenPermissions(string $accessToken): array
    {
        try {
            // Try to introspect the token to see what permissions it has
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0'
            ])->get('https://api.linkedin.com/v2/people/~', [
                'projection' => '(id)'
            ]);

            $permissions = ['basic_profile' => $response->successful()];

            // Test other endpoints to see what's available
            $testEndpoints = [
                'organizations' => 'https://api.linkedin.com/v2/organizationAcls?q=roleAssignee',
                'userinfo' => 'https://api.linkedin.com/v2/userinfo',
            ];

            foreach ($testEndpoints as $name => $url) {
                try {
                    $testResponse = Http::withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'X-Restli-Protocol-Version' => '2.0.0'
                    ])->get($url);
                    
                    $permissions[$name] = $testResponse->successful();
                } catch (\Exception $e) {
                    $permissions[$name] = false;
                }
            }

            return $permissions;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}