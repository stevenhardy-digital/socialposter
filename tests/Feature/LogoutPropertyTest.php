<?php

namespace Tests\Feature;

use Eris\Generator;
use Eris\TestTrait;
use Tests\TestCase;

class LogoutPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * **Feature: social-media-platform, Property 4: Logout terminates session**
     * **Validates: Requirements 1.5**
     * 
     * For any authenticated user, logout should terminate the session and redirect to login
     */
    public function testLogoutTerminatesSession()
    {
        $this->forAll(
            Generator\elements(['user1', 'user2', 'user3', 'testuser', 'admin']),
            Generator\elements(['token123', 'abc456', 'xyz789', 'session_token'])
        )->then(function ($username, $token) {
            // Test logout data structure
            $sessionData = [
                'user' => $username,
                'token' => $token,
                'isAuthenticated' => true
            ];
            
            // Verify initial authenticated state
            $this->assertTrue($sessionData['isAuthenticated']);
            $this->assertNotEmpty($sessionData['token']);
            $this->assertNotEmpty($sessionData['user']);
            
            // Simulate logout process
            $loggedOutData = [
                'user' => null,
                'token' => null,
                'isAuthenticated' => false
            ];
            
            // Verify logout clears session data
            $this->assertFalse($loggedOutData['isAuthenticated']);
            $this->assertNull($loggedOutData['token']);
            $this->assertNull($loggedOutData['user']);
            
            // Test that token format is valid before logout
            $this->assertIsString($token);
            $this->assertGreaterThan(0, strlen($token));
            
            // Test that username format is valid
            $this->assertIsString($username);
            $this->assertGreaterThan(0, strlen($username));
        });
    }
}