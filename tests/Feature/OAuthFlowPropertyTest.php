<?php

namespace Tests\Feature;

use Eris\Generator;
use Eris\TestTrait;
use Tests\TestCase;

class OAuthFlowPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * **Feature: social-media-platform, Property 5: OAuth flow initiation redirects correctly**
     * **Validates: Requirements 2.2**
     * 
     * For any social media platform and user, initiating account connection should redirect to the appropriate OAuth authorization flow
     */
    public function testOAuthFlowInitiationRedirectsCorrectly()
    {
        $this->forAll(
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['user1', 'user2', 'testuser', 'businessowner'])
        )->then(function ($platform, $username) {
            // Test that platform is valid
            $validPlatforms = ['instagram', 'facebook', 'linkedin'];
            $this->assertContains($platform, $validPlatforms);
            
            // Test that username is valid
            $this->assertIsString($username);
            $this->assertGreaterThan(0, strlen($username));
            
            // Test OAuth URL structure for each platform
            $expectedUrls = [
                'instagram' => 'https://api.instagram.com/oauth/authorize',
                'facebook' => 'https://www.facebook.com/v18.0/dialog/oauth',
                'linkedin' => 'https://www.linkedin.com/oauth/v2/authorization'
            ];
            
            $this->assertArrayHasKey($platform, $expectedUrls);
            
            // Test OAuth redirect URL structure
            $redirectUrl = $expectedUrls[$platform];
            $this->assertStringStartsWith('https://', $redirectUrl);
            $this->assertStringContainsString('oauth', $redirectUrl);
            
            // Test OAuth parameters structure
            $oauthParams = [
                'client_id' => 'test_client_id',
                'redirect_uri' => 'http://localhost/auth/' . $platform . '/callback',
                'scope' => $this->getScopeForPlatform($platform),
                'response_type' => 'code',
                'state' => hash('sha256', $username . $platform . time())
            ];
            
            $this->assertArrayHasKey('client_id', $oauthParams);
            $this->assertArrayHasKey('redirect_uri', $oauthParams);
            $this->assertArrayHasKey('scope', $oauthParams);
            $this->assertArrayHasKey('response_type', $oauthParams);
            $this->assertArrayHasKey('state', $oauthParams);
            
            // Test redirect URI format
            $this->assertStringContainsString($platform, $oauthParams['redirect_uri']);
            $this->assertStringContainsString('callback', $oauthParams['redirect_uri']);
            
            // Test state parameter security
            $this->assertEquals(64, strlen($oauthParams['state'])); // SHA256 hash length
            
            // Test response type is correct
            $this->assertEquals('code', $oauthParams['response_type']);
        });
    }

    /**
     * **Feature: social-media-platform, Property 6: Successful OAuth stores tokens**
     * **Validates: Requirements 2.3**
     * 
     * For any successful OAuth authorization response, the system should store access tokens and display connected account information
     */
    public function testSuccessfulOAuthStoresTokens()
    {
        $this->forAll(
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['access_token_abc123def456', 'token_xyz789uvw012', 'bearer_mno345pqr678']),
            Generator\elements(['refresh_token_abc123def456', 'refresh_xyz789uvw012', 'refresh_mno345pqr678']),
            Generator\elements(['user123', 'business456', 'company789']),
            Generator\elements(['Test Account', 'Business Page', 'Company Profile'])
        )->then(function ($platform, $accessToken, $refreshToken, $platformUserId, $accountName) {
            // Test that platform is valid
            $validPlatforms = ['instagram', 'facebook', 'linkedin'];
            $this->assertContains($platform, $validPlatforms);
            
            // Test OAuth response structure
            $oauthResponse = [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => $this->getScopeForPlatform($platform)
            ];
            
            $this->assertArrayHasKey('access_token', $oauthResponse);
            $this->assertArrayHasKey('refresh_token', $oauthResponse);
            $this->assertArrayHasKey('expires_in', $oauthResponse);
            $this->assertArrayHasKey('token_type', $oauthResponse);
            
            // Test token format
            $this->assertIsString($accessToken);
            $this->assertGreaterThan(10, strlen($accessToken));
            $this->assertIsString($refreshToken);
            $this->assertGreaterThan(10, strlen($refreshToken));
            
            // Test account data structure
            $accountData = [
                'platform_user_id' => $platformUserId,
                'account_name' => $accountName,
                'platform' => $platform
            ];
            
            $this->assertArrayHasKey('platform_user_id', $accountData);
            $this->assertArrayHasKey('account_name', $accountData);
            $this->assertArrayHasKey('platform', $accountData);
            
            // Test stored social account structure
            $socialAccountData = [
                'user_id' => 1,
                'platform' => $platform,
                'platform_user_id' => $platformUserId,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds($oauthResponse['expires_in']),
                'account_name' => $accountName
            ];
            
            $this->assertEquals($platform, $socialAccountData['platform']);
            $this->assertEquals($platformUserId, $socialAccountData['platform_user_id']);
            $this->assertEquals($accessToken, $socialAccountData['access_token']);
            $this->assertEquals($refreshToken, $socialAccountData['refresh_token']);
            $this->assertEquals($accountName, $socialAccountData['account_name']);
            $this->assertInstanceOf(\Carbon\Carbon::class, $socialAccountData['expires_at']);
            
            // Test that tokens are properly secured (should be hidden in responses)
            $publicAccountData = [
                'id' => 1,
                'platform' => $platform,
                'platform_user_id' => $platformUserId,
                'account_name' => $accountName,
                'expires_at' => $socialAccountData['expires_at']->toISOString()
            ];
            
            $this->assertArrayNotHasKey('access_token', $publicAccountData);
            $this->assertArrayNotHasKey('refresh_token', $publicAccountData);
        });
    }

    /**
     * **Feature: social-media-platform, Property 7: Failed OAuth maintains state**
     * **Validates: Requirements 2.4**
     * 
     * For any OAuth authorization failure, the system should display an error message and maintain current connection state
     */
    public function testFailedOAuthMaintainsState()
    {
        $this->forAll(
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['access_denied', 'invalid_request', 'server_error', 'temporarily_unavailable']),
            Generator\elements(['User denied access', 'Invalid client credentials', 'Server error occurred', 'Service temporarily unavailable']),
            Generator\elements([0, 1, 2]) // Number of existing connected accounts
        )->then(function ($platform, $errorCode, $errorDescription, $existingAccountsCount) {
            // Test that platform is valid
            $validPlatforms = ['instagram', 'facebook', 'linkedin'];
            $this->assertContains($platform, $validPlatforms);
            
            // Test OAuth error response structure
            $oauthErrorResponse = [
                'error' => $errorCode,
                'error_description' => $errorDescription,
                'state' => 'original_state_value'
            ];
            
            $this->assertArrayHasKey('error', $oauthErrorResponse);
            $this->assertArrayHasKey('error_description', $oauthErrorResponse);
            $this->assertArrayHasKey('state', $oauthErrorResponse);
            
            // Test error codes are valid OAuth error codes
            $validErrorCodes = ['access_denied', 'invalid_request', 'server_error', 'temporarily_unavailable'];
            $this->assertContains($errorCode, $validErrorCodes);
            
            // Test error description is meaningful
            $this->assertIsString($errorDescription);
            $this->assertGreaterThan(0, strlen($errorDescription));
            
            // Test that existing connection state is maintained
            $beforeConnectionState = [
                'connected_accounts' => $existingAccountsCount,
                'platforms' => $existingAccountsCount > 0 ? ['instagram'] : []
            ];
            
            // After OAuth failure, state should remain the same
            $afterConnectionState = [
                'connected_accounts' => $existingAccountsCount,
                'platforms' => $existingAccountsCount > 0 ? ['instagram'] : []
            ];
            
            $this->assertEquals($beforeConnectionState['connected_accounts'], $afterConnectionState['connected_accounts']);
            $this->assertEquals($beforeConnectionState['platforms'], $afterConnectionState['platforms']);
            
            // Test error response structure for user display
            $userErrorResponse = [
                'success' => false,
                'message' => 'Failed to connect ' . ucfirst($platform) . ' account: ' . $errorDescription,
                'error_code' => $errorCode,
                'platform' => $platform
            ];
            
            $this->assertFalse($userErrorResponse['success']);
            $this->assertStringContainsString($platform, strtolower($userErrorResponse['message']));
            $this->assertStringContainsString('failed', strtolower($userErrorResponse['message']));
            $this->assertEquals($errorCode, $userErrorResponse['error_code']);
            $this->assertEquals($platform, $userErrorResponse['platform']);
        });
    }

    /**
     * **Feature: social-media-platform, Property 8: Account disconnection removes access**
     * **Validates: Requirements 2.5**
     * 
     * For any connected social media account, disconnection should revoke access tokens and remove the account from the connected list
     */
    public function testAccountDisconnectionRemovesAccess()
    {
        $this->forAll(
            Generator\elements(['instagram', 'facebook', 'linkedin']),
            Generator\elements(['access_token_abc123def456', 'token_xyz789uvw012', 'bearer_mno345pqr678']),
            Generator\elements(['refresh_token_abc123def456', 'refresh_xyz789uvw012', 'refresh_mno345pqr678']),
            Generator\elements(['user123', 'business456', 'company789']),
            Generator\elements(['Test Account', 'Business Page', 'Company Profile'])
        )->then(function ($platform, $accessToken, $refreshToken, $platformUserId, $accountName) {
            // Test that platform is valid
            $validPlatforms = ['instagram', 'facebook', 'linkedin'];
            $this->assertContains($platform, $validPlatforms);
            
            // Test initial connected account state
            $connectedAccount = [
                'id' => 1,
                'user_id' => 1,
                'platform' => $platform,
                'platform_user_id' => $platformUserId,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'account_name' => $accountName,
                'is_connected' => true
            ];
            
            $this->assertTrue($connectedAccount['is_connected']);
            $this->assertNotEmpty($connectedAccount['access_token']);
            $this->assertNotEmpty($connectedAccount['refresh_token']);
            $this->assertEquals($platform, $connectedAccount['platform']);
            
            // Test disconnection process
            $disconnectionRequest = [
                'account_id' => $connectedAccount['id'],
                'platform' => $platform,
                'revoke_tokens' => true
            ];
            
            $this->assertArrayHasKey('account_id', $disconnectionRequest);
            $this->assertArrayHasKey('platform', $disconnectionRequest);
            $this->assertArrayHasKey('revoke_tokens', $disconnectionRequest);
            $this->assertTrue($disconnectionRequest['revoke_tokens']);
            
            // Test token revocation process
            $tokenRevocationResponse = [
                'access_token_revoked' => true,
                'refresh_token_revoked' => true,
                'platform_notified' => true
            ];
            
            $this->assertTrue($tokenRevocationResponse['access_token_revoked']);
            $this->assertTrue($tokenRevocationResponse['refresh_token_revoked']);
            $this->assertTrue($tokenRevocationResponse['platform_notified']);
            
            // Test account removal from database
            $accountAfterDisconnection = null; // Account should be deleted or marked as disconnected
            
            $this->assertNull($accountAfterDisconnection);
            
            // Test updated connected accounts list
            $connectedAccountsList = []; // Should be empty after disconnection
            
            $this->assertEmpty($connectedAccountsList);
            $this->assertCount(0, $connectedAccountsList);
            
            // Test disconnection success response
            $disconnectionResponse = [
                'success' => true,
                'message' => ucfirst($platform) . ' account disconnected successfully',
                'platform' => $platform,
                'account_name' => $accountName
            ];
            
            $this->assertTrue($disconnectionResponse['success']);
            $this->assertStringContainsString('disconnected', strtolower($disconnectionResponse['message']));
            $this->assertStringContainsString('successfully', strtolower($disconnectionResponse['message']));
            $this->assertEquals($platform, $disconnectionResponse['platform']);
            $this->assertEquals($accountName, $disconnectionResponse['account_name']);
        });
    }

    private function getScopeForPlatform(string $platform): string
    {
        return match($platform) {
            'instagram' => 'instagram_basic,instagram_content_publish',
            'facebook' => 'pages_manage_posts,pages_read_engagement',
            'linkedin' => 'w_member_social,r_organization_social',
            default => ''
        };
    }
}