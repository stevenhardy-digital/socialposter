<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use Illuminate\Console\Command;

class CleanupSocialAccounts extends Command
{
    protected $signature = 'cleanup:social-accounts';
    protected $description = 'Clean up social accounts with null or empty platform values';

    public function handle()
    {
        $this->info('Checking for social accounts with null/empty platform values...');
        
        // Find accounts with null or empty platform
        $problematicAccounts = SocialAccount::whereNull('platform')
            ->orWhere('platform', '')
            ->get();
            
        if ($problematicAccounts->isEmpty()) {
            $this->info('✅ No problematic social accounts found.');
            return 0;
        }
        
        $this->warn("Found {$problematicAccounts->count()} social accounts with null/empty platform values:");
        
        foreach ($problematicAccounts as $account) {
            $this->line("  ID: {$account->id}, User: {$account->user_id}, Platform: " . json_encode($account->platform) . ", Account Name: " . ($account->account_name ?: 'null'));
        }
        
        if ($this->confirm('Do you want to delete these problematic accounts?')) {
            $deleted = $problematicAccounts->count();
            SocialAccount::whereNull('platform')->orWhere('platform', '')->delete();
            $this->info("✅ Deleted {$deleted} problematic social accounts.");
        } else {
            $this->info('No accounts were deleted.');
        }
        
        // Also check for accounts with null account_name
        $nullNameAccounts = SocialAccount::whereNull('account_name')
            ->orWhere('account_name', '')
            ->get();
            
        if (!$nullNameAccounts->isEmpty()) {
            $this->warn("Found {$nullNameAccounts->count()} social accounts with null/empty account names:");
            
            foreach ($nullNameAccounts as $account) {
                $this->line("  ID: {$account->id}, Platform: {$account->platform}, Account Name: " . json_encode($account->account_name));
            }
            
            if ($this->confirm('Do you want to set default names for these accounts?')) {
                foreach ($nullNameAccounts as $account) {
                    $defaultName = ucfirst($account->platform ?: 'Unknown') . ' Account';
                    $account->update(['account_name' => $defaultName]);
                    $this->info("  Updated account {$account->id} name to: {$defaultName}");
                }
            }
        }
        
        return 0;
    }
}