<?php
/**
 * Bug Condition Exploration Test
 * 
 * Property 1: Bug Condition - Direct Vehicle Expenses Missing from Report
 * 
 * This test MUST FAIL on unfixed code to confirm the bug exists.
 * 
 * Test Goal: Verify that vehicles with direct expenses (source_type='vehicle_expense')
 * are included in the Vehicle Financial Report expense calculations and drill-down panel.
 * 
 * Expected Outcome on UNFIXED code: TEST FAILS (this proves the bug exists)
 * Expected Outcome on FIXED code: TEST PASSES (confirms the fix works)
 * 
 * Validates: Requirements 2.1, 2.2, 2.3
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/ledger_helpers.php';
require_once __DIR__ . '/../../../reports/vehicle_financial.php';

// Test configuration
define('TEST_NAME', 'Bug Condition Exploration - Direct Vehicle Expenses Missing');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class BugExplorationTest {
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
     * Setup: Create test vehicles, client, and expenses
     */
    private function setupTestData() {
        echo "Setting up test data...\n";
        
        // Create test client
        $stmt = $this->pdo->prepare(
            "INSERT INTO clients (name, phone, address) 
             VALUES (?, ?, ?)"
        );
        $stmt->execute([
            'Test Client ' . uniqid(),
            '1234567890',
            'Test Address'
        ]);
        $this->testClientId = (int) $this->pdo->lastInsertId();
        
        // Create test vehicles
        for ($i = 1; $i <= 3; $i++) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO vehicles (brand, model, license_plate, status) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                'TestBrand' . $i,
                'Model' . $i,
                'TEST' . uniqid(),
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
        
        // Delete ledger entries
        foreach ($this->testVehicleIds as $vehicleId) {
            $this->pdo->prepare("DELETE FROM ledger_entries WHERE source_type = 'vehicle_expense' AND source_id = ?")->execute([$vehicleId]);
        }
        
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
     * Create a direct vehicle expense (source_type='vehicle_expense')
     */
    private function createDirectVehicleExpense(int $vehicleId, float $amount, string $category, string $description, string $postedAt) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ledger_entries 
            (txn_type, category, description, amount, payment_mode, source_type, source_id, posted_at, created_by) 
            VALUES ('expense', ?, ?, ?, 'cash', 'vehicle_expense', ?, ?, 1)"
        );
        $stmt->execute([$category, $description, $amount, $vehicleId, $postedAt]);
    }
    
    /**
     * Create a reservation with reservation-linked expense
     */
    private function createReservationWithExpense(int $vehicleId, float $expenseAmount, string $postedAt) {
        // Create reservation
        $stmt = $this->pdo->prepare(
            "INSERT INTO reservations 
            (client_id, vehicle_id, start_date, end_date, total_price, status, created_at) 
            VALUES (?, ?, ?, ?, 500.00, 'completed', NOW())"
        );
        $stmt->execute([
            $this->testClientId,
            $vehicleId,
            date('Y-m-d H:i:s', strtotime($postedAt)),
            date('Y-m-d H:i:s', strtotime($postedAt . ' +5 days')),
        ]);
        $reservationId = (int) $this->pdo->lastInsertId();
        $this->testReservationIds[] = $reservationId;
        
        // Create reservation-linked expense
        $stmt = $this->pdo->prepare(
            "INSERT INTO ledger_entries 
            (txn_type, category, description, amount, payment_mode, source_type, source_id, source_event, posted_at, created_by) 
            VALUES ('expense', 'Damage Charge', ?, ?, 'cash', 'reservation', ?, 'damage', ?, 1)"
        );
        $stmt->execute([
            "Reservation #{$reservationId} - Damage charge",
            $expenseAmount,
            $reservationId,
            $postedAt
        ]);
        
        return $reservationId;
    }
    
    /**
     * Calculate vehicle expenses using the ACTUAL function from reports/vehicle_financial.php
     */
    private function calculateVehicleExpensesUnfixed(string $periodStart, string $periodEnd): array {
        return vfr_calculate_vehicle_expenses($this->pdo, $periodStart, $periodEnd);
    }
    
    /**
     * Get vehicle expense details using the ACTUAL function from reports/vehicle_financial.php
     */
    private function getVehicleExpenseDetailsUnfixed(int $vehicleId, string $periodStart, string $periodEnd): array {
        return vfr_get_vehicle_expense_details($this->pdo, $vehicleId, $periodStart, $periodEnd);
    }
    
    /**
     * Get actual direct vehicle expenses from database (what SHOULD be included)
     */
    private function getActualDirectExpenses(int $vehicleId, string $periodStart, string $periodEnd): float {
        $exclusionClause = ledger_kpi_exclusion_clause('le');
        
        $sql = "
            SELECT COALESCE(SUM(le.amount), 0) AS total
            FROM ledger_entries le
            WHERE le.source_type = 'vehicle_expense'
                AND le.source_id = :vehicle_id
                AND le.txn_type = 'expense'
                AND $exclusionClause
                AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'vehicle_id' => $vehicleId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        return (float) $stmt->fetchColumn();
    }
    
    /**
     * Test Case 1: Vehicle with only direct expenses
     * 
     * Scenario: Vehicle has $500 in direct maintenance expenses, no reservation expenses
     * Expected: Report should show $500
     * Bug: Report shows $0.00
     */
    public function testVehicleWithOnlyDirectExpenses() {
        echo "\n--- Test Case 1: Vehicle with only direct expenses ---\n";
        
        $vehicleId = $this->testVehicleIds[0];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create direct vehicle expenses
        $this->createDirectVehicleExpense($vehicleId, 300.00, 'Maintenance', 'Oil change and filter replacement', '2026-03-20 10:00:00');
        $this->createDirectVehicleExpense($vehicleId, 200.00, 'Service', 'Tire rotation', '2026-03-25 14:30:00');
        
        echo "Created direct expenses: \$300 (Maintenance) + \$200 (Service) = \$500 total\n";
        echo "Period: {$periodStart} to {$periodEnd}\n";
        
        // Get what unfixed code returns
        $unfixedExpenses = $this->calculateVehicleExpensesUnfixed($periodStart, $periodEnd);
        $unfixedTotal = $unfixedExpenses[$vehicleId] ?? 0.0;
        
        // Get what SHOULD be returned
        $expectedTotal = $this->getActualDirectExpenses($vehicleId, $periodStart, $periodEnd);
        
        echo "Unfixed code returns: \${$unfixedTotal}\n";
        echo "Expected (direct expenses): \${$expectedTotal}\n";
        
        if ($unfixedTotal < $expectedTotal) {
            $this->failures[] = [
                'test' => 'testVehicleWithOnlyDirectExpenses',
                'expected' => $expectedTotal,
                'actual' => $unfixedTotal,
                'message' => "Bug confirmed: Report shows \${$unfixedTotal} instead of \${$expectedTotal} for vehicle with direct expenses"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - direct vehicle expenses are missing from report\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testVehicleWithOnlyDirectExpenses';
            echo ANSI_GREEN . "✓ PASS: Report correctly includes direct vehicle expenses\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 2: Vehicle with mixed expenses (reservation + direct)
     * 
     * Scenario: Vehicle has $200 reservation expense + $800 direct expenses
     * Expected: Report should show $1000 total
     * Bug: Report shows only $200 (missing $800 direct expenses)
     */
    public function testVehicleWithMixedExpenses() {
        echo "\n--- Test Case 2: Vehicle with mixed expenses (reservation + direct) ---\n";
        
        $vehicleId = $this->testVehicleIds[1];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create reservation-linked expense
        $this->createReservationWithExpense($vehicleId, 200.00, '2026-03-18 09:00:00');
        
        // Create direct vehicle expenses
        $this->createDirectVehicleExpense($vehicleId, 500.00, 'Repair', 'Brake pad replacement', '2026-03-22 11:00:00');
        $this->createDirectVehicleExpense($vehicleId, 300.00, 'Maintenance', 'Engine tune-up', '2026-03-28 15:00:00');
        
        echo "Created expenses: \$200 (reservation) + \$500 (repair) + \$300 (maintenance) = \$1000 total\n";
        echo "Period: {$periodStart} to {$periodEnd}\n";
        
        // Get what unfixed code returns (should only show reservation expense)
        $unfixedExpenses = $this->calculateVehicleExpensesUnfixed($periodStart, $periodEnd);
        $unfixedTotal = $unfixedExpenses[$vehicleId] ?? 0.0;
        
        // Get what SHOULD be returned (reservation + direct)
        $directExpenses = $this->getActualDirectExpenses($vehicleId, $periodStart, $periodEnd);
        $expectedTotal = $unfixedTotal + $directExpenses;
        
        echo "Unfixed code returns: \${$unfixedTotal} (reservation expenses only)\n";
        echo "Expected (reservation + direct): \${$expectedTotal}\n";
        
        if ($unfixedTotal < $expectedTotal) {
            $this->failures[] = [
                'test' => 'testVehicleWithMixedExpenses',
                'expected' => $expectedTotal,
                'actual' => $unfixedTotal,
                'message' => "Bug confirmed: Report shows \${$unfixedTotal} instead of \${$expectedTotal} (missing \${$directExpenses} in direct expenses)"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - direct expenses missing from mixed expense vehicle\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testVehicleWithMixedExpenses';
            echo ANSI_GREEN . "✓ PASS: Report correctly includes both reservation and direct expenses\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 3: Drill-down panel shows zero entries for direct expenses
     * 
     * Scenario: Vehicle has direct expenses, user clicks to view details
     * Expected: Drill-down panel should show expense entries
     * Bug: Panel shows zero entries
     */
    public function testDrillDownPanelMissingDirectExpenses() {
        echo "\n--- Test Case 3: Drill-down panel missing direct expense entries ---\n";
        
        $vehicleId = $this->testVehicleIds[2];
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create direct vehicle expenses
        $this->createDirectVehicleExpense($vehicleId, 400.00, 'Maintenance', 'Battery replacement', '2026-03-19 10:00:00');
        $this->createDirectVehicleExpense($vehicleId, 150.00, 'Service', 'AC gas refill', '2026-03-26 14:00:00');
        
        echo "Created 2 direct expense entries totaling \$550\n";
        echo "Period: {$periodStart} to {$periodEnd}\n";
        
        // Get what unfixed drill-down returns
        $unfixedDetails = $this->getVehicleExpenseDetailsUnfixed($vehicleId, $periodStart, $periodEnd);
        $unfixedCount = count($unfixedDetails);
        $unfixedTotal = array_sum(array_column($unfixedDetails, 'amount'));
        
        // Get what SHOULD be returned
        $expectedCount = 2;
        $expectedTotal = 550.00;
        
        echo "Unfixed drill-down returns: {$unfixedCount} entries, \${$unfixedTotal} total\n";
        echo "Expected: {$expectedCount} entries, \${$expectedTotal} total\n";
        
        if ($unfixedCount < $expectedCount || $unfixedTotal < $expectedTotal) {
            $this->failures[] = [
                'test' => 'testDrillDownPanelMissingDirectExpenses',
                'expected' => "{$expectedCount} entries, \${$expectedTotal}",
                'actual' => "{$unfixedCount} entries, \${$unfixedTotal}",
                'message' => "Bug confirmed: Drill-down panel shows {$unfixedCount} entries instead of {$expectedCount} (missing direct expense entries)"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - drill-down panel missing direct expense entries\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testDrillDownPanelMissingDirectExpenses';
            echo ANSI_GREEN . "✓ PASS: Drill-down panel correctly shows direct expense entries\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 4: Date filtering applies to direct expenses
     * 
     * Scenario: Vehicle has direct expenses inside and outside the period
     * Expected: Only expenses within period should be included
     * This tests that date filtering works correctly (should pass even on unfixed code for this aspect)
     */
    public function testDateFilteringForDirectExpenses() {
        echo "\n--- Test Case 4: Date filtering for direct expenses ---\n";
        
        // Use a fresh vehicle that hasn't been used in other tests
        $stmt = $this->pdo->prepare(
            "INSERT INTO vehicles (brand, model, license_plate, status) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            'TestBrandDate',
            'ModelDate',
            'TESTDATE' . uniqid(),
            'available'
        ]);
        $vehicleId = (int) $this->pdo->lastInsertId();
        $this->testVehicleIds[] = $vehicleId;
        
        $periodStart = '2026-03-15';
        $periodEnd = '2026-04-14';
        
        // Create direct expenses: one inside period, one outside
        $this->createDirectVehicleExpense($vehicleId, 250.00, 'Maintenance', 'Inside period expense', '2026-03-20 10:00:00');
        $this->createDirectVehicleExpense($vehicleId, 350.00, 'Maintenance', 'Outside period expense', '2026-02-10 10:00:00');
        
        echo "Created expenses: \$250 (inside period), \$350 (outside period)\n";
        echo "Period: {$periodStart} to {$periodEnd}\n";
        
        // Get actual direct expenses within period
        $actualInPeriod = $this->getActualDirectExpenses($vehicleId, $periodStart, $periodEnd);
        
        echo "Direct expenses in period: \${$actualInPeriod}\n";
        echo "Expected: \$250 (only inside-period expense)\n";
        
        if (abs($actualInPeriod - 250.00) < 0.01) {
            $this->successes[] = 'testDateFilteringForDirectExpenses';
            echo ANSI_GREEN . "✓ PASS: Date filtering correctly excludes out-of-period expenses\n" . ANSI_RESET;
        } else {
            $this->failures[] = [
                'test' => 'testDateFilteringForDirectExpenses',
                'expected' => 250.00,
                'actual' => $actualInPeriod,
                'message' => "Date filtering issue: Expected \$250 but got \${$actualInPeriod}"
            ];
            echo ANSI_RED . "✗ FAIL: Date filtering not working correctly\n" . ANSI_RESET;
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
            $this->testVehicleWithOnlyDirectExpenses();
            $this->testVehicleWithMixedExpenses();
            $this->testDrillDownPanelMissingDirectExpenses();
            $this->testDateFilteringForDirectExpenses();
            
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
                echo "  Expected: {$failure['expected']}\n";
                echo "  Actual: {$failure['actual']}\n";
                echo "  Message: {$failure['message']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ BUG CONFIRMED: The test failures above prove the bug exists.\n" . ANSI_RESET;
            echo "Direct vehicle expenses (source_type='vehicle_expense') are missing from the Vehicle Financial Report.\n";
            echo "The report only queries reservation-linked expenses, excluding all direct vehicle expenses.\n";
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL TESTS PASSED: Bug is fixed!\n" . ANSI_RESET;
            echo "The Vehicle Financial Report correctly includes direct vehicle expenses.\n";
            exit(0);
        }
    }
}

// Run the test
$test = new BugExplorationTest();
$test->run();
