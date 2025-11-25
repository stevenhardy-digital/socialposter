<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandGuideline extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_account_id',
        'tone_of_voice',
        'brand_voice',
        'content_themes',
        'hashtag_strategy',
        'posting_frequency',
    ];

    protected $casts = [
        'content_themes' => 'array',
        'hashtag_strategy' => 'array',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}