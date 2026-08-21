<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Image Compression Service
 * 
 * Optimizes images by:
 * - Converting to WebP format (better compression)
 * - Reducing quality while maintaining visual appearance
 * - Resizing if dimensions are too large
 */
class ImageCompressionService
{
    private int $maxWidth = 1920;
    private int $maxHeight = 1920;
    private int $jpegQuality = 85;
    private int $pngQuality = 85;
    private int $webpQuality = 85;

    /**
     * Compress image and optionally convert to WebP
     * 
     * @param string $inputPath Full path to input image
     * @param string|null $outputPath Full path for output (optional, will overwrite if null)
     * @param bool $convertToWebP Convert to WebP format for better compression
     * @return array ['success' => bool, 'output_path' => string, 'original_size' => int, 'compressed_size' => int, 'compression_ratio' => float]
     */
    public function compressImage(string $inputPath, ?string $outputPath = null, bool $convertToWebP = true): array
    {
        $startTime = microtime(true);

        if (!file_exists($inputPath)) {
            return [
                'success' => false,
                'error' => 'Input file not found',
                'output_path' => null
            ];
        }

        // Get original file size
        $originalSize = filesize($inputPath);
        
        // Detect image type
        $imageInfo = getimagesize($inputPath);
        if ($imageInfo === false) {
            return [
                'success' => false,
                'error' => 'Invalid image file',
                'output_path' => null
            ];
        }

        $mimeType = $imageInfo['mime'];
        
        // Load image based on type
        $image = $this->loadImage($inputPath, $mimeType);
        if (!$image) {
            return [
                'success' => false,
                'error' => 'Failed to load image',
                'output_path' => null
            ];
        }

        // Get dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Resize if too large
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG/WebP
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        // Determine output path and format
        if (!$outputPath) {
            if ($convertToWebP && function_exists('imagewebp')) {
                $pathInfo = pathinfo($inputPath);
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
            } else {
                $outputPath = $inputPath;
            }
        }

        // Save compressed image
        $saved = false;
        if ($convertToWebP && function_exists('imagewebp') && (str_ends_with($outputPath, '.webp') || !$outputPath)) {
            // Save as WebP
            if (!str_ends_with($outputPath, '.webp')) {
                $pathInfo = pathinfo($outputPath);
                $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
            }
            $saved = imagewebp($image, $outputPath, $this->webpQuality);
        } else {
            // Save in original format or specified format
            $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $saved = imagejpeg($image, $outputPath, $this->jpegQuality);
                    break;
                case 'png':
                    // PNG quality is 0-9 (compression level), convert from 0-100
                    $pngCompression = (int)(9 - (($this->pngQuality / 100) * 9));
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    $saved = imagepng($image, $outputPath, $pngCompression);
                    break;
                case 'gif':
                    $saved = imagegif($image, $outputPath);
                    break;
                case 'webp':
                    if (function_exists('imagewebp')) {
                        $saved = imagewebp($image, $outputPath, $this->webpQuality);
                    }
                    break;
                default:
                    // Default to JPEG
                    $saved = imagejpeg($image, $outputPath, $this->jpegQuality);
            }
        }

        imagedestroy($image);

        if (!$saved || !file_exists($outputPath)) {
            return [
                'success' => false,
                'error' => 'Failed to save compressed image',
                'output_path' => null
            ];
        }

        $compressedSize = filesize($outputPath);
        $compressionRatio = $originalSize > 0 ? (1 - ($compressedSize / $originalSize)) * 100 : 0;
        $processingTime = round((microtime(true) - $startTime) * 1000);

        log_message('info', sprintf(
            "Image compressed: %s → %s (%.1f%% reduction, %dms)",
            $this->formatBytes($originalSize),
            $this->formatBytes($compressedSize),
            $compressionRatio,
            $processingTime
        ));

        // If we converted to WebP and it's larger than original, revert
        if ($convertToWebP && $compressedSize > $originalSize && $outputPath !== $inputPath) {
            @unlink($outputPath);
            return [
                'success' => true,
                'output_path' => $inputPath,
                'original_size' => $originalSize,
                'compressed_size' => $originalSize,
                'compression_ratio' => 0,
                'processing_time_ms' => $processingTime,
                'note' => 'WebP conversion resulted in larger file, kept original'
            ];
        }

        return [
            'success' => true,
            'output_path' => $outputPath,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'compression_ratio' => round($compressionRatio, 1),
            'processing_time_ms' => $processingTime
        ];
    }

    /**
     * Load image from file based on MIME type
     */
    private function loadImage(string $path, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
            case 'image/bmp':
            case 'image/x-ms-bmp':
                return function_exists('imagecreatefrombmp') ? imagecreatefrombmp($path) : false;
            default:
                return false;
        }
    }

    /**
     * Format bytes to human readable string
     */
    private function formatBytes(int $bytes): string
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

    /**
     * Set maximum dimensions for resizing
     */
    public function setMaxDimensions(int $width, int $height): void
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;
    }

    /**
     * Set quality settings
     */
    public function setQuality(int $quality): void
    {
        $this->jpegQuality = max(1, min(100, $quality));
        $this->pngQuality = max(1, min(100, $quality));
        $this->webpQuality = max(1, min(100, $quality));
    }
}
