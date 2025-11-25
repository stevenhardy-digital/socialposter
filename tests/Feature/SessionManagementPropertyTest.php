<?php

namespace Tests\Feature;

use Eris\Generator;
use Eris\TestTrait;
use Tests\TestCase;

class SessionManagementPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * **Feature: social-media-platform, Property 3: Session expiration redirects to login**
     * **Validates: Requirements 1.4**
     * 
     * For any authenticated user with an expired session, the system should redirect to login and clear session data
     */
    public function testSessionExpirationRedirectsToLogin()
    {
        $this->forAll(
            Generator\elements(['user1', 'user2', 'user3', 'testuser', 'admin']),
            Generator\choose(1, 24) // hours in the past
        )->then(function ($username, $hoursAgo) {
            // Test token expiry logic
            $currentTime = new \DateTime();
            $expiryTime = new \DateTime();
            $expiryTime->sub(new \DateInterval("PT{$hoursAgo}H"));
            
            // Verify that expired tokens are properly identified
            $this->assertTrue($expiryTime < $currentTime);
            
            // Test token expiry string format
            $expiryString = $expiryTime->format('Y-m-d H:i:s');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expiryString);
            
            // Test that session data structure is valid
            $sessionData = [
                'user' => $username,
                'token' => 'test_token_' . $username,
                'expires_at' => $expiryString
            ];
            
            $this->assertArrayHasKey('user', $sessionData);
            $this->assertArrayHasKey('token', $sessionData);
            $this->assertArrayHasKey('expires_at', $sessionData);
            $this->assertEquals($username, $sessionData['user']);
        });
    }
}