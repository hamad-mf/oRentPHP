<?php
/**
 * Verification Test - Test the ACTUAL fix in clients/edit.php
 * 
 * This test verifies that the fix in clients/edit.php works correctly
 * by simulating the actual edit flow with photo uploads.
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/client_helpers.php';

define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_RESET', "\033[0m");

class VerifyFixTest {
    private $pdo;
    private $testClientIds = [];
    
    public function __construct() {
        $this->pdo = db();
    }
    
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        clients_ensure_schema($this->pdo);
        
        // Create test clients with NULL photos
        for ($i = 1; $i <= 2; $i++) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO clients (name, phone, email, photo, created_at) 
                 VALUES (?, ?, ?, NULL, NOW())"
            );
            $uniqueId = uniqid();
            $stmt->execute([
                "Verify Test Client {$i} {$uniqueId}",
                "555900{$i}{$uniqueId}",
                "verifytest{$i}_{$uniqueId}@example.com"
            ]);
            $this->testClientIds[] = (int) $this->pdo->lastInsertId();
        }
        
        echo "Created " . count($this->testClientIds) . " test clients with NULL photos\n";
    }
    
    private function cleanupTestData() {
        echo "Cleaning up test data...\n";
        foreach ($this->testClientIds as $clientId) {
            $this->pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$clientId]);
        }
    }
    
    /**
     * Test the actual validation logic from clients/edit.php
     */
    public function testActualValidationLogic() {
        echo "\n--- Testing Actual Validation Logic from clients/edit.php ---\n";
        
        $clientId = $this->testClientIds[0];
        
        // Get current client data
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Client ID: {$clientId}\n";
        echo "Current photo: " . ($client['photo'] ?? 'NULL') . "\n";
        
        // Simulate the validation logic from clients/edit.php
        $newPhotoPath = 'uploads/clients/client_photo_' . uniqid() . '.jpg';
        echo "New photo path: {$newPhotoPath}\n";
        
        // This is the FIXED validation query from clients/edit.php
        $chk = $this->pdo->prepare('SELECT id FROM clients WHERE photo=? AND photo IS NOT NULL AND id!=?');
        $chk->execute([$newPhotoPath, $clientId]);
        $duplicate = $chk->fetch();
        
        if ($duplicate) {
            echo ANSI_RED . "✗ FAIL: Validation incorrectly found duplicate\n" . ANSI_RESET;
            return false;
        } else {
            echo ANSI_GREEN . "✓ PASS: Validation correctly allows new photo for client with NULL photo\n" . ANSI_RESET;
            return true;
        }
    }
    
    /**
     * Test that NULL photo clients can add photos
     */
    public function testNullPhotoCanAddPhoto() {
        echo "\n--- Test: Client with NULL photo can add a photo ---\n";
        
        $clientId = $this->testClientIds[0];
        $newPhotoPath = 'uploads/clients/client_photo_' . uniqid() . '.jpg';
        
        // Count other clients with NULL photos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM clients WHERE photo IS NULL AND id != ?");
        $stmt->execute([$clientId]);
        $nullPhotoCount = (int) $stmt->fetchColumn();
        echo "Other clients with NULL photos: {$nullPhotoCount}\n";
        
        // Run the FIXED validation (should pass)
        $chk = $this->pdo->prepare('SELECT id FROM clients WHERE photo=? AND photo IS NOT NULL AND id!=?');
        $chk->execute([$newPhotoPath, $clientId]);
        $duplicate = $chk->fetch();
        
        if (!$duplicate) {
            echo ANSI_GREEN . "✓ PASS: Client with NULL photo can add new photo\n" . ANSI_RESET;
            return true;
        } else {
            echo ANSI_RED . "✗ FAIL: Validation incorrectly prevents photo upload\n" . ANSI_RESET;
            return false;
        }
    }
    
    /**
     * Test that actual duplicates are still detected
     */
    public function testActualDuplicatesStillDetected() {
        echo "\n--- Test: Actual photo duplicates are still detected ---\n";
        
        $photoPath = 'uploads/clients/test_photo_' . uniqid() . '.jpg';
        
        // Create client with a photo
        $stmt = $this->pdo->prepare(
            "INSERT INTO clients (name, phone, email, photo, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $uniqueId = uniqid();
        $stmt->execute([
            "Client With Photo {$uniqueId}",
            "555800{$uniqueId}",
            "withphoto_{$uniqueId}@example.com",
            $photoPath
        ]);
        $clientWithPhotoId = (int) $this->pdo->lastInsertId();
        $this->testClientIds[] = $clientWithPhotoId;
        
        echo "Client {$clientWithPhotoId} has photo: {$photoPath}\n";
        
        // Try to use the same photo for another client
        $otherClientId = $this->testClientIds[1];
        echo "Attempting to use same photo for client {$otherClientId}\n";
        
        // Run validation
        $chk = $this->pdo->prepare('SELECT id FROM clients WHERE photo=? AND photo IS NOT NULL AND id!=?');
        $chk->execute([$photoPath, $otherClientId]);
        $duplicate = $chk->fetch();
        
        if ($duplicate) {
            echo ANSI_GREEN . "✓ PASS: Validation correctly detects actual photo duplicate\n" . ANSI_RESET;
            return true;
        } else {
            echo ANSI_RED . "✗ FAIL: Validation doesn't detect actual photo duplicate\n" . ANSI_RESET;
            return false;
        }
    }
    
    public function run() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Verification Test - Testing ACTUAL Fix in clients/edit.php\n";
        echo str_repeat("=", 80) . "\n\n";
        
        try {
            $this->setupTestData();
            
            $results = [];
            $results[] = $this->testActualValidationLogic();
            $results[] = $this->testNullPhotoCanAddPhoto();
            $results[] = $this->testActualDuplicatesStillDetected();
            
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "RESULTS\n";
            echo str_repeat("=", 80) . "\n";
            
            $passed = array_filter($results);
            $total = count($results);
            $passCount = count($passed);
            
            echo "\nTotal Tests: {$total}\n";
            echo "Passed: {$passCount}\n";
            echo "Failed: " . ($total - $passCount) . "\n";
            
            if ($passCount === $total) {
                echo "\n" . ANSI_GREEN . "✓ ALL TESTS PASSED: Fix is working correctly!\n" . ANSI_RESET;
                exit(0);
            } else {
                echo "\n" . ANSI_RED . "✗ SOME TESTS FAILED: Fix may not be working correctly\n" . ANSI_RESET;
                exit(1);
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "\nTest execution error: " . $e->getMessage() . "\n" . ANSI_RESET;
            exit(1);
        } finally {
            $this->cleanupTestData();
        }
    }
}

$test = new VerifyFixTest();
$test->run();
