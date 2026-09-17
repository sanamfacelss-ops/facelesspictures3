<?php
/**
 * File Download Proxy
 * Streams files with Content-Disposition: attachment header
 * so browsers immediately show Save As dialog without deliberation.
 * 
 * Usage: /download.php?path=/uploads/settings/xxx.mp3&name=song_title
 */

require_once __DIR__ . '/../app/config/config.php';

// Get parameters
$path = $_GET['path'] ?? '';
$name = $_GET['name'] ?? '';

// Validate: must be a path under /uploads/
if (!$path || strpos($path, '/uploads/') !== 0) {
    http_response_code(400);
    exit('Invalid file path');
}

// Prevent directory traversal
if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
    http_response_code(400);
    exit('Invalid path');
}

// Resolve to actual filesystem path
$realPath = __DIR__ . $path;
$realResolved = realpath($realPath);
$uploadsRoot = realpath(__DIR__ . '/uploads');

if (!$realResolved || !$uploadsRoot || strpos($realResolved, $uploadsRoot) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

if (!file_exists($realResolved) || !is_file($realResolved)) {
    http_response_code(404);
    exit('File not found');
}

// Determine filename
$origFilename = basename($realResolved);
$ext = strtolower(pathinfo($realResolved, PATHINFO_EXTENSION));

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

$fileSize = filesize($realResolved);

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

// Stream file in chunks (no memory buffer for large files)
$fp = fopen($realResolved, 'rb');
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
