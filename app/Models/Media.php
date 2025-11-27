<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'original_path',
        'thumbnail_path',
        'thumbnail_url',
        'platform_crops',
        'file_size',
        'mime_type',
        'width',
        'height',
    ];

    protected $casts = [
        'platform_crops' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected $appends = [
        'original_url',
        'formatted_file_size'
    ];

    /**
     * Get the user that owns the media
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the original image URL
     */
    public function getOriginalUrlAttribute(): string
    {
        return $this->original_path ? Storage::disk('public')->url($this->original_path) : '';
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get platform-specific crop URL
     */
    public function getPlatformUrl(string $platform): ?string
    {
        $crops = $this->platform_crops ?? [];
        
        if (isset($crops[$platform]['url'])) {
            return $crops[$platform]['url'];
        }

        // Fallback to original if no platform-specific crop exists
        return $this->original_url;
    }

    /**
     * Get all available platform URLs
     */
    public function getPlatformUrls(): array
    {
        $crops = $this->platform_crops ?? [];
        $urls = [];

        foreach ($crops as $platform => $cropData) {
            $urls[$platform] = $cropData['url'] ?? null;
        }

        return $urls;
    }

    /**
     * Check if media has crop for specific platform
     */
    public function hasPlatformCrop(string $platform): bool
    {
        $crops = $this->platform_crops ?? [];
        return isset($crops[$platform]);
    }
}