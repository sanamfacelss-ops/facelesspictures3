<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Background Processor
 * Spawns async PHP processes for non-blocking video processing
 */
class BackgroundProcessor
{
    /**
     * Queue a video for background processing (non-blocking)
     */
    public static function queueVideoProcessing(int $videoId): bool
    {
        $scriptPath = BASE_PATH . '/cron/process-single.php';
        
        // Check if script exists
        if (!file_exists($scriptPath)) {
            log_message('error', "Background processor script not found: {$scriptPath}");
            return false;
        }

        // Build command based on OS
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows - use start /B for background
            $cmd = sprintf(
                'start /B php %s %d > NUL 2>&1',
                escapeshellarg($scriptPath),
                $videoId
            );
            pclose(popen($cmd, 'r'));
        } else {
            // Linux/Unix - use nohup and &
            $cmd = sprintf(
                'nohup php %s %d > /dev/null 2>&1 &',
                escapeshellarg($scriptPath),
                $videoId
            );
            exec($cmd);
        }

        log_message('info', "Queued background processing for video {$videoId}");
        return true;
    }

    /**
     * Check if a video is currently being processed
     */
    public static function isProcessing(int $videoId): bool
    {
        $lockFile = sys_get_temp_dir() . '/fp3_processing_' . $videoId . '.lock';
        return file_exists($lockFile);
    }

    /**
     * Acquire processing lock
     */
    public static function acquireLock(int $videoId): bool
    {
        $lockFile = sys_get_temp_dir() . '/fp3_processing_' . $videoId . '.lock';
        
        // Check for stale lock (older than 30 minutes)
        if (file_exists($lockFile)) {
            $age = time() - filemtime($lockFile);
            if ($age > 1800) {
                @unlink($lockFile);
            } else {
                return false; // Already processing
            }
        }

        return file_put_contents($lockFile, date('Y-m-d H:i:s') . "\nPID: " . getmypid()) !== false;
    }

    /**
     * Release processing lock
     */
    public static function releaseLock(int $videoId): void
    {
        $lockFile = sys_get_temp_dir() . '/fp3_processing_' . $videoId . '.lock';
        @unlink($lockFile);
    }
}
