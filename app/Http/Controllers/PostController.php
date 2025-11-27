<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialMediaPublishingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    protected SocialMediaPublishingService $publishingService;

    public function __construct(SocialMediaPublishingService $publishingService)
    {
        $this->publishingService = $publishingService;
    }
    /**
     * Display a listing of posts
     */
    public function index(Request $request)
    {
        $query = Post::with(['socialAccount', 'engagementMetrics'])
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            });

        // Filter by platform
        if ($request->filled('platform')) {
            $query->whereHas('socialAccount', function ($q) use ($request) {
                $q->where('platform', $request->platform);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('scheduled_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('scheduled_at', '<=', $request->date_to);
        }

        // Filter by content type (AI generated or manual)
        if ($request->has('is_ai_generated')) {
            $query->where('is_ai_generated', $request->boolean('is_ai_generated'));
        }

        // Search in content
        if ($request->filled('search')) {
            $query->where('content', 'like', '%' . $request->search . '%');
        }

        $posts = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return PostResource::collection($posts);
    }

    /**
     * Store a newly created post
     */
    public function store(Request $request)
    {
        $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'content' => 'required|string|max:2200',
            'media_urls' => 'nullable|array',
            'media_urls.*' => 'url',
            'scheduled_at' => 'nullable|date|after:now',
            'is_ai_generated' => 'boolean'
        ]);

        // Verify the social account belongs to the authenticated user
        $socialAccount = SocialAccount::where('id', $request->social_account_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $post = Post::create([
            'social_account_id' => $request->social_account_id,
            'content' => $request->content,
            'media_urls' => $request->media_urls,
            'scheduled_at' => $request->scheduled_at ?? now(),
            'status' => 'approved', // Manual posts are automatically approved
            'is_ai_generated' => $request->get('is_ai_generated', false)
        ]);

        return new PostResource($post->load(['socialAccount', 'engagementMetrics']));
    }

    /**
     * Display the specified post
     */
    public function show(Post $post)
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new PostResource($post->load(['socialAccount', 'engagementMetrics']));
    }

    /**
     * Update the specified post
     */
    public function update(Request $request, Post $post)
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'sometimes|required|string|max:2200',
            'media_urls' => 'nullable|array',
            'media_urls.*' => 'url',
            'scheduled_at' => 'nullable|date|after:now',
            'status' => ['sometimes', Rule::in(['draft', 'approved', 'rejected'])]
        ]);

        // Only allow editing if post is not published
        if ($post->status === 'published') {
            return response()->json(['message' => 'Cannot edit published posts'], 422);
        }

        $post->update($request->only([
            'content', 'media_urls', 'scheduled_at', 'status'
        ]));

        return new PostResource($post->load(['socialAccount', 'engagementMetrics']));
    }

    /**
     * Remove the specified post
     */
    public function destroy(Post $post): JsonResponse
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow deletion if post is not published
        if ($post->status === 'published') {
            return response()->json(['message' => 'Cannot delete published posts'], 422);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    /**
     * Approve a draft post
     */
    public function approve(Post $post)
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($post->status !== 'draft') {
            return response()->json(['message' => 'Only draft posts can be approved'], 422);
        }

        $post->update(['status' => 'approved']);

        return new PostResource($post->load(['socialAccount', 'engagementMetrics']));
    }

    /**
     * Reject a draft post
     */
    public function reject(Post $post)
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($post->status !== 'draft') {
            return response()->json(['message' => 'Only draft posts can be rejected'], 422);
        }

        $post->update(['status' => 'rejected']);

        return new PostResource($post->load(['socialAccount', 'engagementMetrics']));
    }

    /**
     * Get posts by status (for draft approval workflow)
     */
    public function getByStatus(string $status)
    {
        $posts = Post::with(['socialAccount', 'engagementMetrics'])
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return PostResource::collection($posts);
    }

    /**
     * Get posts for calendar view with date-based filtering
     */
    public function getCalendarPosts(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = $request->year;
        $month = $request->month;
        
        // Get first and last day of the month
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        $posts = Post::with(['socialAccount', 'engagementMetrics'])
            ->whereHas('socialAccount', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->orderBy('scheduled_at', 'asc')
            ->get()
            ->groupBy(function ($post) {
                return $post->scheduled_at->format('Y-m-d');
            });

        // Transform each group to use PostResource
        $transformedPosts = $posts->map(function ($dayPosts) {
            return PostResource::collection($dayPosts);
        });

        return response()->json($transformedPosts);
    }

    /**
     * Update post scheduled date (for drag-and-drop functionality)
     */
    public function updateSchedule(Request $request, Post $post)
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'scheduled_at' => 'required|date|after:now'
        ]);

        // Only allow rescheduling if post is not published
        if ($post->status === 'published') {
            return response()->json(['message' => 'Cannot reschedule published posts'], 422);
        }

        $post->update(['scheduled_at' => $request->scheduled_at]);

        return new PostResource($post->load(['socialAccount', 'engagementMetrics']));
    }

    /**
     * Create and immediately publish a manual post
     */
    public function createAndPublish(Request $request): JsonResponse
    {
        // Debug log the incoming request
        Log::info('CreateAndPublish request received', [
            'all_data' => $request->all(),
            'social_account_id' => $request->get('social_account_id'),
            'content' => $request->get('content'),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'content' => 'required|string|max:2200',
            'media_urls' => 'nullable|array',
            'media_urls.*' => 'url'
        ]);

        // Verify the social account belongs to the authenticated user
        $socialAccount = SocialAccount::where('id', $request->social_account_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Create manual post (automatically approved)
        $post = Post::create([
            'social_account_id' => $request->social_account_id,
            'content' => $request->content,
            'media_urls' => $request->media_urls,
            'scheduled_at' => now(),
            'status' => 'approved',
            'is_ai_generated' => false
        ]);

        // Attempt to publish immediately
        $publishResult = $this->publishingService->publishPost($post);

        if ($publishResult['success']) {
            return response()->json([
                'post' => new PostResource($post->fresh()->load(['socialAccount', 'engagementMetrics'])),
                'message' => 'Post created and published successfully',
                'platform_post_id' => $publishResult['platform_post_id']
            ], 201);
        } else {
            // Return post with publishing error or manual instructions
            return response()->json([
                'post' => new PostResource($post->fresh()->load(['socialAccount', 'engagementMetrics'])),
                'error' => $publishResult['error'] ?? null,
                'requires_manual_posting' => $publishResult['requires_manual_posting'] ?? false,
                'instructions' => $publishResult['instructions'] ?? null,
                'content_to_copy' => $publishResult['content_to_copy'] ?? null,
                'platform_url' => $publishResult['platform_url'] ?? null
            ], $publishResult['requires_manual_posting'] ? 202 : 422);
        }
    }

    /**
     * Publish an existing approved post
     */
    public function publish(Post $post): JsonResponse
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow publishing approved posts
        if ($post->status !== 'approved') {
            return response()->json(['message' => 'Only approved posts can be published'], 422);
        }

        // Attempt to publish
        $publishResult = $this->publishingService->publishPost($post);

        if ($publishResult['success']) {
            return response()->json([
                'post' => new PostResource($post->fresh()->load(['socialAccount', 'engagementMetrics'])),
                'message' => 'Post published successfully',
                'platform_post_id' => $publishResult['platform_post_id']
            ]);
        } else {
            return response()->json([
                'post' => new PostResource($post->fresh()->load(['socialAccount', 'engagementMetrics'])),
                'error' => $publishResult['error'] ?? null,
                'requires_manual_posting' => $publishResult['requires_manual_posting'] ?? false,
                'instructions' => $publishResult['instructions'] ?? null,
                'content_to_copy' => $publishResult['content_to_copy'] ?? null,
                'platform_url' => $publishResult['platform_url'] ?? null
            ], $publishResult['requires_manual_posting'] ? 202 : 422);
        }
    }

    /**
     * Mark a post as manually published
     */
    public function markAsPublished(Request $request, Post $post): JsonResponse
    {
        // Ensure the post belongs to the authenticated user
        if ($post->socialAccount->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'platform_post_id' => 'nullable|string|max:255'
        ]);

        // Only allow marking approved or draft posts as published
        if (!in_array($post->status, ['approved', 'draft'])) {
            return response()->json(['message' => 'Only approved or draft posts can be marked as published'], 422);
        }

        $result = $this->publishingService->markAsManuallyPublished(
            $post, 
            $request->platform_post_id
        );

        if ($result['success']) {
            return response()->json([
                'post' => $post->fresh()->load(['socialAccount', 'engagementMetrics']),
                'message' => $result['message']
            ]);
        } else {
            return response()->json([
                'error' => $result['error']
            ], 422);
        }
    }
}