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

// Serve uploaded files (uploads folder is outside public)
if (preg_match('/^uploads\/(.+)$/', $uri, $matches)) {
    $filename = basename($matches[1]); // Sanitize - only filename, no paths
    $filePath = UPLOAD_PATH . '/' . $filename;
    
    if (file_exists($filePath) && is_file($filePath)) {
        // Get MIME type
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=86400');
        
        // Support range requests for video seeking
        if (isset($_SERVER['HTTP_RANGE'])) {
            $size = filesize($filePath);
            $range = $_SERVER['HTTP_RANGE'];
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
            $start = intval($matches[1]);
            $end = $matches[2] !== '' ? intval($matches[2]) : $size - 1;
            
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$size");
            header('Content-Length: ' . ($end - $start + 1));
            
            $fp = fopen($filePath, 'rb');
            fseek($fp, $start);
            echo fread($fp, $end - $start + 1);
            fclose($fp);
        } else {
            readfile($filePath);
        }
        exit;
    } else {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
}

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
    'api/moderation/flagged' => [ModerationController::class, 'flaggedList', 'GET'],
    'api/moderation/detail/{id}' => [ModerationController::class, 'detail', 'GET'],
    'api/moderation/approve/{id}' => [ModerationController::class, 'approve', 'POST'],
    'api/moderation/reject/{id}' => [ModerationController::class, 'reject', 'POST'],

    // Leaderboard API
    'api/leaderboard' => [LeaderboardController::class, 'index', 'GET'],
    'api/seasons' => [LeaderboardController::class, 'seasons', 'GET'],

    // AI Quality Check API
    'api/ai/webhook' => [AIController::class, 'webhook', 'POST'],
    'api/ai/process/{id}' => [AIController::class, 'process', 'POST'],
    'api/ai/reprocess/{id}' => [AIController::class, 'reprocess', 'POST'],
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
    'api/admin/video/delete/{id}' => [AdminController::class, 'deleteVideo', 'POST'],
    'api/admin/video/bulk-delete' => [AdminController::class, 'bulkDeleteVideos', 'POST'],
    'api/admin/guides/update' => [AdminController::class, 'updateGuide', 'POST'],
    'api/admin/ai/config' => [AdminController::class, 'getAIConfig', 'GET'],
    'api/admin/ai/config/update' => [AdminController::class, 'updateAIConfig', 'POST'],
    'api/admin/ai/test' => [AdminController::class, 'testAIProvider', 'POST'],
    'api/admin/ai/keys' => [AdminController::class, 'getAPIKeyStatus', 'GET'],
    'api/admin/ai/keys/update' => [AdminController::class, 'updateAPIKeys', 'POST'],
    'api/admin/youtube/publish/{id}' => [AdminController::class, 'publishToYouTube', 'POST'],
    'api/admin/youtube/test' => [AdminController::class, 'testYouTube', 'POST'],
    'api/admin/ai/test-only' => [AdminController::class, 'testAI', 'POST'],
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
