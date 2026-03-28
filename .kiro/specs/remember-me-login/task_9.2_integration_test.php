<?php
/**
 * Integration Test for Task 9.2: Password Change Token Invalidation
 * 
 * This test verifies that all remember tokens are deleted when a user's password is changed.
 * 
 * Requirements tested: 14.4
 * 
 * To run this test:
 * 1. Ensure the database is set up with the remember_tokens table
 * 2. Run: php .kiro/specs/remember-me-login/task_9.2_integration_test.php
 */

require_once __DIR__ . '/../../../config/db.php';

class PasswordChangeTokenInvalidationTest
{
    private PDO $pdo;
    private array $testUserIds = [];
    
    public function __construct()
    {
        $this->pdo = db();
    }
    
    public function run(): void
    {
        echo "=== Task 9.2 Integration Test: Password Change Token Invalidation ===\n\n";
        
        try {
            $this->setUp();
            $this->testPasswordChangeInvalidatesTokens();
            $this->testMultipleTokensInvalidation();
            $this->testNoTokensScenario();
            $this->tearDown();
            
            echo "\n✅ All tests passed!\n";
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            $this->tearDown();
            exit(1);
        }
    }
    
    private function setUp(): void
    {
        echo "Setting up test environment...\n";
        
        // Create test users
        for ($i = 1; $i <= 2; $i++) {
            $username = 'test_user_' . uniqid();
            $hash = password_hash('test123', PASSWORD_BCRYPT);
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (name, username, password_hash, role, is_active) 
                 VALUES (?, ?, ?, 'staff', 1)"
            );
            $stmt->execute(["Test User $i", $username, $hash]);
            $this->testUserIds[] = (int) $this->pdo->lastInsertId();
        }
        
        echo "Created " . count($this->testUserIds) . " test users\n";
    }
    
    private function tearDown(): void
    {
        echo "\nCleaning up test data...\n";
        
        foreach ($this->testUserIds as $userId) {
            // Delete tokens
            $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
            // Delete user
            $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }
        
        echo "Cleanup complete\n";
    }
    
    private function testPasswordChangeInvalidatesTokens(): void
    {
        echo "\n--- Test 1: Password change invalidates single token ---\n";
        
        $userId = $this->testUserIds[0];
        
        // Create a remember token
        $token = generate_remember_token($userId);
        echo "Created remember token for user $userId\n";
        
        // Verify token exists
        $count = $this->countUserTokens($userId);
        $this->assert($count === 1, "Expected 1 token, found $count");
        echo "✓ Token exists in database\n";
        
        // Simulate password change
        $newHash = password_hash('newpassword123', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        
        // Call token invalidation (simulating what staff/edit.php does)
        delete_all_user_tokens($userId);
        echo "Called delete_all_user_tokens($userId)\n";
        
        // Verify all tokens are deleted
        $count = $this->countUserTokens($userId);
        $this->assert($count === 0, "Expected 0 tokens after password change, found $count");
        echo "✓ All tokens deleted after password change\n";
    }
    
    private function testMultipleTokensInvalidation(): void
    {
        echo "\n--- Test 2: Password change invalidates multiple tokens ---\n";
        
        $userId = $this->testUserIds[1];
        
        // Create multiple remember tokens (simulating multiple devices)
        $tokenCount = 3;
        for ($i = 0; $i < $tokenCount; $i++) {
            generate_remember_token($userId);
        }
        echo "Created $tokenCount remember tokens for user $userId\n";
        
        // Verify tokens exist
        $count = $this->countUserTokens($userId);
        $this->assert($count === $tokenCount, "Expected $tokenCount tokens, found $count");
        echo "✓ All $tokenCount tokens exist in database\n";
        
        // Simulate password change
        $newHash = password_hash('newpassword456', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        
        // Call token invalidation
        delete_all_user_tokens($userId);
        echo "Called delete_all_user_tokens($userId)\n";
        
        // Verify all tokens are deleted
        $count = $this->countUserTokens($userId);
        $this->assert($count === 0, "Expected 0 tokens after password change, found $count");
        echo "✓ All $tokenCount tokens deleted after password change\n";
    }
    
    private function testNoTokensScenario(): void
    {
        echo "\n--- Test 3: Password change with no existing tokens ---\n";
        
        $userId = $this->testUserIds[0];
        
        // Ensure no tokens exist
        delete_all_user_tokens($userId);
        $count = $this->countUserTokens($userId);
        $this->assert($count === 0, "Expected 0 tokens initially, found $count");
        echo "✓ No tokens exist initially\n";
        
        // Simulate password change
        $newHash = password_hash('anotherpassword789', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        
        // Call token invalidation (should not cause errors)
        delete_all_user_tokens($userId);
        echo "Called delete_all_user_tokens($userId) with no existing tokens\n";
        
        // Verify still no tokens
        $count = $this->countUserTokens($userId);
        $this->assert($count === 0, "Expected 0 tokens after password change, found $count");
        echo "✓ Function handles no-tokens scenario gracefully\n";
    }
    
    private function countUserTokens(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
    
    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }
}

// Run the test
$test = new PasswordChangeTokenInvalidationTest();
$test->run();
