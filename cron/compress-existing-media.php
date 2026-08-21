#!/usr/bin/env php
<?php
/**
 * One-time script to compress all existing videos and images
 * Run: php cron/compress-existing-media.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';

use App\Services\VideoProcessingService;
use App\Services\ImageCompressionService;

$startTime = microtime(true);
$stats = [
    'videos_processed' => 0,
    'videos_compressed' => 0,
    'videos_failed' => 0,
    'videos_saved_bytes' => 0,
    'images_processed' => 0,
    'images_compressed' => 0,
    'images_failed' => 0,
    'images_saved_bytes' => 0,
];

echo "=== MEDIA COMPRESSION BATCH JOB ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// Initialize services
$videoService = new VideoProcessingService();
$imageService = new ImageCompressionService();

// Compress videos in uploads/ directory
echo "--- COMPRESSING VIDEOS ---\n";
$videoPattern = UPLOAD_PATH . '/*.{mp4,mov,avi,webm}';
$videoFiles = glob($videoPattern, GLOB_BRACE);

if ($videoFiles) {
    foreach ($videoFiles as $videoPath) {
        $filename = basename($videoPath);
        $stats['videos_processed']++;
        
        echo "[{$stats['videos_processed']}] Processing: $filename ... ";
        
        try {
            $result = $videoService->compressVideo($videoPath);
            
            if ($result['success']) {
                $compressedPath = $result['output_path'];
                
                // If compression created a new file, replace original
                if ($compressedPath !== $videoPath && file_exists($compressedPath)) {
                    $originalSize = filesize($videoPath);
                    $compressedSize = filesize($compressedPath);
                    
                    // Only replace if compressed is smaller
                    if ($compressedSize < $originalSize) {
                        unlink($videoPath);
                        rename($compressedPath, $videoPath);
                        
                        $saved = $originalSize - $compressedSize;
                        $stats['videos_compressed']++;
                        $stats['videos_saved_bytes'] += $saved;
                        
                        echo "✓ Compressed {$result['compression_ratio']}% (saved " . formatBytes($saved) . ")\n";
                    } else {
                        // Compressed is larger, keep original
                        unlink($compressedPath);
                        echo "⊘ Skipped (compressed larger than original)\n";
                    }
                } else {
                    echo "⊘ Already optimal\n";
                }
            } else {
                $stats['videos_failed']++;
                echo "✗ Failed: " . ($result['error'] ?? 'unknown error') . "\n";
            }
        } catch (\Exception $e) {
            $stats['videos_failed']++;
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "No video files found.\n";
}

echo "\n--- COMPRESSING IMAGES ---\n";

// Compress images in uploads/ and uploads/settings/
$imageDirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . '/settings',
];

$imageExtensions = '{jpg,jpeg,png,gif,bmp}';

foreach ($imageDirs as $dir) {
    if (!is_dir($dir)) continue;
    
    $imagePattern = $dir . '/*.' . $imageExtensions;
    $imageFiles = glob($imagePattern, GLOB_BRACE);
    
    if ($imageFiles) {
        $dirName = basename($dir);
        echo "\nDirectory: $dirName/\n";
        
        foreach ($imageFiles as $imagePath) {
            $filename = basename($imagePath);
            $stats['images_processed']++;
            
            echo "[{$stats['images_processed']}] Processing: $filename ... ";
            
            // Skip SVG files
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg') {
                echo "⊘ Skipped (SVG)\n";
                continue;
            }
            
            try {
                $originalSize = filesize($imagePath);
                $result = $imageService->compressImage($imagePath, null, true); // Try WebP conversion
                
                if ($result['success']) {
                    $compressedPath = $result['output_path'];
                    $compressedSize = $result['compressed_size'];
                    
                    // If converted to WebP and it's a different file
                    if ($compressedPath !== $imagePath && file_exists($compressedPath)) {
                        // Check if WebP is actually smaller
                        if ($compressedSize < $originalSize) {
                            // Keep both original and WebP for now
                            $saved = $originalSize - $compressedSize;
                            $stats['images_compressed']++;
                            $stats['images_saved_bytes'] += $saved;
                            
                            echo "✓ WebP {$result['compression_ratio']}% (saved " . formatBytes($saved) . ")\n";
                        } else {
                            // WebP is larger, delete it
                            if (file_exists($compressedPath)) {
                                unlink($compressedPath);
                            }
                            echo "⊘ WebP larger, kept original\n";
                        }
                    } else if ($compressedSize < $originalSize) {
                        // Compressed in-place
                        $saved = $originalSize - $compressedSize;
                        $stats['images_compressed']++;
                        $stats['images_saved_bytes'] += $saved;
                        
                        echo "✓ Compressed {$result['compression_ratio']}% (saved " . formatBytes($saved) . ")\n";
                    } else {
                        echo "⊘ Already optimal\n";
                    }
                } else {
                    $stats['images_failed']++;
                    echo "✗ Failed: " . ($result['error'] ?? 'unknown error') . "\n";
                }
            } catch (\Exception $e) {
                $stats['images_failed']++;
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Summary
$totalTime = round(microtime(true) - $startTime, 2);
$totalSaved = $stats['videos_saved_bytes'] + $stats['images_saved_bytes'];

echo "\n=== COMPRESSION SUMMARY ===\n";
echo "Videos:\n";
echo "  Processed: {$stats['videos_processed']}\n";
echo "  Compressed: {$stats['videos_compressed']}\n";
echo "  Failed: {$stats['videos_failed']}\n";
echo "  Saved: " . formatBytes($stats['videos_saved_bytes']) . "\n";

echo "\nImages:\n";
echo "  Processed: {$stats['images_processed']}\n";
echo "  Compressed: {$stats['images_compressed']}\n";
echo "  Failed: {$stats['images_failed']}\n";
echo "  Saved: " . formatBytes($stats['images_saved_bytes']) . "\n";

echo "\nTotal:\n";
echo "  Files: " . ($stats['videos_processed'] + $stats['images_processed']) . "\n";
echo "  Compressed: " . ($stats['videos_compressed'] + $stats['images_compressed']) . "\n";
echo "  Space Saved: " . formatBytes($totalSaved) . "\n";
echo "  Time: {$totalTime}s\n";

echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";

function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
