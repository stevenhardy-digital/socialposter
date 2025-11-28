<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $scopes;

    public function __construct()
    {
        $this->clientId = config('services.instagram.client_id');
        $this->clientSecret = config('services.instagram.client_secret');
        $this->redirectUri = config('services.instagram.redirect');
        
        // Validate credentials are set
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::error('Instagram OAuth credentials missing', [
                'client_id_set' => !empty($this->clientId),
                'client_secret_set' => !empty($this->clientSecret),
                'redirect_uri' => $this->redirectUri,
            ]);
            throw new \Exception('Instagram OAuth credentials not configured. Please set INSTAGRAM_CLIENT_ID and INSTAGRAM_CLIENT_SECRET in your .env file.');
        }
        
        // Instagram Business API scopes - comprehensive permissions
        $this->scopes = [
            'instagram_business_basic',              // Basic profile information and media
            'instagram_business_manage_messages',    // Manage direct messages
            'instagram_business_manage_comments',    // Manage comments on posts
            'instagram_business_content_publish',    // Create and publish content
            'instagram_business_manage_insights'     // Access analytics and insights
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
        session(['instagram_oauth_state' => $state]);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode('%2C', $this->scopes), // URL encode commas
            'response_type' => 'code',
            'state' => $state,
            'force_reauth' => 'true', // Force re-authentication for fresh permissions
        ];

        Log::info('Instagram OAuth URL generated', [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scopes' => $this->scopes,
            'state' => $state,
        ]);

        return 'https://www.instagram.com/oauth/authorize?' . http_build_query($params);
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
            $sessionState = session('instagram_oauth_state');
            if ($state !== $sessionState) {
                throw new \Exception('Invalid OAuth state parameter');
            }
            $stateData = []; // Empty state data for session-based flow
        }

        // Clear the state from session
        session()->forget('instagram_oauth_state');

        // Check for OAuth errors
        if ($request->has('error')) {
            throw new \Exception($request->get('error_description', $request->get('error')));
        }

        $code = $request->get('code');
        if (!$code) {
            throw new \Exception('Authorization code not provided');
        }

        Log::info('Instagram token exchange attempt', [
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'code_length' => strlen($code),
            'code_preview' => substr($code, 0, 10) . '...',
        ]);

        // Exchange code for access token using Instagram's token endpoint
        $tokenResponse = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Instagram token exchange failed', [
                'status' => $tokenResponse->status(),
                'response' => $tokenResponse->body(),
                'request_params' => array_merge([
                    'client_id' => $this->clientId,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri,
                    'code' => substr($code, 0, 10) . '...',
                ], ['client_secret' => '[REDACTED]']),
            ]);
            throw new \Exception('Failed to exchange authorization code for access token: ' . $tokenResponse->body());
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'];

        // Get Instagram Business account information
        $profileInfo = $this->tryGetInstagramProfile($accessToken);
        
        // Use profile info if available, otherwise generate fallback
        $userId = $profileInfo['id'] ?? 'instagram_' . substr(hash('sha256', $accessToken), 0, 16);
        $name = $profileInfo['username'] ?? $profileInfo['name'] ?? 'Instagram Business Account';

        Log::info('Instagram OAuth complete', [
            'profile_info' => $profileInfo,
            'access_token_preview' => substr($accessToken, 0, 20) . '...'
        ]);

        return [
            'id' => $userId,
            'name' => $name,
            'access_token' => $accessToken,
            'refresh_token' => null, // Instagram doesn't use refresh tokens
            'expires_in' => $tokenData['expires_in'] ?? 5184000, // Default to 60 days
            'state_data' => $stateData, // Include the decoded state data
            'profile_info' => $profileInfo,
        ];
    }

    /**
     * Try to get Instagram Business profile information
     */
    private function tryGetInstagramProfile(string $accessToken): array
    {
        $profileInfo = [];
        
        try {
            Log::info('Attempting Instagram Business profile endpoint');
            
            // Try Instagram Business API endpoint
            $response = Http::get('https://graph.instagram.com/me', [
                'access_token' => $accessToken,
                'fields' => 'id,username,name,profile_picture_url,followers_count,media_count'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Instagram Business profile success', ['data' => $data]);
                
                return [
                    'id' => $data['id'] ?? null,
                    'username' => $data['username'] ?? null,
                    'name' => $data['name'] ?? null,
                    'profile_picture_url' => $data['profile_picture_url'] ?? null,
                    'followers_count' => $data['followers_count'] ?? null,
                    'media_count' => $data['media_count'] ?? null,
                    'source' => 'instagram_business_api'
                ];
            } else {
                Log::warning('Instagram Business profile failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Instagram Business profile exception', ['error' => $e->getMessage()]);
        }

        // If Business API fails, return limited info
        Log::info('Instagram Business profile endpoint failed - using fallback');
        
        return [
            'status' => 'limited_access',
            'message' => 'Instagram Business account connected with basic access',
            'source' => 'fallback'
        ];
    }

    /**
     * Refresh/extend access token
     */
    public function refreshToken(string $accessToken): array
    {
        // For Facebook/Instagram, we extend the token rather than refresh it
        $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'fb_exchange_token' => $accessToken,
        ]);

        if (!$response->successful()) {
            Log::error('Instagram token refresh failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to refresh Instagram access token');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => null,
            'expires_in' => $data['expires_in'] ?? 5184000,
        ];
    }

    /**
     * Get available Instagram Business accounts for a user
     */
    public function getAvailableAccounts(string $accessToken): array
    {
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name}'
        ]);

        if (!$pagesResponse->successful()) {
            return [];
        }

        $pagesData = $pagesResponse->json();
        $accounts = [];

        foreach ($pagesData['data'] ?? [] as $page) {
            if (isset($page['instagram_business_account'])) {
                $accounts[] = [
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                    'instagram_id' => $page['instagram_business_account']['id'],
                    'instagram_username' => $page['instagram_business_account']['username'] ?? '',
                    'instagram_name' => $page['instagram_business_account']['name'] ?? $page['name'],
                    'page_access_token' => $page['access_token']
                ];
            }
        }

        return $accounts;
    }
}