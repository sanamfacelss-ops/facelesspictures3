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

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Send download headers
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Disable time limit for large files
@set_time_limit(0);

// Stream file in chunks (no memory buffer for large files)
$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Cannot open file');
}

while (!feof($fp)) {
    echo fread($fp, 8192);
    if (connection_status() !== CONNECTION_NORMAL) break;
    flush();
}
fclose($fp);
exit;
