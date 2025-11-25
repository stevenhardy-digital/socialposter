<?php

namespace App\Services;

use App\Models\Post;
use App\Models\SocialAccount;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialMediaPublishingService
{
    protected SocialMediaApiService $socialMediaApiService;

    public function __construct(SocialMediaApiService $socialMediaApiService)
    {
        $this->socialMediaApiService = $socialMediaApiService;
    }
    /**
     * Publish a post to the appropriate social media platform
     */
    public function publishPost(Post $post): array
    {
        $socialAccount = $post->socialAccount;
        
        try {
            // Check if platform supports API posting
            if (!$this->platformSupportsAPIPosting($socialAccount->platform)) {
                return $this->getManualPostingInstructions($post);
            }

            // Attempt to publish via API
            $result = $this->publishViaAPI($post, $socialAccount);
            
            if ($result['success']) {
                // Update post with platform ID and published timestamp
                $post->update([
                    'status' => 'published',
                    'platform_post_id' => $result['platform_post_id'],
                    'published_at' => now()
                ]);
                
                // Schedule metrics collection job for later
                \App\Jobs\CollectEngagementMetricsJob::dispatch($post)->delay(now()->addMinutes(5));
                
                return [
                    'success' => true,
                    'message' => 'Post published successfully',
                    'platform_post_id' => $result['platform_post_id']
                ];
            } else {
                // Revert to draft status on failure
                $post->update([
                    'status' => 'draft',
                    'last_error' => $result['error'],
                    'error_at' => now()
                ]);
                
                return [
                    'success' => false,
                    'error' => $result['error'],
                    'requires_manual_posting' => false
                ];
            }
            
        } catch (Exception $e) {
            Log::error('Social media publishing failed', [
                'post_id' => $post->id,
                'platform' => $socialAccount->platform,
                'error' => $e->getMessage()
            ]);
            
            // Revert to draft status on exception
            $post->update([
                'status' => 'draft',
                'last_error' => $e->getMessage(),
                'error_at' => now()
            ]);
            
            return [
                'success' => false,
                'error' => 'Publishing failed: ' . $e->getMessage(),
                'requires_manual_posting' => false
            ];
        }
    }

    /**
     * Check if platform supports automated API posting
     */
    private function platformSupportsAPIPosting(string $platform): bool
    {
        // For demo purposes, assume Instagram has API restrictions
        // In real implementation, this would check platform policies and account permissions
        $restrictedPlatforms = ['instagram']; // Instagram often restricts automated posting
        
        return !in_array($platform, $restrictedPlatforms);
    }

    /**
     * Publish post via platform API
     */
    private function publishViaAPI(Post $post, SocialAccount $socialAccount): array
    {
        $postData = [
            'content' => $post->content,
            'image_url' => $post->media_urls ? json_decode($post->media_urls, true)[0] ?? null : null,
            'scheduled_publish_time' => $post->scheduled_at?->timestamp,
        ];

        $result = $this->socialMediaApiService->publishPost($socialAccount, $postData);
        
        if ($result['success']) {
            return [
                'success' => true,
                'platform_post_id' => $result['data']['id'] ?? $socialAccount->platform . '_' . time()
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
    }



    /**
     * Get manual posting instructions for restricted platforms
     */
    private function getManualPostingInstructions(Post $post): array
    {
        $platform = $post->socialAccount->platform;
        $platformUrls = [
            'instagram' => 'https://www.instagram.com',
            'facebook' => 'https://www.facebook.com',
            'linkedin' => 'https://www.linkedin.com'
        ];

        return [
            'success' => false,
            'requires_manual_posting' => true,
            'instructions' => [
                'step_1' => "Log into your {$platform} account",
                'step_2' => "Navigate to create new post",
                'step_3' => "Copy the following content: {$post->content}",
                'step_4' => "Paste content and publish manually",
                'step_5' => "Return to mark post as published"
            ],
            'content_to_copy' => $post->content,
            'platform_url' => $platformUrls[$platform] ?? 'https://example.com',
            'post_id' => $post->id
        ];
    }

    /**
     * Mark a post as manually published
     */
    public function markAsManuallyPublished(Post $post, string $platformPostId = null): array
    {
        try {
            $post->update([
                'status' => 'published',
                'platform_post_id' => $platformPostId ?? $post->socialAccount->platform . '_manual_' . time(),
                'published_at' => now()
            ]);

            // Schedule metrics collection job for manually published posts too
            \App\Jobs\CollectEngagementMetricsJob::dispatch($post)->delay(now()->addMinutes(5));

            return [
                'success' => true,
                'message' => 'Post marked as manually published'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to mark post as published: ' . $e->getMessage()
            ];
        }
    }
}