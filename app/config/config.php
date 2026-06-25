<?php

declare(strict_types=1);

// Load .env file if it exists (local development), otherwise use system env vars (production)
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

define('APP_NAME', $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Faceless Pictures 3');
define('APP_ENV', $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development');
define('APP_URL', $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost');
define('UPLOAD_MAX_SIZE', $_ENV['UPLOAD_MAX_SIZE'] ?? getenv('UPLOAD_MAX_SIZE') ?: '500M');
define('CSRF_TOKEN_NAME', $_ENV['CSRF_TOKEN_NAME'] ?? getenv('CSRF_TOKEN_NAME') ?: 'fp3_csrf_token');

// Debug mode - set FP3_DEBUG=true in .env to enable
define('FP3_DEBUG', filter_var($_ENV['FP3_DEBUG'] ?? getenv('FP3_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));

// Paths
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../'));
}
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH', BASE_PATH . '/logs');

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (int)(($_ENV['SESSION_LIFETIME'] ?? getenv('SESSION_LIFETIME') ?: 120) * 60));
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Error reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\Controllers\\' => BASE_PATH . '/app/controllers/',
        'App\\Models\\' => BASE_PATH . '/app/models/',
        'App\\Services\\' => BASE_PATH . '/app/services/',
        'App\\Helpers\\' => BASE_PATH . '/app/helpers/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../helpers/functions.php';
