<?php

namespace Database\Factories;

use App\Models\BrandGuideline;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BrandGuideline>
 */
class BrandGuidelineFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BrandGuideline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_account_id' => SocialAccount::factory(),
            'tone_of_voice' => $this->faker->randomElement([
                'Professional and informative',
                'Casual and friendly', 
                'Authoritative and expert',
                'Creative and inspiring'
            ]),
            'brand_voice' => $this->faker->randomElement([
                'Confident',
                'Approachable', 
                'Knowledgeable',
                'Enthusiastic'
            ]),
            'content_themes' => $this->faker->randomElement([
                ['Technology', 'Business'],
                ['Lifestyle', 'Health'],
                ['Education', 'Finance'],
                ['Marketing', 'Innovation']
            ]),
            'hashtag_strategy' => $this->faker->randomElement([
                ['#tech', '#business'],
                ['#lifestyle', '#health'],
                ['#education', '#finance'],
                ['#marketing', '#innovation']
            ]),
            'posting_frequency' => $this->faker->randomElement([
                'daily',
                'weekly',
                'bi-weekly',
                'monthly'
            ]),
        ];
    }
}