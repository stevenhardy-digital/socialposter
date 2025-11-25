<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// System health monitoring
Artisan::command('system:monitor', function () {
    $this->call('system:health-check', ['--alert' => true]);
})->purpose('Monitor system health and send alerts')->everyFiveMinutes();

// Queue monitoring
Artisan::command('queue:monitor', function () {
    $queueSize = \Illuminate\Support\Facades\Queue::size();
    if ($queueSize > 100) {
        \Log::warning('Large queue backlog detected', ['queue_size' => $queueSize]);
    }
})->purpose('Monitor queue size')->everyMinute();

// Clean up old logs and metrics
Artisan::command('system:cleanup', function () {
    // Clean up old log files
    $logPath = storage_path('logs');
    $files = glob($logPath . '/laravel-*.log');
    $cutoff = now()->subDays(30);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff->timestamp) {
            unlink($file);
        }
    }
    
    $this->info('Cleaned up old log files');
})->purpose('Clean up old logs and metrics')->daily();