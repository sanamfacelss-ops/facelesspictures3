<?php
/**
 * Direct router debug - bypasses everything
 * Visit /debug-router.php?test=1 to simulate /api/login POST
 */
header('Content-Type: application/json');

$result = [
    'time' => date('Y-m-d H:i:s'),
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'not set',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'not set',
];

// Parse URI like the router does
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$result['parsed_uri'] = $uri;
$result['trimmed_uri'] = trim($uri, '/');

// Check if logs directory exists and is writable
$logDir = __DIR__ . '/../logs';
$result['log_dir'] = $logDir;
$result['log_dir_exists'] = is_dir($logDir);
$result['log_dir_writable'] = is_writable($logDir);

// Try to write a test log
$testLogFile = $logDir . '/debug.log';
$testWrite = @file_put_contents($testLogFile, "[" . date('Y-m-d H:i:s') . "] Test write from debug-router.php\n", FILE_APPEND);
$result['test_write'] = $testWrite !== false ? 'success' : 'failed';

// Test route matching
$testUri = 'api/login';
$pattern = 'api/login';
$regex = '#^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern) . '$#';
$result['test_route'] = [
    'uri' => $testUri,
    'pattern' => $pattern,
    'regex' => $regex,
    'matches' => preg_match($regex, $testUri) ? 'YES' : 'NO'
];

// Simulate what happens with /api/login
if (isset($_GET['simulate'])) {
    require_once __DIR__ . '/../app/config/config.php';
    
    $result['FP3_DEBUG'] = defined('FP3_DEBUG') ? FP3_DEBUG : 'not defined';
    $result['LOG_PATH'] = defined('LOG_PATH') ? LOG_PATH : 'not defined';
    
    // Try loading AuthController
    try {
        $controller = new \App\Controllers\AuthController();
        $result['auth_controller'] = 'loaded successfully';
    } catch (Throwable $e) {
        $result['auth_controller'] = 'error: ' . $e->getMessage();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
