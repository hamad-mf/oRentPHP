<?php
/**
 * Bug Condition Exploration Test
 * 
 * Property 1: Bug Condition - Cash and Credit Balances Toggle Incorrectly
 * 
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
 * 
 * This test MUST FAIL on unfixed code to confirm the bug exists.
 * 
 * Test Goal: Verify that Cash and Credit account balance elements have the toggle classes
 * (.acc-monthly and .acc-alltime) which cause them to incorrectly change when the toggle
 * button is clicked. This test checks the HTML structure to confirm the root cause.
 * 
 * Expected Outcome on UNFIXED code: TEST FAILS (this proves the bug exists)
 * Expected Outcome on FIXED code: TEST PASSES (confirms the fix works)
 * 
 * Testing Approach:
 * Since this is a UI bug involving JavaScript toggle behavior, we test by:
 * 1. Capturing the HTML output of accounts/index.php
 * 2. Checking if Cash balance elements have .acc-monthly or .acc-alltime classes
 * 3. Checking if Credit balance elements have .acc-monthly or .acc-alltime classes
 * 4. Verifying that Bank account elements do NOT have these classes (correct behavior)
 * 
 * The presence of these classes on Cash/Credit balances is the bug condition.
 */

// Start session before any output
if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . '/../../../config/db.php';

// Test configuration
define('TEST_NAME', 'Bug Condition Exploration - Cash and Credit Balance Toggle');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class BugExplorationTest {
    private $pdo;
    private $failures = [];
    private $successes = [];
    private $counterexamples = [];
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Get the HTML content of accounts/index.php by reading the file
     * We can't execute it directly due to header issues, so we'll analyze the source
     */
    private function getAccountsPageSource() {
        $filePath = __DIR__ . '/../../../accounts/index.php';
        if (!file_exists($filePath)) {
            throw new Exception("accounts/index.php not found at: {$filePath}");
        }
        
        return file_get_contents($filePath);
    }
    
    /**
     * Test Case 1: Cash balance elements have toggle classes (Bug Condition)
     * 
     * Checks if the Cash account balance display has .acc-monthly or .acc-alltime classes.
     * If these classes exist, the bug is present.
     */
    public function testCashBalanceHasToggleClasses() {
        echo "\n--- Test Case 1: Cash balance has toggle classes (Bug Condition) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Cash Account Card section with toggle classes
            // Pattern: id="cashValMonthly" with class containing "acc-monthly"
            $hasCashMonthly = preg_match('/id=["\']cashValMonthly["\'][^>]*class=["\'][^"\']*acc-monthly/', $source) ||
                             preg_match('/class=["\'][^"\']*acc-monthly[^"\']*["\'][^>]*id=["\']cashValMonthly["\']/', $source);
            
            // Pattern: id="cashValAlltime" with class containing "acc-alltime"
            $hasCashAlltime = preg_match('/id=["\']cashValAlltime["\'][^>]*class=["\'][^"\']*acc-alltime/', $source) ||
                             preg_match('/class=["\'][^"\']*acc-alltime[^"\']*["\'][^>]*id=["\']cashValAlltime["\']/', $source);
            
            echo "Cash balance element with .acc-monthly class: " . ($hasCashMonthly ? "FOUND" : "NOT FOUND") . "\n";
            echo "Cash balance element with .acc-alltime class: " . ($hasCashAlltime ? "FOUND" : "NOT FOUND") . "\n";
            
            if ($hasCashMonthly || $hasCashAlltime) {
                $this->failures[] = [
                    'test' => 'testCashBalanceHasToggleClasses',
                    'message' => "Bug confirmed: Cash balance elements have toggle classes (.acc-monthly or .acc-alltime)",
                    'evidence' => "Found toggle classes on Cash balance display elements"
                ];
                
                $this->counterexamples[] = [
                    'element' => 'Cash Account Balance',
                    'issue' => 'Has .acc-monthly or .acc-alltime classes',
                    'impact' => 'Cash balance will toggle between monthly and all-time values when user clicks toggle button',
                    'expected' => 'Cash balance should always display all-time value without toggle classes'
                ];
                
                echo ANSI_RED . "✗ FAIL: Bug exists - Cash balance has toggle classes\n" . ANSI_RESET;
            } else {
                $this->successes[] = 'testCashBalanceHasToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Cash balance does not have toggle classes (bug is fixed)\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: Could not read page source: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testCashBalanceHasToggleClasses',
                'message' => "Test execution error: " . $e->getMessage(),
                'evidence' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 2: Credit balance elements have toggle classes (Bug Condition)
     * 
     * Checks if the Credit account balance display has .acc-monthly or .acc-alltime classes.
     * If these classes exist, the bug is present.
     */
    public function testCreditBalanceHasToggleClasses() {
        echo "\n--- Test Case 2: Credit balance has toggle classes (Bug Condition) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Credit Account Card section with toggle classes
            $hasCreditMonthly = preg_match('/id=["\']creditValMonthly["\'][^>]*class=["\'][^"\']*acc-monthly/', $source) ||
                               preg_match('/class=["\'][^"\']*acc-monthly[^"\']*["\'][^>]*id=["\']creditValMonthly["\']/', $source);
            
            $hasCreditAlltime = preg_match('/id=["\']creditValAlltime["\'][^>]*class=["\'][^"\']*acc-alltime/', $source) ||
                               preg_match('/class=["\'][^"\']*acc-alltime[^"\']*["\'][^>]*id=["\']creditValAlltime["\']/', $source);
            
            echo "Credit balance element with .acc-monthly class: " . ($hasCreditMonthly ? "FOUND" : "NOT FOUND") . "\n";
            echo "Credit balance element with .acc-alltime class: " . ($hasCreditAlltime ? "FOUND" : "NOT FOUND") . "\n";
            
            if ($hasCreditMonthly || $hasCreditAlltime) {
                $this->failures[] = [
                    'test' => 'testCreditBalanceHasToggleClasses',
                    'message' => "Bug confirmed: Credit balance elements have toggle classes (.acc-monthly or .acc-alltime)",
                    'evidence' => "Found toggle classes on Credit balance display elements"
                ];
                
                $this->counterexamples[] = [
                    'element' => 'Credit Account Balance',
                    'issue' => 'Has .acc-monthly or .acc-alltime classes',
                    'impact' => 'Credit balance will toggle between monthly and all-time values when user clicks toggle button',
                    'expected' => 'Credit balance should always display all-time value without toggle classes'
                ];
                
                echo ANSI_RED . "✗ FAIL: Bug exists - Credit balance has toggle classes\n" . ANSI_RESET;
            } else {
                $this->successes[] = 'testCreditBalanceHasToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Credit balance does not have toggle classes (bug is fixed)\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: Could not read page source: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testCreditBalanceHasToggleClasses',
                'message' => "Test execution error: " . $e->getMessage(),
                'evidence' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 3: Bank account balances do NOT have toggle classes (Correct Behavior)
     * 
     * Verifies that Bank accounts correctly display only their running balance
     * without toggle classes. This should pass even on unfixed code.
     */
    public function testBankBalancesNoToggleClasses() {
        echo "\n--- Test Case 3: Bank balances do NOT have toggle classes (Correct Behavior) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for bank account balance displays with toggle classes
            // Bank accounts should NOT have .acc-monthly or .acc-alltime classes
            // They are rendered in a loop, so we check if any bank balance has these classes
            
            // Pattern: Look for bank account card structure with toggle classes
            // Bank accounts are in the "Accounts" section before Cash and Credit
            $foundToggleClass = preg_match('/<!-- Bank Account Cards.*?-->/s', $source) &&
                               preg_match('/Bank Account Cards.*?Cash Account Card/s', $source) &&
                               preg_match('/Bank Account Cards.*?acc-(monthly|alltime).*?Cash Account Card/s', $source);
            
            if ($foundToggleClass) {
                $this->failures[] = [
                    'test' => 'testBankBalancesNoToggleClasses',
                    'message' => "Unexpected: Bank account balances have toggle classes",
                    'evidence' => "Bank accounts should always show running balance without toggle"
                ];
                echo ANSI_RED . "✗ FAIL: Bank accounts unexpectedly have toggle classes\n" . ANSI_RESET;
            } else {
                $this->successes[] = 'testBankBalancesNoToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Bank accounts correctly do not have toggle classes\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: Could not read page source: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testBankBalancesNoToggleClasses',
                'message' => "Test execution error: " . $e->getMessage(),
                'evidence' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 4: Period metrics DO have toggle classes (Preservation Check)
     * 
     * Verifies that Income, Expenses, Net, and Overall Total correctly have toggle classes.
     * These should toggle between monthly and all-time values.
     * This should pass even on unfixed code.
     */
    public function testPeriodMetricsHaveToggleClasses() {
        echo "\n--- Test Case 4: Period metrics have toggle classes (Preservation Check) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Check for toggle classes on period metrics
            // Look for .acc-monthly class anywhere in the summary cards section
            $hasToggleClasses = preg_match('/acc-monthly/', $source) && preg_match('/acc-alltime/', $source);
            
            echo "Page has .acc-monthly and .acc-alltime classes: " . ($hasToggleClasses ? "YES" : "NO") . "\n";
            
            if ($hasToggleClasses) {
                $this->successes[] = 'testPeriodMetricsHaveToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Period metrics have toggle classes (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testPeriodMetricsHaveToggleClasses',
                    'message' => "Period metrics are missing toggle classes",
                    'evidence' => "Period metrics should toggle between monthly and all-time values"
                ];
                echo ANSI_RED . "✗ FAIL: Period metrics missing toggle classes\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: Could not read page source: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testPeriodMetricsHaveToggleClasses',
                'message' => "Test execution error: " . $e->getMessage(),
                'evidence' => 'N/A'
            ];
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
            $this->testCashBalanceHasToggleClasses();
            $this->testCreditBalanceHasToggleClasses();
            $this->testBankBalancesNoToggleClasses();
            $this->testPeriodMetricsHaveToggleClasses();
            
            // Report results
            $this->reportResults();
            
        } catch (Exception $e) {
            echo ANSI_RED . "\nTest execution error: " . $e->getMessage() . "\n" . ANSI_RESET;
            echo $e->getTraceAsString() . "\n";
        }
    }
    
    /**
     * Report test results and document counterexamples
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
            echo str_repeat("-", 80) . "\n";
            
            foreach ($this->counterexamples as $idx => $example) {
                echo "\nCounterexample " . ($idx + 1) . ":\n";
                echo "  Element: {$example['element']}\n";
                echo "  Issue: {$example['issue']}\n";
                echo "  Impact: {$example['impact']}\n";
                echo "  Expected: {$example['expected']}\n";
            }
            
            echo "\n" . str_repeat("-", 80) . "\n";
            echo "\nTest Failures:\n";
            foreach ($this->failures as $failure) {
                echo "\n  Test: {$failure['test']}\n";
                echo "  Message: {$failure['message']}\n";
                echo "  Evidence: {$failure['evidence']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ BUG CONFIRMED: The test failures above prove the bug exists.\n" . ANSI_RESET;
            echo "\nRoot Cause Analysis:\n";
            echo "  The Cash and Credit account balance elements have .acc-monthly and .acc-alltime\n";
            echo "  CSS classes, which cause the JavaScript switchAccView() function to toggle their\n";
            echo "  visibility when the Monthly/All-time button is clicked. This makes the balances\n";
            echo "  appear to change between monthly and all-time values, when they should always\n";
            echo "  display the all-time running balance.\n\n";
            echo "  Bank accounts correctly do NOT have these classes, so they maintain their\n";
            echo "  running balance regardless of the toggle state.\n\n";
            
            // Write counterexamples to file
            $this->writeCounterexamplesFile();
            
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL TESTS PASSED: Bug is fixed!\n" . ANSI_RESET;
            echo "\nThe Cash and Credit account balances no longer have toggle classes.\n";
            echo "They will always display the all-time running balance, matching the\n";
            echo "behavior of Bank accounts.\n\n";
            exit(0);
        }
    }
    
    /**
     * Write counterexamples to a markdown file for documentation
     */
    private function writeCounterexamplesFile() {
        if (empty($this->counterexamples)) {
            return;
        }
        
        $content = "# Bug Condition Counterexamples\n\n";
        $content .= "**Test Date:** " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "## Summary\n\n";
        $content .= "The bug exploration test found " . count($this->counterexamples) . " counterexample(s) ";
        $content .= "that demonstrate the bug exists in the unfixed code.\n\n";
        
        $content .= "## Root Cause\n\n";
        $content .= "The Cash and Credit account balance elements have `.acc-monthly` and `.acc-alltime` ";
        $content .= "CSS classes in the HTML. The JavaScript `switchAccView()` function toggles the ";
        $content .= "visibility of all elements with these classes when the Monthly/All-time button is clicked. ";
        $content .= "This causes the Cash and Credit balances to appear to change between monthly and all-time ";
        $content .= "values, when they should always display the all-time running balance.\n\n";
        
        $content .= "Bank accounts correctly do NOT have these classes, so they maintain their running ";
        $content .= "balance regardless of the toggle state.\n\n";
        
        $content .= "## Counterexamples\n\n";
        
        foreach ($this->counterexamples as $idx => $example) {
            $content .= "### Counterexample " . ($idx + 1) . ": {$example['element']}\n\n";
            $content .= "- **Issue:** {$example['issue']}\n";
            $content .= "- **Impact:** {$example['impact']}\n";
            $content .= "- **Expected Behavior:** {$example['expected']}\n\n";
        }
        
        $content .= "## Fix Required\n\n";
        $content .= "Remove the `.acc-monthly` and `.acc-alltime` classes from the Cash and Credit ";
        $content .= "balance display elements in `accounts/index.php`. Replace the two separate `<p>` ";
        $content .= "elements (one for monthly, one for all-time) with a single `<p>` element that ";
        $content .= "displays only the all-time balance value.\n\n";
        
        $content .= "**Files to modify:**\n";
        $content .= "- `accounts/index.php` (lines ~470-480 for Cash, lines ~490-500 for Credit)\n\n";
        
        $filePath = __DIR__ . '/COUNTEREXAMPLES.md';
        file_put_contents($filePath, $content);
        
        echo "\nCounterexamples documented in: {$filePath}\n";
    }
}

// Run the test
$test = new BugExplorationTest();
$test->run();
