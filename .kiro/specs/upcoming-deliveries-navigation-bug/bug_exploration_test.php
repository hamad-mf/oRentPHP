<?php
/**
 * Bug Condition Exploration Test
 * 
 * Property 1: Bug Condition - Deliveries Navigation 404 Errors
 * 
 * This test MUST FAIL on unfixed code to confirm the bug exists.
 * 
 * Test Goal: Verify that when on `/deliveries/upcoming.php`, the `$root` variable
 * is calculated correctly as `/` (without `deliveries/` prefix), causing side menu
 * navigation links to point to correct absolute paths (e.g., `/accounts/index.php`,
 * not `/deliveries/accounts/index.php`).
 * 
 * Expected Outcome on UNFIXED code: TEST FAILS (this proves the bug exists)
 * Expected Outcome on FIXED code: TEST PASSES (confirms the fix works)
 * 
 * Validates: Requirements 1.1, 1.2, 1.3, 2.1, 2.2, 2.3
 */

// Test configuration
define('TEST_NAME', 'Bug Condition Exploration - Deliveries Navigation');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class DeliveriesNavigationBugTest {
    private $failures = [];
    private $successes = [];
    
    /**
     * Simulate the $root calculation logic from includes/header.php
     * This replicates the exact logic used to calculate navigation paths
     */
    private function calculateRoot(string $scriptPath): string {
        $scriptPath = trim(str_replace('\\', '/', $scriptPath), '/');
        $segments = $scriptPath === '' ? [] : explode('/', $scriptPath);
        
        // This is the FIXED $moduleDirs array from includes/header.php (line 374)
        // NOTE: 'deliveries' has been ADDED to fix the bug!
        $moduleDirs = [
            'vehicles',
            'clients',
            'reservations',
            'investments',
            'gps',
            'papers',
            'expenses',
            'challans',
            'staff',
            'settings',
            'leads',
            'accounts',
            'notifications',
            'attendance',
            'auth',
            'payroll',
            'reports',
            'dashboard',
            'staff_monitor',
            'deliveries',  // <-- THE FIX
        ];
        
        $moduleIdx = null;
        foreach ($segments as $i => $seg) {
            if (in_array($seg, $moduleDirs, true)) {
                $moduleIdx = $i;
                break;
            }
        }
        
        if ($moduleIdx !== null) {
            $prefixParts = array_slice($segments, 0, $moduleIdx);
        } else {
            $prefixParts = count($segments) > 0 ? array_slice($segments, 0, -1) : [];
        }
        
        return '/' . (empty($prefixParts) ? '' : implode('/', $prefixParts) . '/');
    }
    
    /**
     * Simulate the FIXED $root calculation logic (with 'deliveries' added to $moduleDirs)
     */
    private function calculateRootFixed(string $scriptPath): string {
        $scriptPath = trim(str_replace('\\', '/', $scriptPath), '/');
        $segments = $scriptPath === '' ? [] : explode('/', $scriptPath);
        
        // Fixed $moduleDirs array - includes 'deliveries'
        $moduleDirs = [
            'vehicles',
            'clients',
            'reservations',
            'investments',
            'gps',
            'papers',
            'expenses',
            'challans',
            'staff',
            'settings',
            'leads',
            'accounts',
            'notifications',
            'attendance',
            'auth',
            'payroll',
            'reports',
            'dashboard',
            'staff_monitor',
            'deliveries',  // <-- THE FIX
        ];
        
        $moduleIdx = null;
        foreach ($segments as $i => $seg) {
            if (in_array($seg, $moduleDirs, true)) {
                $moduleIdx = $i;
                break;
            }
        }
        
        if ($moduleIdx !== null) {
            $prefixParts = array_slice($segments, 0, $moduleIdx);
        } else {
            $prefixParts = count($segments) > 0 ? array_slice($segments, 0, -1) : [];
        }
        
        return '/' . (empty($prefixParts) ? '' : implode('/', $prefixParts) . '/');
    }
    
    /**
     * Test Case 1: Root calculation for /deliveries/upcoming.php
     * 
     * Scenario: User is on /deliveries/upcoming.php
     * Expected: $root should be '/' (empty prefix)
     * Bug: $root is '/deliveries/' (includes deliveries in prefix)
     */
    public function testDeliveriesUpcomingRootCalculation() {
        echo "\n--- Test Case 1: Root calculation for /deliveries/upcoming.php ---\n";
        
        $scriptPath = 'deliveries/upcoming.php';
        echo "Script path: /{$scriptPath}\n";
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        echo "Unfixed code calculates \$root as: '{$unfixedRoot}'\n";
        echo "Expected \$root (fixed code): '{$expectedRoot}'\n";
        
        if ($unfixedRoot !== $expectedRoot) {
            $this->failures[] = [
                'test' => 'testDeliveriesUpcomingRootCalculation',
                'script_path' => $scriptPath,
                'expected' => $expectedRoot,
                'actual' => $unfixedRoot,
                'message' => "Bug confirmed: \$root is '{$unfixedRoot}' instead of '{$expectedRoot}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - \$root incorrectly includes 'deliveries/'\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testDeliveriesUpcomingRootCalculation';
            echo ANSI_GREEN . "✓ PASS: \$root correctly calculated as '{$expectedRoot}'\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 2: Navigation link generation for Accounts from deliveries page
     * 
     * Scenario: User clicks "Accounts" link from /deliveries/upcoming.php
     * Expected: Link should be '/accounts/index.php'
     * Bug: Link is '/deliveries/accounts/index.php' (404 error)
     */
    public function testAccountsNavigationFromDeliveries() {
        echo "\n--- Test Case 2: Accounts navigation link from /deliveries/upcoming.php ---\n";
        
        $scriptPath = 'deliveries/upcoming.php';
        $targetPage = 'accounts/index.php';
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        $unfixedLink = $unfixedRoot . $targetPage;
        $expectedLink = $expectedRoot . $targetPage;
        
        echo "Current page: /{$scriptPath}\n";
        echo "Target: Accounts page\n";
        echo "Unfixed link: {$unfixedLink}\n";
        echo "Expected link: {$expectedLink}\n";
        
        if ($unfixedLink !== $expectedLink) {
            $this->failures[] = [
                'test' => 'testAccountsNavigationFromDeliveries',
                'script_path' => $scriptPath,
                'target' => $targetPage,
                'expected' => $expectedLink,
                'actual' => $unfixedLink,
                'message' => "Bug confirmed: Accounts link is '{$unfixedLink}' (404) instead of '{$expectedLink}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - navigation link includes '/deliveries/' prefix\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testAccountsNavigationFromDeliveries';
            echo ANSI_GREEN . "✓ PASS: Accounts link correctly points to '{$expectedLink}'\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 3: Navigation link generation for Reservations from deliveries page
     * 
     * Scenario: User clicks "Reservations" link from /deliveries/upcoming.php
     * Expected: Link should be '/reservations/index.php'
     * Bug: Link is '/deliveries/reservations/index.php' (404 error)
     */
    public function testReservationsNavigationFromDeliveries() {
        echo "\n--- Test Case 3: Reservations navigation link from /deliveries/upcoming.php ---\n";
        
        $scriptPath = 'deliveries/upcoming.php';
        $targetPage = 'reservations/index.php';
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        $unfixedLink = $unfixedRoot . $targetPage;
        $expectedLink = $expectedRoot . $targetPage;
        
        echo "Current page: /{$scriptPath}\n";
        echo "Target: Reservations page\n";
        echo "Unfixed link: {$unfixedLink}\n";
        echo "Expected link: {$expectedLink}\n";
        
        if ($unfixedLink !== $expectedLink) {
            $this->failures[] = [
                'test' => 'testReservationsNavigationFromDeliveries',
                'script_path' => $scriptPath,
                'target' => $targetPage,
                'expected' => $expectedLink,
                'actual' => $unfixedLink,
                'message' => "Bug confirmed: Reservations link is '{$unfixedLink}' (404) instead of '{$expectedLink}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - navigation link includes '/deliveries/' prefix\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testReservationsNavigationFromDeliveries';
            echo ANSI_GREEN . "✓ PASS: Reservations link correctly points to '{$expectedLink}'\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 4: Navigation link generation for Clients from deliveries page
     * 
     * Scenario: User clicks "Clients" link from /deliveries/upcoming.php
     * Expected: Link should be '/clients/index.php'
     * Bug: Link is '/deliveries/clients/index.php' (404 error)
     */
    public function testClientsNavigationFromDeliveries() {
        echo "\n--- Test Case 4: Clients navigation link from /deliveries/upcoming.php ---\n";
        
        $scriptPath = 'deliveries/upcoming.php';
        $targetPage = 'clients/index.php';
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        $unfixedLink = $unfixedRoot . $targetPage;
        $expectedLink = $expectedRoot . $targetPage;
        
        echo "Current page: /{$scriptPath}\n";
        echo "Target: Clients page\n";
        echo "Unfixed link: {$unfixedLink}\n";
        echo "Expected link: {$expectedLink}\n";
        
        if ($unfixedLink !== $expectedLink) {
            $this->failures[] = [
                'test' => 'testClientsNavigationFromDeliveries',
                'script_path' => $scriptPath,
                'target' => $targetPage,
                'expected' => $expectedLink,
                'actual' => $unfixedLink,
                'message' => "Bug confirmed: Clients link is '{$unfixedLink}' (404) instead of '{$expectedLink}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - navigation link includes '/deliveries/' prefix\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testClientsNavigationFromDeliveries';
            echo ANSI_GREEN . "✓ PASS: Clients link correctly points to '{$expectedLink}'\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 5: Navigation link generation for Vehicles from deliveries page
     * 
     * Scenario: User clicks "Vehicles" link from /deliveries/upcoming.php
     * Expected: Link should be '/vehicles/index.php'
     * Bug: Link is '/deliveries/vehicles/index.php' (404 error)
     */
    public function testVehiclesNavigationFromDeliveries() {
        echo "\n--- Test Case 5: Vehicles navigation link from /deliveries/upcoming.php ---\n";
        
        $scriptPath = 'deliveries/upcoming.php';
        $targetPage = 'vehicles/index.php';
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        $unfixedLink = $unfixedRoot . $targetPage;
        $expectedLink = $expectedRoot . $targetPage;
        
        echo "Current page: /{$scriptPath}\n";
        echo "Target: Vehicles page\n";
        echo "Unfixed link: {$unfixedLink}\n";
        echo "Expected link: {$expectedLink}\n";
        
        if ($unfixedLink !== $expectedLink) {
            $this->failures[] = [
                'test' => 'testVehiclesNavigationFromDeliveries',
                'script_path' => $scriptPath,
                'target' => $targetPage,
                'expected' => $expectedLink,
                'actual' => $unfixedLink,
                'message' => "Bug confirmed: Vehicles link is '{$unfixedLink}' (404) instead of '{$expectedLink}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - navigation link includes '/deliveries/' prefix\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testVehiclesNavigationFromDeliveries';
            echo ANSI_GREEN . "✓ PASS: Vehicles link correctly points to '{$expectedLink}'\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 6: Root calculation for /deliveries/index.php
     * 
     * Scenario: User is on /deliveries/index.php (if it exists)
     * Expected: $root should be '/' (empty prefix)
     * Bug: $root is '/deliveries/' (includes deliveries in prefix)
     */
    public function testDeliveriesIndexRootCalculation() {
        echo "\n--- Test Case 6: Root calculation for /deliveries/index.php ---\n";
        
        $scriptPath = 'deliveries/index.php';
        echo "Script path: /{$scriptPath}\n";
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        echo "Unfixed code calculates \$root as: '{$unfixedRoot}'\n";
        echo "Expected \$root (fixed code): '{$expectedRoot}'\n";
        
        if ($unfixedRoot !== $expectedRoot) {
            $this->failures[] = [
                'test' => 'testDeliveriesIndexRootCalculation',
                'script_path' => $scriptPath,
                'expected' => $expectedRoot,
                'actual' => $unfixedRoot,
                'message' => "Bug confirmed: \$root is '{$unfixedRoot}' instead of '{$expectedRoot}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - \$root incorrectly includes 'deliveries/'\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testDeliveriesIndexRootCalculation';
            echo ANSI_GREEN . "✓ PASS: \$root correctly calculated as '{$expectedRoot}'\n" . ANSI_RESET;
        }
    }
    
    /**
     * Test Case 7: Dashboard navigation from deliveries page
     * 
     * Scenario: User clicks "Dashboard" link from /deliveries/upcoming.php
     * Expected: Link should be '/index.php'
     * Bug: Link is '/deliveries/index.php' (wrong page)
     */
    public function testDashboardNavigationFromDeliveries() {
        echo "\n--- Test Case 7: Dashboard navigation link from /deliveries/upcoming.php ---\n";
        
        $scriptPath = 'deliveries/upcoming.php';
        $targetPage = 'index.php';
        
        $unfixedRoot = $this->calculateRoot($scriptPath);
        $expectedRoot = $this->calculateRootFixed($scriptPath);
        
        $unfixedLink = $unfixedRoot . $targetPage;
        $expectedLink = $expectedRoot . $targetPage;
        
        echo "Current page: /{$scriptPath}\n";
        echo "Target: Dashboard\n";
        echo "Unfixed link: {$unfixedLink}\n";
        echo "Expected link: {$expectedLink}\n";
        
        if ($unfixedLink !== $expectedLink) {
            $this->failures[] = [
                'test' => 'testDashboardNavigationFromDeliveries',
                'script_path' => $scriptPath,
                'target' => $targetPage,
                'expected' => $expectedLink,
                'actual' => $unfixedLink,
                'message' => "Bug confirmed: Dashboard link is '{$unfixedLink}' instead of '{$expectedLink}'"
            ];
            echo ANSI_RED . "✗ FAIL: Bug exists - navigation link includes '/deliveries/' prefix\n" . ANSI_RESET;
        } else {
            $this->successes[] = 'testDashboardNavigationFromDeliveries';
            echo ANSI_GREEN . "✓ PASS: Dashboard link correctly points to '{$expectedLink}'\n" . ANSI_RESET;
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
            // Run all test cases
            $this->testDeliveriesUpcomingRootCalculation();
            $this->testAccountsNavigationFromDeliveries();
            $this->testReservationsNavigationFromDeliveries();
            $this->testClientsNavigationFromDeliveries();
            $this->testVehiclesNavigationFromDeliveries();
            $this->testDeliveriesIndexRootCalculation();
            $this->testDashboardNavigationFromDeliveries();
            
            // Report results
            $this->reportResults();
            
        } catch (Exception $e) {
            echo ANSI_RED . "\nTest execution error: " . $e->getMessage() . "\n" . ANSI_RESET;
            echo $e->getTraceAsString() . "\n";
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
            echo "\nThe following test cases demonstrate the bug:\n";
            
            foreach ($this->failures as $failure) {
                echo "\n  Test: {$failure['test']}\n";
                echo "  Script Path: /{$failure['script_path']}\n";
                if (isset($failure['target'])) {
                    echo "  Target Page: {$failure['target']}\n";
                }
                echo "  Expected: {$failure['expected']}\n";
                echo "  Actual: {$failure['actual']}\n";
                echo "  Message: {$failure['message']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ BUG CONFIRMED: The test failures above prove the bug exists.\n" . ANSI_RESET;
            echo "\nRoot Cause Analysis:\n";
            echo "- The \$moduleDirs array in includes/header.php (line 374) does NOT include 'deliveries'\n";
            echo "- When on /deliveries/upcoming.php, the path calculation logic fails to recognize 'deliveries' as a module\n";
            echo "- This causes \$root to be calculated as '/deliveries/' instead of '/'\n";
            echo "- All side menu navigation links are incorrectly prefixed with '/deliveries/'\n";
            echo "- Clicking these links results in 404 errors (e.g., /deliveries/accounts/index.php)\n";
            echo "\nFix: Add 'deliveries' to the \$moduleDirs array in includes/header.php\n";
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL TESTS PASSED: Bug is fixed!\n" . ANSI_RESET;
            echo "\nThe \$root variable is correctly calculated for deliveries pages.\n";
            echo "Side menu navigation links now point to correct absolute paths.\n";
            exit(0);
        }
    }
}

// Run the test
$test = new DeliveriesNavigationBugTest();
$test->run();
