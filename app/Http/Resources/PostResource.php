<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'social_account_id' => $this->social_account_id,
            'content' => $this->content ?? '',
            'media_urls' => $this->media_urls ?? [],
            'status' => $this->status ?? 'draft',
            'scheduled_at' => $this->scheduled_at,
            'published_at' => $this->published_at,
            'platform_post_id' => $this->platform_post_id ?? '',
            'is_ai_generated' => $this->is_ai_generated ?? false,
            'last_error' => $this->last_error ?? '',
            'error_at' => $this->error_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'social_account' => $this->whenLoaded('socialAccount', function () {
                return [
                    'id' => $this->socialAccount->id,
                    'platform' => $this->socialAccount->platform ?? '',
                    'platform_user_id' => $this->socialAccount->platform_user_id ?? '',
                    'account_name' => $this->socialAccount->account_name ?? '',
                    'expires_at' => $this->socialAccount->expires_at,
                    'created_at' => $this->socialAccount->created_at,
                    'updated_at' => $this->socialAccount->updated_at,
                ];
            }),
            
            'engagement_metrics' => $this->whenLoaded('engagementMetrics', function () {
                return [
                    'id' => $this->engagementMetrics->id ?? null,
                    'likes_count' => $this->engagementMetrics->likes_count ?? 0,
                    'comments_count' => $this->engagementMetrics->comments_count ?? 0,
                    'shares_count' => $this->engagementMetrics->shares_count ?? 0,
                    'reach' => $this->engagementMetrics->reach ?? 0,
                    'impressions' => $this->engagementMetrics->impressions ?? 0,
                    'collected_at' => $this->engagementMetrics->collected_at ?? null,
                ];
            }, [
                // Default engagement metrics if not loaded
                'id' => null,
                'likes_count' => 0,
                'comments_count' => 0,
                'shares_count' => 0,
                'reach' => 0,
                'impressions' => 0,
                'collected_at' => null,
            ]),
        ];
    }
}