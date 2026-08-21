<?php
/**
 * Simple Page Cache Helper
 * Caches entire rendered HTML pages to avoid PHP/database processing on every request
 */

class PageCache {
    private static $cacheDir;
    private static $cacheTime = 300; // 5 minutes
    
    public static function init($dir = null) {
        self::$cacheDir = $dir ?? __DIR__ . '/../../cache/pages';
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached page content if available and fresh
     */
    public static function get($key) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.html';
        
        if (!file_exists($file)) {
            return null;
        }
        
        // Check if cache is expired
        if (time() - filemtime($file) > self::$cacheTime) {
            @unlink($file);
            return null;
        }
        
        return file_get_contents($file);
    }
    
    /**
     * Save page content to cache
     */
    public static function set($key, $content) {
        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.html';
        
        // Write to temp file first, then rename (atomic operation)
        $temp = $file . '.tmp';
        if (file_put_contents($temp, $content) !== false) {
            @rename($temp, $file);
        }
    }
    
    /**
     * Clear all cached pages
     */
    public static function clear() {
        self::init();
        $files = glob(self::$cacheDir . '/*.html');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    
    /**
     * Set cache lifetime in seconds
     */
    public static function setCacheTime($seconds) {
        self::$cacheTime = $seconds;
    }
}
