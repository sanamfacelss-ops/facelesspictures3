#!/usr/bin/env php
<?php
/**
 * Bulk Image Optimization Script
 * 
 * Optimizes all existing images by:
 * 1. Converting to WebP format (85% quality)
 * 2. Resizing if larger than 1920x1920
 * 3. Replacing original with optimized version
 * 4. Logging compression results
 * 
 * Usage: php cron/optimize-images-bulk.php
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/services/ImageCompressionService.php';

echo "══════════════════════════════════════════════════════\n";
echo "  BULK IMAGE OPTIMIZATION SCRIPT\n";
echo "══════════════════════════════════════════════════════\n\n";

// Try public/uploads first, then fall back to root uploads
$uploadDir = __DIR__ . '/../public/uploads';
if (!is_dir($uploadDir)) {
    $uploadDir = __DIR__ . '/../uploads';
}

echo "Upload directory: " . realpath($uploadDir) . "\n";

$imageService = new ImageCompressionService();

// Check if WebP is supported
$webpSupported = function_exists('imagewebp');
if ($webpSupported) {
    echo "WebP support: ENABLED (will convert to WebP)\n\n";
} else {
    echo "WebP support: DISABLED (will use JPEG/PNG compression)\n";
    echo "→ To enable WebP: sudo apt-get install libwebp-dev && sudo apt-get install --reinstall php-gd\n\n";
}

// Find all images
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
$images = [];

echo "Scanning for images in: $uploadDir\n\n";

foreach ($extensions as $ext) {
    $pattern = $uploadDir . '/**/*.' . $ext;
    $found = glob($pattern, GLOB_BRACE);
    if ($found) {
        $images = array_merge($images, $found);
    }
}

// Also check root uploads directory
foreach ($extensions as $ext) {
    $pattern = $uploadDir . '/*.' . $ext;
    $found = glob($pattern);
    if ($found) {
        $images = array_merge($images, $found);
    }
}

$totalImages = count($images);
echo "Found $totalImages images to optimize\n\n";

if ($totalImages === 0) {
    echo "No images found. Exiting.\n";
    exit(0);
}

$optimized = 0;
$skipped = 0;
$errors = 0;
$totalSaved = 0;

foreach ($images as $index => $imagePath) {
    $num = $index + 1;
    $filename = basename($imagePath);
    $originalSize = filesize($imagePath);
    
    echo "[$num/$totalImages] Processing: $filename\n";
    echo "  Original size: " . formatBytes($originalSize) . "\n";
    
    // Skip if already optimized (WebP with reasonable size)
    if (pathinfo($imagePath, PATHINFO_EXTENSION) === 'webp' && $originalSize < 500000) {
        echo "  ✓ Already optimized (WebP < 500KB)\n\n";
        $skipped++;
        continue;
    }
    
    try {
        // Compress the image (WebP conversion will be skipped if not supported)
        $result = $imageService->compressImage($imagePath, null, $webpSupported);
        
        if ($result['success']) {
            $newSize = filesize($result['path']);
            $saved = $originalSize - $newSize;
            $savedPercent = round(($saved / $originalSize) * 100, 1);
            
            echo "  ✓ Optimized: " . formatBytes($newSize) . "\n";
            echo "  → Saved: " . formatBytes($saved) . " ($savedPercent%)\n";
            echo "  → Processing time: " . round($result['processing_time'], 2) . "s\n\n";
            
            $optimized++;
            $totalSaved += $saved;
        } else {
            echo "  ⚠ Skipped: " . ($result['message'] ?? 'No compression needed') . "\n\n";
            $skipped++;
        }
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $errors++;
    }
    
    // Sleep briefly to avoid overwhelming the server
    if ($num % 10 === 0) {
        echo "  → Pausing for 2 seconds...\n\n";
        sleep(2);
    }
}

echo "══════════════════════════════════════════════════════\n";
echo "  OPTIMIZATION COMPLETE\n";
echo "══════════════════════════════════════════════════════\n\n";
echo "Total images processed: $totalImages\n";
echo "Successfully optimized: $optimized\n";
echo "Skipped (already optimal): $skipped\n";
echo "Errors: $errors\n";
echo "Total space saved: " . formatBytes($totalSaved) . "\n";
echo "Average reduction: " . ($totalImages > 0 ? round(($totalSaved / array_sum(array_map('filesize', $images))) * 100, 1) : 0) . "%\n\n";

/**
 * Format bytes to human-readable string
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
