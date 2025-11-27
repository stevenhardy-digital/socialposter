<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_account_id',
        'content',
        'media_urls',
        'status',
        'scheduled_at',
        'published_at',
        'platform_post_id',
        'is_ai_generated',
        'last_error',
        'error_at',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'is_ai_generated' => 'boolean',
        'error_at' => 'datetime',
    ];

    /**
     * Ensure content is never null for frontend compatibility
     */
    public function getContentAttribute($value): string
    {
        return $value ?? '';
    }

    /**
     * Ensure status is never null for frontend compatibility
     */
    public function getStatusAttribute($value): string
    {
        return $value ?? 'draft';
    }

    /**
     * Ensure platform_post_id is never null for frontend compatibility
     */
    public function getPlatformPostIdAttribute($value): string
    {
        return $value ?? '';
    }

    /**
     * Ensure last_error is never null for frontend compatibility
     */
    public function getLastErrorAttribute($value): string
    {
        return $value ?? '';
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function engagementMetrics(): HasOne
    {
        return $this->hasOne(EngagementMetric::class);
    }
}