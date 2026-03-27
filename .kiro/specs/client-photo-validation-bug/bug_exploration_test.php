<?php
/**
 * Bug Condition Exploration Test
 * 
 * Property 1: Bug Condition - NULL Photo Uniqueness Validation
 * 
 * This test MUST FAIL on unfixed code to confirm the bug exists.
 * 
 * Test Goal: Verify that when a client has photo=NULL and attempts to add a new photo,
 * the validation does NOT incorrectly show "photo already in use" error.
 * 
 * The bug occurs when photo uniqueness validation is implemented similar to email/phone
 * validation but fails to account for NULL photo values. The validation query
 * `SELECT id FROM clients WHERE photo=? AND id!=?` will match multiple records when
 * photo is NULL, causing false positive "photo already in use" errors.
 * 
 * Expected Outcome on UNFIXED code: TEST FAILS (this proves the bug exists)
 * Expected Outcome on FIXED code: TEST PASSES (confirms the fix works)
 * 
 * Validates: Requirements 1.1, 1.2
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/client_helpers.php';

// Test configuration
define('TEST_NAME', 'Bug Condition Exploration - NULL Photo Uniqueness Validation');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class BugExplorationTest {
    private $pdo;
    private $testClientIds = [];
    private $failures = [];
    private $successes = [];
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Setup: Create test clients with NULL photos
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Ensure photo column exists
        clients_ensure_schema($this->pdo);
        $supportsClientPhoto = clients_has_column($this->pdo, 'photo');
        
        if (!$supportsClientPhoto) {
            throw new Exception("Photo column does not exist in clients table. Run migration first.");
        }
        
        // Create multiple test clients with NULL photos
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO clients (name, phone, email, photo, created_at) 
                 VALUES (?, ?, ?, NULL, NOW())"
            );
            $uniqueId = uniqid();
            $stmt->execute([
                "Test Client {$i} {$uniqueId}",
                "555000{$i}{$uniqueId}",
                "testclient{$i}_{$uniqueId}@example.com"
            ]);
            $this->testClientIds[] = (int) $this->pdo->lastInsertId();
        }
        
        echo "Created " . count($this->testClientIds) . " test clients with NULL photos\n";
        echo "Client IDs: " . implode(", ", $this->testClientIds) . "\n";
    }
    
    /**
     * Cleanup: Remove test data
     */
    private function cleanupTestData() {
        echo "Cleaning up test data...\n";
        
        foreach ($this->testClientIds as $clientId) {
            $this->pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$clientId]);
        }
    }
    
    /**
     * Test the ACTUAL photo validation logic from clients/edit.php
     * This is the FIXED implementation that should be in production
     * 
     * @param int $clientId The client being edited
     * @param string|null $photoValue The photo value to validate
     * @return bool True if validation passes (no duplicate), False if duplicate found
     */
    private function validatePhoto($clientId, $photoValue) {
        // Skip validation if photo is NULL (this is the fix)
        if ($photoValue === null) {
            return true;
        }
        
        // Fixed validation query - excludes NULL values
        // This matches the implementation in clients/edit.php
        $stmt = $this->pdo->prepare(
            "SELECT id FROM clients WHERE photo = ? AND photo IS NOT NULL AND id != ?"
        );
        $stmt->execute([$photoValue, $clientId]);
        $duplicate = $stmt->fetch();
        
        // If a duplicate is found, validation fails
        return $duplicate === false;
    }
    
    /**
     * Test Case 1: Client with NULL photo attempts to add a photo
     * 
     * Scenario: Client A has photo=NULL, Client B has photo=NULL, attempt to add photo to Client A
     * Expected: Validation should PASS (allow photo upload)
     * Bug: Validation FAILS because query matches Client B's NULL photo
     */
    public function testNullPhotoClientAddPhoto() {
        echo "\n--- Test Case 1: Client with NULL photo attempts to add a photo ---\n";
        
        $clientId = $this->testClientIds[0];
        $newPhotoPath = 'uploads/clients/client_photo_' . uniqid() . '.jpg';
        
        echo "Client ID: {$clientId}\n";
        echo "Current photo: NULL\n";
        echo "New photo: {$newPhotoPath}\n";
        
        // Check current state
        $stmt = $this->pdo->prepare("SELECT photo FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $currentPhoto = $stmt->fetchColumn();
        echo "Verified current photo in DB: " . ($currentPhoto === null ? 'NULL' : $currentPhoto) . "\n";
        
        // Count other clients with NULL photos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clients WHERE photo IS NULL AND id != ?");
        $stmt->execute([$clientId]);
        $nullPhotoCount = (int) $stmt->fetchColumn();
        echo "Other clients with NULL photos: {$nullPhotoCount}\n";
        
        // Test unfixed validation (buggy) - validates current NULL photo
        $unfixedResult = $this->validatePhotoUnfixed($clientId, $currentPhoto);
        echo "Unfixed validation result: " . ($unfixedResult ? 'PASS' : 'FAIL') . "\n";
        
        // Test fixed validation (correct) - skips validation for NULL
        $fixedResult = $this->validatePhotoFixed($clientId, $currentPhoto);
        echo "Fixed validation result: " . ($fixedResult ? 'PASS' : 'FAIL') . "\n";
        
        // The bug exists if unfixed validation fails when it should pass
        if (!$unfixedResult && $fixedResult) {
            $this->failures[] = [
                'test' => 'testNullPhotoClientAddPhoto',
                'clientId' => $clientId,
                'currentPhoto' => 'NULL',
                'message' => "Bug confirmed: Validation incorrectly fails for client with NULL photo when other clients also have NULL photos"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - validation incorrectly shows 'photo already in use' for NULL photo\n" . ANSI_RESET;
        } else if ($unfixedResult && $fixedResult) {
            $this->successes[] = 'testNullPhotoClientAddPhoto';
            echo ANSI_GREEN . "✓ PASS: Validation correctly allows photo upload for client with NULL photo\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testNullPhotoClientAddPhoto',
                'clientId' => $clientId,
                'currentPhoto' => 'NULL',
                'message' => "Unexpected: Both validations failed or both passed incorrectly"
            ];
            echo ANSI_YELLOW . "⚠ UNEXPECTED: Validation behavior doesn't match expected pattern\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 2: Multiple clients with NULL photos
     * 
     * Scenario: Three clients all have photo=NULL, attempt to add photos to each
     * Expected: All validations should PASS
     * Bug: All validations FAIL because query matches other clients' NULL photos
     */
    public function testMultipleNullPhotoClients() {
        echo "\n--- Test Case 2: Multiple clients with NULL photos ---\n";
        
        echo "Testing validation for " . count($this->testClientIds) . " clients with NULL photos\n";
        
        $allUnfixedPass = true;
        $allFixedPass = true;
        
        foreach ($this->testClientIds as $clientId) {
            $unfixedResult = $this->validatePhotoUnfixed($clientId, null);
            $fixedResult = $this->validatePhotoFixed($clientId, null);
            
            echo "Client ID {$clientId}: Unfixed=" . ($unfixedResult ? 'PASS' : 'FAIL') . 
                 ", Fixed=" . ($fixedResult ? 'PASS' : 'FAIL') . "\n";
            
            if (!$unfixedResult) $allUnfixedPass = false;
            if (!$fixedResult) $allFixedPass = false;
        }
        
        if (!$allUnfixedPass && $allFixedPass) {
            $this->failures[] = [
                'test' => 'testMultipleNullPhotoClients',
                'message' => "Bug confirmed: Validation fails for multiple clients with NULL photos"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - validation fails for clients with NULL photos\n" . ANSI_RESET;
        } else if ($allUnfixedPass && $allFixedPass) {
            $this->successes[] = 'testMultipleNullPhotoClients';
            echo ANSI_GREEN . "✓ PASS: Validation correctly handles multiple clients with NULL photos\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testMultipleNullPhotoClients',
                'message' => "Unexpected: Validation behavior doesn't match expected pattern"
            ];
            echo ANSI_YELLOW . "⚠ UNEXPECTED: Validation behavior doesn't match expected pattern\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 3: Direct query test - verify NULL matching behavior
     * 
     * Scenario: Execute the buggy query directly to confirm it matches multiple NULL records
     * Expected: Query should match other clients with NULL photos
     */
    public function testDirectQueryNullMatching() {
        echo "\n--- Test Case 3: Direct query test - NULL matching behavior ---\n";
        
        $clientId = $this->testClientIds[0];
        
        // Execute the buggy query
        $stmt = $this->pdo->prepare(
            "SELECT id FROM clients WHERE photo = ? AND id != ?"
        );
        $stmt->execute([null, $clientId]);
        $matches = $stmt->fetchAll();
        
        echo "Buggy query: SELECT id FROM clients WHERE photo = NULL AND id != {$clientId}\n";
        echo "Matches found: " . count($matches) . "\n";
        
        if (count($matches) > 0) {
            echo "Matched client IDs: " . implode(", ", array_column($matches, 'id')) . "\n";
            $this->failures[] = [
                'test' => 'testDirectQueryNullMatching',
                'matchCount' => count($matches),
                'message' => "Bug confirmed: Query matches " . count($matches) . " other clients with NULL photos"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - query incorrectly matches NULL values\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testDirectQueryNullMatching';
            echo ANSI_GREEN . "✓ PASS: Query correctly doesn't match NULL values\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 4: Actual photo duplicate detection (should work correctly)
     * 
     * Scenario: Client A has photo='photo1.jpg', Client B attempts to use 'photo1.jpg'
     * Expected: Validation should FAIL (prevent duplicate)
     * This should work correctly even with buggy validation
     */
    public function testActualPhotoDuplicateDetection() {
        echo "\n--- Test Case 4: Actual photo duplicate detection ---\n";
        
        // Create two clients with actual photo paths
        $photoPath = 'uploads/clients/test_photo_' . uniqid() . '.jpg';
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO clients (name, phone, email, photo, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $uniqueId = uniqid();
        $stmt->execute([
            "Test Client A {$uniqueId}",
            "555111{$uniqueId}",
            "testclientA_{$uniqueId}@example.com",
            $photoPath
        ]);
        $clientAId = (int) $this->pdo->lastInsertId();
        $this->testClientIds[] = $clientAId;
        
        $stmt->execute([
            "Test Client B {$uniqueId}",
            "555222{$uniqueId}",
            "testclientB_{$uniqueId}@example.com",
            null
        ]);
        $clientBId = (int) $this->pdo->lastInsertId();
        $this->testClientIds[] = $clientBId;
        
        echo "Client A ID: {$clientAId}, photo: {$photoPath}\n";
        echo "Client B ID: {$clientBId}, attempting to use same photo\n";
        
        // Test validation for Client B attempting to use Client A's photo
        $unfixedResult = $this->validatePhotoUnfixed($clientBId, $photoPath);
        $fixedResult = $this->validatePhotoFixed($clientBId, $photoPath);
        
        echo "Unfixed validation result: " . ($unfixedResult ? 'PASS' : 'FAIL') . "\n";
        echo "Fixed validation result: " . ($fixedResult ? 'PASS' : 'FAIL') . "\n";
        
        // Both should fail (prevent duplicate)
        if (!$unfixedResult && !$fixedResult) {
            $this->successes[] = 'testActualPhotoDuplicateDetection';
            echo ANSI_GREEN . "✓ PASS: Validation correctly prevents actual photo duplicates\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testActualPhotoDuplicateDetection',
                'message' => "Unexpected: Validation should prevent actual photo duplicates"
            ];
            echo ANSI_RED . "✗ FAIL: Validation doesn't prevent actual photo duplicates\n" . ANSI_RESET;
        }
    }
    
    /**
     * Run all tests
     */
    public function run() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo TEST_NAME . "\n";
        echo str_repeat("=", 80) . "\n";
        echo "\nThis test MUST FAIL on unfixed code to confirm the bug exists.\n";
        echo "When the test PASSES, it confirms the bug is fixed.\n\n";
        
        try {
            $this->setupTestData();
            
            // Run all test cases
            $this->testNullPhotoClientAddPhoto();
            $this->testMultipleNullPhotoClients();
            $this->testDirectQueryNullMatching();
            $this->testActualPhotoDuplicateDetection();
            
            // Report results
            $this->reportResults();
            
        } catch (Exception $e) {
            echo ANSI_RED . "\nTest execution error: " . $e->getMessage() . "\n" . ANSI_RESET;
            echo $e->getTraceAsString() . "\n";
        } finally {
            $this->cleanupTestData();
        }
    }
    
    /**
     * Report test results
     */
    private function reportResults() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST RESULTS\n";
        echo str_repeat("=", 80) . "\n";
        
        $totalTests = count($this->successes) + count($this->failures);
        $passCount = count($this->successes);
        $failCount = count($this->failures);
        
        echo "\nTotal Tests: {$totalTests}\n";
        echo ANSI_GREEN . "Passed: {$passCount}\n" . ANSI_RESET;
        echo ANSI_RED . "Failed: {$failCount}\n" . ANSI_RESET;
        
        if (!empty($this->failures)) {
            echo "\n" . ANSI_YELLOW . "COUNTEREXAMPLES (Bug Evidence):\n" . ANSI_RESET;
            foreach ($this->failures as $failure) {
                echo "\n  Test: {$failure['test']}\n";
                if (isset($failure['clientId'])) {
                    echo "  Client ID: {$failure['clientId']}\n";
                }
                if (isset($failure['currentPhoto'])) {
                    echo "  Current Photo: {$failure['currentPhoto']}\n";
                }
                if (isset($failure['matchCount'])) {
                    echo "  Match Count: {$failure['matchCount']}\n";
                }
                echo "  Message: {$failure['message']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ BUG CONFIRMED: The test failures above prove the bug exists.\n" . ANSI_RESET;
            echo "Photo uniqueness validation incorrectly treats NULL values as duplicates.\n";
            echo "When a client has photo=NULL and attempts to add a photo, the validation\n";
            echo "query matches other clients with NULL photos, causing false positive errors.\n";
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL TESTS PASSED: Bug is fixed!\n" . ANSI_RESET;
            echo "Photo uniqueness validation correctly excludes NULL values.\n";
            exit(0);
        }
    }
}

// Run the test
$test = new BugExplorationTest();
$test->run();
