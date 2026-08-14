<?php

/**
 * Caffe BMS — Application Bootstrap
 *
 * require_once this at the top of every entry point in public/ before any
 * other logic runs. Responsibilities:
 *   - Load environment configuration from .env
 *   - Configure and start the session securely
 *   - Open the shared PDO database connection (lazy, via db())
 *   - Define app-wide path/role constants
 *   - Provide auth, CSRF, and role-guard helper functions
 *
 * This file must never echo output or assume a specific request route.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Path constants
// ---------------------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__, 2));
define('PRIVATE_PATH', APP_ROOT . '/private');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('CONTROLLERS_PATH', PRIVATE_PATH . '/controllers');
define('VIEWS_PATH', PRIVATE_PATH . '/views');

// ---------------------------------------------------------------------------
// Role constants — must match the `roles.name` seed rows in deployment/caffe.sql
// ---------------------------------------------------------------------------
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_CASHIER', 'cashier');
define('ROLE_BARISTA', 'barista');

// ---------------------------------------------------------------------------
// Environment loading
// php-fpm is configured with `variables_order = "EGPCS"` (see Dockerfile), so
// .env values are already in $_ENV when running under Docker. This loader is
// the fallback for running php-fpm/php-cli directly on a host without that.
// ---------------------------------------------------------------------------
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

$envPath = APP_ROOT . '/.env';
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && env($key) === null) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$appEnv = env('APP_ENV', 'production');
define('APP_ENV', $appEnv);
define('IS_LOCAL', APP_ENV === 'local');

// ---------------------------------------------------------------------------
// Error handling — verbose locally, logged-only in production
// ---------------------------------------------------------------------------
ini_set('display_errors', IS_LOCAL ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Secure session configuration — must be set before session_start()
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name(env('SESSION_NAME', 'caffe_bms_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => env('SESSION_DOMAIN', ''),
        'secure' => !IS_LOCAL,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Rotate the session ID periodically to limit fixation risk, without
// discarding session data.
if (empty($_SESSION['_last_regen']) || (time() - $_SESSION['_last_regen']) > 900) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ---------------------------------------------------------------------------
// Baseline security headers (CSP is left to be tightened once asset sources
// are finalized — see README security section)
// ---------------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---------------------------------------------------------------------------
// Database connection — shared PDO singleton, lazily opened on first use
// ---------------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', 'db');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'caffe');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('[caffe-bms] DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable.');
    }

    return $pdo;
}

// ---------------------------------------------------------------------------
// CSRF token helpers — call csrf_token() to render a hidden field value,
// csrf_verify($_POST['csrf_token'] ?? null) before processing any mutation.
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------------------------------------------------------------------------
// Auth helpers
// Session shape once logged in: $_SESSION['user'] = [
//     'id' => int, 'full_name' => string, 'email' => string,
//     'role' => string, 'cafe_id' => int|null,
// ]
// ---------------------------------------------------------------------------
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Guard a page to one or more roles. Redirects unauthenticated users to
 * login, and hard-403s authenticated users with the wrong role.
 */
function require_role(string ...$allowedRoles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

/**
 * Returns the dashboard URL a role should land on after login.
 */
function dashboard_for_role(string $role): string
{
    return match ($role) {
        ROLE_ADMIN, ROLE_MANAGER => '/manager_dashboard.php',
        ROLE_CASHIER => '/cashier_dashboard.php',
        ROLE_BARISTA => '/Barista_dashboard.php',
        default => '/login.php',
    };
}
