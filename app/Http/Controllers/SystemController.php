<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentGenerationService;
use App\Services\SocialMediaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class SystemController extends Controller
{
    public function __construct(
        private SocialMediaApiService $socialMediaApiService,
        private ContentGenerationService $contentGenerationService
    ) {}

    /**
     * Get comprehensive system health status
     */
    public function healthCheck()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'social_apis' => $this->checkSocialApis(),
            'ai_service' => $this->checkAiService(),
            'storage' => $this->checkStorage(),
        ];

        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'healthy') ? 'healthy' : 'unhealthy';

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ]);
    }

    /**
     * Get system dashboard overview
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        // Get user's connected accounts
        $connectedAccounts = SocialAccount::where('user_id', $user->id)
            ->with(['brandGuidelines'])
            ->get()
            ->groupBy('platform');

        // Get post statistics
        $postStats = [
            'total' => Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))->count(),
            'draft' => Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))->where('status', 'draft')->count(),
            'approved' => Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))->where('status', 'approved')->count(),
            'published' => Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))->where('status', 'published')->count(),
            'scheduled' => Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))
                ->where('status', 'approved')
                ->where('scheduled_at', '>', now())
                ->count(),
        ];

        // Get recent activity
        $recentPosts = Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))
            ->with(['socialAccount', 'engagementMetrics'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get engagement summary
        $engagementSummary = $this->getEngagementSummary($user->id);

        // Get system alerts
        $systemAlerts = $this->getSystemAlerts($user->id);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'connected_accounts' => $connectedAccounts,
            'post_stats' => $postStats,
            'recent_posts' => $recentPosts,
            'engagement_summary' => $engagementSummary,
            'system_alerts' => $systemAlerts,
            'system_status' => $this->getQuickSystemStatus(),
        ]);
    }

    /**
     * Get system status for monitoring
     */
    public function status()
    {
        return response()->json([
            'status' => 'operational',
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'timestamp' => now()->toISOString(),
            'uptime' => $this->getUptime(),
        ]);
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Request $request)
    {
        $monitoringService = app(\App\Services\MonitoringService::class);
        
        return response()->json([
            'performance_summary' => $monitoringService->getPerformanceSummary(),
            'system_alerts' => $monitoringService->getSystemAlerts(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Test end-to-end workflow
     */
    public function testWorkflow(Request $request)
    {
        $user = $request->user();
        $results = [];

        try {
            // Test 1: User authentication
            $results['authentication'] = [
                'status' => 'passed',
                'message' => 'User successfully authenticated',
            ];

            // Test 2: Social account connection check
            $socialAccounts = SocialAccount::where('user_id', $user->id)->get();
            $results['social_accounts'] = [
                'status' => $socialAccounts->count() > 0 ? 'passed' : 'warning',
                'message' => "Found {$socialAccounts->count()} connected social accounts",
                'accounts' => $socialAccounts->pluck('platform')->toArray(),
            ];

            // Test 3: Brand guidelines check
            $hasGuidelines = $socialAccounts->some(fn($account) => $account->brandGuidelines()->exists());
            $results['brand_guidelines'] = [
                'status' => $hasGuidelines ? 'passed' : 'warning',
                'message' => $hasGuidelines ? 'Brand guidelines configured' : 'No brand guidelines found',
            ];

            // Test 4: Content generation test (if accounts exist)
            if ($socialAccounts->count() > 0) {
                try {
                    $testAccount = $socialAccounts->first();
                    $canGenerate = $this->contentGenerationService->canGenerateContent($testAccount);
                    $results['content_generation'] = [
                        'status' => $canGenerate ? 'passed' : 'warning',
                        'message' => $canGenerate ? 'Content generation available' : 'Content generation not available',
                    ];
                } catch (\Exception $e) {
                    $results['content_generation'] = [
                        'status' => 'failed',
                        'message' => 'Content generation test failed: ' . $e->getMessage(),
                    ];
                }
            }

            // Test 5: Post management
            $postCount = Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))->count();
            $results['post_management'] = [
                'status' => 'passed',
                'message' => "Found {$postCount} posts in system",
            ];

            // Test 6: Analytics collection
            $hasMetrics = Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $user->id))
                ->whereHas('engagementMetrics')
                ->exists();
            $results['analytics'] = [
                'status' => $hasMetrics ? 'passed' : 'warning',
                'message' => $hasMetrics ? 'Analytics data available' : 'No analytics data found',
            ];

        } catch (\Exception $e) {
            Log::error('End-to-end workflow test failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'failed',
                'message' => 'Workflow test failed: ' . $e->getMessage(),
                'results' => $results,
            ], 500);
        }

        $overallStatus = collect($results)->every(fn($result) => in_array($result['status'], ['passed', 'warning'])) ? 'passed' : 'failed';

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'results' => $results,
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $userCount = User::count();
            return [
                'status' => 'healthy',
                'message' => "Database connection successful. {$userCount} users in system.",
                'response_time' => $this->measureResponseTime(fn() => DB::select('SELECT 1')),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            // Only check Redis if it's actually configured as the cache driver
            if (config('cache.default') !== 'redis' && config('session.driver') !== 'redis') {
                return [
                    'status' => 'skipped',
                    'message' => 'Redis not configured as cache or session driver',
                ];
            }

            Redis::ping();
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
                'response_time' => $this->measureResponseTime(fn() => Redis::ping()),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $queueSize = Queue::size();
            $failedJobs = DB::table('failed_jobs')->count();
            
            return [
                'status' => $failedJobs > 10 ? 'warning' : 'healthy',
                'message' => "Queue size: {$queueSize}, Failed jobs: {$failedJobs}",
                'queue_size' => $queueSize,
                'failed_jobs' => $failedJobs,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkSocialApis(): array
    {
        $apiStatuses = [];
        $platforms = ['instagram', 'facebook', 'linkedin'];
        
        foreach ($platforms as $platform) {
            try {
                $status = $this->socialMediaApiService->checkApiStatus($platform);
                $apiStatuses[$platform] = $status;
            } catch (\Exception $e) {
                $apiStatuses[$platform] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $overallStatus = collect($apiStatuses)->every(fn($status) => $status['status'] === 'healthy') ? 'healthy' : 'warning';

        return [
            'status' => $overallStatus,
            'message' => 'Social media API status checked',
            'apis' => $apiStatuses,
        ];
    }

    private function checkAiService(): array
    {
        try {
            $canConnect = $this->contentGenerationService->testConnection();
            return [
                'status' => $canConnect ? 'healthy' : 'warning',
                'message' => $canConnect ? 'AI service connection successful' : 'AI service connection failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'AI service check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $diskSpace = disk_free_space(storage_path());
            $totalSpace = disk_total_space(storage_path());
            $usagePercent = (($totalSpace - $diskSpace) / $totalSpace) * 100;

            return [
                'status' => $usagePercent > 90 ? 'warning' : 'healthy',
                'message' => sprintf('Storage usage: %.1f%%', $usagePercent),
                'free_space' => $this->formatBytes($diskSpace),
                'total_space' => $this->formatBytes($totalSpace),
                'usage_percent' => round($usagePercent, 1),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function getEngagementSummary(int $userId): array
    {
        $posts = Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $userId))
            ->with('engagementMetrics')
            ->where('status', 'published')
            ->get();

        if ($posts->isEmpty()) {
            return [
                'total_posts' => 0,
                'total_likes' => 0,
                'total_comments' => 0,
                'total_shares' => 0,
                'average_engagement' => 0,
            ];
        }

        $metrics = $posts->pluck('engagementMetrics')->filter();
        
        return [
            'total_posts' => $posts->count(),
            'total_likes' => $metrics->sum('likes_count'),
            'total_comments' => $metrics->sum('comments_count'),
            'total_shares' => $metrics->sum('shares_count'),
            'average_engagement' => $metrics->avg(fn($m) => $m->likes_count + $m->comments_count + $m->shares_count),
        ];
    }

    private function getSystemAlerts(int $userId): array
    {
        $alerts = [];

        // Check for expired tokens
        $expiredTokens = SocialAccount::where('user_id', $userId)
            ->where('expires_at', '<', now())
            ->count();

        if ($expiredTokens > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$expiredTokens} social account token(s) have expired and need renewal",
                'action' => 'Reconnect accounts in Account Settings',
            ];
        }

        // Check for failed posts
        $failedPosts = Post::whereHas('socialAccount', fn($q) => $q->where('user_id', $userId))
            ->where('status', 'failed')
            ->where('created_at', '>', now()->subDays(7))
            ->count();

        if ($failedPosts > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "{$failedPosts} post(s) failed to publish in the last 7 days",
                'action' => 'Review failed posts in Post Management',
            ];
        }

        // Check for missing brand guidelines
        $accountsWithoutGuidelines = SocialAccount::where('user_id', $userId)
            ->doesntHave('brandGuidelines')
            ->count();

        if ($accountsWithoutGuidelines > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$accountsWithoutGuidelines} account(s) don't have brand guidelines configured",
                'action' => 'Set up brand guidelines for better AI content generation',
            ];
        }

        return $alerts;
    }

    private function getQuickSystemStatus(): array
    {
        return Cache::remember('system_status', 300, function () {
            $redisCheck = $this->checkRedis();
            return [
                'database' => $this->checkDatabase()['status'],
                'redis' => $redisCheck['status'] === 'skipped' ? 'not_configured' : $redisCheck['status'],
                'queue' => $this->checkQueue()['status'],
            ];
        });
    }

    private function getUptime(): string
    {
        $uptimeSeconds = (int) shell_exec('cat /proc/uptime | cut -d" " -f1');
        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }

    private function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return round((microtime(true) - $start) * 1000, 2); // milliseconds
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}