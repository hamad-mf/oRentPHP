<?php
/**
 * Manual Test for Permanent Scratch Deletion
 * 
 * This script tests the deletion functionality by:
 * 1. Creating a test scratch record with a photo file
 * 2. Verifying the record and file exist
 * 3. Simulating the deletion process
 * 4. Verifying both record and file are deleted
 * 
 * Run from project root: php .kiro/specs/vehicle-permanent-scratches/test_deletion.php
 */

require_once __DIR__ . '/../../../config/db.php';

class PermanentScratchDeletionTest {
    private $pdo;
    private $testVehicleId;
    private $testScratchId;
    private $testFilePath;
    
    public function __construct() {
        $this->pdo = db();
    }
    
    public function run() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Permanent Scratch Deletion Test\n";
        echo str_repeat("=", 80) . "\n\n";
        
        try {
            $this->setup();
            $this->testDeletion();
            $this->testGracefulDegradation();
            $this->cleanup();
            
            echo "\n✓ All tests passed!\n\n";
        } catch (Exception $e) {
            echo "\n✗ Test failed: " . $e->getMessage() . "\n\n";
            $this->cleanup();
            exit(1);
        }
    }
    
    private function setup() {
        echo "Setting up test data...\n";
        
        // Get or create a test vehicle
        $stmt = $this->pdo->query("SELECT id FROM vehicles LIMIT 1");
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) {
            throw new Exception("No vehicles found in database. Please add at least one vehicle.");
        }
        
        $this->testVehicleId = $vehicle['id'];
        echo "  Using vehicle ID: {$this->testVehicleId}\n";
    }
    
    private function testDeletion() {
        echo "\nTest 1: Complete deletion (database + file)\n";
        
        // Create test scratch with file
        $scratchId = $this->createTestScratch(true);
        $this->testScratchId = $scratchId;
        
        // Verify record exists
        $record = $this->fetchScratch($scratchId);
        if (!$record) {
            throw new Exception("Failed to create test scratch record");
        }
        echo "  ✓ Scratch record created (ID: {$scratchId})\n";
        
        // Verify file exists
        $filePath = __DIR__ . '/../../../' . $record['file_path'];
        if (!file_exists($filePath)) {
            throw new Exception("Test file was not created: {$filePath}");
        }
        echo "  ✓ Photo file created: {$record['file_path']}\n";
        
        // Simulate deletion process
        $this->deleteScratch($scratchId);
        
        // Verify record is deleted
        $deletedRecord = $this->fetchScratch($scratchId);
        if ($deletedRecord) {
            throw new Exception("Scratch record still exists after deletion");
        }
        echo "  ✓ Database record deleted\n";
        
        // Verify file is deleted
        if (file_exists($filePath)) {
            throw new Exception("Photo file still exists after deletion");
        }
        echo "  ✓ Photo file deleted\n";
        
        $this->testScratchId = null;
    }
    
    private function testGracefulDegradation() {
        echo "\nTest 2: Graceful degradation (file doesn't exist)\n";
        
        // Create test scratch with file
        $scratchId = $this->createTestScratch(true);
        $this->testScratchId = $scratchId;
        
        // Get file path and manually delete the file
        $record = $this->fetchScratch($scratchId);
        $filePath = __DIR__ . '/../../../' . $record['file_path'];
        unlink($filePath);
        echo "  ✓ Manually deleted photo file\n";
        
        // Simulate deletion process (should not fail)
        try {
            $this->deleteScratch($scratchId);
            echo "  ✓ Deletion succeeded despite missing file\n";
        } catch (Exception $e) {
            throw new Exception("Deletion failed when file was missing: " . $e->getMessage());
        }
        
        // Verify record is deleted
        $deletedRecord = $this->fetchScratch($scratchId);
        if ($deletedRecord) {
            throw new Exception("Scratch record still exists after deletion");
        }
        echo "  ✓ Database record deleted\n";
        
        $this->testScratchId = null;
    }
    
    private function createTestScratch($withFile = true) {
        $description = "Test scratch - " . time();
        $filename = "test_permanent_{$this->testVehicleId}_" . time() . ".jpg";
        $filePath = "uploads/permanent_scratches/{$filename}";
        
        // Create directory if needed
        $dir = __DIR__ . '/../../../uploads/permanent_scratches/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Create test file if requested
        if ($withFile) {
            $fullPath = $dir . $filename;
            file_put_contents($fullPath, "test image data");
        }
        
        // Insert record
        $stmt = $this->pdo->prepare(
            "INSERT INTO vehicle_permanent_scratches (vehicle_id, description, file_path, created_by) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$this->testVehicleId, $description, $filePath, 1]);
        
        return $this->pdo->lastInsertId();
    }
    
    private function fetchScratch($scratchId) {
        $stmt = $this->pdo->prepare("SELECT * FROM vehicle_permanent_scratches WHERE id = ?");
        $stmt->execute([$scratchId]);
        return $stmt->fetch();
    }
    
    private function deleteScratch($scratchId) {
        // Simulate the deletion logic from permanent_scratches.php
        $fetchStmt = $this->pdo->prepare('SELECT file_path FROM vehicle_permanent_scratches WHERE id = ?');
        $fetchStmt->execute([$scratchId]);
        $scratchRecord = $fetchStmt->fetch();
        
        if ($scratchRecord) {
            // Delete database record
            $deleteStmt = $this->pdo->prepare('DELETE FROM vehicle_permanent_scratches WHERE id = ?');
            $deleteStmt->execute([$scratchId]);
            
            // Delete photo file (graceful degradation if file doesn't exist)
            $filePath = __DIR__ . '/../../../' . $scratchRecord['file_path'];
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    throw new Exception("Failed to delete file: {$filePath}");
                }
            }
        }
    }
    
    private function cleanup() {
        echo "\nCleaning up...\n";
        
        // Delete any remaining test scratches
        if ($this->testScratchId) {
            try {
                $record = $this->fetchScratch($this->testScratchId);
                if ($record) {
                    $filePath = __DIR__ . '/../../../' . $record['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $this->pdo->prepare("DELETE FROM vehicle_permanent_scratches WHERE id = ?")->execute([$this->testScratchId]);
                }
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        
        echo "  ✓ Cleanup complete\n";
    }
}

// Run the test
$test = new PermanentScratchDeletionTest();
$test->run();
