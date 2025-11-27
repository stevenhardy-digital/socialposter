<?php

namespace App\Services;

use App\Models\BrandGuideline;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;
use OpenAI;

class ContentGenerationService
{
    /**
     * Generate monthly content for all connected social accounts
     */
    public function generateMonthlyContent(int $userId): array
    {
        $results = [];
        
        $socialAccounts = SocialAccount::where('user_id', $userId)->get();
        
        foreach ($socialAccounts as $account) {
            try {
                $posts = $this->generateContentForAccount($account);
                $results[$account->id] = [
                    'success' => true,
                    'posts_generated' => count($posts),
                    'posts' => $posts
                ];
            } catch (\Exception $e) {
                Log::error('Content generation failed for account ' . $account->id, [
                    'error' => $e->getMessage(),
                    'account_id' => $account->id,
                    'platform' => $account->platform
                ]);
                
                $results[$account->id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Generate content for a specific social account
     */
    public function generateContentForAccount(SocialAccount $account, int $postCount = 10): array
    {
        $brandGuidelines = $account->brandGuidelines;
        $posts = [];
        
        for ($i = 0; $i < $postCount; $i++) {
            $content = $this->generateSinglePost($account, $brandGuidelines);
            
            $post = Post::create([
                'social_account_id' => $account->id,
                'content' => $content,
                'status' => 'draft',
                'is_ai_generated' => true,
                'scheduled_at' => now()->addDays($i + 1)
            ]);
            
            $posts[] = $post;
        }
        
        return $posts;
    }

    /**
     * Generate a single post using AI
     */
    public function generateSinglePost(SocialAccount $account, ?BrandGuideline $guidelines = null): string
    {
        $prompt = $this->buildPrompt($account, $guidelines);
        
        try {
            $client = OpenAI::client(config('services.openai.api_key'));
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a social media content creator. Generate engaging posts that follow the provided brand guidelines.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 300,
                'temperature' => 0.7
            ]);
            
            return trim($response->choices[0]->message->content);
        } catch (\Exception $e) {
            Log::error('OpenAI API call failed', [
                'error' => $e->getMessage(),
                'account_id' => $account->id
            ]);
            
            // Fallback to default template
            return $this->getDefaultTemplate($account->platform);
        }
    }

    /**
     * Build AI prompt based on brand guidelines and platform
     */
    private function buildPrompt(SocialAccount $account, ?BrandGuideline $guidelines): string
    {
        $platform = ucfirst($account->platform);
        $prompt = "Create a {$platform} post for {$account->account_name}. ";
        
        if ($guidelines) {
            if ($guidelines->tone_of_voice) {
                $prompt .= "Use a {$guidelines->tone_of_voice} tone of voice. ";
            }
            
            if ($guidelines->brand_voice) {
                $prompt .= "Brand voice: {$guidelines->brand_voice}. ";
            }
            
            if ($guidelines->content_themes && is_array($guidelines->content_themes)) {
                $themes = implode(', ', $guidelines->content_themes);
                $prompt .= "Focus on these themes: {$themes}. ";
            }
            
            if ($guidelines->hashtag_strategy && is_array($guidelines->hashtag_strategy)) {
                $hashtags = implode(' ', $guidelines->hashtag_strategy);
                $prompt .= "Include relevant hashtags from: {$hashtags}. ";
            }
        } else {
            $prompt .= "Use a professional and engaging tone. ";
        }
        
        $prompt .= $this->getPlatformSpecificGuidelines($account->platform);
        
        return $prompt;
    }

    /**
     * Get platform-specific content guidelines
     */
    private function getPlatformSpecificGuidelines(string $platform): string
    {
        return match ($platform) {
            'instagram' => 'Keep it visual-focused and use relevant hashtags. Limit to 2200 characters.',
            'facebook' => 'Make it conversational and engaging. Optimal length is 40-80 characters for higher engagement.',
            'linkedin' => 'Keep it professional and industry-focused. Longer posts (1900+ characters) perform well.',
            default => 'Create engaging content appropriate for the platform.'
        };
    }

    /**
     * Get default content template when guidelines are missing
     */
    private function getDefaultTemplate(string $platform): string
    {
        $templates = [
            'instagram' => "🌟 Exciting updates coming your way! Stay tuned for more amazing content. #business #growth #inspiration",
            'facebook' => "We're thrilled to share some exciting news with our community! What would you like to see more of?",
            'linkedin' => "Reflecting on the importance of innovation in today's business landscape. What strategies have worked best for your organization?"
        ];
        
        return $templates[$platform] ?? "Great things are happening! Stay connected for updates.";
    }

    /**
     * Check if content generation is needed for an account
     */
    public function isGenerationNeeded(SocialAccount $account): bool
    {
        $lastGeneration = Post::where('social_account_id', $account->id)
            ->where('is_ai_generated', true)
            ->where('created_at', '>=', now()->startOfMonth())
            ->exists();
            
        return !$lastGeneration;
    }

    /**
     * Test connection to AI service
     */
    public function testConnection(): bool
    {
        try {
            $client = OpenAI::client(config('services.openai.api_key'));
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection']
                ],
                'max_tokens' => 10
            ]);
            
            return isset($response->choices[0]->message->content);
        } catch (\Exception $e) {
            Log::error('AI service connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if content can be generated for an account
     */
    public function canGenerateContent(SocialAccount $account): bool
    {
        // Check if account has valid access token
        if (!$account->access_token) {
            return false;
        }

        // Check if AI service is available
        if (!$this->testConnection()) {
            return false;
        }

        return true;
    }
}