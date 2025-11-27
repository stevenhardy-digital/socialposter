<?php

namespace App\Console\Commands;

use App\Services\LinkedInOAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestLinkedInIntegration extends Command
{
    protected $signature = 'test:linkedin {access_token?}';
    protected $description = 'Test LinkedIn API integration and permissions';

    public function handle()
    {
        $this->info('Testing LinkedIn API Integration...');
        
        // Test 1: Check LinkedIn API connectivity
        $this->info('1. Testing LinkedIn API connectivity...');
        try {
            $response = Http::timeout(10)->get('https://api.linkedin.com/v2');
            $this->info("✓ LinkedIn API is accessible (Status: {$response->status()})");
        } catch (\Exception $e) {
            $this->error("✗ LinkedIn API connectivity failed: {$e->getMessage()}");
            return 1;
        }

        // Test 2: Check OAuth service configuration
        $this->info('2. Testing OAuth service configuration...');
        try {
            $oauthService = new LinkedInOAuthService();
            $authUrl = $oauthService->getAuthorizationUrl();
            $this->info("✓ OAuth service configured correctly");
            $this->info("   Authorization URL: " . substr($authUrl, 0, 100) . "...");
        } catch (\Exception $e) {
            $this->error("✗ OAuth service configuration failed: {$e->getMessage()}");
            return 1;
        }

        // Test 3: Test with access token if provided
        $accessToken = $this->argument('access_token');
        if ($accessToken) {
            $this->info('3. Testing with provided access token...');
            $this->testAccessToken($accessToken);
        } else {
            $this->info('3. Skipping access token tests (no token provided)');
            $this->info('   To test with a token, run: php artisan test:linkedin YOUR_ACCESS_TOKEN');
        }

        $this->info('LinkedIn integration test completed.');
        return 0;
    }

    private function testAccessToken(string $accessToken): void
    {
        $endpoints = [
            'me' => 'https://api.linkedin.com/v2/me',
            'email' => 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))',
            'organizations' => 'https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&projection=(elements*(organization~(id,name)))',
        ];

        foreach ($endpoints as $name => $url) {
            try {
                $headers = ['Authorization' => "Bearer {$accessToken}"];
                if ($name !== 'userinfo') {
                    $headers['X-Restli-Protocol-Version'] = '2.0.0';
                }

                $response = Http::withHeaders($headers)->get($url);
                
                if ($response->successful()) {
                    $this->info("   ✓ {$name} endpoint: SUCCESS");
                    $data = $response->json();
                    if ($name === 'me' && (isset($data['localizedFirstName']) || isset($data['localizedLastName']))) {
                        $userName = trim(($data['localizedFirstName'] ?? '') . ' ' . ($data['localizedLastName'] ?? ''));
                        $this->info("     User: {$userName}");
                    }
                    if ($name === 'email' && isset($data['elements'][0]['handle~']['emailAddress'])) {
                        $this->info("     Email: {$data['elements'][0]['handle~']['emailAddress']}");
                    }
                } else {
                    $this->warn("   ✗ {$name} endpoint: FAILED (Status: {$response->status()})");
                    $errorBody = $response->body();
                    if (strlen($errorBody) > 200) {
                        $errorBody = substr($errorBody, 0, 200) . '...';
                    }
                    $this->warn("     Error: {$errorBody}");
                }
            } catch (\Exception $e) {
                $this->error("   ✗ {$name} endpoint: EXCEPTION - {$e->getMessage()}");
            }
        }
    }
}