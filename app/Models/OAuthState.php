<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthState extends Model
{
    protected $fillable = [
        'state_key',
        'user_id',
        'platform',
        'auth_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clean up expired OAuth states
     */
    public static function cleanupExpired(): void
    {
        static::where('expires_at', '<', now())->delete();
    }

    /**
     * Create a new OAuth state
     */
    public static function createState(int $userId, string $platform, ?string $authToken = null): string
    {
        // Clean up old states first
        static::cleanupExpired();
        
        $stateKey = \Illuminate\Support\Str::random(40);
        
        static::create([
            'state_key' => $stateKey,
            'user_id' => $userId,
            'platform' => $platform,
            'auth_token' => $authToken,
            'expires_at' => now()->addMinutes(30), // 30 minutes should be enough for OAuth
        ]);
        
        return $stateKey;
    }

    /**
     * Retrieve and delete OAuth state
     */
    public static function consumeState(string $stateKey): ?array
    {
        $state = static::where('state_key', $stateKey)
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$state) {
            return null;
        }
        
        $data = [
            'user_id' => $state->user_id,
            'platform' => $state->platform,
            'auth_token' => $state->auth_token,
        ];
        
        // Delete the state after use
        $state->delete();
        
        return $data;
    }
}