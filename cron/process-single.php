<?php
/**
 * Process Single Video - Background Worker
 * 
 * Called by BackgroundProcessor to process a single video asynchronously.
 * Usage: php process-single.php <video_id>
 */

// Prevent timeout
set_time_limit(600); // 10 minutes max
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../app/config/config.php';

use App\Services\VideoProcessingService;
use App\Services\BackgroundProcessor;

// Get video ID from argument
$videoId = (int) ($argv[1] ?? 0);

if ($videoId <= 0) {
    echo "Error: Invalid video ID\n";
    exit(1);
}

// Acquire lock to prevent duplicate processing
if (!BackgroundProcessor::acquireLock($videoId)) {
    echo "Video {$videoId} is already being processed\n";
    exit(0);
}

echo "Starting processing for video {$videoId}...\n";
$startTime = microtime(true);

try {
    $processor = new VideoProcessingService();
    $result = $processor->processVideo($videoId);

    $elapsed = round(microtime(true) - $startTime, 2);
    
    if ($result['success']) {
        echo "Video {$videoId} processed successfully in {$elapsed}s\n";
        echo "Status: {$result['status']}\n";
        echo "Score: " . ($result['score'] ?? 'N/A') . "\n";
        
        if (!empty($result['feedback'])) {
            echo "Feedback:\n";
            foreach ($result['feedback'] as $msg) {
                echo "  - {$msg}\n";
            }
        }
    } else {
        echo "Video {$videoId} processing failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

    log_message('info', "Background processing completed for video {$videoId} in {$elapsed}s - Status: " . ($result['status'] ?? 'unknown'));

} catch (Exception $e) {
    echo "Error processing video {$videoId}: " . $e->getMessage() . "\n";
    log_message('error', "Background processing error for video {$videoId}: " . $e->getMessage());
    exit(1);
} finally {
    // Always release lock
    BackgroundProcessor::releaseLock($videoId);
}

exit(0);
