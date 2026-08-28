<?php
/**
 * Media Library API - List all images from uploads directory
 * Returns JSON array of image files with metadata
 */

header('Content-Type: application/json');

// Start session to check authentication
session_start();

// Require authentication (admin only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Path to uploads directory (relative to this API file)
$uploadsDir = realpath(__DIR__ . '/../../uploads');

if (!$uploadsDir || !is_dir($uploadsDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Uploads directory not found']);
    exit;
}

// Allowed image extensions
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

// Scan uploads directory recursively
$images = [];

function scanDirectoryForImages($dir, $baseDir, $allowedExtensions) {
    $images = [];
    
    if (!is_dir($dir)) {
        return $images;
    }
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        
        // If it's a directory, scan recursively
        if (is_dir($fullPath)) {
            $images = array_merge($images, scanDirectoryForImages($fullPath, $baseDir, $allowedExtensions));
        } else {
            // Check if it's an image file
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            if (in_array($extension, $allowedExtensions)) {
                $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $fullPath);
                $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators
                
                $fileSize = filesize($fullPath);
                $modified = filemtime($fullPath);
                
                $images[] = [
                    'name' => $item,
                    'path' => '/uploads/' . $relativePath,
                    'size' => $fileSize,
                    'sizeFormatted' => formatFileSize($fileSize),
                    'modified' => $modified,
                    'modifiedFormatted' => date('Y-m-d H:i:s', $modified),
                    'extension' => $extension
                ];
            }
        }
    }
    
    return $images;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

try {
    $images = scanDirectoryForImages($uploadsDir, $uploadsDir, $allowedExtensions);
    
    // Sort by modified date (newest first)
    usort($images, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($images),
        'images' => $images
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error scanning uploads directory: ' . $e->getMessage()
    ]);
}
