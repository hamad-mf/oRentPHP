<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/remember_token_helpers.php';

// Handle remember token cleanup before session destruction
if (isset($_COOKIE['remember_token'])) {
    // Extract selector from cookie value (format: selector:validator)
    $cookie_parts = explode(':', $_COOKIE['remember_token'], 2);
    
    if (count($cookie_parts) === 2) {
        $selector = $cookie_parts[0];
        
        // Delete token record from database
        delete_remember_token($selector);
        
        // Log token deletion event
        if (function_exists('app_log')) {
            app_log('AUTH', 'Remember token deleted during logout', [
                'selector' => $selector,
                'user_id' => $_SESSION['user']['id'] ?? 'unknown'
            ]);
        }
    }
    
    // Clear remember_token cookie
    clear_remember_cookie();
}

if (function_exists('app_log')) {
    app_log('ACTION', 'User logged out');
}

session_unset();
session_destroy();
header('Location: ../auth/login.php');
exit;
