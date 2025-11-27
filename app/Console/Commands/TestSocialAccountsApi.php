<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSocialAccountsApi extends Command
{
    protected $signature = 'test:social-accounts-api {user_id=1}';
    protected $description = 'Test the social accounts API endpoint';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $this->info("Testing social accounts API for user {$userId}...");
        
        // Get user
        $user = User::find($userId);
        if (!$user) {
            $this->error("User {$userId} not found");
            return 1;
        }
        
        $this->info("User found: {$user->name} ({$user->email})");
        
        // Get social accounts directly from database
        $accounts = $user->socialAccounts()->get();
        $this->info("Social accounts in database: {$accounts->count()}");
        
        foreach ($accounts as $account) {
            $this->line("  ID: {$account->id}");
            $this->line("  Platform: " . json_encode($account->platform));
            $this->line("  Account Name: " . json_encode($account->account_name));
            $this->line("  Platform User ID: " . json_encode($account->platform_user_id));
            $this->line("  ---");
        }
        
        // Test the API endpoint structure
        $this->info("Testing API response structure...");
        
        // Simulate the controller response
        $controller = new \App\Http\Controllers\SocialAccountController();
        
        // We can't easily test the authenticated endpoint here, but we can test the resource
        if ($accounts->isNotEmpty()) {
            $resource = \App\Http\Resources\SocialAccountResource::collection($accounts);
            $resourceData = $resource->toArray(request());
            
            $this->info("Resource collection structure:");
            $this->line(json_encode($resourceData, JSON_PRETTY_PRINT));
        }
        
        return 0;
    }
}