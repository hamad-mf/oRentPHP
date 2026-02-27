<?php
// ── Database Configuration ─────────────────────────────────
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'u230826074_orentin');
// define('DB_USER', 'u230826074_orentin');
// define('DB_PASS', 'Jazir@123gold');
define('DB_HOST', 'localhost');
define('DB_NAME', 'orent');
define('DB_USER', 'root');   // Change to your MySQL username
define('DB_PASS', '');       // Change to your MySQL password

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;padding:2rem;background:#1f1f1f;color:#f87171;border:1px solid #f87171;margin:2rem;border-radius:8px">
                <strong>Database Error:</strong><br>' . htmlspecialchars($e->getMessage()) . '
                <br><br>Please check your <code>config/db.php</code> credentials.
            </div>');
        }
    }
    return $pdo;
}

// ── Session Helper ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string
{
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// ── Helpers ─────────────────────────────────────────────────
function e(mixed $val): string
{
    return htmlspecialchars((string) ($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function old(string $key, mixed $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

function starDisplay(?int $rating): string
{
    if (!$rating)
        return '';
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function durationDays(string $start, string $end): int
{
    return max(0, (int) ceil((strtotime($end) - strtotime($start)) / 86400));
}

function isOverdue(string $endDate, string $status): bool
{
    return $status === 'active' && strtotime($endDate) < strtotime(date('Y-m-d'));
}

// ── Auth Helpers ─────────────────────────────────────────────
/**
 * Returns the current logged-in user array or null.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Redirect to login if not authenticated.
 */
function auth_check(): void
{
    if (!isset($_SESSION['user'])) {
        // Determine depth to build correct path to auth/login.php
        $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 1);
        $prefix = str_repeat('../', $depth);
        header('Location: ' . $prefix . 'auth/login.php');
        exit;
    }
}

/**
 * Redirect non-admins to dashboard.
 */
function auth_require_admin(): void
{
    auth_check();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 1);
        $prefix = str_repeat('../', $depth);
        header('Location: ' . $prefix . 'index.php');
        exit;
    }
}

/**
 * Check if the current user has a given permission.
 * Admin always returns true.
 */
function auth_has_perm(string $perm): bool
{
    $user = $_SESSION['user'] ?? null;
    if (!$user)
        return false;
    if ($user['role'] === 'admin')
        return true;
    return in_array($perm, $user['permissions'] ?? [], true);
}
