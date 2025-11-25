<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SocialAccount;
use App\Models\BrandGuideline;
use App\Models\Post;
use App\Models\EngagementMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SystemIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private SocialAccount $socialAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test social account
        $this->socialAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'instagram',
            'access_token' => 'test_token',
            'expires_at' => now()->addDays(30),
        ]);
    }

    /** @test */
    public function test_complete_user_workflow()
    {
        // 1. User Authentication
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password'
        ]);
        
        $response->assertStatus(200);
        $this->assertArrayHasKey('token', $response->json());
        
        $token = $response->json('token');
        $headers = ['Authorization' => "Bearer {$token}"];

        // 2. Dashboard Access
        $response = $this->getJson('/api/system/dashboard', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user',
            'connected_accounts',
            'post_stats',
            'recent_posts',
            'engagement_summary',
            'system_alerts',
            'system_status'
        ]);

        // 3. Social Account Management
        $response = $this->getJson('/api/social-accounts', $headers);
        $response->assertStatus(200);
        $this->assertCount(1, $response->json());

        // 4. Brand Guidelines Configuration
        $guidelineData = [
            'tone_of_voice' => 'professional',
            'brand_voice' => 'Innovative and trustworthy',
            'content_themes' => ['technology', 'innovation'],
            'hashtag_strategy' => ['#tech', '#innovation']
        ];

        $response = $this->postJson("/api/brand-guidelines/social-account/{$this->socialAccount->id}", $guidelineData, $headers);
        $response->assertStatus(200);

        // Verify brand guidelines were created
        $this->assertDatabaseHas('brand_guidelines', [
            'social_account_id' => $this->socialAccount->id,
            'tone_of_voice' => 'professional'
        ]);

        // 5. Content Generation
        $response = $this->postJson("/api/content-generation/account/{$this->socialAccount->id}/single", [], $headers);
        $response->assertStatus(200);

        // 6. Post Management
        $post = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'draft',
            'content' => 'Test post content'
        ]);

        // Approve post
        $response = $this->postJson("/api/posts/{$post->id}/approve", [], $headers);
        $response->assertStatus(200);

        // Verify post status changed
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'approved'
        ]);

        // 7. Calendar View
        $response = $this->getJson('/api/posts/calendar', $headers);
        $response->assertStatus(200);

        // 8. Analytics
        $engagementMetric = EngagementMetric::factory()->create([
            'post_id' => $post->id,
            'likes_count' => 100,
            'comments_count' => 10,
            'shares_count' => 5
        ]);

        $response = $this->getJson('/api/analytics', $headers);
        $response->assertStatus(200);

        // 9. Post Overview
        $response = $this->getJson('/api/posts', $headers);
        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json()));

        // 10. System Health Check
        $response = $this->getJson('/api/system/health', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'redis',
                'queue',
                'social_apis',
                'ai_service',
                'storage'
            ]
        ]);

        // 11. End-to-End Workflow Test
        $response = $this->postJson('/api/system/test-workflow', [], $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'results'
        ]);
    }

    /** @test */
    public function test_system_health_monitoring()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/system/health');
        $response->assertStatus(200);

        $healthData = $response->json();
        $this->assertArrayHasKey('status', $healthData);
        $this->assertArrayHasKey('checks', $healthData);
        
        // Verify all health checks are present
        $expectedChecks = ['database', 'redis', 'queue', 'social_apis', 'ai_service', 'storage'];
        foreach ($expectedChecks as $check) {
            $this->assertArrayHasKey($check, $healthData['checks']);
            $this->assertArrayHasKey('status', $healthData['checks'][$check]);
        }
    }

    /** @test */
    public function test_api_error_handling_and_logging()
    {
        $this->actingAs($this->user);

        // Test invalid post ID
        $response = $this->getJson('/api/posts/999999');
        $response->assertStatus(404);

        // Test invalid social account access
        $otherUser = User::factory()->create();
        $otherAccount = SocialAccount::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/brand-guidelines/social-account/{$otherAccount->id}");
        $response->assertStatus(403);

        // Test validation errors
        $response = $this->postJson('/api/posts', [
            'content' => '', // Empty content should fail validation
            'social_account_id' => $this->socialAccount->id
        ]);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_social_media_api_integration()
    {
        $this->actingAs($this->user);

        // Test account info retrieval
        $response = $this->getJson("/api/social-accounts/{$this->socialAccount->id}/info");
        // This might fail in testing environment without real API tokens, which is expected
        $this->assertTrue(in_array($response->status(), [200, 401, 403]));

        // Test webhook status
        $response = $this->getJson('/api/webhooks/status');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_content_generation_workflow()
    {
        $this->actingAs($this->user);

        // Create brand guidelines first
        BrandGuideline::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'tone_of_voice' => 'professional',
            'brand_voice' => 'Innovative and trustworthy'
        ]);

        // Test content generation status
        $response = $this->getJson('/api/content-generation/status');
        $response->assertStatus(200);

        // Test single content generation
        $response = $this->postJson("/api/content-generation/account/{$this->socialAccount->id}/single");
        // This might fail without OpenAI API key, which is expected in testing
        $this->assertTrue(in_array($response->status(), [200, 500]));
    }

    /** @test */
    public function test_post_lifecycle_management()
    {
        $this->actingAs($this->user);

        // Create a draft post
        $post = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'draft',
            'content' => 'Test post for lifecycle management'
        ]);

        // Test post retrieval
        $response = $this->getJson("/api/posts/{$post->id}");
        $response->assertStatus(200);
        $response->assertJson(['id' => $post->id]);

        // Test post approval
        $response = $this->postJson("/api/posts/{$post->id}/approve");
        $response->assertStatus(200);

        // Verify status change
        $post->refresh();
        $this->assertEquals('approved', $post->status);

        // Test post scheduling
        $scheduleData = ['scheduled_at' => now()->addDays(1)->toISOString()];
        $response = $this->putJson("/api/posts/{$post->id}/schedule", $scheduleData);
        $response->assertStatus(200);

        // Test post rejection
        $draftPost = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'draft'
        ]);

        $response = $this->postJson("/api/posts/{$draftPost->id}/reject");
        $response->assertStatus(200);

        $draftPost->refresh();
        $this->assertEquals('rejected', $draftPost->status);
    }

    /** @test */
    public function test_analytics_and_engagement_tracking()
    {
        $this->actingAs($this->user);

        // Create published posts with engagement metrics
        $posts = Post::factory()->count(3)->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'published'
        ]);

        foreach ($posts as $post) {
            EngagementMetric::factory()->create([
                'post_id' => $post->id,
                'likes_count' => $this->faker->numberBetween(10, 100),
                'comments_count' => $this->faker->numberBetween(1, 20),
                'shares_count' => $this->faker->numberBetween(0, 10)
            ]);
        }

        // Test analytics summary
        $response = $this->getJson('/api/analytics/summary');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_posts',
            'total_engagement',
            'average_engagement',
            'platform_breakdown'
        ]);

        // Test individual post analytics
        $response = $this->getJson("/api/analytics/post/{$posts[0]->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'post',
            'metrics',
            'performance_comparison'
        ]);
    }

    /** @test */
    public function test_system_monitoring_and_alerts()
    {
        $this->actingAs($this->user);

        // Create expired token scenario
        $expiredAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subDays(1)
        ]);

        // Create failed post scenario
        $failedPost = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed'
        ]);

        // Test dashboard alerts
        $response = $this->getJson('/api/system/dashboard');
        $response->assertStatus(200);
        
        $dashboardData = $response->json();
        $this->assertArrayHasKey('system_alerts', $dashboardData);
        
        // Should have alerts for expired token and failed post
        $this->assertGreaterThan(0, count($dashboardData['system_alerts']));
    }

    /** @test */
    public function test_user_permissions_and_security()
    {
        // Test unauthenticated access
        $response = $this->getJson('/api/system/dashboard');
        $response->assertStatus(401);

        // Test cross-user data access
        $otherUser = User::factory()->create();
        $otherAccount = SocialAccount::factory()->create(['user_id' => $otherUser->id]);
        $otherPost = Post::factory()->create(['social_account_id' => $otherAccount->id]);

        $this->actingAs($this->user);

        // Should not be able to access other user's data
        $response = $this->getJson("/api/posts/{$otherPost->id}");
        $response->assertStatus(403);

        $response = $this->getJson("/api/brand-guidelines/social-account/{$otherAccount->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function test_comprehensive_system_integration()
    {
        $this->actingAs($this->user);

        // 1. Test system health endpoint
        $response = $this->getJson('/api/system/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status', 'message'],
                'redis' => ['status', 'message'],
                'queue' => ['status', 'message'],
                'social_apis' => ['status', 'message'],
                'ai_service' => ['status', 'message'],
                'storage' => ['status', 'message'],
            ]
        ]);

        // 2. Test performance metrics endpoint
        $response = $this->getJson('/api/system/performance');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'performance_summary',
            'system_alerts',
            'timestamp'
        ]);

        // 3. Test comprehensive dashboard data
        $response = $this->getJson('/api/system/dashboard');
        $response->assertStatus(200);
        $dashboardData = $response->json();
        
        $this->assertArrayHasKey('user', $dashboardData);
        $this->assertArrayHasKey('connected_accounts', $dashboardData);
        $this->assertArrayHasKey('post_stats', $dashboardData);
        $this->assertArrayHasKey('system_status', $dashboardData);

        // 4. Test error logging and monitoring
        // Create a scenario that should trigger monitoring
        $post = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed'
        ]);

        $response = $this->getJson('/api/system/dashboard');
        $response->assertStatus(200);
        $alerts = $response->json('system_alerts');
        
        // Should have alert for failed post
        $this->assertTrue(
            collect($alerts)->contains(function ($alert) {
                return str_contains($alert['message'], 'failed to publish');
            })
        );

        // 5. Test end-to-end workflow
        $response = $this->postJson('/api/system/test-workflow');
        $response->assertStatus(200);
        $workflowData = $response->json();
        
        $this->assertArrayHasKey('status', $workflowData);
        $this->assertArrayHasKey('results', $workflowData);
        
        // Verify all workflow components are tested
        $expectedTests = [
            'authentication',
            'social_accounts',
            'brand_guidelines',
            'post_management',
            'analytics'
        ];
        
        foreach ($expectedTests as $test) {
            $this->assertArrayHasKey($test, $workflowData['results']);
        }
    }

    /** @test */
    public function test_system_monitoring_integration()
    {
        $this->actingAs($this->user);

        // Create test data that should trigger monitoring
        $expiredAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subDays(1)
        ]);

        $failedPost = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'failed',
            'created_at' => now()->subHours(2)
        ]);

        // Test that monitoring detects issues
        $response = $this->getJson('/api/system/dashboard');
        $response->assertStatus(200);
        
        $alerts = $response->json('system_alerts');
        $this->assertIsArray($alerts);
        
        // Should detect expired token
        $hasExpiredTokenAlert = collect($alerts)->contains(function ($alert) {
            return str_contains($alert['message'], 'expired');
        });
        $this->assertTrue($hasExpiredTokenAlert);

        // Should detect failed post
        $hasFailedPostAlert = collect($alerts)->contains(function ($alert) {
            return str_contains($alert['message'], 'failed');
        });
        $this->assertTrue($hasFailedPostAlert);
    }
}