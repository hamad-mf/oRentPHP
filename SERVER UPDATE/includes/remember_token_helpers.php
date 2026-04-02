<?php
/**
 * includes/remember_token_helpers.php — Remember Me Token Management
 * 
 * Provides secure token generation, validation, and cleanup for persistent authentication.
 * Uses the selector/validator pattern for security:
 * - Selector: 16 bytes (32 hex chars) - stored plaintext for lookup
 * - Validator: 32 bytes (64 hex chars) - hashed before storage
 * 
 * Usage:
 *   $token = generate_remember_token($user_id);
 *   setcookie('remember_token', $token['cookie_value'], ...);
 *   
 *   // Later, on page load:
 *   validate_remember_token(); // Auto-creates session if valid
 */

/**
 * Generate a secure remember token for a user.
 * 
 * Creates a cryptographically secure token with selector and validator parts.
 * Enforces a maximum of 5 active tokens per user (deletes oldest if limit reached).
 * 
 * @param int $user_id The user ID to generate the token for
 * @return array Token data with keys: selector, validator, cookie_value, expires_at
 * @throws Exception If random_bytes() fails or database operations fail
 */
function generate_remember_token(int $user_id): array
{
    try {
        $pdo = db();
        
        // Check token limit and delete oldest if at limit
        $count = count_user_tokens($user_id);
        if ($count >= 5) {
            app_log('AUTH', "Token limit reached for user {$user_id}, deleting oldest token", [
                'current_count' => $count,
                'limit' => 5
            ]);
            delete_oldest_user_token($user_id);
        }
        
        // Generate cryptographically secure random bytes
        $selector = bin2hex(random_bytes(16));   // 32 hex chars
        $validator = bin2hex(random_bytes(32));  // 64 hex chars
        
        // Hash the validator for secure storage
        $validator_hash = password_hash($validator, PASSWORD_DEFAULT);
        
        // Calculate expiry (30 days from now)
        $expires_at = date('Y-m-d H:i:s', time() + (30 * 86400));
        
        // Store in database
        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $selector, $validator_hash, $expires_at]);
        
        // Log token creation
        app_log('AUTH', "Remember token created for user {$user_id}", [
            'selector' => $selector,
            'expires_at' => $expires_at
        ]);
        
        return [
            'selector' => $selector,
            'validator' => $validator,
            'cookie_value' => "{$selector}:{$validator}",
            'expires_at' => $expires_at
        ];
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to generate remember token', [
            'user_id' => $user_id,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
        throw $e;
    }
}

/**
 * Delete a specific remember token by its selector.
 * 
 * @param string $selector The token selector (32 hex chars)
 * @return void
 */
function delete_remember_token(string $selector): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->execute([$selector]);
        
        app_log('AUTH', "Remember token deleted", ['selector' => $selector]);
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to delete remember token', [
            'selector' => $selector,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
    }
}

/**
 * Delete all remember tokens for a specific user.
 * 
 * Used when password changes or potential security compromise detected.
 * 
 * @param int $user_id The user ID
 * @return void
 */
function delete_all_user_tokens(int $user_id): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $count = $stmt->rowCount();
        app_log('AUTH', "All remember tokens deleted for user {$user_id}", [
            'count' => $count
        ]);
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to delete all user tokens', [
            'user_id' => $user_id,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
    }
}

/**
 * Count active (non-expired) remember tokens for a user.
 * 
 * @param int $user_id The user ID
 * @return int Number of active tokens
 */
function count_user_tokens(int $user_id): int
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM remember_tokens 
            WHERE user_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$user_id]);
        
        return (int) $stmt->fetchColumn();
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to count user tokens', [
            'user_id' => $user_id,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
        return 0;
    }
}

/**
 * Delete the oldest remember token for a user.
 * 
 * Used when token limit is reached (5 tokens per user).
 * 
 * @param int $user_id The user ID
 * @return void
 */
function delete_oldest_user_token(int $user_id): void
{
    try {
        $pdo = db();
        
        // Find the oldest token
        $stmt = $pdo->prepare("
            SELECT selector 
            FROM remember_tokens 
            WHERE user_id = ? 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $selector = $stmt->fetchColumn();
        
        if ($selector) {
            delete_remember_token($selector);
            app_log('AUTH', "Oldest remember token deleted for user {$user_id}", [
                'selector' => $selector
            ]);
        }
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to delete oldest user token', [
            'user_id' => $user_id,
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
    }
}

/**
 * Clear the remember_token cookie.
 * 
 * Sets the cookie expiry to a past date to ensure browser deletion.
 * 
 * @return void
 */
function clear_remember_cookie(): void
{
    try {
        // Detect if HTTPS is being used
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        
        setcookie('remember_token', '', [
            'expires' => time() - 3600,  // Past date
            'path' => '/',
            'httponly' => true,
            'secure' => $is_https,
            'samesite' => 'Lax'
        ]);
        
        // Also unset from current request
        unset($_COOKIE['remember_token']);
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to clear remember cookie', [
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
    }
}

/**
 * Cleanup all expired remember tokens from the database.
 * 
 * Should be called periodically via cron job or scheduled task.
 * Deletes tokens in a single batch query for efficiency.
 * 
 * @return int Number of tokens deleted
 */
function cleanup_expired_tokens(): int
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        
        $count = $stmt->rowCount();
        
        if ($count > 0) {
            app_log('AUTH', "Expired remember tokens cleaned up", ['count' => $count]);
        }
        
        return $count;
        
    } catch (Exception $e) {
        app_log('ERROR', 'Failed to cleanup expired tokens', [
            'error' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine()
        ]);
        return 0;
    }
}
