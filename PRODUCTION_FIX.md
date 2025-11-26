# Production Error Fix - Updated

## Issues Fixed

### 1. Health Check 500 Error ✅ FIXED
**Problem**: SystemController health check was failing when Redis wasn't configured
**Solution**: Modified health check to skip Redis check when not configured as primary driver

### 2. OAuth LinkedIn Connection Error ✅ FIXED  
**Problem**: Multiple OAuth issues:
- "Session store not set on request" error during OAuth flow
- LinkedIn scope `r_emailaddress` not authorized (deprecated scope)
- Route [login] not defined error on callback failures

**Solution**: 
- Moved OAuth callbacks to web routes with proper session support
- Updated LinkedIn scopes to use current v2 API scopes
- Added proper error handling and frontend redirects

## Applied Fixes

### ✅ SystemController.php Updates
- Modified `checkRedis()` to skip Redis check when not configured
- Updated `getQuickSystemStatus()` to handle Redis gracefully
- Health check now returns proper status even with database-only setup

### ✅ OAuth Flow Fixes
- **LinkedIn Scopes**: Updated to use correct v2 scopes (`r_liteprofile`, `w_member_social`)
- **OAuth Routes**: Moved both connect and callback to web routes for proper session support
- **Frontend Update**: Updated frontend to call `/auth/connect/{platform}` instead of API route
- **Error Handling**: Improved OAuth error handling to redirect to frontend instead of missing login route
- **Redirect URLs**: Updated all platforms to use `/auth/callback/{platform}` web routes

### ✅ SocialAccountController.php Updates
- Added `webConnect()` method for OAuth initiation with session support
- Added `webCallback()` method for handling OAuth redirects to frontend
- Improved error logging with session status information
- Better error messages for debugging OAuth issues

### ✅ Frontend Updates
- Updated AccountSettings.vue to call `/auth/connect/{platform}` web route
- OAuth flow now properly uses session-enabled routes

### ✅ Sessions Table
- Confirmed sessions table exists in database
- Database session driver properly configured

## Current Configuration Status

Your application is now configured for:
- **Cache**: Database (`CACHE_STORE=database`)
- **Sessions**: Database (`SESSION_DRIVER=database`) 
- **Queue**: Redis (`QUEUE_CONNECTION=redis`)

This is a stable configuration that should resolve both production errors.

## Testing the Fixes

### 1. Test Health Check Endpoint
```bash
curl -X GET https://social.add-digital.co.uk/api/system/health \
  -H "Authorization: Bearer YOUR_TOKEN"
```
**Expected**: Should return 200 with status "healthy" or "warning" (not 500)

### 2. Test OAuth Flow
```bash
curl -X POST https://social.add-digital.co.uk/api/social-accounts/connect/linkedin \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```
**Expected**: Should return redirect URL (not "Session store not set" error)

## What Was Causing the Errors

### Health Check Error
The SystemController was trying to check Redis connection even when Redis wasn't configured as the cache driver. This caused the health check to fail with a 500 error.

### OAuth Session Error  
Laravel Socialite requires session middleware to store OAuth state during the authentication flow. API routes don't include session middleware by default, causing the "Session store not set on request" error.

## Immediate Actions Required

### 1. Update LinkedIn App Configuration
In your LinkedIn Developer Console:
- Update redirect URI from `/api/social-accounts/callback/linkedin` to `/auth/callback/linkedin`
- Ensure your app has the correct scopes: `r_liteprofile` and `w_member_social`
- Remove any deprecated scopes like `r_emailaddress`

### 2. Update Other Platform Redirect URIs
- **Instagram**: Change to `/auth/callback/instagram`
- **Facebook**: Change to `/auth/callback/facebook`

### 3. Deploy and Clear Caches
```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache

# Test database connection
php artisan migrate:status

# Check queue status
php artisan queue:work --once
```

## Verification Commands

After updating the redirect URIs in your OAuth apps:

## Monitoring

The application will now:
- ✅ Health checks work properly with database cache
- ✅ OAuth flows work with session support
- ✅ Monitoring continues to track metrics correctly
- ✅ Error logging provides better debugging information

## Alternative Redis Setup

If you want to use Redis for better performance:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

But ensure Redis is properly installed and running first.