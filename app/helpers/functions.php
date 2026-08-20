<?php

declare(strict_types=1);

use App\Config\Database;

/**
 * Get the current base URL dynamically from request
 * Works correctly in both development (localhost) and production (custom domain)
 */
function get_base_url(): string
{
    static $baseUrl = null;
    
    if ($baseUrl === null) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                    ($_SERVER['SERVER_PORT'] ?? 80) == 443
                    ? 'https' : 'http';
        
        $host = $_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost';
        
        $baseUrl = $protocol . '://' . $host;
    }
    
    return $baseUrl;
}

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
    header("Location: " . get_base_url() . "/" . ltrim($url, '/'));
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
    return get_base_url() . '/assets/' . ltrim($path, '/');
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

/**
 * Log activity to activity_log table
 * 
 * @param int $userId User who performed the action
 * @param string $action Action performed (e.g., 'create', 'update', 'delete', 'login')
 * @param string $entityType Type of entity (video, script, season, user, submission, system, auth, settings)
 * @param int|null $entityId ID of the entity (null for system actions)
 * @param string $description Human-readable description
 * @param array|null $metadata Additional context data
 * @return int|null Activity log ID, or null if logging failed
 */
function log_activity(
    int $userId,
    string $action,
    string $entityType,
    ?int $entityId,
    string $description,
    ?array $metadata = null
): ?int {
    try {
        $activityLog = new \App\Models\ActivityLog();
        
        if (!$activityLog->tableExists()) {
            return null;
        }
        
        return $activityLog->log(
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $metadata
        );
    } catch (\Throwable $e) {
        // Don't let activity logging break the app
        debug_log("Failed to log activity: " . $e->getMessage(), 'ACTIVITY_LOG');
        return null;
    }
}

/**
 * Format text with support for numbered lists and line breaks
 * Converts:
 * - "1. Item\n2. Item" to <ol><li>Item</li><li>Item</li></ol>
 * - "\n" to <br>
 * 
 * @param string $text Text to format
 * @return string HTML formatted text
 */
function format_text_content(string $text): string
{
    if (empty($text)) {
        return '';
    }
    
    // Split by double newlines to get paragraphs
    $paragraphs = explode("\n\n", $text);
    $output = [];
    
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if (empty($para)) continue;
        
        // Check if this paragraph is a numbered list
        $lines = explode("\n", $para);
        $isNumberedList = true;
        $listItems = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Match lines starting with "1." or "1)" or "1 -" etc
            if (preg_match('/^(\d+)[\.\)\-\:]\s+(.+)$/', $line, $matches)) {
                $listItems[] = $matches[2];
            } else {
                $isNumberedList = false;
                break;
            }
        }
        
        if ($isNumberedList && !empty($listItems)) {
            // Generate ordered list
            $output[] = '<ol style="list-style-type: decimal; padding-left: 1.5rem; margin: 0.5rem 0;">';
            foreach ($listItems as $item) {
                $output[] = '<li style="margin-bottom: 0.25rem;">' . htmlspecialchars($item) . '</li>';
            }
            $output[] = '</ol>';
        } else {
            // Regular paragraph - just convert newlines to <br>
            $output[] = '<p style="margin-bottom: 0.5rem;">' . nl2br(htmlspecialchars($para)) . '</p>';
        }
    }
    
    return implode('', $output);
}
