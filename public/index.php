<?php
/**
 * Faceless Pitcher 3 - Main Router
 * Routes API requests to controllers and page requests to views
 */

require_once __DIR__ . '/../app/config/config.php';

use App\Controllers\AuthController;
use App\Controllers\UploadController;
use App\Controllers\ModerationController;
use App\Controllers\LeaderboardController;
use App\Controllers\AIController;
use App\Controllers\AdminController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Debug routing - log every request
debug_log("=== ROUTER: URI='$uri' METHOD='$method' ===", 'ROUTER');

// Debug endpoint - check system status (remove in production)
if ($uri === 'api/debug') {
    header('Content-Type: application/json');
    $status = ['timestamp' => date('Y-m-d H:i:s'), 'php' => PHP_VERSION];
    
    // Test database
    try {
        $db = \App\Config\Database::getConnection();
        $status['database'] = 'connected';
        
        // Check if users table exists
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        $status['users_table'] = $stmt->rowCount() > 0 ? 'exists' : 'missing';
        
        // Count users
        if ($status['users_table'] === 'exists') {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
            $status['user_count'] = $stmt->fetch()['cnt'];
            
            // Check if content_categories column exists
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'content_categories'");
            $status['content_categories_column'] = $stmt->rowCount() > 0 ? 'exists' : 'MISSING - run migration 002!';
            
            // Show last user's categories
            $stmt = $db->query("SELECT id, name, email, role, content_categories FROM users ORDER BY id DESC LIMIT 1");
            $lastUser = $stmt->fetch();
            if ($lastUser) {
                $status['last_user'] = [
                    'id' => $lastUser['id'],
                    'name' => $lastUser['name'],
                    'role' => $lastUser['role'],
                    'content_categories_raw' => $lastUser['content_categories'],
                    'content_categories_decoded' => json_decode($lastUser['content_categories'] ?? '[]', true),
                ];
            }
        }
        
        // Check scripts table
        $stmt = $db->query("SHOW TABLES LIKE 'scripts'");
        $status['scripts_table'] = $stmt->rowCount() > 0 ? 'exists' : 'MISSING - run migration 003!';
        
        if ($status['scripts_table'] === 'exists') {
            $stmt = $db->query("SELECT category, COUNT(*) as count FROM scripts WHERE is_active = 1 GROUP BY category");
            $status['scripts_by_category'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
    } catch (\Exception $e) {
        $status['database'] = 'error: ' . $e->getMessage();
    }
    
    // Check session
    $status['session'] = session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive';
    $status['csrf_set'] = isset($_SESSION[CSRF_TOKEN_NAME]);
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    exit;
}

// API routes
$routes = [
    // Auth API
    'api/register' => [AuthController::class, 'register', 'POST'],
    'api/login' => [AuthController::class, 'login', 'POST'],
    'api/logout' => [AuthController::class, 'logout', 'POST'],
    'api/delete-account' => [AuthController::class, 'deleteAccount', 'POST'],
    'api/forgot-password' => [AuthController::class, 'forgotPassword', 'POST'],
    'api/verify-otp' => [AuthController::class, 'verifyOTP', 'POST'],
    'api/reset-password' => [AuthController::class, 'resetPassword', 'POST'],

    // Upload API
    'api/upload' => [UploadController::class, 'store', 'POST'],

    // Moderation API
    'api/moderation/pending' => [ModerationController::class, 'pendingList', 'GET'],
    'api/moderation/approve/{id}' => [ModerationController::class, 'approve', 'POST'],
    'api/moderation/reject/{id}' => [ModerationController::class, 'reject', 'POST'],

    // Leaderboard API
    'api/leaderboard' => [LeaderboardController::class, 'index', 'GET'],
    'api/seasons' => [LeaderboardController::class, 'seasons', 'GET'],

    // AI Quality Check API
    'api/ai/webhook' => [AIController::class, 'webhook', 'POST'],
    'api/ai/process/{id}' => [AIController::class, 'process', 'POST'],
    'api/ai/queue' => [AIController::class, 'processQueue', 'GET'],
    'api/ai/status/{id}' => [AIController::class, 'status', 'GET'],

    // Admin API
    'api/admin/scripts/create' => [AdminController::class, 'createScript', 'POST'],
    'api/admin/scripts/update/{id}' => [AdminController::class, 'updateScript', 'POST'],
    'api/admin/scripts/delete/{id}' => [AdminController::class, 'deleteScript', 'POST'],
    'api/admin/seasons/create' => [AdminController::class, 'createSeason', 'POST'],
    'api/admin/seasons/update/{id}' => [AdminController::class, 'updateSeason', 'POST'],
    'api/admin/users/delete/{id}' => [AdminController::class, 'deleteUser', 'POST'],
    'api/admin/videos' => [AdminController::class, 'allVideos', 'GET'],
];

$matched = false;
$params = [];

foreach ($routes as $pattern => $handler) {
    $regex = '#^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern) . '$#';
    if (preg_match($regex, $uri, $matches)) {
        debug_log("ROUTER: Matched pattern '$pattern'", 'ROUTER');
        if ($method !== $handler[2]) {
            debug_log("ROUTER: Method mismatch - expected {$handler[2]}, got $method", 'ROUTER');
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        array_shift($matches);
        $params = $matches;
        $matched = true;

        debug_log("ROUTER: Calling {$handler[0]}::{$handler[1]}", 'ROUTER');
        $controller = new $handler[0]();
        $action = $handler[1];

        if (!empty($params)) {
            $controller->$action((int) $params[0]);
        } else {
            $controller->$action();
        }
        exit;
    }
}

debug_log("ROUTER: No API route matched for '$uri'", 'ROUTER');

// Page routes (render HTML)
$pageRoutes = [
    '' => 'home.php',
    'home' => 'home.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'forgot-password' => 'forgot-password.php',
    'reset-password' => 'reset-password.php',
    'dashboard' => 'dashboard.php',
    'upload' => 'upload.php',
    'leaderboard' => 'leaderboard.php',
    // Creator studio routes
    'creator/dashboard' => 'creator/dashboard.php',
    'creator/record' => 'creator/record.php',
    'creator/videos' => 'creator/videos.php',
    // Onboarding
    'onboarding' => 'onboarding.php',
];

if (isset($pageRoutes[$uri])) {
    $pageFile = __DIR__ . '/' . $pageRoutes[$uri];
    if (file_exists($pageFile)) {
        require_once $pageFile;
        exit;
    }
}

// Admin routes
if ($uri === 'admin') {
    require_once __DIR__ . '/admin.php';
    exit;
}

// 404
http_response_code(404);
require_once __DIR__ . '/404.php';
