<?php
/**
 * AI Processing Queue Cron Job
 * 
 * Processes pending videos through AI quality check
 * Run via cron: * * * * * php /path/to/cron/ai-process.php
 */

require_once __DIR__ . '/../app/config/config.php';

use App\Services\AIQualityService;

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

$aiService = new AIQualityService();
$limit = (int) ($argv[1] ?? $_GET['limit'] ?? 5);

echo "Processing AI queue (limit: {$limit})...\n";

try {
    $results = $aiService->processQueue($limit);
    
    echo "Processed " . count($results) . " videos:\n";
    foreach ($results as $item) {
        $status = $item['result']['status'] ?? 'unknown';
        $score = $item['result']['score'] ?? 'N/A';
        echo "  - Video #{$item['video_id']} '{$item['title']}': {$status} (score: {$score})\n";
    }
    
    log_message('info', "AI cron processed " . count($results) . " videos");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    log_message('error', "AI cron error: " . $e->getMessage());
    exit(1);
}
