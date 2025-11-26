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
        // Comprehensive LinkedIn scopes for full functionality
        $this->scopes = [
            // Basic profile and connections
            'r_basicprofile',                    // Basic profile including name, photo, headline
            'r_1st_connections_size',            // Number of 1st-degree connections
            
            // Member social and analytics
            'w_member_social',                   // Create, modify, delete posts/comments/reactions
            'w_member_social_feed',              // Create, modify, delete comments/reactions on posts
            'r_member_postAnalytics',            // Retrieve posts and reporting data
            'r_member_profileAnalytics',         // Profile analytics, viewers, followers, search appearances
            
            // Organization management and social
            'rw_organization_admin',             // Manage organization pages and retrieve reporting data
            'w_organization_social',             // Create, modify, delete posts/comments/reactions for organization
            'w_organization_social_feed',        // Create, modify, delete comments/reactions on organization posts
            'r_organization_social',             // Retrieve organization posts, comments, reactions, engagement data
            'r_organization_social_feed',        // Retrieve comments, reactions, engagement data on organization posts
            'r_organization_followers',          // Use followers' data for mentions in posts
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

        // Get user profile information using current LinkedIn v2 API
        $profileResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-Restli-Protocol-Version' => '2.0.0'
        ])->get('https://api.linkedin.com/v2/userinfo');

        if (!$profileResponse->successful()) {
            Log::error('LinkedIn profile fetch failed', [
                'status' => $profileResponse->status(),
                'response' => $profileResponse->body(),
                'access_token_preview' => substr($accessToken, 0, 20) . '...',
            ]);
            throw new \Exception('Failed to fetch user profile from LinkedIn');
        }

        $profileData = $profileResponse->json();

        Log::info('LinkedIn profile data received', [
            'profile_keys' => array_keys($profileData),
            'profile_data' => $profileData,
        ]);

        return [
            'id' => $profileData['sub'], // LinkedIn userinfo uses 'sub' for user ID
            'name' => $profileData['name'] ?? ($profileData['given_name'] . ' ' . $profileData['family_name']),
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in' => $tokenData['expires_in'] ?? 5184000, // 60 days default
            'state_data' => $stateData, // Include the decoded state data
        ];
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
}