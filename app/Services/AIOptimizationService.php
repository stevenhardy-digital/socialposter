<?php

namespace App\Services;

use App\Models\EngagementMetric;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\BrandGuideline;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AIOptimizationService
{
    /**
     * Analyze engagement patterns for high-performing content
     */
    public function analyzePerformancePatterns(SocialAccount $account): array
    {
        $posts = Post::where('social_account_id', $account->id)
            ->where('status', 'published')
            ->whereHas('engagementMetrics')
            ->with('engagementMetrics')
            ->get();

        if ($posts->isEmpty()) {
            return $this->getDefaultPatterns($account->platform);
        }

        $patterns = [];
        
        // Calculate engagement rates and identify high performers
        $engagementRates = $posts->map(function ($post) {
            $metrics = $post->engagementMetrics;
            if (!$metrics || !$metrics->reach) {
                return null;
            }
            
            $totalEngagement = $metrics->likes_count + $metrics->comments_count + $metrics->shares_count;
            return [
                'post' => $post,
                'engagement_rate' => $totalEngagement / $metrics->reach,
                'total_engagement' => $totalEngagement,
                'reach' => $metrics->reach
            ];
        })->filter();

        if ($engagementRates->isEmpty()) {
            return $this->getDefaultPatterns($account->platform);
        }

        // Find top performing posts (top 25%)
        $sortedByEngagement = $engagementRates->sortByDesc('engagement_rate');
        $topPerformers = $sortedByEngagement->take(max(1, ceil($sortedByEngagement->count() * 0.25)));

        // Analyze patterns in top performers
        $patterns['content_length'] = $this->analyzeContentLength($topPerformers);
        $patterns['hashtag_usage'] = $this->analyzeHashtagUsage($topPerformers);
        $patterns['posting_time'] = $this->analyzePostingTime($topPerformers);
        $patterns['content_themes'] = $this->analyzeContentThemes($topPerformers);
        $patterns['engagement_triggers'] = $this->analyzeEngagementTriggers($topPerformers);

        return $patterns;
    }

    /**
     * Incorporate successful patterns into new content generation
     */
    public function incorporateSuccessfulPatterns(SocialAccount $account, array $patterns, ?BrandGuideline $guidelines = null): array
    {
        $optimizedParameters = [];

        // Apply content length optimization
        if (isset($patterns['content_length'])) {
            $optimizedParameters['target_length'] = $patterns['content_length']['optimal_range'];
        }

        // Apply hashtag optimization
        if (isset($patterns['hashtag_usage'])) {
            $optimizedParameters['hashtag_count'] = $patterns['hashtag_usage']['optimal_count'];
            $optimizedParameters['top_hashtags'] = $patterns['hashtag_usage']['high_performing_tags'];
        }

        // Apply content theme optimization
        if (isset($patterns['content_themes'])) {
            $optimizedParameters['preferred_themes'] = $patterns['content_themes']['top_themes'];
        }

        // Apply engagement trigger optimization
        if (isset($patterns['engagement_triggers'])) {
            $optimizedParameters['engagement_triggers'] = $patterns['engagement_triggers']['effective_triggers'];
        }

        // Merge with existing brand guidelines if available
        if ($guidelines) {
            $optimizedParameters = $this->mergeWithBrandGuidelines($optimizedParameters, $guidelines);
        }

        return $optimizedParameters;
    }

    /**
     * Adjust parameters based on poor performance
     */
    public function adjustParametersForPoorPerformance(SocialAccount $account): array
    {
        $recentPosts = Post::where('social_account_id', $account->id)
            ->where('status', 'published')
            ->whereHas('engagementMetrics')
            ->with('engagementMetrics')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        if ($recentPosts->isEmpty()) {
            return $this->getDefaultParameters($account->platform);
        }

        // Calculate average engagement rate
        $totalEngagement = 0;
        $totalReach = 0;
        $validPosts = 0;

        foreach ($recentPosts as $post) {
            $metrics = $post->engagementMetrics;
            if ($metrics && $metrics->reach > 0) {
                $totalEngagement += $metrics->likes_count + $metrics->comments_count + $metrics->shares_count;
                $totalReach += $metrics->reach;
                $validPosts++;
            }
        }

        if ($validPosts === 0) {
            return $this->getDefaultParameters($account->platform);
        }

        $avgEngagementRate = $totalEngagement / $totalReach;
        $platformBenchmark = $this->getPlatformBenchmark($account->platform);

        // If performance is below benchmark, adjust parameters
        if ($avgEngagementRate < $platformBenchmark) {
            return $this->generateAdjustedParameters($account->platform, $avgEngagementRate, $platformBenchmark);
        }

        return [];
    }

    /**
     * Refine prompts based on engagement feedback
     */
    public function refinePromptsWithEngagementFeedback(SocialAccount $account): array
    {
        $patterns = $this->analyzePerformancePatterns($account);
        
        if (empty($patterns) || !isset($patterns['content_themes'])) {
            return $this->getDefaultPromptRefinements($account->platform);
        }

        $refinements = [];

        // Refine tone based on engagement
        if (isset($patterns['engagement_triggers']['tone_preferences'])) {
            $refinements['tone_adjustments'] = $patterns['engagement_triggers']['tone_preferences'];
        }

        // Refine content structure based on performance
        if (isset($patterns['content_length']['optimal_range'])) {
            $refinements['structure_guidance'] = $this->generateStructureGuidance($patterns['content_length']);
        }

        // Refine call-to-action based on engagement
        if (isset($patterns['engagement_triggers']['effective_triggers'])) {
            $refinements['cta_recommendations'] = $patterns['engagement_triggers']['effective_triggers'];
        }

        return $refinements;
    }

    /**
     * Use default parameters when insufficient data exists
     */
    public function useDefaultParameters(string $platform): array
    {
        return $this->getDefaultParameters($platform);
    }

    /**
     * Analyze content length patterns
     */
    private function analyzeContentLength(Collection $topPerformers): array
    {
        $lengths = $topPerformers->map(function ($item) {
            return strlen($item['post']->content);
        });

        return [
            'optimal_range' => [
                'min' => $lengths->min(),
                'max' => $lengths->max(),
                'average' => $lengths->avg()
            ],
            'distribution' => $lengths->toArray()
        ];
    }

    /**
     * Analyze hashtag usage patterns
     */
    private function analyzeHashtagUsage(Collection $topPerformers): array
    {
        $hashtagCounts = [];
        $allHashtags = [];

        foreach ($topPerformers as $item) {
            $content = $item['post']->content;
            preg_match_all('/#\w+/', $content, $matches);
            $hashtags = $matches[0];
            
            $hashtagCounts[] = count($hashtags);
            $allHashtags = array_merge($allHashtags, $hashtags);
        }

        $hashtagFrequency = array_count_values($allHashtags);
        arsort($hashtagFrequency);

        return [
            'optimal_count' => !empty($hashtagCounts) ? round(array_sum($hashtagCounts) / count($hashtagCounts)) : 3,
            'high_performing_tags' => array_slice(array_keys($hashtagFrequency), 0, 10),
            'frequency_distribution' => $hashtagFrequency
        ];
    }

    /**
     * Analyze posting time patterns
     */
    private function analyzePostingTime(Collection $topPerformers): array
    {
        $postingTimes = $topPerformers->map(function ($item) {
            return [
                'hour' => $item['post']->published_at->hour,
                'day_of_week' => $item['post']->published_at->dayOfWeek,
                'engagement_rate' => $item['engagement_rate']
            ];
        });

        $hourlyPerformance = $postingTimes->groupBy('hour')->map(function ($group) {
            return $group->avg('engagement_rate');
        });

        $dailyPerformance = $postingTimes->groupBy('day_of_week')->map(function ($group) {
            return $group->avg('engagement_rate');
        });

        return [
            'optimal_hours' => $hourlyPerformance->sortDesc()->keys()->take(3)->toArray(),
            'optimal_days' => $dailyPerformance->sortDesc()->keys()->take(3)->toArray(),
            'hourly_performance' => $hourlyPerformance->toArray(),
            'daily_performance' => $dailyPerformance->toArray()
        ];
    }

    /**
     * Analyze content themes
     */
    private function analyzeContentThemes(Collection $topPerformers): array
    {
        $themes = [];
        $keywords = [];

        foreach ($topPerformers as $item) {
            $content = strtolower($item['post']->content);
            
            // Extract keywords (simple approach)
            $words = str_word_count($content, 1);
            $filteredWords = array_filter($words, function ($word) {
                return strlen($word) > 3 && !in_array($word, ['this', 'that', 'with', 'from', 'they', 'have', 'been']);
            });
            
            $keywords = array_merge($keywords, $filteredWords);
        }

        $keywordFrequency = array_count_values($keywords);
        arsort($keywordFrequency);

        return [
            'top_themes' => array_slice(array_keys($keywordFrequency), 0, 10),
            'keyword_frequency' => $keywordFrequency
        ];
    }

    /**
     * Analyze engagement triggers
     */
    private function analyzeEngagementTriggers(Collection $topPerformers): array
    {
        $triggers = [];
        
        foreach ($topPerformers as $item) {
            $content = $item['post']->content;
            
            // Check for questions
            if (strpos($content, '?') !== false) {
                $triggers['questions'][] = $item['engagement_rate'];
            }
            
            // Check for emojis
            if (preg_match('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]/u', $content)) {
                $triggers['emojis'][] = $item['engagement_rate'];
            }
            
            // Check for calls to action
            $ctaWords = ['comment', 'share', 'like', 'follow', 'click', 'visit', 'check out', 'learn more'];
            foreach ($ctaWords as $cta) {
                if (stripos($content, $cta) !== false) {
                    $triggers['call_to_action'][] = $item['engagement_rate'];
                    break;
                }
            }
        }

        $effectiveTriggers = [];
        foreach ($triggers as $trigger => $rates) {
            if (!empty($rates)) {
                $effectiveTriggers[$trigger] = array_sum($rates) / count($rates);
            }
        }

        return [
            'effective_triggers' => $effectiveTriggers,
            'tone_preferences' => $this->analyzeTonePreferences($topPerformers)
        ];
    }

    /**
     * Analyze tone preferences from top performers
     */
    private function analyzeTonePreferences(Collection $topPerformers): array
    {
        $toneIndicators = [
            'professional' => ['business', 'professional', 'industry', 'expertise', 'solution'],
            'casual' => ['hey', 'awesome', 'cool', 'fun', 'love', 'amazing'],
            'inspirational' => ['inspire', 'motivate', 'achieve', 'success', 'dream', 'goal']
        ];

        $toneScores = [];
        
        foreach ($topPerformers as $item) {
            $content = strtolower($item['post']->content);
            
            foreach ($toneIndicators as $tone => $indicators) {
                $score = 0;
                foreach ($indicators as $indicator) {
                    if (strpos($content, $indicator) !== false) {
                        $score++;
                    }
                }
                
                if (!isset($toneScores[$tone])) {
                    $toneScores[$tone] = [];
                }
                $toneScores[$tone][] = $score * $item['engagement_rate'];
            }
        }

        $avgToneScores = [];
        foreach ($toneScores as $tone => $scores) {
            if (!empty($scores)) {
                $avgToneScores[$tone] = array_sum($scores) / count($scores);
            }
        }

        arsort($avgToneScores);
        
        return $avgToneScores;
    }

    /**
     * Merge optimized parameters with existing brand guidelines
     */
    private function mergeWithBrandGuidelines(array $optimizedParameters, BrandGuideline $guidelines): array
    {
        $merged = $optimizedParameters;

        // Preserve core brand elements while incorporating optimizations
        if ($guidelines->tone_of_voice) {
            $merged['base_tone'] = $guidelines->tone_of_voice;
        }

        if ($guidelines->brand_voice) {
            $merged['brand_voice'] = $guidelines->brand_voice;
        }

        // Merge hashtag strategies
        if ($guidelines->hashtag_strategy && isset($optimizedParameters['top_hashtags'])) {
            $merged['combined_hashtags'] = array_unique(array_merge(
                $guidelines->hashtag_strategy,
                $optimizedParameters['top_hashtags']
            ));
        }

        // Merge content themes
        if ($guidelines->content_themes && isset($optimizedParameters['preferred_themes'])) {
            $merged['combined_themes'] = array_unique(array_merge(
                $guidelines->content_themes,
                $optimizedParameters['preferred_themes']
            ));
        }

        return $merged;
    }

    /**
     * Get default patterns when no data is available
     */
    private function getDefaultPatterns(string $platform): array
    {
        return [
            'content_length' => $this->getDefaultContentLength($platform),
            'hashtag_usage' => $this->getDefaultHashtagUsage($platform),
            'posting_time' => $this->getDefaultPostingTime($platform),
            'content_themes' => $this->getDefaultContentThemes($platform),
            'engagement_triggers' => $this->getDefaultEngagementTriggers($platform)
        ];
    }

    /**
     * Get default parameters for a platform
     */
    private function getDefaultParameters(string $platform): array
    {
        $defaults = [
            'instagram' => [
                'target_length' => ['min' => 100, 'max' => 300, 'average' => 200],
                'hashtag_count' => 8,
                'tone' => 'visual and engaging',
                'structure' => 'hook, content, call-to-action'
            ],
            'facebook' => [
                'target_length' => ['min' => 50, 'max' => 150, 'average' => 100],
                'hashtag_count' => 3,
                'tone' => 'conversational and community-focused',
                'structure' => 'question, content, engagement prompt'
            ],
            'linkedin' => [
                'target_length' => ['min' => 200, 'max' => 600, 'average' => 400],
                'hashtag_count' => 5,
                'tone' => 'professional and insightful',
                'structure' => 'insight, explanation, professional call-to-action'
            ]
        ];

        return $defaults[$platform] ?? $defaults['instagram'];
    }

    /**
     * Get platform engagement benchmark
     */
    private function getPlatformBenchmark(string $platform): float
    {
        $benchmarks = [
            'instagram' => 0.018, // 1.8% average engagement rate
            'facebook' => 0.009,  // 0.9% average engagement rate
            'linkedin' => 0.027   // 2.7% average engagement rate
        ];

        return $benchmarks[$platform] ?? 0.015;
    }

    /**
     * Generate adjusted parameters for poor performance
     */
    private function generateAdjustedParameters(string $platform, float $currentRate, float $benchmark): array
    {
        $adjustments = [];
        
        // If performance is significantly below benchmark, suggest changes
        $performanceGap = ($benchmark - $currentRate) / $benchmark;
        
        if ($performanceGap > 0.5) { // More than 50% below benchmark
            $adjustments['tone_adjustment'] = 'more engaging and interactive';
            $adjustments['content_adjustment'] = 'include more questions and calls-to-action';
            $adjustments['hashtag_adjustment'] = 'use more trending and relevant hashtags';
        } elseif ($performanceGap > 0.25) { // 25-50% below benchmark
            $adjustments['tone_adjustment'] = 'slightly more conversational';
            $adjustments['content_adjustment'] = 'add more visual elements or storytelling';
        }

        return $adjustments;
    }

    /**
     * Get default prompt refinements
     */
    private function getDefaultPromptRefinements(string $platform): array
    {
        $refinements = [
            'instagram' => [
                'tone_adjustments' => ['visual', 'engaging', 'authentic'],
                'structure_guidance' => 'Start with a hook, provide value, end with engagement',
                'cta_recommendations' => ['Double tap if you agree', 'Share your thoughts below', 'Tag someone who needs this']
            ],
            'facebook' => [
                'tone_adjustments' => ['conversational', 'community-focused', 'relatable'],
                'structure_guidance' => 'Ask a question, share insight, encourage discussion',
                'cta_recommendations' => ['What do you think?', 'Share your experience', 'Comment below']
            ],
            'linkedin' => [
                'tone_adjustments' => ['professional', 'insightful', 'thought-provoking'],
                'structure_guidance' => 'Share insight, provide context, invite professional discussion',
                'cta_recommendations' => ['What\'s your experience?', 'Thoughts?', 'How do you approach this?']
            ]
        ];

        return $refinements[$platform] ?? $refinements['instagram'];
    }

    /**
     * Generate structure guidance based on content length patterns
     */
    private function generateStructureGuidance(array $lengthPatterns): string
    {
        $avgLength = $lengthPatterns['optimal_range']['average'];
        
        if ($avgLength < 100) {
            return 'Keep content concise and punchy with clear call-to-action';
        } elseif ($avgLength < 300) {
            return 'Use moderate length with hook, main content, and engagement prompt';
        } else {
            return 'Develop longer-form content with introduction, detailed insights, and professional conclusion';
        }
    }

    /**
     * Get default content length for platform
     */
    private function getDefaultContentLength(string $platform): array
    {
        $lengths = [
            'instagram' => ['min' => 100, 'max' => 300, 'average' => 200],
            'facebook' => ['min' => 50, 'max' => 150, 'average' => 100],
            'linkedin' => ['min' => 200, 'max' => 600, 'average' => 400]
        ];

        return $lengths[$platform] ?? $lengths['instagram'];
    }

    /**
     * Get default hashtag usage for platform
     */
    private function getDefaultHashtagUsage(string $platform): array
    {
        $usage = [
            'instagram' => ['optimal_count' => 8, 'high_performing_tags' => ['#business', '#growth', '#inspiration']],
            'facebook' => ['optimal_count' => 3, 'high_performing_tags' => ['#community', '#business', '#local']],
            'linkedin' => ['optimal_count' => 5, 'high_performing_tags' => ['#professional', '#industry', '#leadership']]
        ];

        return $usage[$platform] ?? $usage['instagram'];
    }

    /**
     * Get default posting time for platform
     */
    private function getDefaultPostingTime(string $platform): array
    {
        return [
            'optimal_hours' => [9, 12, 15], // 9 AM, 12 PM, 3 PM
            'optimal_days' => [1, 2, 3], // Monday, Tuesday, Wednesday
            'hourly_performance' => [],
            'daily_performance' => []
        ];
    }

    /**
     * Get default content themes for platform
     */
    private function getDefaultContentThemes(string $platform): array
    {
        $themes = [
            'instagram' => ['top_themes' => ['inspiration', 'lifestyle', 'business', 'growth']],
            'facebook' => ['top_themes' => ['community', 'local', 'business', 'events']],
            'linkedin' => ['top_themes' => ['professional', 'industry', 'leadership', 'innovation']]
        ];

        return $themes[$platform] ?? $themes['instagram'];
    }

    /**
     * Get default engagement triggers for platform
     */
    private function getDefaultEngagementTriggers(string $platform): array
    {
        return [
            'effective_triggers' => ['questions' => 0.025, 'emojis' => 0.020, 'call_to_action' => 0.022],
            'tone_preferences' => ['professional' => 0.018, 'casual' => 0.022, 'inspirational' => 0.025]
        ];
    }
}