<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SocialAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => $this->faker->randomElement(['instagram', 'facebook', 'linkedin']),
            'platform_user_id' => $this->faker->unique()->numerify('##########'),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
            'account_name' => $this->faker->userName(),
        ];
    }

    /**
     * Indicate that the social account is for Instagram.
     */
    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'instagram',
        ]);
    }

    /**
     * Indicate that the social account is for Facebook.
     */
    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'facebook',
        ]);
    }

    /**
     * Indicate that the social account is for LinkedIn.
     */
    public function linkedin(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'linkedin',
        ]);
    }
}