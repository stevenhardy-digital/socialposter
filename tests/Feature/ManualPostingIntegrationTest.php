<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPostingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected SocialAccount $socialAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->socialAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'facebook', // Use Facebook as it supports API posting in our implementation
            'access_token' => 'test_token_123'
        ]);
    }

    public function test_manual_post_creation_and_validation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/posts/create-and-publish', [
                'social_account_id' => $this->socialAccount->id,
                'content' => 'Test manual post content',
                'media_urls' => ['https://example.com/image.jpg']
            ]);

        // Should create post but fail to publish due to mock API
        $response->assertStatus(422);
        
        // Verify post was created
        $this->assertDatabaseHas('posts', [
            'social_account_id' => $this->socialAccount->id,
            'content' => 'Test manual post content',
            'is_ai_generated' => false,
            'status' => 'draft' // Should revert to draft on API failure
        ]);
    }

    public function test_manual_post_with_api_restrictions()
    {
        // Create Instagram account (has API restrictions in our implementation)
        $instagramAccount = SocialAccount::factory()->create([
            'user_id' => $this->user->id,
            'platform' => 'instagram',
            'access_token' => 'instagram_token_123'
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/posts/create-and-publish', [
                'social_account_id' => $instagramAccount->id,
                'content' => 'Instagram manual post content'
            ]);

        // Should return manual instructions
        $response->assertStatus(202)
            ->assertJsonStructure([
                'post',
                'requires_manual_posting',
                'instructions',
                'content_to_copy',
                'platform_url'
            ]);

        // Verify post was created as approved
        $this->assertDatabaseHas('posts', [
            'social_account_id' => $instagramAccount->id,
            'content' => 'Instagram manual post content',
            'is_ai_generated' => false,
            'status' => 'approved'
        ]);
    }

    public function test_mark_post_as_manually_published()
    {
        $post = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'approved',
            'is_ai_generated' => false
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$post->id}/mark-published", [
                'platform_post_id' => 'manual_facebook_12345'
            ]);

        $response->assertStatus(200);

        // Verify post is marked as published
        $post->refresh();
        $this->assertEquals('published', $post->status);
        $this->assertEquals('manual_facebook_12345', $post->platform_post_id);
        $this->assertNotNull($post->published_at);
    }

    public function test_publish_existing_approved_post()
    {
        $post = Post::factory()->create([
            'social_account_id' => $this->socialAccount->id,
            'status' => 'approved',
            'is_ai_generated' => false,
            'content' => 'Existing approved post'
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$post->id}/publish");

        // Should fail due to mock API but attempt publishing
        $response->assertStatus(422);
        
        // Verify post status reverted to draft on failure
        $post->refresh();
        $this->assertEquals('draft', $post->status);
        $this->assertNotNull($post->last_error);
    }

    public function test_validation_errors_for_manual_posting()
    {
        // Test missing content
        $response = $this->actingAs($this->user)
            ->postJson('/api/posts/create-and-publish', [
                'social_account_id' => $this->socialAccount->id,
                'content' => '' // Empty content
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        // Test invalid social account
        $response = $this->actingAs($this->user)
            ->postJson('/api/posts/create-and-publish', [
                'social_account_id' => 99999, // Non-existent account
                'content' => 'Test content'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['social_account_id']);

        // Test content too long
        $response = $this->actingAs($this->user)
            ->postJson('/api/posts/create-and-publish', [
                'social_account_id' => $this->socialAccount->id,
                'content' => str_repeat('a', 2201) // Exceeds 2200 character limit
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_unauthorized_access_to_other_users_posts()
    {
        $otherUser = User::factory()->create();
        $otherSocialAccount = SocialAccount::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $post = Post::factory()->create([
            'social_account_id' => $otherSocialAccount->id,
            'status' => 'approved'
        ]);

        // Try to publish another user's post
        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$post->id}/publish");

        $response->assertStatus(403);

        // Try to mark another user's post as published
        $response = $this->actingAs($this->user)
            ->postJson("/api/posts/{$post->id}/mark-published");

        $response->assertStatus(403);
    }
}