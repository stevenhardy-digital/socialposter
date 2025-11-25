<?php

namespace App\Http\Controllers;

use App\Models\EngagementMetric;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    /**
     * Get analytics data for a specific post
     */
    public function getPostAnalytics(Post $post): JsonResponse
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $metrics = $post->engagementMetrics;
        
        if (!$metrics) {
            return response()->json([
                'post_id' => $post->id,
                'metrics' => null,
                'message' => 'No analytics data available yet'
            ]);
        }

        return response()->json([
            'post_id' => $post->id,
            'metrics' => [
                'likes_count' => $metrics->likes_count,
                'comments_count' => $metrics->comments_count,
                'shares_count' => $metrics->shares_count,
                'reach' => $metrics->reach,
                'impressions' => $metrics->impressions,
                'collected_at' => $metrics->collected_at->toISOString(),
            ],
            'post' => [
                'content' => $post->content,
                'platform' => $post->socialAccount->platform,
                'published_at' => $post->published_at?->toISOString(),
                'status' => $post->status,
            ]
        ]);
    }

    /**
     * Get analytics data for multiple posts with filtering
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Post::whereHas('socialAccount', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['engagementMetrics', 'socialAccount']);

        // Apply filters
        if ($request->has('platform')) {
            $query->whereHas('socialAccount', function ($q) use ($request) {
                $q->where('platform', $request->platform);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('published_at', '<=', $request->date_to);
        }

        if ($request->has('post_type')) {
            if ($request->post_type === 'ai_generated') {
                $query->where('is_ai_generated', true);
            } elseif ($request->post_type === 'manual') {
                $query->where('is_ai_generated', false);
            }
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $posts = $query->orderBy('published_at', 'desc')->paginate($perPage);

        $analytics = $posts->map(function ($post) {
            $metrics = $post->engagementMetrics;
            
            return [
                'post_id' => $post->id,
                'content' => $post->content,
                'platform' => $post->socialAccount->platform,
                'account_name' => $post->socialAccount->account_name,
                'status' => $post->status,
                'published_at' => $post->published_at?->toISOString(),
                'is_ai_generated' => $post->is_ai_generated,
                'metrics' => $metrics ? [
                    'likes_count' => $metrics->likes_count,
                    'comments_count' => $metrics->comments_count,
                    'shares_count' => $metrics->shares_count,
                    'reach' => $metrics->reach,
                    'impressions' => $metrics->impressions,
                    'collected_at' => $metrics->collected_at->toISOString(),
                ] : null
            ];
        });

        return response()->json([
            'data' => $analytics,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ]
        ]);
    }

    /**
     * Get analytics summary/overview
     */
    public function getAnalyticsSummary(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Post::whereHas('socialAccount', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with('engagementMetrics');

        // Apply date filter if provided
        if ($request->has('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('published_at', '<=', $request->date_to);
        }

        $posts = $query->where('status', 'published')->get();

        $totalPosts = $posts->count();
        $postsWithMetrics = $posts->filter(fn($post) => $post->engagementMetrics)->count();
        
        $totalLikes = $posts->sum(fn($post) => $post->engagementMetrics?->likes_count ?? 0);
        $totalComments = $posts->sum(fn($post) => $post->engagementMetrics?->comments_count ?? 0);
        $totalShares = $posts->sum(fn($post) => $post->engagementMetrics?->shares_count ?? 0);
        $totalReach = $posts->sum(fn($post) => $post->engagementMetrics?->reach ?? 0);
        $totalImpressions = $posts->sum(fn($post) => $post->engagementMetrics?->impressions ?? 0);

        // Calculate averages
        $avgLikes = $postsWithMetrics > 0 ? round($totalLikes / $postsWithMetrics, 2) : 0;
        $avgComments = $postsWithMetrics > 0 ? round($totalComments / $postsWithMetrics, 2) : 0;
        $avgShares = $postsWithMetrics > 0 ? round($totalShares / $postsWithMetrics, 2) : 0;
        $avgReach = $postsWithMetrics > 0 ? round($totalReach / $postsWithMetrics, 2) : 0;
        $avgImpressions = $postsWithMetrics > 0 ? round($totalImpressions / $postsWithMetrics, 2) : 0;

        // Platform breakdown
        $platformBreakdown = $posts->groupBy('socialAccount.platform')->map(function ($platformPosts, $platform) {
            $postsCount = $platformPosts->count();
            $postsWithMetrics = $platformPosts->filter(fn($post) => $post->engagementMetrics)->count();
            
            return [
                'posts_count' => $postsCount,
                'posts_with_metrics' => $postsWithMetrics,
                'total_likes' => $platformPosts->sum(fn($post) => $post->engagementMetrics?->likes_count ?? 0),
                'total_comments' => $platformPosts->sum(fn($post) => $post->engagementMetrics?->comments_count ?? 0),
                'total_shares' => $platformPosts->sum(fn($post) => $post->engagementMetrics?->shares_count ?? 0),
                'avg_likes' => $postsWithMetrics > 0 ? round($platformPosts->sum(fn($post) => $post->engagementMetrics?->likes_count ?? 0) / $postsWithMetrics, 2) : 0,
            ];
        });

        return response()->json([
            'summary' => [
                'total_posts' => $totalPosts,
                'posts_with_metrics' => $postsWithMetrics,
                'totals' => [
                    'likes' => $totalLikes,
                    'comments' => $totalComments,
                    'shares' => $totalShares,
                    'reach' => $totalReach,
                    'impressions' => $totalImpressions,
                ],
                'averages' => [
                    'likes' => $avgLikes,
                    'comments' => $avgComments,
                    'shares' => $avgShares,
                    'reach' => $avgReach,
                    'impressions' => $avgImpressions,
                ]
            ],
            'platform_breakdown' => $platformBreakdown,
            'period' => [
                'from' => $request->get('date_from'),
                'to' => $request->get('date_to'),
            ]
        ]);
    }

    /**
     * Trigger metrics collection for a specific post
     */
    public function collectMetrics(Post $post): JsonResponse
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($post->status !== 'published' || !$post->platform_post_id) {
            return response()->json([
                'error' => 'Cannot collect metrics for unpublished posts or posts without platform ID'
            ], 400);
        }

        // Dispatch the metrics collection job
        \App\Jobs\CollectEngagementMetricsJob::dispatch($post);

        return response()->json([
            'message' => 'Metrics collection job queued successfully',
            'post_id' => $post->id
        ]);
    }
}