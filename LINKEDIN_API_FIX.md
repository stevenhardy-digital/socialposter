# LinkedIn API Permission Issues - Fix Guide

## Current Issues
The LinkedIn integration is failing with `ACCESS_DENIED` errors because:

1. **Outdated API scopes** - Using deprecated `r_basicprofile` scope
2. **Missing LinkedIn Products** - App needs specific LinkedIn Products enabled
3. **Incorrect API endpoints** - Some endpoints require different permissions

## Errors Observed
```
ACCESS_DENIED: Unpermitted fields present in RESOURCE_KEY: Data Processing Exception while processing fields [/memberId]
ACCESS_DENIED: Not enough permissions to access: userinfo.GET.NO_VERSION
ACCESS_DENIED: Not enough permissions to access: organizationAcls.FINDER-roleAssignee.NO_VERSION
```

## Code Changes Made

### 1. Updated OAuth Scopes
**File:** `app/Services/LinkedInOAuthService.php`

**Old scopes:**
```php
$this->scopes = [
    'r_basicprofile',     // DEPRECATED
    'w_member_social',
];
```

**New scopes (all available permissions):**
```php
$this->scopes = [
    // Profile and Member Data
    'r_basicprofile',                    // Basic profile (name, photo, headline, public profile URL)
    'r_member_profileAnalytics',         // Profile analytics (viewers, followers, search appearances)
    'r_1st_connections_size',            // Number of 1st-degree connections
    
    // Member Social Actions
    'w_member_social',                   // Create, modify, delete posts, comments, reactions
    'w_member_social_feed',              // Create, modify, delete comments and reactions on posts
    'r_member_postAnalytics',            // Retrieve posts and their reporting data
    
    // Organization Management
    'rw_organization_admin',             // Manage organization pages and retrieve reporting data
    'r_organization_social',             // Retrieve organization posts, comments, reactions, engagement data
    'w_organization_social',             // Create, modify, delete posts, comments, reactions for organization
    'r_organization_social_feed',        // Retrieve comments, reactions, engagement data on organization posts
    'w_organization_social_feed',        // Create, modify, delete comments and reactions on organization posts
    'r_organization_followers',          // Use followers' data for organization mentions
];
```

### 2. Updated Profile Information Retrieval
- Primary method: LinkedIn `/me` endpoint (standard API)
- Separate email retrieval using `/emailAddress` endpoint
- Falls back to basic profile endpoint with minimal fields
- Handles permission errors gracefully

### 3. Simplified Company Pages Retrieval
- Removed complex projection fields that require additional permissions
- Uses basic organization information only

## LinkedIn Developer Portal Configuration Required

### Step 1: Enable Required Products
In your LinkedIn Developer Portal (https://developer.linkedin.com/), you need to enable:

1. **Share on LinkedIn** (default product)
   - Enables: `r_basicprofile`, `w_member_social`
   - Basic posting and profile access

2. **Marketing Developer Platform** (request access)
   - Enables: `r_member_profileAnalytics`, `r_member_postAnalytics`, `r_organization_social`, `w_organization_social`, `rw_organization_admin`, `r_organization_followers`, `r_organization_social_feed`, `w_organization_social_feed`
   - Advanced analytics and organization management

3. **Advertising API** (request access if needed)
   - Enables: `r_1st_connections_size`, `w_member_social_feed`
   - Connection data and advanced social features

### Step 2: Verify App Configuration
1. Go to your LinkedIn app settings
2. Navigate to the "Products" tab
3. Ensure "Share on LinkedIn" is enabled (should be by default)

### Step 3: Verify Scopes
In the "Auth" tab, verify which scopes are available based on your approved products:

**Default (Share on LinkedIn):**
- `r_basicprofile`
- `w_member_social`

**Marketing Developer Platform:**
- `r_member_profileAnalytics`
- `r_member_postAnalytics`
- `r_organization_social`
- `w_organization_social`
- `rw_organization_admin`
- `r_organization_followers`
- `r_organization_social_feed`
- `w_organization_social_feed`

**Advertising API:**
- `r_1st_connections_size`
- `w_member_social_feed`

## Testing the Fix

### 1. Test OAuth Flow
```bash
# Clear any cached tokens
php artisan cache:clear

# Test the OAuth flow in browser
# Go to: /auth/redirect/linkedin
```

### 2. Check Logs
Monitor the Laravel logs for:
- Successful userinfo endpoint calls
- Fallback to basic profile if needed
- Any remaining permission errors

### 3. Verify Profile Data
The integration will now return enhanced data based on available permissions:
```php
[
    'id' => 'user_id',
    'name' => 'Full Name',
    'headline' => 'Professional Title',
    'public_profile_url' => 'https://linkedin.com/in/username',
    'connections_count' => 500, // if r_1st_connections_size available
    'analytics' => [...], // if r_member_profileAnalytics available
    'source' => 'me_endpoint'
]
```

**Available data depends on approved LinkedIn Products:**
- Basic profile: Always available
- Analytics: Requires Marketing Developer Platform
- Connections count: Requires Advertising API access

## Alternative Solutions

### If Sign In with LinkedIn Product is Not Available
If you cannot get the Sign In with LinkedIn product approved:

1. **Use minimal profile data:**
   ```php
   // In LinkedInOAuthService.php, modify tryGetProfileInfo()
   return [
       'id' => 'linkedin_' . substr(hash('sha256', $accessToken), 0, 16),
       'name' => 'LinkedIn User',
       'source' => 'token_based'
   ];
   ```

2. **Focus on posting functionality only:**
   - Remove profile information requirements
   - Use posting capabilities only (`w_member_social` scope)

### For Company Pages Access
If you need company pages access, you'll need:
1. **Marketing Developer Platform** product
2. Additional scopes: `r_organization_social`, `rw_organization_admin`

## Environment Variables
Ensure these are set in your `.env`:
```
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret
```

## Next Steps
1. Apply for LinkedIn Products in Developer Portal
2. Test the OAuth flow after approval
3. Monitor logs for any remaining issues
4. Consider implementing retry logic for temporary API failures

## Support
If issues persist:
1. Check LinkedIn API status: https://www.linkedin-apistatus.com/
2. Review LinkedIn API documentation: https://docs.microsoft.com/en-us/linkedin/
3. Contact LinkedIn Developer Support if products are not being approved