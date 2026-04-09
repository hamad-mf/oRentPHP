<?php
//── Database Configuration ─────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'u230826074_orentin');
define('DB_USER', 'u230826074_orentin');
define('DB_PASS', 'Jazir@123gold');


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
            $pdo->exec("SET time_zone = '+05:30'");
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

// ── Logger (auto-logs page loads + errors) ─────────────────
require_once __DIR__ . '/../includes/logger.php';

// ── Remember Me Token Helpers ──────────────────────────────
require_once __DIR__ . '/../includes/remember_token_helpers.php';

// ── Validate Remember Token (immediately after session_start) ──
try {
    validate_remember_token();
} catch (Exception $e) {
    // Graceful error handling - log and continue without crashing
    error_log('Remember token validation error: ' . $e->getMessage());
}

// ── Log page load ──────────────────────────────────────────
_logger_log_page_load();

/**
 * Validate remember token and auto-login user if valid.
 * 
 * This function runs on every page load after session_start().
 * If no active session exists and a valid remember_token cookie is found,
 * it will automatically create a session for the user.
 * 
 * Security features:
 * - Skips validation if session already exists
 * - Verifies token validator using password_verify (timing-safe)
 * - Deletes expired tokens automatically
 * - On validator mismatch: deletes ALL user tokens (security event)
 * - Comprehensive logging for all outcomes
 * 
 * @return bool True if auto-login succeeded, false otherwise
 */
function validate_remember_token(): bool
{
    try {
        // Skip if session already exists
        if (isset($_SESSION['user'])) {
            return false;
        }
        
        // Check for remember_token cookie
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        // Parse cookie value into selector and validator
        $cookie_value = $_COOKIE['remember_token'];
        $parts = explode(':', $cookie_value, 2);
        
        if (count($parts) !== 2) {
            app_log('AUTH', 'Remember token validation failed: malformed cookie value', [
                'cookie_length' => strlen($cookie_value)
            ]);
            clear_remember_cookie();
            return false;
        }
        
        [$selector, $validator] = $parts;
        
        // Validate selector format (32 hex chars)
        if (!preg_match('/^[a-f0-9]{32}$/i', $selector)) {
            app_log('AUTH', 'Remember token validation failed: invalid selector format', [
                'selector' => $selector
            ]);
            clear_remember_cookie();
            return false;
        }
        
        // Validate validator format (64 hex chars)
        if (!preg_match('/^[a-f0-9]{64}$/i', $validator)) {
            app_log('AUTH', 'Remember token validation failed: invalid validator format');
            clear_remember_cookie();
            return false;
        }
        
        // Look up token by selector in database
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT user_id, validator_hash, expires_at 
            FROM remember_tokens 
            WHERE selector = ? 
            LIMIT 1
        ");
        $stmt->execute([$selector]);
        $token = $stmt->fetch();
        
        // Selector not found in database
        if (!$token) {
            app_log('AUTH', 'Remember token validation failed: selector not found', [
                'selector' => $selector
            ]);
            clear_remember_cookie();
            return false;
        }
        
        // Check token expiry timestamp
        if (strtotime($token['expires_at']) < time()) {
            app_log('AUTH', 'Remember token validation failed: token expired', [
                'user_id' => $token['user_id'],
                'selector' => $selector,
                'expires_at' => $token['expires_at']
            ]);
            delete_remember_token($selector);
            clear_remember_cookie();
            return false;
        }
        
        // Verify validator using password_verify() against stored hash
        if (!password_verify($validator, $token['validator_hash'])) {
            // SECURITY EVENT: Validator mismatch - delete ALL user tokens
            app_log('AUTH', 'SECURITY WARNING: Remember token validator mismatch - deleting all user tokens', [
                'user_id' => $token['user_id'],
                'selector' => $selector
            ]);
            delete_all_user_tokens($token['user_id']);
            clear_remember_cookie();
            return false;
        }
        
        // Token is valid - create session with user data and permissions
        $user_id = $token['user_id'];
        
        // Load user from database
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            app_log('AUTH', 'Remember token validation failed: user not found or inactive', [
                'user_id' => $user_id,
                'selector' => $selector
            ]);
            delete_remember_token($selector);
            clear_remember_cookie();
            return false;
        }
        
        // Load permissions
        $permStmt = $pdo->prepare("SELECT permission FROM staff_permissions WHERE user_id = ?");
        $permStmt->execute([$user_id]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Admin gets all permissions
        if ($user['role'] === 'admin') {
            $permissions = [
                'add_vehicles',
                'view_all_vehicles',
                'view_vehicle_availability',
                'view_vehicle_requests',
                'add_reservations',
                'add_leads',
                'do_delivery',
                'do_return',
                'view_finances',
                'manage_clients',
                'manage_staff'
            ];
        }
        
        // Create session with same structure as manual login
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'staff_id' => $user['staff_id'],
            'permissions' => $permissions,
        ];
        
        // Set remember_me_login flag
        $_SESSION['remember_me_login'] = true;
        
        // Log successful auto-login
        app_log('AUTH', 'Auto-login successful via remember token', [
            'user_id' => $user_id,
            'username' => $user['username'],
            'selector' => $selector
        ]);
        
        return true;
        
    } catch (Exception $e) {
        // Graceful error handling - log error and continue without crashing
        app_log('ERROR', 'Remember token validation error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
        
        // Clear cookie on error to prevent repeated failures
        clear_remember_cookie();
        
        return false;
    }
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
    // ceil handles partial last day (e.g. 21 Mar 09:00 → 4 Apr 10:00 = 15 days)
    return max(1, (int) ceil((strtotime($end) - strtotime($start)) / 86400));
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
        $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
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
        $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
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

/**
 * Get permission dependencies.
 * Returns an array where key = permission, value = array of required permissions.
 * Example: if 'add_vehicles' requires 'view_all_vehicles', this function returns:
 *   ['add_vehicles' => ['view_all_vehicles'], ...]
 */
function get_permission_dependencies(): array
{
    return [
        'add_vehicles' => ['view_all_vehicles'],  // Can't add vehicles without seeing full list
    ];
}

/**
 * Sync/validate permissions based on dependency rules.
 * If a permission is enabled, its dependencies are automatically enabled.
 * If a permission is disabled, any permissions depending on it are disabled.
 * 
 * @param array $permissions - List of permission keys
 * @return array - Validated and synced permissions
 */
function validate_and_sync_permissions(array $permissions): array
{
    $dependencies = get_permission_dependencies();
    $perms = array_flip($permissions);  // Better for isset() checks
    
    // First pass: If a permission is enabled, enable its dependencies
    foreach ($dependencies as $perm => $deps) {
        if (isset($perms[$perm])) {
            foreach ($deps as $dep) {
                $perms[$dep] = 1;  // Enable dependency
            }
        }
    }
    
    // Second pass: If a dependency is disabled, disable permissions that depend on it
    foreach ($dependencies as $perm => $deps) {
        foreach ($deps as $dep) {
            if (!isset($perms[$dep])) {  // Dependency disabled
                unset($perms[$perm]);     // Disable dependent permission
            }
        }
    }
    
    return array_keys($perms);
}

/**
 * Get which permissions depend on a given permission.
 * Example: if 'add_vehicles' depends on 'view_all_vehicles',
 * this returns ['add_vehicles'] when called with 'view_all_vehicles'.
 */
function get_dependents(string $permission): array
{
    $dependencies = get_permission_dependencies();
    $dependents = [];
    
    foreach ($dependencies as $perm => $deps) {
        if (in_array($permission, $deps, true)) {
            $dependents[] = $perm;
        }
    }
    
    return $dependents;
}
