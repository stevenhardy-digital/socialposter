<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhookJob;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialMediaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected SocialMediaApiService $socialMediaService;

    public function __construct(SocialMediaApiService $socialMediaService)
    {
        $this->socialMediaService = $socialMediaService;
    }

    /**
     * Handle Facebook/Instagram webhook verification
     */
    public function verifyFacebookWebhook(Request $request): JsonResponse|string
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Verify the webhook
        if ($mode === 'subscribe' && $token === config('services.facebook.webhook_verify_token')) {
            Log::info('Facebook webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('Facebook webhook verification failed', [
            'mode' => $mode,
            'token' => $token
        ]);

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle Facebook/Instagram webhook events
     */
    public function handleFacebookWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Hub-Signature-256');

            // Validate webhook signature
            if (!$this->socialMediaService->validateWebhookSignature('facebook', $payload, $signature)) {
                Log::warning('Facebook webhook signature validation failed');
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $data = $request->json()->all();
            
            Log::info('Facebook webhook received', [
                'object' => $data['object'] ?? 'unknown',
                'entries_count' => count($data['entry'] ?? [])
            ]);

            // Process webhook data asynchronously
            ProcessWebhookJob::dispatch('facebook', $data);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Facebook webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle Instagram webhook verification (uses Facebook webhook system)
     */
    public function verifyInstagramWebhook(Request $request): JsonResponse|string
    {
        return $this->verifyFacebookWebhook($request);
    }

    /**
     * Handle Instagram webhook events (uses Facebook webhook system)
     */
    public function handleInstagramWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Hub-Signature-256');

            // Validate webhook signature
            if (!$this->socialMediaService->validateWebhookSignature('instagram', $payload, $signature)) {
                Log::warning('Instagram webhook signature validation failed');
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $data = $request->json()->all();
            
            Log::info('Instagram webhook received', [
                'object' => $data['object'] ?? 'unknown',
                'entries_count' => count($data['entry'] ?? [])
            ]);

            // Process webhook data asynchronously
            ProcessWebhookJob::dispatch('instagram', $data);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Instagram webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle LinkedIn webhook verification (placeholder - LinkedIn doesn't support webhooks for UGC)
     */
    public function verifyLinkedInWebhook(Request $request): JsonResponse
    {
        Log::info('LinkedIn webhook verification attempted - not supported');
        return response()->json(['error' => 'LinkedIn webhooks not supported'], 404);
    }

    /**
     * Handle LinkedIn webhook events (placeholder - LinkedIn doesn't support webhooks for UGC)
     */
    public function handleLinkedInWebhook(Request $request): JsonResponse
    {
        Log::info('LinkedIn webhook event attempted - not supported');
        return response()->json(['error' => 'LinkedIn webhooks not supported'], 404);
    }

    /**
     * Manual webhook test endpoint for development
     */
    public function testWebhook(Request $request, string $platform): JsonResponse
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json(['error' => 'Test endpoint only available in development'], 403);
        }

        try {
            $testData = $request->json()->all();
            
            Log::info("Test webhook for {$platform}", $testData);

            // Process test webhook data
            ProcessWebhookJob::dispatch($platform, $testData);

            return response()->json([
                'status' => 'success',
                'message' => "Test webhook for {$platform} processed",
                'data' => $testData
            ]);
        } catch (\Exception $e) {
            Log::error("Test webhook for {$platform} failed", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Test webhook failed'], 500);
        }
    }

    /**
     * Get webhook status and configuration
     */
    public function getWebhookStatus(): JsonResponse
    {
        try {
            $status = [
                'facebook' => [
                    'supported' => true,
                    'verify_token_configured' => !empty(config('services.facebook.webhook_verify_token')),
                    'secret_configured' => !empty(config('services.facebook.webhook_secret')),
                    'endpoint' => route('webhooks.facebook.handle')
                ],
                'instagram' => [
                    'supported' => true,
                    'verify_token_configured' => !empty(config('services.instagram.webhook_verify_token')),
                    'secret_configured' => !empty(config('services.instagram.webhook_secret')),
                    'endpoint' => route('webhooks.instagram.handle'),
                    'note' => 'Uses Facebook Graph API webhook system'
                ],
                'linkedin' => [
                    'supported' => false,
                    'reason' => 'LinkedIn does not support webhooks for UGC posts',
                    'alternative' => 'Use periodic polling for engagement metrics'
                ]
            ];

            return response()->json([
                'status' => 'success',
                'webhook_status' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook status check failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to get webhook status'], 500);
        }
    }
}