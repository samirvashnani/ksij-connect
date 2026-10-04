<?php
require_once __DIR__ . '/session.php';
$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Create config.php from config.example.php.');
}
require_once $configPath;

function appUrl(string $path = ''): string
{
    return rtrim(defined('APP_BASE_URL') ? APP_BASE_URL : '/ksij-connect', '/') . '/' . ltrim($path, '/');
}

function redirectTo(string $path): void
{
    header('Location: ' . appUrl($path), true, 303);
    exit;
}

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Your session changed. Reload the page and try again.');
    }
}

function csrfField(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . escapeHtml(csrfToken()) . '">';
}

function resetLoginSession(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function allowLoginAttempt(string $key, int $maximum = 10, int $seconds = 300): bool
{
    $attempts = $_SESSION['login_attempts'][$key] ?? ['start' => time(), 'count' => 0];
    if (time() - $attempts['start'] >= $seconds) {
        $attempts = ['start' => time(), 'count' => 0];
    }
    $attempts['count']++;
    $_SESSION['login_attempts'][$key] = $attempts;
    return $attempts['count'] <= $maximum;
}
