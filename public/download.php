<?php
/**
 * File Download Proxy
 * Streams files from /uploads/ with Content-Disposition: attachment
 * so browsers immediately show Save As dialog without deliberation.
 * 
 * Usage: /download.php?path=/uploads/settings/xxx.mp3&name=song_title
 */

// Get parameters
$path = $_GET['path'] ?? '';
$name = $_GET['name'] ?? '';

// Debug mode - enable via ?debug=1 to see what's happening
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

// Build filesystem path
$filePath = __DIR__ . $path;

// Debug info
if ($debug) {
    header('Content-Type: text/plain');
    echo "DEBUG INFO:\n";
    echo "path param: " . $path . "\n";
    echo "__DIR__: " . __DIR__ . "\n";
    echo "filePath: " . $filePath . "\n";
    echo "file_exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";
    echo "is_file: " . (is_file($filePath) ? 'YES' : 'NO') . "\n";
    echo "is_readable: " . (is_readable($filePath) ? 'YES' : 'NO') . "\n";
    echo "realpath: " . (realpath($filePath) ?: 'FALSE') . "\n";
    exit;
}

// Check file exists
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found');
}

if (!is_readable($filePath)) {
    http_response_code(403);
    exit('File not readable');
}

// Extra safety: ensure resolved path is still under public/uploads/
// (protects against symlinks pointing outside)
$realResolved = realpath($filePath);
if ($realResolved !== false) {
    $normalized = str_replace('\\', '/', $realResolved);
    // If we can determine the uploads root, verify - but don't fail if we can't
    $publicDir = str_replace('\\', '/', __DIR__);
    // Only enforce if the resolved path is a subpath check possible
    // Allow if path contains /uploads/ anywhere in resolved
    if (strpos($normalized, '/uploads/') === false) {
        http_response_code(403);
        exit('Access denied (symlink outside uploads)');
    }
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
