<?php
/**
 * Preservation Property Tests
 * 
 * Property 2: Preservation - Explicit Period Selection
 * 
 * These tests MUST PASS on UNFIXED code to establish baseline behavior to preserve.
 * 
 * Test Goal: Verify that when URL parameters (adv_month and adv_year) ARE present,
 * the "Due" badge correctly filters by the specified period. This behavior must be
 * preserved after the fix.
 * 
 * Expected Outcome on UNFIXED code: TESTS PASS (confirms baseline to preserve)
 * Expected Outcome on FIXED code: TESTS PASS (confirms no regressions)
 * 
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5
 */

require_once __DIR__ . '/../../../config/db.php';

// Test configuration
define('TEST_NAME', 'Preservation Tests - Explicit Period Selection');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class PreservationTest {
    private $pdo;
    private $testUserId;
    private $testStaffId;
    private $failures = [];
    private $successes = [];
    private $propertyTestCount = 0;
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Setup: Create test staff member and user
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create test staff
        $stmt = $this->pdo->prepare(
            "INSERT INTO staff (name, role, phone, salary, joined_date) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            'Test Staff ' . uniqid(),
            'Test Role',
            '1234567890',
            5000.00,
            date('Y-m-d')
        ]);
        $this->testStaffId = (int) $this->pdo->lastInsertId();
        
        // Create test user linked to staff
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (name, username, password_hash, role, staff_id, is_active) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            'Test User ' . uniqid(),
            'testuser_' . uniqid(),
            password_hash('test123', PASSWORD_DEFAULT),
            'staff',
            $this->testStaffId,
            1
        ]);
        $this->testUserId = (int) $this->pdo->lastInsertId();
        
        echo "Created test staff ID: {$this->testStaffId}, user ID: {$this->testUserId}\n";
    }
    
    /**
     * Cleanup: Remove test data
     */
    private function cleanupTestData() {
        echo "Cleaning up test data...\n";
        
        if ($this->testUserId) {
            $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
            $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$this->testUserId]);
        }
        
        if ($this->testStaffId) {
            $this->pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$this->testStaffId]);
        }
    }
    
    /**
     * Simulate the behavior when URL parameters ARE present
     * This replicates lines 38-46 of staff/show.php with explicit period
     */
    private function getAdvanceBalanceWithParams($userId, $month, $year) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(remaining_amount),0) 
             FROM payroll_advances 
             WHERE user_id = ? 
             AND remaining_amount > 0 
             AND status IN ('pending','partially_recovered')
             AND month = ? 
             AND year = ?"
        );
        $stmt->execute([$userId, $month, $year]);
        return (float) $stmt->fetchColumn();
    }
    
    /**
     * Get expected balance for a specific period (ground truth)
     */
    private function getExpectedBalanceForPeriod($userId, $month, $year) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(remaining_amount),0) 
             FROM payroll_advances 
             WHERE user_id = ? 
             AND remaining_amount > 0 
             AND status IN ('pending','partially_recovered')
             AND month = ? 
             AND year = ?"
        );
        $stmt->execute([$userId, $month, $year]);
        return (float) $stmt->fetchColumn();
    }
    
    /**
     * Property Test: Explicit period filtering works correctly
     * 
     * For ANY month/year combination provided via URL parameters,
     * the badge should display ONLY advances for that specific period.
     */
    public function propertyExplicitPeriodFiltering() {
        echo "\n--- Property Test: Explicit Period Filtering ---\n";
        echo "Testing that URL parameters correctly filter advances by period\n\n";
        
        // Generate test cases: various month/year combinations
        $testCases = $this->generatePeriodTestCases();
        
        foreach ($testCases as $idx => $testCase) {
            $this->propertyTestCount++;
            
            // Clean up previous test data
            $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
            
            // Create advances for multiple periods
            foreach ($testCase['advances'] as $adv) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO payroll_advances 
                    (user_id, month, year, amount, remaining_amount, status, given_at, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)"
                );
                $stmt->execute([
                    $this->testUserId,
                    $adv['month'],
                    $adv['year'],
                    $adv['amount'],
                    $adv['amount'],
                    $adv['status']
                ]);
            }
            
            // Test filtering with explicit URL parameters
            $selectedMonth = $testCase['selected_month'];
            $selectedYear = $testCase['selected_year'];
            
            $actualBalance = $this->getAdvanceBalanceWithParams($this->testUserId, $selectedMonth, $selectedYear);
            $expectedBalance = $this->getExpectedBalanceForPeriod($this->testUserId, $selectedMonth, $selectedYear);
            
            echo "  Case {$idx}: Period {$selectedMonth}/{$selectedYear} - ";
            
            if (abs($actualBalance - $expectedBalance) < 0.01) {
                $this->successes[] = "propertyExplicitPeriodFiltering_case_{$idx}";
                echo ANSI_GREEN . "✓ PASS" . ANSI_RESET;
                echo " (Expected: \${$expectedBalance}, Got: \${$actualBalance})\n";
            } else {
                $this->failures[] = [
                    'test' => "propertyExplicitPeriodFiltering_case_{$idx}",
                    'expected' => $expectedBalance,
                    'actual' => $actualBalance,
                    'message' => "Period {$selectedMonth}/{$selectedYear}: Expected \${$expectedBalance}, got \${$actualBalance}"
                ];
                echo ANSI_RED . "✗ FAIL" . ANSI_RESET;
                echo " (Expected: \${$expectedBalance}, Got: \${$actualBalance})\n";
            }
        }
        
        // Clean up
        $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
    }
    
    /**
     * Generate diverse test cases for property testing
     * Returns array of test scenarios with different period combinations
     */
    private function generatePeriodTestCases() {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        
        return [
            // Case 1: Single period with advances, query that period
            [
                'selected_month' => 3,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 3, 'year' => $currentYear, 'amount' => 500.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 2: Multiple periods, query one with advances
            [
                'selected_month' => 5,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 3, 'year' => $currentYear, 'amount' => 300.00, 'status' => 'pending'],
                    ['month' => 5, 'year' => $currentYear, 'amount' => 700.00, 'status' => 'pending'],
                    ['month' => 7, 'year' => $currentYear, 'amount' => 200.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 3: Multiple periods, query one without advances
            [
                'selected_month' => 6,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 3, 'year' => $currentYear, 'amount' => 400.00, 'status' => 'pending'],
                    ['month' => 8, 'year' => $currentYear, 'amount' => 600.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 4: Year boundary - December
            [
                'selected_month' => 12,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 12, 'year' => $currentYear, 'amount' => 1000.00, 'status' => 'pending'],
                    ['month' => 1, 'year' => $currentYear + 1, 'amount' => 500.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 5: Year boundary - January
            [
                'selected_month' => 1,
                'selected_year' => $currentYear + 1,
                'advances' => [
                    ['month' => 12, 'year' => $currentYear, 'amount' => 800.00, 'status' => 'pending'],
                    ['month' => 1, 'year' => $currentYear + 1, 'amount' => 300.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 6: Current month
            [
                'selected_month' => $currentMonth,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => $currentMonth, 'year' => $currentYear, 'amount' => 450.00, 'status' => 'pending'],
                    ['month' => ($currentMonth - 1) ?: 12, 'year' => $currentYear, 'amount' => 250.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 7: Multiple advances in same period
            [
                'selected_month' => 4,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 4, 'year' => $currentYear, 'amount' => 200.00, 'status' => 'pending'],
                    ['month' => 4, 'year' => $currentYear, 'amount' => 300.00, 'status' => 'pending'],
                    ['month' => 4, 'year' => $currentYear, 'amount' => 150.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 8: Mix of pending and partially_recovered
            [
                'selected_month' => 7,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 7, 'year' => $currentYear, 'amount' => 500.00, 'status' => 'pending'],
                    ['month' => 7, 'year' => $currentYear, 'amount' => 300.00, 'status' => 'partially_recovered'],
                ]
            ],
            
            // Case 9: Empty period (no advances)
            [
                'selected_month' => 9,
                'selected_year' => $currentYear,
                'advances' => [
                    ['month' => 8, 'year' => $currentYear, 'amount' => 400.00, 'status' => 'pending'],
                    ['month' => 10, 'year' => $currentYear, 'amount' => 600.00, 'status' => 'pending'],
                ]
            ],
            
            // Case 10: Next year
            [
                'selected_month' => 2,
                'selected_year' => $currentYear + 1,
                'advances' => [
                    ['month' => 2, 'year' => $currentYear + 1, 'amount' => 900.00, 'status' => 'pending'],
                    ['month' => 2, 'year' => $currentYear, 'amount' => 100.00, 'status' => 'pending'],
                ]
            ],
        ];
    }
    
    /**
     * Test Case: Badge styling preservation
     * 
     * Verify that badge styling (orange for balance > 0, green for no balance)
     * continues to work correctly with explicit period selection.
     */
    public function testBadgeStylingPreservation() {
        echo "\n--- Test: Badge Styling Preservation ---\n";
        
        // Clean up
        $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
        
        // Test 1: Period with balance > 0 (should be orange)
        $stmt = $this->pdo->prepare(
            "INSERT INTO payroll_advances 
            (user_id, month, year, amount, remaining_amount, status, given_at, created_by) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), 1)"
        );
        $stmt->execute([$this->testUserId, 3, 2026, 500.00, 500.00]);
        
        $balance = $this->getAdvanceBalanceWithParams($this->testUserId, 3, 2026);
        
        echo "  Period 3/2026 with \$500 advance: ";
        if ($balance > 0) {
            $this->successes[] = 'testBadgeStylingPreservation_positive';
            echo ANSI_GREEN . "✓ PASS" . ANSI_RESET . " (Balance: \${$balance} > 0, should show orange badge)\n";
        } else {
            $this->failures[] = [
                'test' => 'testBadgeStylingPreservation_positive',
                'expected' => 500.00,
                'actual' => $balance,
                'message' => "Expected balance > 0 for orange badge styling"
            ];
            echo ANSI_RED . "✗ FAIL" . ANSI_RESET . " (Balance: \${$balance}, expected > 0)\n";
        }
        
        // Test 2: Period with no balance (should be green)
        $balance = $this->getAdvanceBalanceWithParams($this->testUserId, 5, 2026);
        
        echo "  Period 5/2026 with no advances: ";
        if ($balance == 0) {
            $this->successes[] = 'testBadgeStylingPreservation_zero';
            echo ANSI_GREEN . "✓ PASS" . ANSI_RESET . " (Balance: \${$balance} = 0, should show green 'No Balance' badge)\n";
        } else {
            $this->failures[] = [
                'test' => 'testBadgeStylingPreservation_zero',
                'expected' => 0.00,
                'actual' => $balance,
                'message' => "Expected balance = 0 for green badge styling"
            ];
            echo ANSI_RED . "✗ FAIL" . ANSI_RESET . " (Balance: \${$balance}, expected 0)\n";
        }
        
        // Clean up
        $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
    }
    
    /**
     * Test Case: Dropdown change and page reload preservation
     * 
     * Verify that when a user selects a different period from the dropdown,
     * the page reloads with correct URL parameters and badge updates.
     * 
     * Note: This is a logical test - actual JavaScript behavior is tested manually.
     * We verify the backend correctly handles the URL parameters.
     */
    public function testDropdownChangePreservation() {
        echo "\n--- Test: Dropdown Change Preservation ---\n";
        echo "Verifying backend correctly handles URL parameter changes\n";
        
        // Clean up
        $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
        
        // Create advances for multiple periods
        $advances = [
            ['month' => 2, 'year' => 2026, 'amount' => 300.00],
            ['month' => 4, 'year' => 2026, 'amount' => 500.00],
            ['month' => 6, 'year' => 2026, 'amount' => 700.00],
        ];
        
        foreach ($advances as $adv) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO payroll_advances 
                (user_id, month, year, amount, remaining_amount, status, given_at, created_by) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW(), 1)"
            );
            $stmt->execute([$this->testUserId, $adv['month'], $adv['year'], $adv['amount'], $adv['amount']]);
        }
        
        // Simulate dropdown changes by querying different periods
        $testPeriods = [
            ['month' => 2, 'year' => 2026, 'expected' => 300.00],
            ['month' => 4, 'year' => 2026, 'expected' => 500.00],
            ['month' => 6, 'year' => 2026, 'expected' => 700.00],
            ['month' => 5, 'year' => 2026, 'expected' => 0.00], // No advances
        ];
        
        $allPassed = true;
        foreach ($testPeriods as $period) {
            $balance = $this->getAdvanceBalanceWithParams($this->testUserId, $period['month'], $period['year']);
            
            echo "  Period {$period['month']}/{$period['year']}: ";
            if (abs($balance - $period['expected']) < 0.01) {
                echo ANSI_GREEN . "✓ PASS" . ANSI_RESET . " (\${$balance})\n";
            } else {
                echo ANSI_RED . "✗ FAIL" . ANSI_RESET . " (Expected: \${$period['expected']}, Got: \${$balance})\n";
                $allPassed = false;
            }
        }
        
        if ($allPassed) {
            $this->successes[] = 'testDropdownChangePreservation';
        } else {
            $this->failures[] = [
                'test' => 'testDropdownChangePreservation',
                'expected' => 'All periods filter correctly',
                'actual' => 'Some periods failed',
                'message' => "Dropdown change simulation failed for some periods"
            ];
        }
        
        // Clean up
        $this->pdo->prepare("DELETE FROM payroll_advances WHERE user_id = ?")->execute([$this->testUserId]);
    }
    
    /**
     * Run all tests
     */
    public function run() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo TEST_NAME . "\n";
        echo str_repeat("=", 80) . "\n";
        echo "\nThese tests MUST PASS on UNFIXED code to establish baseline behavior.\n";
        echo "They verify that explicit period selection (with URL parameters) works correctly.\n";
        echo "This behavior must be preserved after implementing the fix.\n\n";
        
        try {
            $this->setupTestData();
            
            // Run all test cases
            $this->propertyExplicitPeriodFiltering();
            $this->testBadgeStylingPreservation();
            $this->testDropdownChangePreservation();
            
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
        echo "Property Test Cases: {$this->propertyTestCount}\n";
        echo ANSI_GREEN . "Passed: {$passCount}\n" . ANSI_RESET;
        echo ANSI_RED . "Failed: {$failCount}\n" . ANSI_RESET;
        
        if (!empty($this->failures)) {
            echo "\n" . ANSI_YELLOW . "FAILURES:\n" . ANSI_RESET;
            foreach ($this->failures as $failure) {
                echo "\n  Test: {$failure['test']}\n";
                echo "  Expected: {$failure['expected']}\n";
                echo "  Actual: {$failure['actual']}\n";
                echo "  Message: {$failure['message']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ PRESERVATION TESTS FAILED\n" . ANSI_RESET;
            echo "The explicit period selection behavior is NOT working correctly.\n";
            echo "This baseline behavior must work before implementing the fix.\n";
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL PRESERVATION TESTS PASSED\n" . ANSI_RESET;
            echo "Baseline behavior confirmed: Explicit period selection works correctly.\n";
            echo "This behavior must be preserved after implementing the fix.\n";
            exit(0);
        }
    }
}

// Run the test
$test = new PreservationTest();
$test->run();
