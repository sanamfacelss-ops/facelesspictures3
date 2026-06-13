<?php

require_once __DIR__ . '/../app/config/config.php';

use App\Services\ModerationService;

// Ensure this can only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden');
}

log_message('info', 'Starting moderation cron job.');

$moderation = new ModerationService();
$moderation->runQueue();

log_message('info', 'Moderation cron job completed.');
echo "Moderation queue processed.\n";
