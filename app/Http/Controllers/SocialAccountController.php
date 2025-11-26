<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class SocialAccountController extends Controller
{
    /**
     * Get all connected social accounts for the authenticated user
     */
    public function index(): JsonResponse
    {
        $accounts = Auth::user()->socialAccounts()->get();
        
        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }

    /**
     * Initiate OAuth flow for a social media platform
     */
    public function connect(string $platform): JsonResponse
    {
        try {
            $validPlatforms = ['instagram', 'facebook', 'linkedin'];
            
            if (!in_array($platform, $validPlatforms)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid platform specified'
                ], 400);
            }

            // Check if session is available
            if (!session()->isStarted()) {
                session()->start();
            }

            // Configure platform-specific scopes
            $scopes = $this->getScopesForPlatform($platform);
            
            $redirectUrl = Socialite::driver($platform)
                ->scopes($scopes)
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'success' => true,
                'redirect_url' => $redirectUrl,
                'platform' => $platform
            ]);
        } catch (Exception $e) {
            Log::error('OAuth initiation failed', [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'session_started' => session()->isStarted(),
                'session_driver' => config('session.driver'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate OAuth flow: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle OAuth callback and store tokens
     */
    public function callback(string $platform, Request $request): JsonResponse
    {
        try {
            // Check for OAuth errors
            if ($request->has('error')) {
                return response()->json([
                    'success' => false,
                    'message' => 'OAuth authorization failed: ' . $request->get('error_description', $request->get('error')),
                    'error_code' => $request->get('error'),
                    'platform' => $platform
                ], 400);
            }

            $socialiteUser = Socialite::driver($platform)->user();
            
            // Store or update social account
            $socialAccount = SocialAccount::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'platform' => $platform,
                    'platform_user_id' => $socialiteUser->getId()
                ],
                [
                    'access_token' => $socialiteUser->token,
                    'refresh_token' => $socialiteUser->refreshToken,
                    'expires_at' => $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null,
                    'account_name' => $socialiteUser->getName() ?? $socialiteUser->getNickname()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => ucfirst($platform) . ' account connected successfully',
                'account' => $socialAccount->makeHidden(['access_token', 'refresh_token'])
            ]);
        } catch (Exception $e) {
            Log::error('OAuth callback failed', [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect ' . ucfirst($platform) . ' account',
                'platform' => $platform
            ], 500);
        }
    }

    /**
     * Disconnect a social media account
     */
    public function disconnect(SocialAccount $socialAccount): JsonResponse
    {
        try {
            // Verify the account belongs to the authenticated user
            if ($socialAccount->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $platform = $socialAccount->platform;
            $accountName = $socialAccount->account_name;

            // Attempt to revoke tokens with the platform
            $this->revokeTokens($socialAccount);

            // Delete the social account
            $socialAccount->delete();

            return response()->json([
                'success' => true,
                'message' => ucfirst($platform) . ' account disconnected successfully',
                'platform' => $platform,
                'account_name' => $accountName
            ]);
        } catch (Exception $e) {
            Log::error('Account disconnection failed', [
                'account_id' => $socialAccount->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect account'
            ], 500);
        }
    }

    /**
     * Refresh access token for a social account
     */
    public function refreshToken(SocialAccount $socialAccount): JsonResponse
    {
        try {
            // Verify the account belongs to the authenticated user
            if ($socialAccount->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Attempt to refresh the token
            $refreshedUser = Socialite::driver($socialAccount->platform)
                ->refreshToken($socialAccount->refresh_token);

            // Update the stored tokens
            $socialAccount->update([
                'access_token' => $refreshedUser->token,
                'refresh_token' => $refreshedUser->refreshToken ?? $socialAccount->refresh_token,
                'expires_at' => $refreshedUser->expiresIn ? now()->addSeconds($refreshedUser->expiresIn) : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'account' => $socialAccount->makeHidden(['access_token', 'refresh_token'])
            ]);
        } catch (Exception $e) {
            Log::error('Token refresh failed', [
                'account_id' => $socialAccount->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Get platform-specific OAuth scopes
     */
    private function getScopesForPlatform(string $platform): array
    {
        return match($platform) {
            'instagram' => ['instagram_basic', 'instagram_content_publish'],
            'facebook' => ['pages_manage_posts', 'pages_read_engagement'],
            'linkedin' => ['w_member_social', 'r_organization_social'],
            default => []
        };
    }

    /**
     * Subscribe to webhooks for a social account
     */
    public function subscribeToWebhooks(SocialAccount $socialAccount, Request $request): JsonResponse
    {
        try {
            // Verify the account belongs to the authenticated user
            if ($socialAccount->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $socialMediaService = app(\App\Services\SocialMediaApiService::class);
            $fields = $request->input('fields', ['feed', 'reactions', 'comments']);
            
            $result = $socialMediaService->subscribeToWebhooks($socialAccount, $fields);

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Webhook subscription failed', [
                'account_id' => $socialAccount->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe to webhooks'
            ], 500);
        }
    }

    /**
     * Get account information from the platform
     */
    public function getAccountInfo(SocialAccount $socialAccount): JsonResponse
    {
        try {
            // Verify the account belongs to the authenticated user
            if ($socialAccount->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $socialMediaService = app(\App\Services\SocialMediaApiService::class);
            $result = $socialMediaService->getAccountInfo($socialAccount);

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Account info retrieval failed', [
                'account_id' => $socialAccount->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get account information'
            ], 500);
        }
    }

    /**
     * Attempt to revoke tokens with the platform
     */
    private function revokeTokens(SocialAccount $socialAccount): void
    {
        try {
            // Platform-specific token revocation logic would go here
            // For now, we'll just log the attempt
            Log::info('Token revocation attempted', [
                'platform' => $socialAccount->platform,
                'account_id' => $socialAccount->id
            ]);
        } catch (Exception $e) {
            Log::warning('Token revocation failed', [
                'platform' => $socialAccount->platform,
                'account_id' => $socialAccount->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}