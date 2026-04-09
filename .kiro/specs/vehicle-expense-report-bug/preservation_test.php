<?php
/**
 * Preservation Property Tests
 * 
 * Property 2: Preservation - Reservation-Linked Expenses and Income Unchanged
 * 
 * IMPORTANT: Follow observation-first methodology
 * - Observe behavior on UNFIXED code for vehicles with only reservation-linked expenses
 * - Write property-based tests capturing observed behavior patterns
 * - Run tests on UNFIXED code
 * - EXPECTED OUTCOME: Tests PASS (confirms baseline behavior to preserve)
 * 
 * Test Goal: Verify that the fix does NOT alter existing behavior for:
 * - Vehicles with only reservation-linked expenses
 * - Vehicles with zero expenses
 * - KPI exclusion filters
 * - Date filtering
 * - Income calculations
 * 
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/ledger_helpers.php';
require_once __DIR__ . '/../../../reports/vehicle_financial.php';

// Test configuration
define('TEST_NAME', 'Preservation Property Tests - Reservation Expenses Unchanged');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class PreservationTest {
    private $pdo;
    private $testVehicleIds = [];
    private $testReservationIds = [];
    private $testClientId;
    private $failures = [];
    private $successes = [];
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Setup: Create test vehicles and client
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create test client
        $stmt = $this->pdo->prepare(
            "INSERT INTO clients (name, phone, address) 
             VALUES (?, ?, ?)"
        );
        $stmt->execute([
            'Preservation Test Client ' . uniqid(),
            '9876543210',
            'Preservation Test Address'
        ]);
        $this->testClientId = (int) $this->pdo->lastInsertId();
        
        // Create test vehicles
        for ($i = 1; $i <= 5; $i++) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO vehicles (brand, model, license_plate, status) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                'PreserveBrand' . $i,
                'Model' . $i,
                'PRES' . uniqid(),
                'available'
            ]);
            $this->testVehicleIds[] = (int) $this->pdo->lastInsertId();
        }
        
        echo "Created test client ID: {$this->testClientId}\n";
        echo "Created test vehicle IDs: " . implode(', ', $this->testVehicleIds) . "\n";
    }
    
    /**
     * Cleanup: Remove test data
     */
    private function cleanupTestData() {
        echo "Cleaning up test data...\n";
        
        // Delete reservation-linked ledger entries
        foreach ($this->testReservationIds as $resId) {
            $this->pdo->prepare("DELETE FROM ledger_entries WHERE source_type = 'reservation' AND source_id = ?")->execute([$resId]);
        }
        
        // Delete reservations
        foreach ($this->testReservationIds as $resId) {
            $this->pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$resId]);
        }
        
        // Delete vehicles
        foreach ($this->testVehicleIds as $vehicleId) {
            $this->pdo->prepare("DELETE FROM vehicles WHERE id = ?")->execute([$vehicleId]);
        }
        
        // Delete client
        if ($this->testClientId) {
            $this->pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$this->testClientId]);
        }
    }
    
    /**
     * Create a reservation with income and/or expense
     */
    private function createReservation(int $vehicleId, ?float $incomeAmount, ?float $expenseAmount, string $postedAt, ?string $expenseCategory = null) {
        // Create reservation
        $stmt = $this->pdo->prepare(
            "INSERT INTO reservations 
            (client_id, vehicle_id, start_date, end_date, total_price, status, created_at) 
            VALUES (?, ?, ?, ?, 1000.00, 'completed', NOW())"
        );
        $stmt->execute([
            $this->testClientId,
            $vehicleId,
            date('Y-m-d H:i:s', strtotime($postedAt)),
            date('Y-m-d H:i:s', strtotime($postedAt . ' +3 days')),
        ]);
        $reservationId = (int) $this->pdo->lastInsertId();
        $this->testReservationIds[] = $reservationId;
        
        // Create income entry if specified
        if ($incomeAmount !== null) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO ledger_entries 
                (txn_type, category, description, amount, payment_mode, source_type, source_id, source_event, posted_at, created_by) 
                VALUES ('income', 'Rental Income', ?, ?, 'cash', 'reservation', ?, 'delivery', ?, 1)"
            );
            $stmt->execute([
                "Reservation #{$reservationId} - Rental payment",
                $incomeAmount,
                $reservationId,
                $postedAt
            ]);
        }
        
        // Create expense entry if specified
        if ($expenseAmount !== null) {
            $category = $expenseCategory ?? 'Damage Charge';
            $stmt = $this->pdo->prepare(
                "INSERT INTO ledger_entries 
                (txn_type, category, description, amount, payment_mode, source_type, source_id, source_event, posted_at, created_by) 
                VALUES ('expense', ?, ?, ?, 'cash', 'reservation', ?, 'damage', ?, 1)"
            );
            $stmt->execute([
                $category,
                "Reservation #{$reservationId} - {$category}",
                $expenseAmount,
                $reservationId,
                $postedAt
            ]);
        }
        
        return $reservationId;
    }
    
    /**
     * Calculate vehicle expenses using the ACTUAL function from reports/vehicle_financial.php
     */
    private function calculateVehicleExpenses(string $periodStart, string $periodEnd): array {
        return vfr_calculate_vehicle_expenses($this->pdo, $periodStart, $periodEnd);
    }
    
    /**
     * Get vehicle expense details using the ACTUAL function from reports/vehicle_financial.php
     */
    private function getVehicleExpenseDetails(int $vehicleId, string $periodStart, string $periodEnd): array {
        return vfr_get_vehicle_expense_details($this->pdo, $vehicleId, $periodStart, $periodEnd);
    }
    
    /**
     * Calculate vehicle income using the ACTUAL function from reports/vehicle_financial.php
     */
    private function calculateVehicleIncome(string $periodStart, string $periodEnd): array {
        return vfr_calculate_vehicle_income($this->pdo, $periodStart, $periodEnd);
    }
    
    /**
     * Get vehicle income details using the ACTUAL function from reports/vehicle_financial.php
     */
    private function getVehicleIncomeDetails(int $vehicleId, string $periodStart, string $periodEnd): array {
        return vfr_get_vehicle_income_details($this->pdo, $vehicleId, $periodStart, $periodEnd);
    }
    
    /**
     * Property 1: Vehicles with only reservation expenses show correct totals
     * 
     * Validates: Requirement 3.1 - Reservation-linked expenses continue to be included
     */
    public function testReservationExpensesPreserved() {
        echo "\n--- Property 1: Reservation-linked expenses preserved ---\n";
        
        $vehicleId = $this->testVehicleIds[0];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create reservation with expense
        $this->createReservation($vehicleId, null, 350.00, '2026-03-20 10:00:00');
        $this->createReservation($vehicleId, null, 150.00, '2026-03-25 14:00:00');
        
        echo "Created 2 reservation-linked expenses: \$350 + \$150 = \$500 total\n";
        
        // Calculate expenses
        $expenses = $this->calculateVehicleExpenses($periodStart, $periodEnd);
        $total = $expenses[$vehicleId] ?? 0.0;
        
        echo "Calculated total: \${$total}\n";
        echo "Expected: \$500.00\n";
        
        if (abs($total - 500.00) < 0.01) {
            $this->successes[] = 'testReservationExpensesPreserved';
            echo ANSI_GREEN . "✓ PASS: Reservation expenses correctly calculated\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testReservationExpensesPreserved',
                'expected' => 500.00,
                'actual' => $total,
                'message' => "Reservation expense calculation incorrect"
            ];
            echo ANSI_RED . "✗ FAIL: Expected \$500.00 but got \${$total}\n" . ANSI_RESET;
        }
    }
    
    /**
     * Property 2: Vehicles with zero expenses return $0.00
     * 
     * Validates: Requirement 3.1 - Correct handling of vehicles with no expenses
     */
    public function testZeroExpensesPreserved() {
        echo "\n--- Property 2: Zero expenses return \$0.00 ---\n";
        
        $vehicleId = $this->testVehicleIds[1];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        echo "Vehicle has no expenses\n";
        
        // Calculate expenses (should be 0)
        $expenses = $this->calculateVehicleExpenses($periodStart, $periodEnd);
        $total = $expenses[$vehicleId] ?? 0.0;
        
        echo "Calculated total: \${$total}\n";
        echo "Expected: \$0.00\n";
        
        if (abs($total) < 0.01) {
            $this->successes[] = 'testZeroExpensesPreserved';
            echo ANSI_GREEN . "✓ PASS: Zero expenses correctly handled\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testZeroExpensesPreserved',
                'expected' => 0.00,
                'actual' => $total,
                'message' => "Zero expense handling incorrect"
            ];
            echo ANSI_RED . "✗ FAIL: Expected \$0.00 but got \${$total}\n" . ANSI_RESET;
        }
    }
    
    /**
     * Property 3: KPI exclusion filters work correctly
     * 
     * Validates: Requirement 3.3 - KPI exclusions exclude security deposits and transfers
     */
    public function testKPIExclusionPreserved() {
        echo "\n--- Property 3: KPI exclusion filters preserved ---\n";
        
        $vehicleId = $this->testVehicleIds[2];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create regular expense
        $this->createReservation($vehicleId, null, 200.00, '2026-03-20 10:00:00', 'Damage Charge');
        
        // Create security deposit expense (should be excluded)
        $this->createReservation($vehicleId, null, 500.00, '2026-03-22 11:00:00', 'Security Deposit Return');
        
        echo "Created expenses: \$200 (Damage) + \$500 (Security Deposit - should be excluded)\n";
        
        // Calculate expenses (should only include damage charge)
        $expenses = $this->calculateVehicleExpenses($periodStart, $periodEnd);
        $total = $expenses[$vehicleId] ?? 0.0;
        
        echo "Calculated total: \${$total}\n";
        echo "Expected: \$200.00 (security deposit excluded)\n";
        
        if (abs($total - 200.00) < 0.01) {
            $this->successes[] = 'testKPIExclusionPreserved';
            echo ANSI_GREEN . "✓ PASS: KPI exclusion correctly filters security deposits\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testKPIExclusionPreserved',
                'expected' => 200.00,
                'actual' => $total,
                'message' => "KPI exclusion filter not working correctly"
            ];
            echo ANSI_RED . "✗ FAIL: Expected \$200.00 but got \${$total}\n" . ANSI_RESET;
        }
    }
    
    /**
     * Property 4: Date filtering applies correctly
     * 
     * Validates: Requirement 3.5 - Date filtering uses posted_at field
     */
    public function testDateFilteringPreserved() {
        echo "\n--- Property 4: Date filtering preserved ---\n";
        
        $vehicleId = $this->testVehicleIds[3];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create expenses: one inside period, one outside
        $this->createReservation($vehicleId, null, 300.00, '2026-03-20 10:00:00');
        $this->createReservation($vehicleId, null, 400.00, '2026-02-10 10:00:00'); // Outside period
        
        echo "Created expenses: \$300 (inside period) + \$400 (outside period)\n";
        echo "Period: {$periodStart} to {$periodEnd}\n";
        
        // Calculate expenses (should only include inside-period expense)
        $expenses = $this->calculateVehicleExpenses($periodStart, $periodEnd);
        $total = $expenses[$vehicleId] ?? 0.0;
        
        echo "Calculated total: \${$total}\n";
        echo "Expected: \$300.00 (only inside-period expense)\n";
        
        if (abs($total - 300.00) < 0.01) {
            $this->successes[] = 'testDateFilteringPreserved';
            echo ANSI_GREEN . "✓ PASS: Date filtering correctly excludes out-of-period expenses\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testDateFilteringPreserved',
                'expected' => 300.00,
                'actual' => $total,
                'message' => "Date filtering not working correctly"
            ];
            echo ANSI_RED . "✗ FAIL: Expected \$300.00 but got \${$total}\n" . ANSI_RESET;
        }
    }
    
    /**
     * Property 5: Income calculations remain unchanged
     * 
     * Validates: Requirement 3.2 - Income queries only reservation-linked income
     */
    public function testIncomeCalculationPreserved() {
        echo "\n--- Property 5: Income calculations preserved ---\n";
        
        $vehicleId = $this->testVehicleIds[4];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create reservation with income
        $this->createReservation($vehicleId, 800.00, null, '2026-03-20 10:00:00');
        $this->createReservation($vehicleId, 600.00, null, '2026-03-25 14:00:00');
        
        echo "Created 2 reservation-linked income entries: \$800 + \$600 = \$1400 total\n";
        
        // Calculate income
        $income = $this->calculateVehicleIncome($periodStart, $periodEnd);
        $total = $income[$vehicleId] ?? 0.0;
        
        echo "Calculated total: \${$total}\n";
        echo "Expected: \$1400.00\n";
        
        if (abs($total - 1400.00) < 0.01) {
            $this->successes[] = 'testIncomeCalculationPreserved';
            echo ANSI_GREEN . "✓ PASS: Income calculation unchanged\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testIncomeCalculationPreserved',
                'expected' => 1400.00,
                'actual' => $total,
                'message' => "Income calculation changed unexpectedly"
            ];
            echo ANSI_RED . "✗ FAIL: Expected \$1400.00 but got \${$total}\n" . ANSI_RESET;
        }
    }
    
    /**
     * Property 6: Expense details drill-down preserved
     * 
     * Validates: Requirement 3.1 - Drill-down panel shows reservation expense entries
     */
    public function testExpenseDetailsPreserved() {
        echo "\n--- Property 6: Expense details drill-down preserved ---\n";
        
        // Reuse vehicle from Property 1 which has 2 reservation expenses
        $vehicleId = $this->testVehicleIds[0];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Get expense details
        $details = $this->getVehicleExpenseDetails($vehicleId, $periodStart, $periodEnd);
        $count = count($details);
        $total = array_sum(array_column($details, 'amount'));
        
        echo "Retrieved {$count} expense entries, total: \${$total}\n";
        echo "Expected: 2 entries, \$500.00 total\n";
        
        if ($count === 2 && abs($total - 500.00) < 0.01) {
            $this->successes[] = 'testExpenseDetailsPreserved';
            echo ANSI_GREEN . "✓ PASS: Expense details drill-down preserved\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testExpenseDetailsPreserved',
                'expected' => "2 entries, \$500.00",
                'actual' => "{$count} entries, \${$total}",
                'message' => "Expense details drill-down changed unexpectedly"
            ];
            echo ANSI_RED . "✗ FAIL: Expected 2 entries/\$500.00 but got {$count} entries/\${$total}\n" . ANSI_RESET;
        }
    }
    
    /**
     * Property 7: Income details drill-down preserved
     * 
     * Validates: Requirement 3.4 - Income drill-down shows reservation-linked income with client names
     */
    public function testIncomeDetailsPreserved() {
        echo "\n--- Property 7: Income details drill-down preserved ---\n";
        
        // Reuse vehicle from Property 5 which has 2 reservation income entries
        $vehicleId = $this->testVehicleIds[4];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Get income details
        $details = $this->getVehicleIncomeDetails($vehicleId, $periodStart, $periodEnd);
        $count = count($details);
        $total = array_sum(array_column($details, 'amount'));
        
        // Check that client_name and reservation_id are present
        $hasClientName = !empty($details[0]['client_name'] ?? '');
        $hasReservationId = !empty($details[0]['reservation_id'] ?? 0);
        
        echo "Retrieved {$count} income entries, total: \${$total}\n";
        echo "Client name present: " . ($hasClientName ? 'Yes' : 'No') . "\n";
        echo "Reservation ID present: " . ($hasReservationId ? 'Yes' : 'No') . "\n";
        echo "Expected: 2 entries, \$1400.00 total, with client names and reservation IDs\n";
        
        if ($count === 2 && abs($total - 1400.00) < 0.01 && $hasClientName && $hasReservationId) {
            $this->successes[] = 'testIncomeDetailsPreserved';
            echo ANSI_GREEN . "✓ PASS: Income details drill-down preserved\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testIncomeDetailsPreserved',
                'expected' => "2 entries, \$1400.00, with client names and reservation IDs",
                'actual' => "{$count} entries, \${$total}, client_name: " . ($hasClientName ? 'present' : 'missing') . ", reservation_id: " . ($hasReservationId ? 'present' : 'missing'),
                'message' => "Income details drill-down changed unexpectedly"
            ];
            echo ANSI_RED . "✗ FAIL: Income details structure changed\n" . ANSI_RESET;
        }
    }
    
    /**
     * Run all tests
     */
    public function run() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo TEST_NAME . "\n";
        echo str_repeat("=", 80) . "\n";
        echo "\nThese tests observe and capture CURRENT behavior on UNFIXED code.\n";
        echo "They MUST PASS on unfixed code to establish the baseline to preserve.\n";
        echo "After the fix, these tests MUST STILL PASS to confirm no regressions.\n\n";
        
        try {
            $this->setupTestData();
            
            // Run all property tests
            $this->testReservationExpensesPreserved();
            $this->testZeroExpensesPreserved();
            $this->testKPIExclusionPreserved();
            $this->testDateFilteringPreserved();
            $this->testIncomeCalculationPreserved();
            $this->testExpenseDetailsPreserved();
            $this->testIncomeDetailsPreserved();
            
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
            echo "\n" . ANSI_RED . "PRESERVATION FAILURES:\n" . ANSI_RESET;
            foreach ($this->failures as $failure) {
                echo "\n  Test: {$failure['test']}\n";
                echo "  Expected: {$failure['expected']}\n";
                echo "  Actual: {$failure['actual']}\n";
                echo "  Message: {$failure['message']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ PRESERVATION TESTS FAILED\n" . ANSI_RESET;
            echo "The baseline behavior is not as expected. This may indicate:\n";
            echo "1. The unfixed code has other issues beyond the known bug\n";
            echo "2. The test assumptions need adjustment\n";
            echo "3. The preservation properties need refinement\n";
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL PRESERVATION TESTS PASSED\n" . ANSI_RESET;
            echo "Baseline behavior successfully captured and validated.\n";
            echo "These properties MUST continue to pass after implementing the fix.\n";
            exit(0);
        }
    }
}

// Run the test
$test = new PreservationTest();
$test->run();
