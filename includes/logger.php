<?php
/**
 * includes/logger.php — Application-wide file logger
 * Writes daily log files to logs/YYYY-MM-DD.log
 *
 * Usage:
 *   app_log('ACTION', 'Created client: John Doe (ID: 42)');
 *   app_log('ERROR',  'Failed to save reservation', ['exception' => $e->getMessage()]);
 *
 * Levels: PAGE, ACTION, ERROR
 * Automatically included via config/db.php — no manual includes needed.
 */

// Global app timezone baseline for all date()/time()/strtotime() usage.
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Kolkata');
}
if (date_default_timezone_get() !== APP_TIMEZONE) {
    date_default_timezone_set(APP_TIMEZONE);
}

function app_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
}

function app_now_sql(): string
{
    return app_now()->format('Y-m-d H:i:s');
}

function app_today_sql(): string
{
    return app_now()->format('Y-m-d');
}

/**
 * Write a log entry to the daily log file.
 *
 * @param string $level   PAGE | ACTION | ERROR
 * @param string $message Human-readable description
 * @param array  $context Optional extra data
 */
function app_log(string $level, string $message, array $context = []): void
{
    try {
        $now = app_now();
        $timestamp = $now->format('Y-m-d H:i:s');
        $date = $now->format('Y-m-d');

        // Ensure logs/ directory exists
        $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Who is logged in?
        $user = '-';
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
            $u = $_SESSION['user'];
            $user = ($u['username'] ?? 'unknown') . '/' . ($u['role'] ?? '?');
        }

        // Request info
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? '(cli)';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Build log line
        $level = str_pad($level, 6);
        $line = "[$timestamp] [$level] [$user] [$ip] $method $uri | $message";

        // Append context if provided
        if (!empty($context)) {
            $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $logFile = $logDir . DIRECTORY_SEPARATOR . $date . '.log';
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

    } catch (Throwable $e) {
        // Logger itself should never crash the app
    }
}

/**
 * Auto-log page loads. Called once from db.php after session starts.
 */
function _logger_log_page_load(): void
{
    // Skip AJAX/API requests to reduce noise (optional)
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Log every page load
    app_log('PAGE', 'Page loaded');
}

/**
 * Global error handler — logs PHP errors.
 */
function _logger_error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Don't log suppressed errors (@operator)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $levelMap = [
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_WARNING => 'WARNING',
        E_USER_NOTICE => 'NOTICE',
        E_USER_ERROR => 'ERROR',
        E_DEPRECATED => 'NOTICE',
        E_USER_DEPRECATED => 'NOTICE',
    ];
    $level = $levelMap[$errno] ?? 'ERROR';

    app_log('ERROR', "$errstr in $errfile:$errline", ['errno' => $errno, 'level' => $level]);

    // Return false so PHP's default handler still runs
    return false;
}

/**
 * Global exception handler — logs uncaught exceptions.
 */
function _logger_exception_handler(Throwable $e): void
{
    app_log('ERROR', 'Uncaught ' . get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5),
    ]);

    // Re-throw so PHP shows the error page
    // (can't re-throw from exception handler, so output it)
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo '<div style="font-family:monospace;padding:2rem;background:#1f1f1f;color:#f87171;border:1px solid #f87171;margin:2rem;border-radius:8px">
            <strong>Server Error</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Shutdown handler — catches fatal errors.
 */
function _logger_shutdown_handler(): void
{
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_log('ERROR', 'FATAL: ' . $error['message'], [
            'file' => $error['file'] . ':' . $error['line'],
            'type' => $error['type'],
        ]);
    }
}

// Register handlers
set_error_handler('_logger_error_handler');
set_exception_handler('_logger_exception_handler');
register_shutdown_function('_logger_shutdown_handler');
