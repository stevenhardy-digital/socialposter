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
        // Instagram Business API scopes for managed accounts
        $this->scopes = [
            'instagram_basic',
            'instagram_content_publish',
            'pages_show_list',
            'pages_read_engagement',
            'business_management'
        ];
    }

    /**
     * Generate OAuth authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        $state = Str::random(40);
        session(['instagram_oauth_state' => $state]);

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
        $sessionState = session('instagram_oauth_state');
        
        if (!$state || $state !== $sessionState) {
            throw new \Exception('Invalid OAuth state parameter');
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

        // Exchange code for access token
        $tokenResponse = Http::asForm()->post('https://graph.facebook.com/v18.0/oauth/access_token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Instagram token exchange failed', [
                'status' => $tokenResponse->status(),
                'response' => $tokenResponse->body(),
            ]);
            throw new \Exception('Failed to exchange authorization code for access token');
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'];

        // Get user's Facebook pages (which may have Instagram Business accounts)
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $accessToken,
            'fields' => 'id,name,access_token,instagram_business_account'
        ]);

        if (!$pagesResponse->successful()) {
            Log::error('Instagram pages fetch failed', [
                'status' => $pagesResponse->status(),
                'response' => $pagesResponse->body(),
            ]);
            throw new \Exception('Failed to fetch Facebook pages');
        }

        $pagesData = $pagesResponse->json();
        
        // Find pages with Instagram Business accounts
        $instagramAccounts = [];
        foreach ($pagesData['data'] ?? [] as $page) {
            if (isset($page['instagram_business_account']['id'])) {
                $instagramAccounts[] = [
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                    'instagram_id' => $page['instagram_business_account']['id'],
                    'page_access_token' => $page['access_token']
                ];
            }
        }

        if (empty($instagramAccounts)) {
            throw new \Exception('No Instagram Business accounts found. Please ensure your Facebook page is connected to an Instagram Business account.');
        }

        // For now, return the first Instagram account found
        // In a full implementation, you might want to let the user choose
        $primaryAccount = $instagramAccounts[0];

        // Get Instagram account details
        $instagramResponse = Http::get("https://graph.facebook.com/v18.0/{$primaryAccount['instagram_id']}", [
            'access_token' => $primaryAccount['page_access_token'],
            'fields' => 'id,username,name,profile_picture_url'
        ]);

        if (!$instagramResponse->successful()) {
            Log::error('Instagram account details fetch failed', [
                'status' => $instagramResponse->status(),
                'response' => $instagramResponse->body(),
            ]);
            throw new \Exception('Failed to fetch Instagram account details');
        }

        $instagramData = $instagramResponse->json();

        return [
            'id' => $instagramData['id'],
            'name' => $instagramData['username'] ?? $instagramData['name'] ?? 'Instagram Business Account',
            'access_token' => $primaryAccount['page_access_token'],
            'refresh_token' => null, // Facebook tokens don't use refresh tokens in the traditional sense
            'expires_in' => 5184000, // 60 days - Facebook long-lived tokens
            'page_id' => $primaryAccount['page_id'],
            'page_name' => $primaryAccount['page_name'],
            'all_accounts' => $instagramAccounts, // Store all available accounts for future use
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