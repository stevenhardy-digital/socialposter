<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
     * Initiate OAuth flow from web route (with session support)
     */
    public function webConnect(string $platform, Request $request): JsonResponse
    {
        try {
            $validPlatforms = ['instagram', 'facebook', 'linkedin'];
            
            if (!in_array($platform, $validPlatforms)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid platform specified'
                ], 400);
            }

            // Store user ID in session for callback
            session(['oauth_user_id' => Auth::id()]);
            session(['oauth_platform' => $platform]);

            // Use custom OAuth services for all platforms
            $redirectUrl = match ($platform) {
                'linkedin' => app(\App\Services\LinkedInOAuthService::class)->getAuthorizationUrl(),
                'instagram' => app(\App\Services\InstagramOAuthService::class)->getAuthorizationUrl(),
                'facebook' => app(\App\Services\FacebookOAuthService::class)->getAuthorizationUrl(),
                default => throw new \Exception("Unsupported platform: {$platform}")
            };

            return response()->json([
                'success' => true,
                'redirect_url' => $redirectUrl,
                'platform' => $platform
            ]);
        } catch (Exception $e) {
            Log::error('OAuth initiation failed (web route)', [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'session_started' => session()->isStarted(),
                'session_driver' => config('session.driver'),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate OAuth flow: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle OAuth callback from web route and redirect to frontend
     */
    public function webCallback(string $platform, Request $request)
    {
        try {
            // Check for OAuth errors
            if ($request->has('error')) {
                Log::error('OAuth callback error', [
                    'platform' => $platform,
                    'error' => $request->get('error'),
                    'error_description' => $request->get('error_description'),
                    'state' => $request->get('state'),
                ]);

                // Clean up session data
                session()->forget(['oauth_user_id', 'oauth_platform']);

                // Redirect to frontend with error
                $errorMessage = urlencode($request->get('error_description', $request->get('error')));
                return redirect("/#/accounts?oauth_error={$platform}&message={$errorMessage}");
            }

            // Get user ID from session (stored during OAuth initiation)
            $userId = session('oauth_user_id');
            $sessionPlatform = session('oauth_platform');
            
            if (!$userId || $sessionPlatform !== $platform) {
                Log::warning('OAuth callback without valid session', [
                    'platform' => $platform,
                    'session_platform' => $sessionPlatform,
                    'user_id' => $userId,
                    'session_id' => session()->getId(),
                ]);
                return redirect("/#/login?message=" . urlencode("OAuth session expired. Please try connecting your {$platform} account again."));
            }

            $user = \App\Models\User::find($userId);
            if (!$user) {
                return redirect("/#/login?message=" . urlencode("User not found. Please log in and try again."));
            }

            // Use custom OAuth services for all platforms
            $userData = match ($platform) {
                'linkedin' => app(\App\Services\LinkedInOAuthService::class)->handleCallback($request),
                'instagram' => app(\App\Services\InstagramOAuthService::class)->handleCallback($request),
                'facebook' => app(\App\Services\FacebookOAuthService::class)->handleCallback($request),
                default => throw new \Exception("Unsupported platform: {$platform}")
            };
            
            $socialAccount = SocialAccount::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform' => $platform,
                    'platform_user_id' => $userData['id']
                ],
                [
                    'access_token' => $userData['access_token'],
                    'refresh_token' => $userData['refresh_token'],
                    'expires_at' => $userData['expires_in'] ? now()->addSeconds($userData['expires_in']) : null,
                    'account_name' => $userData['name']
                ]
            );

            // Clean up session data
            session()->forget(['oauth_user_id', 'oauth_platform']);

            // Redirect to frontend with success
            return redirect("/#/accounts?oauth_success={$platform}&account=" . urlencode($socialAccount->account_name));

        } catch (Exception $e) {
            Log::error('OAuth callback failed', [
                'platform' => $platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => session('oauth_user_id'),
            ]);

            // Clean up session data
            session()->forget(['oauth_user_id', 'oauth_platform']);

            $errorMessage = urlencode("Failed to connect {$platform} account: " . $e->getMessage());
            return redirect("/#/accounts?oauth_error={$platform}&message={$errorMessage}");
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

            // Use custom OAuth services for all platforms
            $tokenData = match ($socialAccount->platform) {
                'linkedin' => app(\App\Services\LinkedInOAuthService::class)->refreshToken($socialAccount->refresh_token ?? $socialAccount->access_token),
                'instagram' => app(\App\Services\InstagramOAuthService::class)->refreshToken($socialAccount->access_token),
                'facebook' => app(\App\Services\FacebookOAuthService::class)->refreshToken($socialAccount->access_token),
                default => throw new \Exception("Unsupported platform: {$socialAccount->platform}")
            };
            
            $socialAccount->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null
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