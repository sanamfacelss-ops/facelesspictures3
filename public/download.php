<?php
/**
 * File Download Proxy
 * Streams files from /uploads/ with Content-Disposition: attachment
 * so browsers immediately show Save As dialog without deliberation.
 * 
 * Usage: /download.php?path=/uploads/settings/xxx.mp3&name=song_title
 */

// Load config to get UPLOAD_PATH (may be outside public/)
require_once __DIR__ . '/../app/config/config.php';

// Get parameters
$path = $_GET['path'] ?? '';
$name = $_GET['name'] ?? '';
$debug = isset($_GET['debug']);

// Validate: must start with /uploads/
if (!$path || strpos($path, '/uploads/') !== 0) {
    http_response_code(400);
    exit('Invalid file path: must be under /uploads/');
}

// Prevent directory traversal
if (strpos($path, '..') !== false || strpos($path, '\\') !== false || strpos($path, "\0") !== false) {
    http_response_code(400);
    exit('Invalid path characters');
}

// Convert URL path to filesystem path.
// URL: /uploads/settings/xxx.mp3
// FS:  UPLOAD_PATH/settings/xxx.mp3   (UPLOAD_PATH is BASE_PATH/uploads)
$relativePath = substr($path, strlen('/uploads/')); // "settings/xxx.mp3"
$candidates = [
    UPLOAD_PATH . '/' . $relativePath,     // Primary: BASE_PATH/uploads/settings/xxx.mp3
    __DIR__ . $path,                        // Fallback: public/uploads/settings/xxx.mp3
    dirname(__DIR__) . $path,               // Fallback: BASE_PATH/uploads/settings/xxx.mp3 alt form
];

$filePath = null;
foreach ($candidates as $cand) {
    if (is_file($cand)) {
        $filePath = $cand;
        break;
    }
}

// Debug mode
if ($debug) {
    header('Content-Type: text/plain');
    echo "DEBUG INFO:\n";
    echo "path param: " . $path . "\n";
    echo "relativePath: " . $relativePath . "\n";
    echo "UPLOAD_PATH: " . UPLOAD_PATH . "\n";
    echo "__DIR__: " . __DIR__ . "\n";
    echo "BASE_PATH: " . BASE_PATH . "\n\n";
    echo "Candidates tested:\n";
    foreach ($candidates as $i => $cand) {
        echo "  [" . $i . "] " . $cand . "  -> " . (is_file($cand) ? 'FOUND' : 'not found') . "\n";
    }
    echo "\nfilePath resolved to: " . ($filePath ?: 'NULL') . "\n";
    exit;
}

// File not found in any candidate location
if ($filePath === null) {
    http_response_code(404);
    exit('File not found');
}

if (!is_readable($filePath)) {
    http_response_code(403);
    exit('File not readable');
}

// Determine filename
$origFilename = basename($filePath);
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Sanitize custom filename if provided
if ($name) {
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    $filename = $safeName . '.' . $ext;
} else {
    $filename = $origFilename;
}

// Content-Type mapping
$mimeTypes = [
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
    'aac'  => 'audio/aac',
    'ogg'  => 'audio/ogg',
    'flac' => 'audio/flac',
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'webm' => 'video/webm',
    'avi'  => 'video/x-msvideo',
    'mkv'  => 'video/x-matroska',
    'mpeg' => 'video/mpeg',
    'mpg'  => 'video/mpeg',
    'pdf'  => 'application/pdf',
];
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

$fileSize = filesize($filePath);

// Disable all output buffering and compression (prevents server-side delays)
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', '1');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
while (ob_get_level()) {
    ob_end_clean();
}

// Send download headers
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('X-Accel-Buffering: no'); // disable Nginx buffering
header('Accept-Ranges: bytes');

// Disable time limit and increase memory
@set_time_limit(0);
@ignore_user_abort(false);

// Handle HEAD requests (for prefetch) - send headers only, no body
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

// Stream file with fpassthru (fastest PHP method - no manual chunking overhead)
$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Cannot open file');
}
fpassthru($fp);
fclose($fp);
exit;
