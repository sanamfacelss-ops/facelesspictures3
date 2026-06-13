<?php
// Simple test file - access directly at /test-api.php
header('Content-Type: application/json');

$result = [
    'status' => 'ok',
    'time' => date('Y-m-d H:i:s'),
    'php' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
];

// Test database
try {
    require_once __DIR__ . '/../app/config/config.php';
    
    // Show what env vars we're reading
    $result['env_vars'] = [
        'DB_HOST' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '(not set)',
        'DB_NAME' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '(not set)',
        'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '(not set)',
        'DB_USER' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '(not set)',
        'DB_USERNAME' => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: '(not set)',
        'DB_PASSWORD' => !empty($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD')) ? '(set - hidden)' : '(NOT SET!)',
    ];
    
    $db = \App\Config\Database::getConnection();
    $result['database'] = 'connected';
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $result['tables'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $result['database'] = 'error';
    $result['db_error'] = $e->getMessage();
    $result['db_code'] = $e->getCode();
} catch (Exception $e) {
    $result['database'] = 'error';
    $result['db_error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
