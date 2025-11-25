<?php

namespace App\Jobs;

use App\Models\EngagementMetric;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialMediaApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300]; // 30 seconds, 2 minutes, 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $platform,
        public array $webhookData
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SocialMediaApiService $socialMediaService): void
    {
        try {
            Log::info("Processing {$this->platform} webhook", [
                'platform' => $this->platform,
                'data_keys' => array_keys($this->webhookData)
            ]);

            // Process webhook data using the appropriate client
            $processedUpdates = $socialMediaService->processWebhookData($this->platform, $this->webhookData);

            if (empty($processedUpdates)) {
                Log::info("No processable updates in {$this->platform} webhook");
                return;
            }

            foreach ($processedUpdates as $update) {
                $this->processUpdate($update);
            }

            Log::info("Successfully processed {$this->platform} webhook", [
                'updates_processed' => count($processedUpdates)
            ]);
        } catch (Exception $e) {
            Log::error("Failed to process {$this->platform} webhook", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process a single webhook update
     */
    private function processUpdate(array $update): void
    {
        try {
            // Find the post based on platform post ID
            $post = $this->findPostByPlatformId($update);
            
            if (!$post) {
                Log::warning("Post not found for webhook update", [
                    'platform' => $this->platform,
                    'platform_post_id' => $update['media_id'] ?? $update['post_id'] ?? 'unknown'
                ]);
                return;
            }

            // Update engagement metrics based on the webhook field
            $this->updateEngagementMetrics($post, $update);

            Log::info("Updated engagement metrics from webhook", [
                'post_id' => $post->id,
                'platform' => $this->platform,
                'field' => $update['field']
            ]);
        } catch (Exception $e) {
            Log::error("Failed to process webhook update", [
                'platform' => $this->platform,
                'update' => $update,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Find post by platform post ID
     */
    private function findPostByPlatformId(array $update): ?Post
    {
        $platformPostId = $update['media_id'] ?? $update['post_id'] ?? null;
        
        if (!$platformPostId) {
            return null;
        }

        return Post::whereHas('socialAccount', function ($query) {
            $query->where('platform', $this->platform);
        })
        ->where('platform_post_id', $platformPostId)
        ->first();
    }

    /**
     * Update engagement metrics based on webhook data
     */
    private function updateEngagementMetrics(Post $post, array $update): void
    {
        $field = $update['field'];
        $value = $update['value'];

        // Get or create engagement metrics record
        $metrics = EngagementMetric::firstOrCreate(
            ['post_id' => $post->id],
            [
                'likes_count' => 0,
                'comments_count' => 0,
                'shares_count' => 0,
                'reach' => 0,
                'impressions' => 0,
                'collected_at' => now()
            ]
        );

        // Update metrics based on webhook field
        switch ($field) {
            case 'likes':
            case 'reactions':
                $this->updateLikesCount($metrics, $value);
                break;
            case 'comments':
                $this->updateCommentsCount($metrics, $value);
                break;
            case 'shares':
                $this->updateSharesCount($metrics, $value);
                break;
            case 'feed':
                // General feed update - might contain multiple metrics
                $this->updateFromFeedData($metrics, $value);
                break;
        }

        $metrics->collected_at = now();
        $metrics->save();
    }

    /**
     * Update likes count from webhook data
     */
    private function updateLikesCount(EngagementMetric $metrics, array $value): void
    {
        if (isset($value['reaction_type']) && $value['reaction_type'] === 'like') {
            if ($value['verb'] === 'add') {
                $metrics->likes_count++;
            } elseif ($value['verb'] === 'remove') {
                $metrics->likes_count = max(0, $metrics->likes_count - 1);
            }
        } elseif (isset($value['total_count'])) {
            // Direct count update
            $metrics->likes_count = $value['total_count'];
        }
    }

    /**
     * Update comments count from webhook data
     */
    private function updateCommentsCount(EngagementMetric $metrics, array $value): void
    {
        if (isset($value['verb'])) {
            if ($value['verb'] === 'add') {
                $metrics->comments_count++;
            } elseif ($value['verb'] === 'remove') {
                $metrics->comments_count = max(0, $metrics->comments_count - 1);
            }
        } elseif (isset($value['total_count'])) {
            // Direct count update
            $metrics->comments_count = $value['total_count'];
        }
    }

    /**
     * Update shares count from webhook data
     */
    private function updateSharesCount(EngagementMetric $metrics, array $value): void
    {
        if (isset($value['verb'])) {
            if ($value['verb'] === 'add') {
                $metrics->shares_count++;
            } elseif ($value['verb'] === 'remove') {
                $metrics->shares_count = max(0, $metrics->shares_count - 1);
            }
        } elseif (isset($value['total_count'])) {
            // Direct count update
            $metrics->shares_count = $value['total_count'];
        }
    }

    /**
     * Update metrics from general feed data
     */
    private function updateFromFeedData(EngagementMetric $metrics, array $value): void
    {
        // This handles general feed updates that might contain multiple metrics
        if (isset($value['likes_count'])) {
            $metrics->likes_count = $value['likes_count'];
        }
        if (isset($value['comments_count'])) {
            $metrics->comments_count = $value['comments_count'];
        }
        if (isset($value['shares_count'])) {
            $metrics->shares_count = $value['shares_count'];
        }
        if (isset($value['reach'])) {
            $metrics->reach = $value['reach'];
        }
        if (isset($value['impressions'])) {
            $metrics->impressions = $value['impressions'];
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Webhook processing job failed permanently", [
            'platform' => $this->platform,
            'webhook_data' => $this->webhookData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}