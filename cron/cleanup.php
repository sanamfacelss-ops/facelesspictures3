<?php
/**
 * Cleanup Cron Job
 * 
 * Deletes files and DB records according to rules:
 * - Approved + YouTube uploaded: Delete file immediately (keep DB with youtube_id)
 * - Approved (no YouTube yet): Keep file until YouTube done
 * - Rejected: Delete file + DB row after 48 hours
 * - Pending (no action): Delete file + DB row after 48 hours
 * - Flagged (manual review): Keep until admin acts
 * 
 * Run via cron: 0 */6 * * * php /path/to/cron/cleanup.php
 */

require_once __DIR__ . '/../app/config/config.php';

use App\Config\Database;

// Prevent web access
if (php_sapi_name() !== 'cli' && !isset($_GET['key'])) {
    http_response_code(403);
    exit('CLI or key access only');
}

// Verify cron key if provided
$cronKey = $_ENV['CRON_SECRET_KEY'] ?? '';
if (isset($_GET['key']) && $_GET['key'] !== $cronKey) {
    http_response_code(403);
    exit('Invalid key');
}

$db = Database::getConnection();
$uploadPath = UPLOAD_PATH;
$stats = [
    'files_deleted' => 0,
    'rows_deleted' => 0,
    'youtube_files_deleted' => 0,
];

echo "Starting cleanup at " . date('Y-m-d H:i:s') . "\n";

// 1. Delete files for approved videos that have been uploaded to YouTube
// Keep DB record, just delete the file
$stmt = $db->query("
    SELECT id, file_path 
    FROM videos 
    WHERE status = 'approved' 
    AND youtube_id IS NOT NULL 
    AND file_path IS NOT NULL 
    AND file_path != ''
");
$approvedWithYoutube = $stmt->fetchAll();

foreach ($approvedWithYoutube as $video) {
    $filePath = $uploadPath . '/' . $video['file_path'];
    
    if (file_exists($filePath)) {
        if (@unlink($filePath)) {
            echo "Deleted file for YouTube-published video #{$video['id']}: {$video['file_path']}\n";
            $stats['youtube_files_deleted']++;
        } else {
            echo "Failed to delete file: {$filePath}\n";
        }
    }
    
    // Clear file_path in DB (keep youtube_id)
    $updateStmt = $db->prepare("UPDATE videos SET file_path = NULL WHERE id = ?");
    $updateStmt->execute([$video['id']]);
}

// 2. Delete rejected videos after 48 hours (file + DB row)
$stmt = $db->query("
    SELECT id, file_path, title
    FROM videos 
    WHERE status = 'rejected' 
    AND moderated_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
");
$rejectedOld = $stmt->fetchAll();

foreach ($rejectedOld as $video) {
    // Delete file
    if (!empty($video['file_path'])) {
        $filePath = $uploadPath . '/' . $video['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
            $stats['files_deleted']++;
        }
    }
    
    // Delete moderation logs first (foreign key)
    $delLogs = $db->prepare("DELETE FROM moderation_logs WHERE video_id = ?");
    $delLogs->execute([$video['id']]);
    
    // Delete video row
    $delVideo = $db->prepare("DELETE FROM videos WHERE id = ?");
    $delVideo->execute([$video['id']]);
    
    echo "Deleted rejected video #{$video['id']}: {$video['title']}\n";
    $stats['rows_deleted']++;
}

// 3. Delete pending/stuck videos after 48 hours (no action taken)
$stmt = $db->query("
    SELECT id, file_path, title
    FROM videos 
    WHERE status = 'pending' 
    AND ai_status NOT IN ('processing')
    AND needs_manual_review = 0
    AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
");
$pendingOld = $stmt->fetchAll();

foreach ($pendingOld as $video) {
    // Delete file
    if (!empty($video['file_path'])) {
        $filePath = $uploadPath . '/' . $video['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
            $stats['files_deleted']++;
        }
    }
    
    // Delete related records
    $delLogs = $db->prepare("DELETE FROM moderation_logs WHERE video_id = ?");
    $delLogs->execute([$video['id']]);
    
    // Delete video row
    $delVideo = $db->prepare("DELETE FROM videos WHERE id = ?");
    $delVideo->execute([$video['id']]);
    
    echo "Deleted stale pending video #{$video['id']}: {$video['title']}\n";
    $stats['rows_deleted']++;
}

// 4. Clean up orphan files (files in uploads/ not in DB)
$dbFiles = [];
$stmt = $db->query("SELECT file_path FROM videos WHERE file_path IS NOT NULL AND file_path != ''");
foreach ($stmt->fetchAll() as $row) {
    $dbFiles[] = $row['file_path'];
}

$uploadedFiles = glob($uploadPath . '/fp3_*');
foreach ($uploadedFiles as $file) {
    $filename = basename($file);
    if (!in_array($filename, $dbFiles)) {
        // Check if file is older than 1 hour (might be currently uploading)
        if (filemtime($file) < time() - 3600) {
            @unlink($file);
            echo "Deleted orphan file: {$filename}\n";
            $stats['files_deleted']++;
        }
    }
}

// 5. Clean up old temp files
$tempPatterns = [
    sys_get_temp_dir() . '/fp3_frames_*',
    sys_get_temp_dir() . '/fp3_audio_*',
    sys_get_temp_dir() . '/fp3_processing_*.lock',
];

foreach ($tempPatterns as $pattern) {
    foreach (glob($pattern) as $item) {
        // Only delete if older than 2 hours
        if (filemtime($item) < time() - 7200) {
            if (is_dir($item)) {
                array_map('unlink', glob($item . '/*'));
                @rmdir($item);
            } else {
                @unlink($item);
            }
            echo "Cleaned up temp: " . basename($item) . "\n";
        }
    }
}

echo "\nCleanup completed:\n";
echo "  - Files deleted: {$stats['files_deleted']}\n";
echo "  - DB rows deleted: {$stats['rows_deleted']}\n";
echo "  - YouTube published files deleted: {$stats['youtube_files_deleted']}\n";

log_message('info', "Cleanup cron: files={$stats['files_deleted']}, rows={$stats['rows_deleted']}, youtube_files={$stats['youtube_files_deleted']}");
