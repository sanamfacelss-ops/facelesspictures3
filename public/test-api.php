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
    $db = \App\Config\Database::getConnection();
    $result['database'] = 'connected';
    
    // Check tables
    $stmt = $db->query("SHOW TABLES");
    $result['tables'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $result['database'] = 'error';
    $result['db_error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
