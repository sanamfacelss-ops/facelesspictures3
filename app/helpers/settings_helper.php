<?php
/**
 * Settings Helper - Provides fast cached access to settings
 * Include this file at the top of any page that needs settings
 */

// Load all settings once (cached)
if (!isset($GLOBALS['_cached_settings'])) {
    $settingsModel = new App\Models\Settings();
    $GLOBALS['_cached_settings'] = $settingsModel->getAllCached();
}

/**
 * Get a setting value from cache
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function setting(string $key, $default = '')
{
    return $GLOBALS['_cached_settings'][$key] ?? $default;
}

/**
 * Get multiple settings at once
 * @param array $keys Array of setting keys
 * @param mixed $default Default value for missing keys
 * @return array Associative array of key => value
 */
function settings(array $keys, $default = ''): array
{
    $result = [];
    foreach ($keys as $key) {
        $result[$key] = setting($key, $default);
    }
    return $result;
}
