<?php

namespace App\Console\Commands;

use App\Services\MonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check {--alert : Send alerts for critical issues}';
    protected $description = 'Perform comprehensive system health check';

    public function __construct(
        private MonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🏥 Starting system health check...');
        
        $issues = [];
        $warnings = [];

        // Database connectivity
        try {
            DB::connection()->getPdo();
            $this->info('✅ Database: Connected');
        } catch (\Exception $e) {
            $issues[] = 'Database connection failed: ' . $e->getMessage();
            $this->error('❌ Database: Failed');
        }

        // Redis connectivity
        try {
            Redis::ping();
            $this->info('✅ Redis: Connected');
        } catch (\Exception $e) {
            $issues[] = 'Redis connection failed: ' . $e->getMessage();
            $this->error('❌ Redis: Failed');
        }

        // Queue system
        try {
            $queueSize = \Illuminate\Support\Facades\Queue::size();
            if ($queueSize > 100) {
                $warnings[] = "Large queue backlog: {$queueSize} jobs";
                $this->warn("⚠️ Queue: {$queueSize} jobs pending");
            } else {
                $this->info("✅ Queue: {$queueSize} jobs pending");
            }
        } catch (\Exception $e) {
            $issues[] = 'Queue system check failed: ' . $e->getMessage();
            $this->error('❌ Queue: Failed');
        }

        // Storage space
        $storageUsage = disk_free_space(storage_path()) / disk_total_space(storage_path()) * 100;
        if ($storageUsage < 10) {
            $issues[] = 'Low storage space: ' . round(100 - $storageUsage, 1) . '% used';
            $this->error('❌ Storage: Low space');
        } else {
            $this->info('✅ Storage: ' . round(100 - $storageUsage, 1) . '% used');
        }

        // External API connectivity
        $apis = [
            'OpenAI' => 'https://api.openai.com/v1/models',
            'Facebook' => 'https://graph.facebook.com/v18.0/me',
            'Instagram' => 'https://graph.instagram.com/v18.0/me',
        ];

        foreach ($apis as $name => $url) {
            try {
                $response = Http::timeout(5)->get($url);
                if ($response->successful() || $response->status() === 401) {
                    $this->info("✅ {$name} API: Reachable");
                } else {
                    $warnings[] = "{$name} API returned status: " . $response->status();
                    $this->warn("⚠️ {$name} API: Status " . $response->status());
                }
            } catch (\Exception $e) {
                $warnings[] = "{$name} API unreachable: " . $e->getMessage();
                $this->warn("⚠️ {$name} API: Unreachable");
            }
        }

        // Error rates
        $errorRate = $this->monitoringService->getMetricSum('errors.total', '1h');
        if ($errorRate > 50) {
            $issues[] = "High error rate: {$errorRate} errors in the last hour";
            $this->error("❌ Error Rate: {$errorRate}/hour");
        } elseif ($errorRate > 10) {
            $warnings[] = "Elevated error rate: {$errorRate} errors in the last hour";
            $this->warn("⚠️ Error Rate: {$errorRate}/hour");
        } else {
            $this->info("✅ Error Rate: {$errorRate}/hour");
        }

        // Summary
        $this->newLine();
        if (empty($issues) && empty($warnings)) {
            $this->info('🎉 All systems healthy!');
            return 0;
        }

        if (!empty($warnings)) {
            $this->warn('⚠️ Warnings found:');
            foreach ($warnings as $warning) {
                $this->warn("  • {$warning}");
            }
        }

        if (!empty($issues)) {
            $this->error('❌ Critical issues found:');
            foreach ($issues as $issue) {
                $this->error("  • {$issue}");
            }

            // Send alerts if requested
            if ($this->option('alert')) {
                $this->sendAlerts($issues);
            }

            return 1;
        }

        return 0;
    }

    private function sendAlerts(array $issues): void
    {
        $message = "🚨 Critical system issues detected:\n\n" . implode("\n", array_map(fn($issue) => "• {$issue}", $issues));
        
        // Log the alert
        $this->monitoringService->logEvent('System health alert', [
            'issues' => $issues,
            'severity' => 'critical'
        ], 'critical');

        // Send Slack notification if configured
        if (config('logging.channels.slack.url')) {
            try {
                Http::post(config('logging.channels.slack.url'), [
                    'text' => $message,
                    'username' => 'System Monitor',
                    'icon_emoji' => ':warning:'
                ]);
                $this->info('📱 Alert sent to Slack');
            } catch (\Exception $e) {
                $this->error('Failed to send Slack alert: ' . $e->getMessage());
            }
        }
    }
}