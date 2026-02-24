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
