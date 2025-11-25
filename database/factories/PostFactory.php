<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'content' => $this->faker->paragraph(3),
            'media_urls' => $this->faker->optional(0.3)->randomElements([
                'https://example.com/image1.jpg',
                'https://example.com/image2.jpg',
                'https://example.com/video1.mp4'
            ], $this->faker->numberBetween(1, 2)),
            'status' => $this->faker->randomElement(['draft', 'approved', 'published', 'rejected']),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'published_at' => null,
            'platform_post_id' => null,
            'is_ai_generated' => $this->faker->boolean(70), // 70% chance of being AI generated
        ];
    }

    /**
     * Indicate that the post is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
            'platform_post_id' => null,
        ]);
    }

    /**
     * Indicate that the post is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'published_at' => null,
            'platform_post_id' => null,
        ]);
    }

    /**
     * Indicate that the post is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'platform_post_id' => $this->faker->unique()->numerify('post_##########'),
        ]);
    }

    /**
     * Indicate that the post is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'published_at' => null,
            'platform_post_id' => null,
        ]);
    }

    /**
     * Indicate that the post is AI generated.
     */
    public function aiGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ai_generated' => true,
        ]);
    }

    /**
     * Indicate that the post is manually created.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ai_generated' => false,
        ]);
    }
}