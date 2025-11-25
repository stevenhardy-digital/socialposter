<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ContentGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GenerateMonthlyContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ContentGenerationService $contentService): void
    {
        try {
            $user = User::findOrFail($this->userId);
            
            Log::info('Starting monthly content generation', [
                'user_id' => $this->userId,
                'user_email' => $user->email
            ]);
            
            $results = $contentService->generateMonthlyContent($this->userId);
            
            $totalPosts = 0;
            $failedAccounts = 0;
            
            foreach ($results as $accountId => $result) {
                if ($result['success']) {
                    $totalPosts += $result['posts_generated'];
                } else {
                    $failedAccounts++;
                }
            }
            
            Log::info('Monthly content generation completed', [
                'user_id' => $this->userId,
                'total_posts_generated' => $totalPosts,
                'failed_accounts' => $failedAccounts,
                'results' => $results
            ]);
            
            // TODO: Send notification to user about completion
            // This would be implemented when notification system is built
            
        } catch (\Exception $e) {
            Log::error('Monthly content generation job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateMonthlyContentJob failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
        
        // TODO: Notify user of failure
        // This would be implemented when notification system is built
    }
}