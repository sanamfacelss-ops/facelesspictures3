<?php
/**
 * Activity Log Cleanup Cron Job
 * 
 * Automatically deletes activity logs older than 90 days.
 * 
 * Usage: php cron/activity-cleanup.php
 * Recommended: Run daily via cron/task scheduler
 * 
 * Cron example: 0 2 * * * /usr/bin/php /path/to/facelesspictures3/cron/activity-cleanup.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';

use App\Models\ActivityLog;

try {
    $activityLog = new ActivityLog();
    
    if (!$activityLog->tableExists()) {
        echo "[" . date('Y-m-d H:i:s') . "] Activity log table does not exist. Run migration 013.\n";
        exit(1);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Starting activity log cleanup...\n";
    
    $deletedCount = $activityLog->cleanupOldLogs();
    
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup complete. Deleted {$deletedCount} logs older than 90 days.\n";
    
    log_message('info', "Activity log cleanup: deleted {$deletedCount} entries older than 90 days");
    
    exit(0);
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    log_exception($e, 'ACTIVITY_CLEANUP_CRON');
    exit(1);
}
