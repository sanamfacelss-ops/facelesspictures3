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
        $stmt = $pdo->prepare("SELECT id, name, email, role, is_admin FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
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
