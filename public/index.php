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

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// API routes
$routes = [
    // Auth API
    'api/register' => [AuthController::class, 'register', 'POST'],
    'api/login' => [AuthController::class, 'login', 'POST'],
    'api/logout' => [AuthController::class, 'logout', 'POST'],

    // Upload API
    'api/upload' => [UploadController::class, 'store', 'POST'],

    // Moderation API
    'api/moderation/pending' => [ModerationController::class, 'pendingList', 'GET'],
    'api/moderation/approve/{id}' => [ModerationController::class, 'approve', 'POST'],
    'api/moderation/reject/{id}' => [ModerationController::class, 'reject', 'POST'],

    // Leaderboard API
    'api/leaderboard' => [LeaderboardController::class, 'index', 'GET'],
    'api/seasons' => [LeaderboardController::class, 'seasons', 'GET'],
];

$matched = false;
$params = [];

foreach ($routes as $pattern => $handler) {
    $regex = '#^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $pattern) . '$#';
    if (preg_match($regex, $uri, $matches)) {
        if ($method !== $handler[2]) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        array_shift($matches);
        $params = $matches;
        $matched = true;

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

// Page routes (render HTML)
$pageRoutes = [
    '' => 'home.php',
    'home' => 'home.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'dashboard' => 'dashboard.php',
    'upload' => 'upload.php',
    'leaderboard' => 'leaderboard.php',
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
