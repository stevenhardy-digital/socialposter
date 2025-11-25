<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateMonthlyContentJob;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\ContentGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContentGenerationController extends Controller
{
    public function __construct(
        private ContentGenerationService $contentService
    ) {}

    /**
     * Trigger monthly content generation for all user accounts
     */
    public function generateMonthly(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Dispatch the job to queue
            GenerateMonthlyContentJob::dispatch($userId);
            
            Log::info('Monthly content generation job dispatched', [
                'user_id' => $userId
            ]);
            
            return response()->json([
                'message' => 'Monthly content generation started',
                'status' => 'queued'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to dispatch monthly content generation', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to start content generation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate content for a specific social account
     */
    public function generateForAccount(Request $request, SocialAccount $account): JsonResponse
    {
        // Ensure user owns the account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'post_count' => 'integer|min:1|max:30'
        ]);

        try {
            $postCount = $request->input('post_count', 10);
            $posts = $this->contentService->generateContentForAccount($account, $postCount);
            
            Log::info('Content generated for account', [
                'account_id' => $account->id,
                'posts_generated' => count($posts),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'message' => 'Content generated successfully',
                'posts_generated' => count($posts),
                'posts' => $posts->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'content' => $post->content,
                        'status' => $post->status,
                        'scheduled_at' => $post->scheduled_at,
                        'is_ai_generated' => $post->is_ai_generated
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Content generation failed for account', [
                'account_id' => $account->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Content generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get generation status and recent activity
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Get recent AI-generated posts
            $recentPosts = Post::whereHas('socialAccount', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('is_ai_generated', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->with('socialAccount:id,platform,account_name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

            // Get accounts that need generation
            $accountsNeedingGeneration = SocialAccount::where('user_id', $userId)
                ->get()
                ->filter(function ($account) {
                    return $this->contentService->isGenerationNeeded($account);
                });

            return response()->json([
                'recent_posts' => $recentPosts->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'content' => substr($post->content, 0, 100) . '...',
                        'platform' => $post->socialAccount->platform,
                        'account_name' => $post->socialAccount->account_name,
                        'status' => $post->status,
                        'created_at' => $post->created_at,
                        'scheduled_at' => $post->scheduled_at
                    ];
                }),
                'accounts_needing_generation' => $accountsNeedingGeneration->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'platform' => $account->platform,
                        'account_name' => $account->account_name
                    ];
                }),
                'total_posts_this_month' => $recentPosts->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get generation status', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to get generation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a single post for testing
     */
    public function generateSingle(Request $request, SocialAccount $account): JsonResponse
    {
        // Ensure user owns the account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $content = $this->contentService->generateSinglePost($account, $account->brandGuidelines);
            
            return response()->json([
                'content' => $content,
                'platform' => $account->platform,
                'account_name' => $account->account_name
            ]);
            
        } catch (\Exception $e) {
            Log::error('Single post generation failed', [
                'account_id' => $account->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Post generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}