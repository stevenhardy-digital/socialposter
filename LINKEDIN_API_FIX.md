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

**New scopes:**
```php
$this->scopes = [
    'r_liteprofile',     // Basic profile information
    'r_emailaddress',    // Email address
    'w_member_social',   // Post to LinkedIn
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

1. **Sign In with LinkedIn** (basic product)
   - This enables the `/me` and `/emailAddress` endpoints
   - Provides basic profile information (name, email)
   - Required for the `r_liteprofile` and `r_emailaddress` scopes

2. **Share on LinkedIn** (should already be enabled)
   - Enables posting capabilities
   - Required for `w_member_social` scope

### Step 2: Update App Permissions
1. Go to your LinkedIn app settings
2. Navigate to the "Products" tab
3. Request access to "Sign In with LinkedIn" (basic product)
4. Wait for approval (usually instant for basic products)

### Step 3: Verify Scopes
In the "Auth" tab, ensure these scopes are available:
- `r_liteprofile`
- `r_emailaddress`
- `w_member_social`

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
The integration should now return:
```php
[
    'id' => 'user_id',
    'name' => 'Full Name',
    'email' => 'user@example.com',
    'source' => 'me_endpoint' // or 'basic_profile' if fallback used
]
```

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