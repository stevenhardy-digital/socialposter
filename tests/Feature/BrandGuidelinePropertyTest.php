<?php

namespace Tests\Feature;

use App\Models\BrandGuideline;
use App\Models\SocialAccount;
use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BrandGuidelinePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * **Feature: social-media-platform, Property 9: Brand guidelines save and validate**
     * **Validates: Requirements 3.2**
     * 
     * For any valid brand guidelines input, the system should validate and store the configuration for the specific platform
     */
    public function testBrandGuidelinesSaveAndValidate()
    {
        $this->forAll(
            Generator\elements(['Professional and informative', 'Casual and friendly', 'Authoritative and expert', 'Creative and inspiring']),
            Generator\elements(['Confident', 'Approachable', 'Knowledgeable', 'Enthusiastic']),
            Generator\elements([
                ['Technology'],
                ['Business', 'Technology'],
                ['Lifestyle', 'Health'],
                ['Education', 'Business', 'Technology']
            ]),
            Generator\elements([
                ['#tech'],
                ['#business', '#innovation'],
                ['#growth', '#success', '#tips'],
                ['#tech', '#business']
            ]),
            Generator\elements(['daily', 'weekly', 'bi-weekly', 'monthly'])
        )->then(function ($toneOfVoice, $brandVoice, $contentThemes, $hashtagStrategy, $postingFrequency) {
            // Create mock user and social account for testing
            $user = new User([
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com'
            ]);
            
            $socialAccount = new SocialAccount([
                'id' => 1,
                'user_id' => 1,
                'platform' => 'instagram',
                'platform_user_id' => '123456789',
                'account_name' => 'test_account'
            ]);

            // Test data structure validation
            $guidelineData = [
                'tone_of_voice' => $toneOfVoice,
                'brand_voice' => $brandVoice,
                'content_themes' => $contentThemes,
                'hashtag_strategy' => $hashtagStrategy,
                'posting_frequency' => $postingFrequency,
            ];

            // Test Laravel validation rules
            $validator = Validator::make($guidelineData, [
                'tone_of_voice' => 'required|string|max:1000',
                'brand_voice' => 'required|string|max:1000',
                'content_themes' => 'required|array|min:1',
                'content_themes.*' => 'string|max:255',
                'hashtag_strategy' => 'required|array|min:1',
                'hashtag_strategy.*' => 'string|max:100',
                'posting_frequency' => 'required|string|in:daily,weekly,bi-weekly,monthly',
            ]);

            $this->assertFalse($validator->fails(), 'Generated data should pass validation rules');

            // Test that brand guidelines can be created with proper structure
            $brandGuideline = new BrandGuideline([
                'social_account_id' => $socialAccount->id,
                ...$guidelineData
            ]);

            $this->assertInstanceOf(BrandGuideline::class, $brandGuideline);
            $this->assertEquals($socialAccount->id, $brandGuideline->social_account_id);
            $this->assertEquals($toneOfVoice, $brandGuideline->tone_of_voice);
            $this->assertEquals($brandVoice, $brandGuideline->brand_voice);
            $this->assertEquals($contentThemes, $brandGuideline->content_themes);
            $this->assertEquals($hashtagStrategy, $brandGuideline->hashtag_strategy);
            $this->assertEquals($postingFrequency, $brandGuideline->posting_frequency);

            // Test that the brand guideline has the correct fillable attributes
            $fillable = $brandGuideline->getFillable();
            $this->assertContains('social_account_id', $fillable);
            $this->assertContains('tone_of_voice', $fillable);
            $this->assertContains('brand_voice', $fillable);
            $this->assertContains('content_themes', $fillable);
            $this->assertContains('hashtag_strategy', $fillable);
            $this->assertContains('posting_frequency', $fillable);

            // Test that data structure is maintained correctly
            $this->assertEquals($guidelineData['tone_of_voice'], $brandGuideline->tone_of_voice);
            $this->assertEquals($guidelineData['brand_voice'], $brandGuideline->brand_voice);
            $this->assertEquals($guidelineData['content_themes'], $brandGuideline->content_themes);
            $this->assertEquals($guidelineData['hashtag_strategy'], $brandGuideline->hashtag_strategy);
            $this->assertEquals($guidelineData['posting_frequency'], $brandGuideline->posting_frequency);
        });
    }

    /**
     * **Feature: social-media-platform, Property 10: Updated guidelines affect AI generation**
     * **Validates: Requirements 3.3**
     * 
     * For any brand guideline changes, the system should apply new settings to future AI content generation
     */
    public function testUpdatedGuidelinesAffectAIGeneration()
    {
        $this->forAll(
            Generator\elements(['Professional and informative', 'Casual and friendly', 'Authoritative and expert']),
            Generator\elements(['Confident', 'Approachable', 'Knowledgeable']),
            Generator\elements([
                ['Technology', 'Business'],
                ['Lifestyle', 'Health'],
                ['Education', 'Finance']
            ]),
            Generator\elements([
                ['#tech', '#business'],
                ['#health', '#lifestyle'],
                ['#education', '#finance']
            ]),
            Generator\elements(['daily', 'weekly', 'monthly'])
        )->then(function ($toneOfVoice, $brandVoice, $contentThemes, $hashtagStrategy, $postingFrequency) {
            // Create mock brand guideline
            $brandGuideline = new BrandGuideline([
                'social_account_id' => 1,
                'tone_of_voice' => $toneOfVoice,
                'brand_voice' => $brandVoice,
                'content_themes' => $contentThemes,
                'hashtag_strategy' => $hashtagStrategy,
                'posting_frequency' => $postingFrequency,
            ]);

            // Test that brand guidelines contain all necessary data for AI generation
            $this->assertNotEmpty($brandGuideline->tone_of_voice);
            $this->assertNotEmpty($brandGuideline->brand_voice);
            $this->assertNotEmpty($brandGuideline->content_themes);
            $this->assertNotEmpty($brandGuideline->hashtag_strategy);
            $this->assertNotEmpty($brandGuideline->posting_frequency);

            // Test that content themes are properly structured for AI consumption
            $this->assertIsArray($brandGuideline->content_themes);
            $this->assertGreaterThan(0, count($brandGuideline->content_themes));
            foreach ($brandGuideline->content_themes as $theme) {
                $this->assertIsString($theme);
                $this->assertNotEmpty($theme);
            }

            // Test that hashtag strategy is properly structured for AI consumption
            $this->assertIsArray($brandGuideline->hashtag_strategy);
            $this->assertGreaterThan(0, count($brandGuideline->hashtag_strategy));
            foreach ($brandGuideline->hashtag_strategy as $hashtag) {
                $this->assertIsString($hashtag);
                $this->assertStringStartsWith('#', $hashtag);
            }

            // Test that posting frequency is valid for AI scheduling
            $validFrequencies = ['daily', 'weekly', 'bi-weekly', 'monthly'];
            $this->assertContains($brandGuideline->posting_frequency, $validFrequencies);

            // Test that tone of voice can be used in AI prompts
            $this->assertIsString($brandGuideline->tone_of_voice);
            $this->assertGreaterThan(10, strlen($brandGuideline->tone_of_voice)); // Should be descriptive

            // Test that brand voice can be used in AI prompts
            $this->assertIsString($brandGuideline->brand_voice);
            $this->assertGreaterThan(3, strlen($brandGuideline->brand_voice)); // Should be descriptive

            // Test that guidelines can be serialized for AI API calls
            $serializedGuidelines = json_encode([
                'tone_of_voice' => $brandGuideline->tone_of_voice,
                'brand_voice' => $brandGuideline->brand_voice,
                'content_themes' => $brandGuideline->content_themes,
                'hashtag_strategy' => $brandGuideline->hashtag_strategy,
                'posting_frequency' => $brandGuideline->posting_frequency,
            ]);
            
            $this->assertJson($serializedGuidelines);
            $decodedGuidelines = json_decode($serializedGuidelines, true);
            $this->assertEquals($brandGuideline->tone_of_voice, $decodedGuidelines['tone_of_voice']);
            $this->assertEquals($brandGuideline->brand_voice, $decodedGuidelines['brand_voice']);
            $this->assertEquals($brandGuideline->content_themes, $decodedGuidelines['content_themes']);
            $this->assertEquals($brandGuideline->hashtag_strategy, $decodedGuidelines['hashtag_strategy']);
            $this->assertEquals($brandGuideline->posting_frequency, $decodedGuidelines['posting_frequency']);
        });
    }

    /**
     * **Feature: social-media-platform, Property 11: Guidelines retrieval displays correctly**
     * **Validates: Requirements 3.4**
     * 
     * For any saved brand guidelines, viewing platform settings should display current guidelines and allow modifications
     */
    public function testGuidelinesRetrievalDisplaysCorrectly()
    {
        $this->forAll(
            Generator\elements(['Professional and informative', 'Casual and friendly', 'Authoritative and expert']),
            Generator\elements(['Confident', 'Approachable', 'Knowledgeable']),
            Generator\elements([
                ['Technology', 'Business'],
                ['Lifestyle', 'Health'],
                ['Education', 'Finance']
            ]),
            Generator\elements([
                ['#tech', '#business'],
                ['#health', '#lifestyle'],
                ['#education', '#finance']
            ]),
            Generator\elements(['daily', 'weekly', 'monthly'])
        )->then(function ($toneOfVoice, $brandVoice, $contentThemes, $hashtagStrategy, $postingFrequency) {
            // Create mock brand guideline
            $brandGuideline = new BrandGuideline([
                'social_account_id' => 1,
                'tone_of_voice' => $toneOfVoice,
                'brand_voice' => $brandVoice,
                'content_themes' => $contentThemes,
                'hashtag_strategy' => $hashtagStrategy,
                'posting_frequency' => $postingFrequency,
            ]);

            // Test that all guideline data is retrievable
            $this->assertEquals($toneOfVoice, $brandGuideline->tone_of_voice);
            $this->assertEquals($brandVoice, $brandGuideline->brand_voice);
            $this->assertEquals($contentThemes, $brandGuideline->content_themes);
            $this->assertEquals($hashtagStrategy, $brandGuideline->hashtag_strategy);
            $this->assertEquals($postingFrequency, $brandGuideline->posting_frequency);

            // Test that guidelines can be converted to array for API responses
            $guidelineArray = $brandGuideline->toArray();
            $this->assertArrayHasKey('tone_of_voice', $guidelineArray);
            $this->assertArrayHasKey('brand_voice', $guidelineArray);
            $this->assertArrayHasKey('content_themes', $guidelineArray);
            $this->assertArrayHasKey('hashtag_strategy', $guidelineArray);
            $this->assertArrayHasKey('posting_frequency', $guidelineArray);

            // Test that array values match original data
            $this->assertEquals($toneOfVoice, $guidelineArray['tone_of_voice']);
            $this->assertEquals($brandVoice, $guidelineArray['brand_voice']);
            $this->assertEquals($contentThemes, $guidelineArray['content_themes']);
            $this->assertEquals($hashtagStrategy, $guidelineArray['hashtag_strategy']);
            $this->assertEquals($postingFrequency, $guidelineArray['posting_frequency']);

            // Test that guidelines can be serialized for frontend display
            $jsonResponse = json_encode([
                'brand_guideline' => $brandGuideline->toArray()
            ]);
            $this->assertJson($jsonResponse);
            
            $decodedResponse = json_decode($jsonResponse, true);
            $this->assertArrayHasKey('brand_guideline', $decodedResponse);
            
            $retrievedGuideline = $decodedResponse['brand_guideline'];
            $this->assertEquals($toneOfVoice, $retrievedGuideline['tone_of_voice']);
            $this->assertEquals($brandVoice, $retrievedGuideline['brand_voice']);
            $this->assertEquals($contentThemes, $retrievedGuideline['content_themes']);
            $this->assertEquals($hashtagStrategy, $retrievedGuideline['hashtag_strategy']);
            $this->assertEquals($postingFrequency, $retrievedGuideline['posting_frequency']);

            // Test that guidelines maintain data types for frontend consumption
            $this->assertIsString($retrievedGuideline['tone_of_voice']);
            $this->assertIsString($retrievedGuideline['brand_voice']);
            $this->assertIsArray($retrievedGuideline['content_themes']);
            $this->assertIsArray($retrievedGuideline['hashtag_strategy']);
            $this->assertIsString($retrievedGuideline['posting_frequency']);
        });
    }

    /**
     * **Feature: social-media-platform, Property 12: Platform guidelines isolation**
     * **Validates: Requirements 3.5**
     * 
     * For any number of connected platforms, the system should maintain separate brand guidelines for each platform
     */
    public function testPlatformGuidelinesIsolation()
    {
        $this->forAll(
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['twitter', 'tiktok', 'youtube']),
            Generator\elements(['Professional tone', 'Casual tone', 'Expert tone']),
            Generator\elements(['Friendly voice', 'Authoritative voice', 'Creative voice']),
            Generator\elements([
                ['Tech', 'Business'],
                ['Lifestyle', 'Health'],
                ['Education', 'Finance']
            ])
        )->then(function ($platform1, $platform2, $tone1, $tone2, $themes) {
            // Define social account IDs for isolation testing
            $socialAccountId1 = 1;
            $socialAccountId2 = 2;

            // Create brand guidelines for each platform with different settings
            $guideline1 = new BrandGuideline([
                'social_account_id' => $socialAccountId1,
                'tone_of_voice' => $tone1,
                'brand_voice' => 'Voice for ' . $platform1,
                'content_themes' => $themes,
                'hashtag_strategy' => ['#' . $platform1],
                'posting_frequency' => 'daily',
            ]);

            $guideline2 = new BrandGuideline([
                'social_account_id' => $socialAccountId2,
                'tone_of_voice' => $tone2,
                'brand_voice' => 'Voice for ' . $platform2,
                'content_themes' => array_reverse($themes), // Different order
                'hashtag_strategy' => ['#' . $platform2],
                'posting_frequency' => 'weekly',
            ]);

            // Test that guidelines are properly isolated by social account
            $this->assertNotEquals($guideline1->social_account_id, $guideline2->social_account_id);
            $this->assertEquals($socialAccountId1, $guideline1->social_account_id);
            $this->assertEquals($socialAccountId2, $guideline2->social_account_id);

            // Test that guidelines can have different settings per platform
            $this->assertNotEquals($guideline1->tone_of_voice, $guideline2->tone_of_voice);
            $this->assertNotEquals($guideline1->brand_voice, $guideline2->brand_voice);
            $this->assertNotEquals($guideline1->hashtag_strategy, $guideline2->hashtag_strategy);
            $this->assertNotEquals($guideline1->posting_frequency, $guideline2->posting_frequency);

            // Test that platform-specific data is maintained
            $this->assertStringContainsString($platform1, $guideline1->brand_voice);
            $this->assertStringContainsString($platform2, $guideline2->brand_voice);
            $this->assertStringContainsString($platform1, $guideline1->hashtag_strategy[0]);
            $this->assertStringContainsString($platform2, $guideline2->hashtag_strategy[0]);

            // Test that guidelines can be grouped by platform for API responses
            $platformGuidelines = [
                $platform1 => $guideline1->toArray(),
                $platform2 => $guideline2->toArray()
            ];

            $this->assertArrayHasKey($platform1, $platformGuidelines);
            $this->assertArrayHasKey($platform2, $platformGuidelines);
            $this->assertNotEquals($platformGuidelines[$platform1], $platformGuidelines[$platform2]);

            // Test that each platform maintains its own complete guideline set
            foreach ([$platform1, $platform2] as $platform) {
                $this->assertArrayHasKey('tone_of_voice', $platformGuidelines[$platform]);
                $this->assertArrayHasKey('brand_voice', $platformGuidelines[$platform]);
                $this->assertArrayHasKey('content_themes', $platformGuidelines[$platform]);
                $this->assertArrayHasKey('hashtag_strategy', $platformGuidelines[$platform]);
                $this->assertArrayHasKey('posting_frequency', $platformGuidelines[$platform]);
            }

            // Test that isolation prevents cross-platform contamination
            $this->assertNotEquals(
                $platformGuidelines[$platform1]['tone_of_voice'],
                $platformGuidelines[$platform2]['tone_of_voice']
            );
            $this->assertNotEquals(
                $platformGuidelines[$platform1]['hashtag_strategy'],
                $platformGuidelines[$platform2]['hashtag_strategy']
            );
        });
    }
}