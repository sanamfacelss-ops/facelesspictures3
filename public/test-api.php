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

// Test database - direct connection without our wrapper
try {
    require_once __DIR__ . '/../app/config/config.php';
    
    // Show what env vars we're reading
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $db = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'facelesspictures3';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
    
    $result['env_vars'] = [
        'DB_HOST' => $host,
        'DB_NAME' => $db,
        'DB_USER' => $user,
        'DB_PASSWORD' => !empty($pass) ? '(set - hidden)' : '(NOT SET!)',
    ];
    
    // Try direct PDO connection
    $dsn = "mysql:host={$host};port=3306;dbname={$db};charset=utf8mb4";
    $result['dsn'] = $dsn;
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    $result['database'] = 'connected';
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $result['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Count users
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    $row = $stmt->fetch();
    $result['user_count'] = $row['cnt'] ?? 0;
    
    // Show admin user details (without password)
    $stmt = $pdo->query("SELECT id, name, email, role, is_admin FROM users LIMIT 5");
    $result['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test password verification
    $testEmail = 'sanamfacelss@gmail.com';
    $testPassword = 'FP3@dmin2024!';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $userData = $stmt->fetch();
    if ($userData) {
        $result['password_test'] = [
            'user_found' => true,
            'stored_hash' => substr($userData['password'], 0, 20) . '...',
            'password_verify' => password_verify($testPassword, $userData['password']) ? 'MATCH' : 'NO MATCH'
        ];
    } else {
        $result['password_test'] = ['user_found' => false];
    }
    
} catch (PDOException $e) {
    $result['database'] = 'error';
    $result['db_error'] = $e->getMessage();
    $result['db_code'] = $e->getCode();
} catch (Exception $e) {
    $result['database'] = 'error';
    $result['db_error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
