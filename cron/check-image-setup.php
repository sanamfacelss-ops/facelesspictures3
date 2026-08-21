#!/usr/bin/env php
<?php
/**
 * Image Optimization Setup Checker
 * 
 * Verifies:
 * 1. PHP GD/Imagick extensions available
 * 2. Upload directory exists and is writable
 * 3. Images exist to optimize
 * 4. ImageCompressionService can be loaded
 */

// Load only what we need - avoid session warnings
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// Simple autoloader for our service
spl_autoload_register(function ($class) {
    $class = str_replace('App\\Services\\', '', $class);
    $file = APP_PATH . '/services/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Simple log function
if (!function_exists('log_message')) {
    function log_message($level, $message) {
        // Silent for check script
    }
}

use App\Services\ImageCompressionService;

echo "══════════════════════════════════════════════════════\n";
echo "  IMAGE OPTIMIZATION SETUP CHECKER\n";
echo "══════════════════════════════════════════════════════\n\n";

// Check 1: PHP Extensions
echo "1. Checking PHP Extensions...\n";
$hasGD = extension_loaded('gd');
$hasImagick = extension_loaded('imagick');

if ($hasGD) {
    echo "   ✓ GD Extension: INSTALLED\n";
    $gdInfo = gd_info();
    echo "     - WebP Support: " . ($gdInfo['WebP Support'] ? 'YES' : 'NO') . "\n";
} else {
    echo "   ✗ GD Extension: NOT INSTALLED\n";
}

if ($hasImagick) {
    echo "   ✓ Imagick Extension: INSTALLED\n";
} else {
    echo "   ✗ Imagick Extension: NOT INSTALLED\n";
}

if (!$hasGD && !$hasImagick) {
    echo "\n   ⚠ WARNING: No image processing extensions available!\n";
    echo "   Install GD or Imagick to enable compression.\n\n";
    exit(1);
}

echo "\n";

// Check 2: Upload Directory
echo "2. Checking Upload Directory...\n";
// Try public/uploads first, then fall back to root uploads
$uploadDir = __DIR__ . '/../public/uploads';
$uploadDirFull = realpath($uploadDir);

if (!$uploadDirFull) {
    // Try root uploads directory
    $uploadDir = __DIR__ . '/../uploads';
    $uploadDirFull = realpath($uploadDir);
}

if (!$uploadDirFull) {
    echo "   ✗ Upload directory does not exist: $uploadDir\n\n";
    exit(1);
}

echo "   ✓ Directory exists: $uploadDirFull\n";

if (is_writable($uploadDirFull)) {
    echo "   ✓ Directory is writable\n";
} else {
    echo "   ✗ Directory is NOT writable\n";
    echo "     Run: chmod 755 $uploadDirFull\n";
}

echo "\n";

// Check 3: Find Images
echo "3. Scanning for Images...\n";
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
$images = [];

foreach ($extensions as $ext) {
    $pattern = $uploadDir . '/*.' . $ext;
    $found = glob($pattern);
    if ($found) {
        $images = array_merge($images, $found);
    }
}

// Also check subdirectories
foreach ($extensions as $ext) {
    $pattern = $uploadDir . '/**/*.' . $ext;
    $found = glob($pattern, GLOB_BRACE);
    if ($found) {
        $images = array_merge($images, $found);
    }
}

$images = array_unique($images);
$totalImages = count($images);

echo "   Found: $totalImages images\n";

if ($totalImages === 0) {
    echo "\n   ⚠ No images found in uploads directory.\n";
    echo "   Upload some images through the admin panel first.\n\n";
    exit(0);
}

// Show first 10 images with sizes
echo "\n   Sample images:\n";
$count = 0;
foreach ($images as $img) {
    if ($count >= 10) break;
    $size = filesize($img);
    $sizeFormatted = formatBytes($size);
    $needsCompression = $size > 500000 ? '⚠ LARGE' : '✓';
    echo "   $needsCompression " . basename($img) . " - $sizeFormatted\n";
    $count++;
}

echo "\n";

// Check 4: ImageCompressionService
echo "4. Checking ImageCompressionService...\n";
$servicePath = __DIR__ . '/../app/services/ImageCompressionService.php';

if (!file_exists($servicePath)) {
    echo "   ✗ ImageCompressionService not found: $servicePath\n\n";
    exit(1);
}

try {
    $service = new ImageCompressionService();
    echo "   ✓ ImageCompressionService loaded successfully\n";
} catch (Exception $e) {
    echo "   ✗ Error loading service: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "\n";

// Summary
echo "══════════════════════════════════════════════════════\n";
echo "  SUMMARY\n";
echo "══════════════════════════════════════════════════════\n\n";

if ($hasGD || $hasImagick) {
    echo "✓ Image processing: READY\n";
} else {
    echo "✗ Image processing: NOT READY\n";
}

if ($totalImages > 0) {
    echo "✓ Images found: $totalImages\n";
    
    // Count large images
    $largeImages = 0;
    $totalSize = 0;
    foreach ($images as $img) {
        $size = filesize($img);
        $totalSize += $size;
        if ($size > 500000) $largeImages++;
    }
    
    if ($largeImages > 0) {
        echo "⚠ Large images (>500KB): $largeImages\n";
        echo "  Total size: " . formatBytes($totalSize) . "\n";
        echo "\n";
        echo "  → Run: php cron/optimize-images-bulk.php\n";
        echo "  → This will compress all images and save ~60-80% space\n";
    } else {
        echo "✓ All images already optimized (<500KB each)\n";
    }
} else {
    echo "⚠ No images found\n";
    echo "  Upload images through admin panel first\n";
}

echo "\n";

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
