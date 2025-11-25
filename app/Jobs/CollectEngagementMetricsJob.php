<?php

namespace App\Jobs;

use App\Models\EngagementMetric;
use App\Models\Post;
use App\Services\SocialMediaApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CollectEngagementMetricsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Post $post
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SocialMediaApiService $socialMediaApiService): void
    {
        try {
            // Only collect metrics for published posts with platform IDs
            if ($this->post->status !== 'published' || !$this->post->platform_post_id) {
                Log::info("Skipping metrics collection for post {$this->post->id}: not published or missing platform ID");
                return;
            }

            $socialAccount = $this->post->socialAccount;
            if (!$socialAccount || !$socialAccount->access_token) {
                Log::warning("No valid social account or access token for post {$this->post->id}");
                return;
            }

            $result = $socialMediaApiService->collectEngagementMetrics($socialAccount, $this->post->platform_post_id);
            
            if ($result['success']) {
                $metrics = $result['data'];
                
                // Create or update engagement metrics
                EngagementMetric::updateOrCreate(
                    ['post_id' => $this->post->id],
                    [
                        'likes_count' => $metrics['likes_count'] ?? 0,
                        'comments_count' => $metrics['comments_count'] ?? 0,
                        'shares_count' => $metrics['shares_count'] ?? 0,
                        'reach' => $metrics['reach'] ?? 0,
                        'impressions' => $metrics['impressions'] ?? 0,
                        'collected_at' => now(),
                    ]
                );

                Log::info("Successfully collected metrics for post {$this->post->id}");
            } else {
                Log::warning("Failed to collect metrics for post {$this->post->id}: " . $result['error']);
                
                // Check if it's a rate limit error
                if ($result['is_rate_limit'] ?? false) {
                    $delay = $result['retry_after'] ?? $this->getRetryDelay();
                    $this->release($delay);
                    Log::info("Rate limit hit, retrying post {$this->post->id} metrics collection in {$delay} seconds");
                    return;
                }
                
                // Check if it's retryable
                if ($result['is_retryable'] ?? false) {
                    $delay = $this->getRetryDelay();
                    $this->release($delay);
                    Log::info("Retryable error, retrying post {$this->post->id} metrics collection in {$delay} seconds");
                    return;
                }
            }
        } catch (Exception $e) {
            Log::error("Failed to collect metrics for post {$this->post->id}: " . $e->getMessage());
            
            // Check if it's a rate limit error
            if ($this->isRateLimitError($e)) {
                // Release the job back to queue with exponential backoff
                $delay = $this->getRetryDelay();
                $this->release($delay);
                Log::info("Rate limit hit, retrying post {$this->post->id} metrics collection in {$delay} seconds");
                return;
            }
            
            throw $e;
        }
    }



    /**
     * Check if the exception is a rate limit error
     */
    private function isRateLimitError(Exception $e): bool
    {
        return in_array($e->getCode(), [429, 503]) || 
               str_contains(strtolower($e->getMessage()), 'rate limit');
    }

    /**
     * Get retry delay with exponential backoff
     */
    private function getRetryDelay(): int
    {
        $attempt = $this->attempts();
        return min(60 * pow(2, $attempt - 1), 3600); // Max 1 hour delay
    }
}
