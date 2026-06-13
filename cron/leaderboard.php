<?php

require_once __DIR__ . '/../app/config/config.php';

use App\Services\YouTubeService;

// Ensure this can only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden');
}

log_message('info', 'Starting leaderboard sync cron job.');

$youtube = new YouTubeService();
$youtube->syncStats();

log_message('info', 'Leaderboard sync cron job completed.');
echo "Leaderboard stats synced.\n";
