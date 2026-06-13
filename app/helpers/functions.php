<?php

declare(strict_types=1);

use App\Config\Database;

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf(string $token): bool
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function redirect(string $url): void
{
    header("Location: " . APP_URL . "/" . ltrim($url, '/'));
    exit;
}

function auth_user(): ?array
{
    if (!empty($_SESSION['user_id'])) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, role, content_categories, is_admin FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            // Parse content_categories JSON
            if (!empty($user['content_categories'])) {
                $decoded = json_decode($user['content_categories'], true);
                $user['content_categories'] = is_array($decoded) && !empty($decoded) ? $decoded : [$user['role']];
            } else {
                $user['content_categories'] = [$user['role']];
            }
            return $user;
        }
        return null;
    }
    return null;
}

function is_authenticated(): bool
{
    return auth_user() !== null;
}

function is_admin(): bool
{
    $user = auth_user();
    return $user && !empty($user['is_admin']);
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function asset(string $path): string
{
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function log_message(string $level, string $message): void
{
    $file = LOG_PATH . '/' . date('Y-m-d') . '.log';
    $line = sprintf("[%s] [%s] %s" . PHP_EOL, date('Y-m-d H:i:s'), strtoupper($level), $message);
    error_log($line, 3, $file);
}

/**
 * Debug log - like WP_DEBUG_LOG
 * Only logs when FP3_DEBUG is true
 */
function debug_log($data, string $label = ''): void
{
    if (!defined('FP3_DEBUG') || !FP3_DEBUG) {
        return;
    }
    
    $file = LOG_PATH . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    
    if (is_array($data) || is_object($data)) {
        $output = print_r($data, true);
    } else {
        $output = (string) $data;
    }
    
    $label = $label ? "[$label] " : '';
    $line = "[$timestamp] {$label}{$output}" . PHP_EOL;
    
    error_log($line, 3, $file);
}

/**
 * Log exception with full stack trace
 */
function log_exception(\Throwable $e, string $context = ''): void
{
    $file = LOG_PATH . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    
    $line = "[$timestamp] ";
    if ($context) $line .= "[$context] ";
    $line .= get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    $line .= "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    $line .= "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
    $line .= str_repeat('-', 80) . PHP_EOL;
    
    error_log($line, 3, $file);
    
    // Also log to debug if enabled
    if (defined('FP3_DEBUG') && FP3_DEBUG) {
        debug_log($e->getMessage(), $context ?: 'EXCEPTION');
    }
}
