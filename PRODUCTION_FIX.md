# Production Error Fix

## Issue
The application is throwing a fatal error: `Call to undefined method Illuminate\Cache\RedisStore::expire()`

## Root Cause
The `MonitoringService` was using `Cache::expire()` which doesn't exist in Laravel's cache system.

## Fix Applied
✅ **Fixed MonitoringService.php** - Replaced `Cache::expire()` with proper `Cache::put()` with expiration

## Immediate Production Fix

### 1. Update Your .env File
Change your cache configuration from Redis to database (more reliable):

```env
# Change from:
CACHE_STORE=redis
SESSION_DRIVER=redis

# To:
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 2. Clear Application Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

### 3. Test the Registration Endpoint
```bash
curl -X POST https://social.add-digital.co.uk/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123","password_confirmation":"password123"}'
```

## Alternative: If You Want to Keep Redis

If you prefer to use Redis, ensure it's properly configured:

### 1. Check Redis Connection
```bash
redis-cli ping
# Should return: PONG
```

### 2. Update .env for Redis
```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. Test Redis Configuration
```bash
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
# Should return: "value"
```

## Why This Happened

The `Cache::expire()` method doesn't exist in Laravel. The correct way to set cache expiration is:

```php
// Wrong (what was causing the error):
Cache::increment($key, $value);
Cache::expire($key, 86400 * 7);

// Correct (what's now implemented):
$currentValue = Cache::get($key, 0);
Cache::put($key, $currentValue + $value, now()->addDays(7));
```

## Verification

After applying the fix, the registration endpoint should work without errors. The monitoring system will continue to track metrics but using the correct Laravel cache methods.

## Database vs Redis

**Database Cache (Recommended for shared hosting):**
- ✅ More reliable on shared hosting
- ✅ No additional server requirements
- ✅ Easier to debug
- ❌ Slightly slower than Redis

**Redis Cache (Better for dedicated servers):**
- ✅ Faster performance
- ✅ Better for high-traffic applications
- ❌ Requires Redis server
- ❌ More complex setup on shared hosting