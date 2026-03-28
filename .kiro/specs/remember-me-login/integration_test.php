<?php
/**
 * Integration Test for Remember Me Login Feature
 * 
 * This test verifies the complete remember-me-login functionality including:
 * - Token generation and storage
 * - Token validation and auto-login
 * - Multi-device support
 * - Token limit enforcement
 * - Logout cleanup
 * - Security features
 * 
 * Run from project root: php .kiro/specs/remember-me-login/integration_test.php
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/remember_token_helpers.php';

// Test configuration
$test_user_id = 1; // Assumes user with ID 1 exists
$tests_passed = 0;
$tests_failed = 0;

echo "\n=== Remember Me Login - Integration Test ===\n\n";

/**
 * Helper function to assert conditions
 */
function test_assert($condition, $test_name, &$passed, &$failed) {
    if ($condition) {
        echo "✓ PASS: $test_name\n";
        $passed++;
        return true;
    } else {
        echo "✗ FAIL: $test_name\n";
        $failed++;
        return false;
    }
}

/**
 * Helper function to clean up test data
 */
function cleanup_test_tokens($user_id) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        echo "Warning: Failed to cleanup test tokens: " . $e->getMessage() . "\n";
    }
}

// ============================================================================
// Test 1: Database Table Exists
// ============================================================================
echo "Test Group 1: Database Schema\n";
echo "------------------------------\n";

try {
    $pdo = db();
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    $table_exists = $stmt->rowCount() > 0;
    test_assert($table_exists, "remember_tokens table exists", $tests_passed, $tests_failed);
    
    if ($table_exists) {
        // Check table structure
        $stmt = $pdo->query("DESCRIBE remember_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $required_columns = ['id', 'user_id', 'selector', 'validator_hash', 'expires_at', 'created_at'];
        $has_all_columns = count(array_intersect($required_columns, $columns)) === count($required_columns);
        test_assert($has_all_columns, "Table has all required columns", $tests_passed, $tests_failed);
        
        // Check indexes
        $stmt = $pdo->query("SHOW INDEX FROM remember_tokens");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $index_names = array_column($indexes, 'Key_name');
        
        test_assert(in_array('uq_selector', $index_names), "Unique index on selector exists", $tests_passed, $tests_failed);
        test_assert(in_array('idx_expires_at', $index_names), "Index on expires_at exists", $tests_passed, $tests_failed);
        test_assert(in_array('idx_user_id', $index_names), "Index on user_id exists", $tests_passed, $tests_failed);
    }
} catch (Exception $e) {
    echo "✗ FAIL: Database schema check - " . $e->getMessage() . "\n";
    $tests_failed += 4;
}

echo "\n";

// ============================================================================
// Test 2: Token Generation
// ============================================================================
echo "Test Group 2: Token Generation\n";
echo "------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    $token = generate_remember_token($test_user_id);
    
    test_assert(isset($token['selector']), "Token has selector", $tests_passed, $tests_failed);
    test_assert(isset($token['validator']), "Token has validator", $tests_passed, $tests_failed);
    test_assert(isset($token['cookie_value']), "Token has cookie_value", $tests_passed, $tests_failed);
    test_assert(isset($token['expires_at']), "Token has expires_at", $tests_passed, $tests_failed);
    
    // Check selector length (32 hex chars = 16 bytes)
    test_assert(strlen($token['selector']) === 32, "Selector is 32 characters", $tests_passed, $tests_failed);
    test_assert(ctype_xdigit($token['selector']), "Selector is hexadecimal", $tests_passed, $tests_failed);
    
    // Check validator length (64 hex chars = 32 bytes)
    test_assert(strlen($token['validator']) === 64, "Validator is 64 characters", $tests_passed, $tests_failed);
    test_assert(ctype_xdigit($token['validator']), "Validator is hexadecimal", $tests_passed, $tests_failed);
    
    // Check cookie value format
    $cookie_parts = explode(':', $token['cookie_value']);
    test_assert(count($cookie_parts) === 2, "Cookie value has selector:validator format", $tests_passed, $tests_failed);
    test_assert($cookie_parts[0] === $token['selector'], "Cookie contains correct selector", $tests_passed, $tests_failed);
    test_assert($cookie_parts[1] === $token['validator'], "Cookie contains correct validator", $tests_passed, $tests_failed);
    
    // Check expiry is 30 days from now (allow 1 minute tolerance)
    $expected_expiry = time() + (30 * 86400);
    $actual_expiry = strtotime($token['expires_at']);
    $expiry_diff = abs($expected_expiry - $actual_expiry);
    test_assert($expiry_diff < 60, "Token expires in 30 days", $tests_passed, $tests_failed);
    
    // Check token is stored in database
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$token['selector']]);
    $db_token = $stmt->fetch();
    
    test_assert($db_token !== false, "Token is stored in database", $tests_passed, $tests_failed);
    test_assert($db_token['user_id'] == $test_user_id, "Token has correct user_id", $tests_passed, $tests_failed);
    
    // Check validator is hashed
    test_assert(password_verify($token['validator'], $db_token['validator_hash']), "Validator is properly hashed", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Token generation - " . $e->getMessage() . "\n";
    $tests_failed += 15;
}

echo "\n";

// ============================================================================
// Test 3: Token Validation and Auto-Login
// ============================================================================
echo "Test Group 3: Token Validation\n";
echo "------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    // Generate a token
    $token = generate_remember_token($test_user_id);
    
    // Simulate cookie
    $_COOKIE['remember_token'] = $token['cookie_value'];
    
    // Clear session to simulate logged-out state
    unset($_SESSION['user']);
    
    // Validate token
    $result = validate_remember_token();
    
    test_assert($result === true, "Token validation returns true", $tests_passed, $tests_failed);
    test_assert(isset($_SESSION['user']), "Session is created", $tests_passed, $tests_failed);
    test_assert($_SESSION['user']['id'] == $test_user_id, "Session has correct user_id", $tests_passed, $tests_failed);
    test_assert(isset($_SESSION['remember_me_login']), "remember_me_login flag is set", $tests_passed, $tests_failed);
    test_assert($_SESSION['remember_me_login'] === true, "remember_me_login flag is true", $tests_passed, $tests_failed);
    
    // Test that validation skips when session exists
    $result2 = validate_remember_token();
    test_assert($result2 === false, "Validation skips when session exists", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Token validation - " . $e->getMessage() . "\n";
    $tests_failed += 6;
}

echo "\n";

// ============================================================================
// Test 4: Invalid Token Handling
// ============================================================================
echo "Test Group 4: Invalid Token Handling\n";
echo "-------------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    // Test malformed cookie
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = 'invalid_format';
    $result = validate_remember_token();
    test_assert($result === false, "Malformed cookie returns false", $tests_passed, $tests_failed);
    
    // Test non-existent selector
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = str_repeat('a', 32) . ':' . str_repeat('b', 64);
    $result = validate_remember_token();
    test_assert($result === false, "Non-existent selector returns false", $tests_passed, $tests_failed);
    
    // Test expired token
    $token = generate_remember_token($test_user_id);
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE remember_tokens SET expires_at = ? WHERE selector = ?");
    $stmt->execute([date('Y-m-d H:i:s', time() - 3600), $token['selector']]);
    
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = $token['cookie_value'];
    $result = validate_remember_token();
    test_assert($result === false, "Expired token returns false", $tests_passed, $tests_failed);
    
    // Check that expired token was deleted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$token['selector']]);
    $count = $stmt->fetchColumn();
    test_assert($count == 0, "Expired token is deleted from database", $tests_passed, $tests_failed);
    
    // Test wrong validator
    cleanup_test_tokens($test_user_id);
    $token = generate_remember_token($test_user_id);
    $wrong_validator = str_repeat('f', 64);
    
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = $token['selector'] . ':' . $wrong_validator;
    $result = validate_remember_token();
    test_assert($result === false, "Wrong validator returns false", $tests_passed, $tests_failed);
    
    // Check that all user tokens were deleted (security response)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?");
    $stmt->execute([$test_user_id]);
    $count = $stmt->fetchColumn();
    test_assert($count == 0, "All user tokens deleted on validator mismatch", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Invalid token handling - " . $e->getMessage() . "\n";
    $tests_failed += 6;
}

echo "\n";

// ============================================================================
// Test 5: Multi-Device Support
// ============================================================================
echo "Test Group 5: Multi-Device Support\n";
echo "-----------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    // Generate multiple tokens (simulating different devices)
    $token1 = generate_remember_token($test_user_id);
    $token2 = generate_remember_token($test_user_id);
    $token3 = generate_remember_token($test_user_id);
    
    // Check that all tokens exist
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?");
    $stmt->execute([$test_user_id]);
    $count = $stmt->fetchColumn();
    test_assert($count == 3, "Multiple tokens can exist for same user", $tests_passed, $tests_failed);
    
    // Verify each token works independently
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = $token1['cookie_value'];
    $result1 = validate_remember_token();
    test_assert($result1 === true, "First token validates successfully", $tests_passed, $tests_failed);
    
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = $token2['cookie_value'];
    $result2 = validate_remember_token();
    test_assert($result2 === true, "Second token validates successfully", $tests_passed, $tests_failed);
    
    unset($_SESSION['user']);
    $_COOKIE['remember_token'] = $token3['cookie_value'];
    $result3 = validate_remember_token();
    test_assert($result3 === true, "Third token validates successfully", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Multi-device support - " . $e->getMessage() . "\n";
    $tests_failed += 4;
}

echo "\n";

// ============================================================================
// Test 6: Token Limit Enforcement
// ============================================================================
echo "Test Group 6: Token Limit Enforcement\n";
echo "--------------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    // Generate 5 tokens (the limit)
    $tokens = [];
    for ($i = 0; $i < 5; $i++) {
        $tokens[] = generate_remember_token($test_user_id);
    }
    
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?");
    $stmt->execute([$test_user_id]);
    $count = $stmt->fetchColumn();
    test_assert($count == 5, "Can create 5 tokens (the limit)", $tests_passed, $tests_failed);
    
    // Generate 6th token - should delete one of the old tokens
    $token6 = generate_remember_token($test_user_id);
    
    $stmt->execute([$test_user_id]);
    $count = $stmt->fetchColumn();
    test_assert($count == 5, "Token count stays at 5 after creating 6th", $tests_passed, $tests_failed);
    
    // Check that newest token exists
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE selector = ?");
    $stmt2->execute([$token6['selector']]);
    $newest_exists = $stmt2->fetchColumn() > 0;
    test_assert($newest_exists, "Newest token is created successfully", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Token limit enforcement - " . $e->getMessage() . "\n";
    $tests_failed += 4;
}

echo "\n";

// ============================================================================
// Test 7: Logout Cleanup
// ============================================================================
echo "Test Group 7: Logout Cleanup\n";
echo "----------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    // Generate a token
    $token = generate_remember_token($test_user_id);
    
    // Simulate logout by calling delete_remember_token
    delete_remember_token($token['selector']);
    
    // Check that token is deleted
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$token['selector']]);
    $count = $stmt->fetchColumn();
    test_assert($count == 0, "Token is deleted on logout", $tests_passed, $tests_failed);
    
    // Test that other tokens are not affected
    $token1 = generate_remember_token($test_user_id);
    $token2 = generate_remember_token($test_user_id);
    
    delete_remember_token($token1['selector']);
    
    $stmt->execute([$token2['selector']]);
    $token2_exists = $stmt->fetchColumn() > 0;
    test_assert($token2_exists, "Other tokens are not affected by logout", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Logout cleanup - " . $e->getMessage() . "\n";
    $tests_failed += 2;
}

echo "\n";

// ============================================================================
// Test 8: Helper Functions
// ============================================================================
echo "Test Group 8: Helper Functions\n";
echo "-------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    // Test count_user_tokens
    $count = count_user_tokens($test_user_id);
    test_assert($count == 0, "count_user_tokens returns 0 for no tokens", $tests_passed, $tests_failed);
    
    generate_remember_token($test_user_id);
    generate_remember_token($test_user_id);
    
    $count = count_user_tokens($test_user_id);
    test_assert($count == 2, "count_user_tokens returns correct count", $tests_passed, $tests_failed);
    
    // Test delete_all_user_tokens
    delete_all_user_tokens($test_user_id);
    
    $count = count_user_tokens($test_user_id);
    test_assert($count == 0, "delete_all_user_tokens removes all tokens", $tests_passed, $tests_failed);
    
    // Test cleanup_expired_tokens
    $token1 = generate_remember_token($test_user_id);
    $token2 = generate_remember_token($test_user_id);
    
    // Expire token1
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE remember_tokens SET expires_at = ? WHERE selector = ?");
    $stmt->execute([date('Y-m-d H:i:s', time() - 3600), $token1['selector']]);
    
    $deleted_count = cleanup_expired_tokens();
    test_assert($deleted_count == 1, "cleanup_expired_tokens deletes expired tokens", $tests_passed, $tests_failed);
    
    // Check that non-expired token still exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$token2['selector']]);
    $token2_exists = $stmt->fetchColumn() > 0;
    test_assert($token2_exists, "cleanup_expired_tokens preserves valid tokens", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Helper functions - " . $e->getMessage() . "\n";
    $tests_failed += 5;
}

echo "\n";

// ============================================================================
// Test 9: Security Features
// ============================================================================
echo "Test Group 9: Security Features\n";
echo "--------------------------------\n";

cleanup_test_tokens($test_user_id);

try {
    $token = generate_remember_token($test_user_id);
    
    // Check that cookie value doesn't contain password
    test_assert(strpos($token['cookie_value'], 'password') === false, "Cookie doesn't contain 'password'", $tests_passed, $tests_failed);
    
    // Check that validator is hashed in database
    $pdo = db();
    $stmt = $pdo->prepare("SELECT validator_hash FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$token['selector']]);
    $hash = $stmt->fetchColumn();
    
    test_assert($hash !== $token['validator'], "Validator is hashed in database", $tests_passed, $tests_failed);
    test_assert(strlen($hash) >= 60, "Hash is bcrypt format (60+ chars)", $tests_passed, $tests_failed);
    
    // Check that password_verify works with the hash
    test_assert(password_verify($token['validator'], $hash), "password_verify works with stored hash", $tests_passed, $tests_failed);
    
} catch (Exception $e) {
    echo "✗ FAIL: Security features - " . $e->getMessage() . "\n";
    $tests_failed += 4;
}

echo "\n";

// ============================================================================
// Cleanup and Summary
// ============================================================================
cleanup_test_tokens($test_user_id);
unset($_COOKIE['remember_token']);
unset($_SESSION['user']);
unset($_SESSION['remember_me_login']);

echo "===========================================\n";
echo "Test Summary\n";
echo "===========================================\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";
echo "\n";

if ($tests_failed === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "\nThe remember-me-login feature is working correctly.\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED\n";
    echo "\nPlease review the failed tests above.\n";
    exit(1);
}
