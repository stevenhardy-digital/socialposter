<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $scopes;

    public function __construct()
    {
        $this->clientId = config('services.facebook.client_id');
        $this->clientSecret = config('services.facebook.client_secret');
        $this->redirectUri = config('services.facebook.redirect');
        // Facebook Business API scopes for page management
        $this->scopes = [
            'pages_manage_posts',
            'pages_read_engagement',
            'pages_show_list',
            'business_management',
            'pages_manage_metadata'
        ];
    }

    /**
     * Generate OAuth authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        $state = Str::random(40);
        session(['facebook_oauth_state' => $state]);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(',', $this->scopes),
            'response_type' => 'code',
            'state' => $state,
        ];

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback and exchange code for tokens
     */
    public function handleCallback(Request $request): array
    {
        // Verify state parameter
        $state = $request->get('state');
        $sessionState = session('facebook_oauth_state');
        
        if (!$state || $state !== $sessionState) {
            throw new \Exception('Invalid OAuth state parameter');
        }

        // Clear the state from session
        session()->forget('facebook_oauth_state');

        // Check for OAuth errors
        if ($request->has('error')) {
            throw new \Exception($request->get('error_description', $request->get('error')));
        }

        $code = $request->get('code');
        if (!$code) {
            throw new \Exception('Authorization code not provided');
        }

        // Exchange code for access token
        $tokenResponse = Http::asForm()->post('https://graph.facebook.com/v18.0/oauth/access_token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Facebook token exchange failed', [
                'status' => $tokenResponse->status(),
                'response' => $tokenResponse->body(),
            ]);
            throw new \Exception('Failed to exchange authorization code for access token');
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'];

        // Get user's Facebook pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token,category,fan_count'
        ]);

        if (!$pagesResponse->successful()) {
            Log::error('Facebook pages fetch failed', [
                'status' => $pagesResponse->status(),
                'response' => $pagesResponse->body(),
            ]);
            throw new \Exception('Failed to fetch Facebook pages');
        }

        $pagesData = $pagesResponse->json();
        
        if (empty($pagesData['data'])) {
            throw new \Exception('No Facebook pages found. Please ensure you have admin access to at least one Facebook page.');
        }

        // For now, return the first page found
        // In a full implementation, you might want to let the user choose
        $primaryPage = $pagesData['data'][0];

        // Get user info for the account name
        $userResponse = Http::get('https://graph.facebook.com/v18.0/me', [
            'access_token' => $accessToken,
            'fields' => 'id,name'
        ]);

        $userName = 'Facebook User';
        if ($userResponse->successful()) {
            $userData = $userResponse->json();
            $userName = $userData['name'] ?? 'Facebook User';
        }

        return [
            'id' => $primaryPage['id'],
            'name' => $primaryPage['name'] ?? $userName,
            'access_token' => $primaryPage['access_token'], // Use page access token
            'refresh_token' => null, // Facebook tokens don't use refresh tokens in the traditional sense
            'expires_in' => 5184000, // 60 days - Facebook long-lived tokens
            'user_access_token' => $accessToken, // Store user token for future page queries
            'all_pages' => $pagesData['data'], // Store all available pages for future use
        ];
    }

    /**
     * Refresh/extend access token
     */
    public function refreshToken(string $accessToken): array
    {
        // For Facebook, we extend the token rather than refresh it
        $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'fb_exchange_token' => $accessToken,
        ]);

        if (!$response->successful()) {
            Log::error('Facebook token refresh failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            throw new \Exception('Failed to refresh Facebook access token');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => null,
            'expires_in' => $data['expires_in'] ?? 5184000,
        ];
    }

    /**
     * Get available Facebook pages for a user
     */
    public function getAvailablePages(string $userAccessToken): array
    {
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $userAccessToken,
            'fields' => 'id,name,access_token,category,fan_count,instagram_business_account{id,username}'
        ]);

        if (!$pagesResponse->successful()) {
            return [];
        }

        $pagesData = $pagesResponse->json();
        return $pagesData['data'] ?? [];
    }
}