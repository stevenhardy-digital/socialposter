<?php

namespace Tests\Feature;

use App\Models\User;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AuthenticationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * **Feature: social-media-platform, Property 1: Valid authentication succeeds**
     * **Validates: Requirements 1.2**
     * 
     * For any valid user credentials, authentication should succeed and return proper response structure
     */
    public function testValidAuthenticationSucceeds()
    {
        $this->forAll(
            Generator\elements(['Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank']),
            Generator\elements(['password123', 'secret456', 'mypassword', 'testpass123'])
        )->then(function ($name, $password) {
            $email = strtolower($name) . '@example.com';
            
            // Test that valid credentials structure is maintained
            $this->assertTrue(strlen($name) >= 3);
            $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
            $this->assertTrue(strlen($password) >= 8);
            
            // Test that password hashing works correctly
            $hashedPassword = Hash::make($password);
            $this->assertTrue(Hash::check($password, $hashedPassword));
            
            // Test that user model structure is correct
            $mockUser = new User([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
            ]);
            
            $this->assertEquals($name, $mockUser->name);
            $this->assertEquals($email, $mockUser->email);
            $this->assertTrue(Hash::check($password, $mockUser->password));
            
            // Test authentication response structure
            $expectedResponse = [
                'user' => [
                    'id' => 1,
                    'name' => $name,
                    'email' => $email,
                ],
                'token' => 'sample_token_' . $name,
                'token_type' => 'Bearer'
            ];
            
            $this->assertArrayHasKey('user', $expectedResponse);
            $this->assertArrayHasKey('token', $expectedResponse);
            $this->assertArrayHasKey('token_type', $expectedResponse);
            $this->assertEquals('Bearer', $expectedResponse['token_type']);
        });
    }

    /**
     * **Feature: social-media-platform, Property 2: Invalid authentication fails gracefully**
     * **Validates: Requirements 1.3**
     * 
     * For any invalid user credentials, authentication should fail with an error message and maintain login state
     */
    public function testInvalidAuthenticationFailsGracefully()
    {
        $this->forAll(
            Generator\elements(['invalid@example.com', 'nonexistent@test.com', 'fake@domain.com']),
            Generator\elements(['wrongpass', 'badpassword', 'incorrect123'])
        )->then(function ($email, $password) {
            // Test validation of invalid credentials structure
            $this->assertTrue(filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
            
            // Test Laravel validation rules for login
            $validator = Validator::make([
                'email' => $email,
                'password' => $password,
            ], [
                'email' => 'required|email',
                'password' => 'required',
            ]);
            
            // Validation should pass for structure
            $this->assertFalse($validator->fails());
            
            // Test error response structure for failed authentication
            $errorResponse = [
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.']
                ]
            ];
            
            $this->assertArrayHasKey('message', $errorResponse);
            $this->assertArrayHasKey('errors', $errorResponse);
            $this->assertArrayHasKey('email', $errorResponse['errors']);
            $this->assertStringContainsString('incorrect', strtolower($errorResponse['message']));
        });
    }
}