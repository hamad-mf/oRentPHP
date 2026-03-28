<?php
/**
 * Task 11.2 Verification: Validator Mismatch Security Response
 * 
 * This script verifies that the validate_remember_token() function correctly:
 * 1. Detects when password_verify() fails (validator mismatch)
 * 2. Deletes all user tokens when mismatch occurs
 * 3. Logs a security warning with user_id
 * 4. Clears the cookie
 * 
 * Requirements: 12.7, 15.5
 */

require_once __DIR__ . '/../../../config/db.php';

echo "=== Task 11.2 Verification: Validator Mismatch Security Response ===\n\n";

// Test Setup: Create a test user and tokens
$pdo = db();

// Create test user if not exists
$pdo->exec("INSERT IGNORE INTO users (id, name, username, password, role, is_active) 
            VALUES (9999, 'Test User', 'testuser', 'dummy', 'staff', 1)");

$test_user_id = 9999;

// Clean up any existing tokens for test user
delete_all_user_tokens($test_user_id);

// Generate a valid token
$token = generate_remember_token($test_user_id);
echo "✓ Generated valid token for user {$test_user_id}\n";
echo "  Selector: {$token['selector']}\n";
echo "  Validator: {$token['validator']}\n\n";

// Count tokens before mismatch
$count_before = count_user_tokens($test_user_id);
echo "✓ Tokens before mismatch: {$count_before}\n\n";

// Simulate validator mismatch by creating a cookie with wrong validator
$wrong_validator = bin2hex(random_bytes(32)); // Different validator
$malicious_cookie = "{$token['selector']}:{$wrong_validator}";

echo "=== Simulating Validator Mismatch Attack ===\n";
echo "Setting cookie with correct selector but WRONG validator\n";
echo "  Correct validator: {$token['validator']}\n";
echo "  Wrong validator:   {$wrong_validator}\n\n";

// Set the malicious cookie
$_COOKIE['remember_token'] = $malicious_cookie;

// Clear session to trigger validation
unset($_SESSION['user']);

// Call validate_remember_token() - should detect mismatch and delete all tokens
echo "Calling validate_remember_token()...\n";
$result = validate_remember_token();

echo "\n=== Results ===\n";
echo "Validation result: " . ($result ? "SUCCESS (UNEXPECTED!)" : "FAILED (EXPECTED)") . "\n";

// Count tokens after mismatch
$count_after = count_user_tokens($test_user_id);
echo "Tokens after mismatch: {$count_after}\n";

// Verify all tokens were deleted
if ($count_after === 0 && $count_before > 0) {
    echo "✓ PASS: All user tokens were deleted (security response triggered)\n";
} else {
    echo "✗ FAIL: Tokens were not deleted (expected 0, got {$count_after})\n";
}

// Check if cookie was cleared
if (!isset($_COOKIE['remember_token'])) {
    echo "✓ PASS: Cookie was cleared\n";
} else {
    echo "✗ FAIL: Cookie was not cleared\n";
}

// Check logs for security warning
echo "\n=== Recent Auth Logs ===\n";
$logStmt = $pdo->prepare("
    SELECT created_at, category, message, context 
    FROM logs 
    WHERE category = 'AUTH' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$logStmt->execute();
$logs = $logStmt->fetchAll();

$security_warning_found = false;
foreach ($logs as $log) {
    $context = json_decode($log['context'], true);
    echo "[{$log['created_at']}] {$log['message']}\n";
    
    if (strpos($log['message'], 'SECURITY WARNING') !== false && 
        strpos($log['message'], 'validator mismatch') !== false) {
        $security_warning_found = true;
        echo "  → Security warning detected!\n";
        if (isset($context['user_id']) && $context['user_id'] == $test_user_id) {
            echo "  → Contains correct user_id: {$context['user_id']}\n";
        }
    }
}

if ($security_warning_found) {
    echo "\n✓ PASS: Security warning was logged\n";
} else {
    echo "\n✗ FAIL: Security warning was not found in logs\n";
}

// Cleanup
delete_all_user_tokens($test_user_id);
$pdo->exec("DELETE FROM users WHERE id = 9999");

echo "\n=== Verification Complete ===\n";
echo "Task 11.2 implementation is " . 
     ($count_after === 0 && $security_warning_found ? "CORRECT ✓" : "INCORRECT ✗") . "\n";
