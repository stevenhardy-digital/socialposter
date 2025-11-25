<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--upload-s3 : Upload backup to S3}';
    protected $description = 'Create a database backup';

    public function handle(): int
    {
        $this->info('📦 Starting database backup...');

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup_social_media_platform_{$timestamp}.sql";
        $backupPath = storage_path("app/backups/{$filename}");

        // Ensure backup directory exists
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }

        // Database configuration
        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        // Create mysqldump command
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($backupPath)
        );

        // Execute backup
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('❌ Database backup failed');
            return 1;
        }

        $fileSize = filesize($backupPath);
        $this->info("✅ Database backup created: {$filename} (" . $this->formatBytes($fileSize) . ")");

        // Compress the backup
        $compressedPath = $backupPath . '.gz';
        if (function_exists('gzencode')) {
            $data = file_get_contents($backupPath);
            file_put_contents($compressedPath, gzencode($data, 9));
            unlink($backupPath);
            $backupPath = $compressedPath;
            $filename .= '.gz';
            
            $compressedSize = filesize($backupPath);
            $this->info("🗜️ Backup compressed: " . $this->formatBytes($compressedSize));
        }

        // Upload to S3 if requested
        if ($this->option('upload-s3') && config('filesystems.disks.s3')) {
            try {
                $s3Path = "backups/database/{$filename}";
                Storage::disk('s3')->put($s3Path, file_get_contents($backupPath));
                $this->info("☁️ Backup uploaded to S3: {$s3Path}");
                
                // Remove local file after successful upload
                unlink($backupPath);
                $this->info("🗑️ Local backup file removed");
            } catch (\Exception $e) {
                $this->error("❌ S3 upload failed: " . $e->getMessage());
            }
        }

        // Clean up old backups (keep last 7 days locally)
        $this->cleanupOldBackups();

        $this->info('🎉 Backup process completed');
        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function cleanupOldBackups(): void
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            return;
        }

        $files = glob($backupDir . '/backup_social_media_platform_*.sql*');
        $cutoff = Carbon::now()->subDays(7)->timestamp;
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("🗑️ Cleaned up {$deletedCount} old backup files");
        }
    }
}