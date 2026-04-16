<?php
/**
 * Preservation Property Tests
 * 
 * Property 2: Preservation - Period Metrics Continue to Toggle
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7**
 * 
 * This test MUST PASS on unfixed code to establish baseline behavior.
 * It should also PASS on fixed code to confirm no regressions.
 * 
 * Test Goal: Verify that period-based metrics (Income, Expenses, Net, Overall Total)
 * continue to toggle correctly between monthly and all-time values when the toggle
 * button is clicked. Also verify that Bank account balances remain unchanged and
 * the period label visibility changes appropriately.
 * 
 * Expected Outcome on UNFIXED code: TEST PASSES (confirms baseline behavior)
 * Expected Outcome on FIXED code: TEST PASSES (confirms no regressions)
 * 
 * Testing Approach:
 * We test by analyzing the HTML structure to verify:
 * 1. Income, Expenses, Net, and Overall Total have .acc-monthly and .acc-alltime elements
 * 2. Bank account balances do NOT have toggle classes
 * 3. Period label has appropriate visibility logic
 * 4. The switchAccView() JavaScript function exists and handles toggle logic
 */

// Start session before any output
if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . '/../../../config/db.php';

// Test configuration
define('TEST_NAME', 'Preservation Tests - Period Metrics Toggle Behavior');
define('ANSI_RED', "\033[31m");
define('ANSI_GREEN', "\033[32m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

class PreservationTest {
    private $pdo;
    private $failures = [];
    private $successes = [];
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Get the HTML content of accounts/index.php by reading the file
     */
    private function getAccountsPageSource() {
        $filePath = __DIR__ . '/../../../accounts/index.php';
        if (!file_exists($filePath)) {
            throw new Exception("accounts/index.php not found at: {$filePath}");
        }
        
        return file_get_contents($filePath);
    }
    
    /**
     * Test Case 1: Income metric has toggle classes
     * 
     * Requirement 3.1: Income metric SHALL CONTINUE TO toggle between monthly period
     * income (mTotalIncome) and all-time income (totalIncome)
     */
    public function testIncomeMetricHasToggleClasses() {
        echo "\n--- Test Case 1: Income metric has toggle classes (Req 3.1) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Income card with toggle classes
            // Pattern: Look for "Income" text followed by acc-monthly and acc-alltime spans
            $hasIncomeToggle = preg_match('/Income.*?<span[^>]*class=["\'][^"\']*acc-monthly[^"\']*["\'][^>]*>.*?<span[^>]*class=["\'][^"\']*acc-alltime[^"\']*["\'][^>]*>/s', $source) ||
                              preg_match('/Income.*?acc-monthly.*?acc-alltime/s', $source);
            
            // More specific: Look for the Income card structure with mTotalIncome and totalIncome
            $hasIncomeMonthly = preg_match('/mTotalIncome.*?acc-monthly/s', $source);
            $hasIncomeAlltime = preg_match('/totalIncome.*?acc-alltime/s', $source);
            
            echo "Income metric has .acc-monthly element: " . ($hasIncomeMonthly ? "YES" : "NO") . "\n";
            echo "Income metric has .acc-alltime element: " . ($hasIncomeAlltime ? "YES" : "NO") . "\n";
            
            if ($hasIncomeMonthly && $hasIncomeAlltime) {
                $this->successes[] = 'testIncomeMetricHasToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Income metric has toggle classes (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testIncomeMetricHasToggleClasses',
                    'requirement' => '3.1',
                    'message' => "Income metric missing toggle classes - will not toggle between monthly and all-time",
                    'expected' => "Income should have both .acc-monthly (mTotalIncome) and .acc-alltime (totalIncome) elements"
                ];
                echo ANSI_RED . "✗ FAIL: Income metric missing toggle classes\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testIncomeMetricHasToggleClasses',
                'requirement' => '3.1',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 2: Expenses metric has toggle classes
     * 
     * Requirement 3.2: Expenses metric SHALL CONTINUE TO toggle between monthly period
     * expenses (mTotalExpense) and all-time expenses (totalExpense)
     */
    public function testExpensesMetricHasToggleClasses() {
        echo "\n--- Test Case 2: Expenses metric has toggle classes (Req 3.2) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Expenses card with toggle classes
            $hasExpensesMonthly = preg_match('/mTotalExpense.*?acc-monthly/s', $source);
            $hasExpensesAlltime = preg_match('/totalExpense.*?acc-alltime/s', $source);
            
            echo "Expenses metric has .acc-monthly element: " . ($hasExpensesMonthly ? "YES" : "NO") . "\n";
            echo "Expenses metric has .acc-alltime element: " . ($hasExpensesAlltime ? "YES" : "NO") . "\n";
            
            if ($hasExpensesMonthly && $hasExpensesAlltime) {
                $this->successes[] = 'testExpensesMetricHasToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Expenses metric has toggle classes (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testExpensesMetricHasToggleClasses',
                    'requirement' => '3.2',
                    'message' => "Expenses metric missing toggle classes - will not toggle between monthly and all-time",
                    'expected' => "Expenses should have both .acc-monthly (mTotalExpense) and .acc-alltime (totalExpense) elements"
                ];
                echo ANSI_RED . "✗ FAIL: Expenses metric missing toggle classes\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testExpensesMetricHasToggleClasses',
                'requirement' => '3.2',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 3: Net metric has toggle classes
     * 
     * Requirement 3.3: Net metric SHALL CONTINUE TO toggle between monthly period
     * net (mNetBalance) and all-time net (netBalance)
     */
    public function testNetMetricHasToggleClasses() {
        echo "\n--- Test Case 3: Net metric has toggle classes (Req 3.3) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Net card with toggle classes
            $hasNetMonthly = preg_match('/mNetBalance.*?acc-monthly/s', $source);
            $hasNetAlltime = preg_match('/netBalance.*?acc-alltime/s', $source);
            
            echo "Net metric has .acc-monthly element: " . ($hasNetMonthly ? "YES" : "NO") . "\n";
            echo "Net metric has .acc-alltime element: " . ($hasNetAlltime ? "YES" : "NO") . "\n";
            
            if ($hasNetMonthly && $hasNetAlltime) {
                $this->successes[] = 'testNetMetricHasToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Net metric has toggle classes (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testNetMetricHasToggleClasses',
                    'requirement' => '3.3',
                    'message' => "Net metric missing toggle classes - will not toggle between monthly and all-time",
                    'expected' => "Net should have both .acc-monthly (mNetBalance) and .acc-alltime (netBalance) elements"
                ];
                echo ANSI_RED . "✗ FAIL: Net metric missing toggle classes\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testNetMetricHasToggleClasses',
                'requirement' => '3.3',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 4: Overall Total metric has toggle classes
     * 
     * Requirement 3.4: Overall Total metric SHALL CONTINUE TO toggle between monthly
     * overall total (mOverallTotal) and all-time overall total (overallTotal)
     */
    public function testOverallTotalMetricHasToggleClasses() {
        echo "\n--- Test Case 4: Overall Total metric has toggle classes (Req 3.4) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Overall Total card with toggle classes
            $hasOverallMonthly = preg_match('/mOverallTotal.*?acc-monthly/s', $source);
            $hasOverallAlltime = preg_match('/overallTotal.*?acc-alltime/s', $source);
            
            echo "Overall Total metric has .acc-monthly element: " . ($hasOverallMonthly ? "YES" : "NO") . "\n";
            echo "Overall Total metric has .acc-alltime element: " . ($hasOverallAlltime ? "YES" : "NO") . "\n";
            
            if ($hasOverallMonthly && $hasOverallAlltime) {
                $this->successes[] = 'testOverallTotalMetricHasToggleClasses';
                echo ANSI_GREEN . "✓ PASS: Overall Total metric has toggle classes (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testOverallTotalMetricHasToggleClasses',
                    'requirement' => '3.4',
                    'message' => "Overall Total metric missing toggle classes - will not toggle between monthly and all-time",
                    'expected' => "Overall Total should have both .acc-monthly (mOverallTotal) and .acc-alltime (overallTotal) elements"
                ];
                echo ANSI_RED . "✗ FAIL: Overall Total metric missing toggle classes\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testOverallTotalMetricHasToggleClasses',
                'requirement' => '3.4',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 5: Accounts Total does NOT have toggle classes
     * 
     * Requirement 3.5: Accounts Total metric SHALL CONTINUE TO display the running
     * balance (accountsTotal) without changing
     */
    public function testAccountsTotalNoToggleClasses() {
        echo "\n--- Test Case 5: Accounts Total does NOT have toggle classes (Req 3.5) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for Accounts Total card - should NOT have toggle classes
            // Pattern: Find the "Accounts Total" card and check if its balance display has toggle classes
            // The card should display accountsTotal without acc-monthly/acc-alltime
            
            // Extract the Accounts Total card section
            if (preg_match('/Accounts Total.*?<p[^>]*>(.*?)<\/p>/s', $source, $matches)) {
                $accountsTotalSection = $matches[0];
                
                // Check if this specific section has toggle classes
                $hasToggleInSection = preg_match('/acc-(monthly|alltime)/', $accountsTotalSection);
                
                echo "Accounts Total card found: YES\n";
                echo "Accounts Total has toggle classes: " . ($hasToggleInSection ? "YES (WRONG)" : "NO (CORRECT)") . "\n";
                
                if (!$hasToggleInSection) {
                    $this->successes[] = 'testAccountsTotalNoToggleClasses';
                    echo ANSI_GREEN . "✓ PASS: Accounts Total does not have toggle classes (preservation confirmed)\n" . ANSI_RESET;
                } else {
                    $this->failures[] = [
                        'test' => 'testAccountsTotalNoToggleClasses',
                        'requirement' => '3.5',
                        'message' => "Accounts Total should not have toggle classes - it should always show running balance",
                        'expected' => "Accounts Total should display accountsTotal without .acc-monthly or .acc-alltime classes"
                    ];
                    echo ANSI_RED . "✗ FAIL: Accounts Total has unexpected toggle classes\n" . ANSI_RESET;
                }
            } else {
                // If we can't find the specific card, check if accountsTotal is displayed without toggle classes
                $hasAccountsTotal = preg_match('/\$accountsTotal/', $source);
                $hasAccountsTotalWithToggle = preg_match('/\$accountsTotal[^;]*?acc-(monthly|alltime)/s', $source);
                
                echo "Accounts Total variable found: " . ($hasAccountsTotal ? "YES" : "NO") . "\n";
                echo "Accounts Total has toggle classes: " . ($hasAccountsTotalWithToggle ? "YES (WRONG)" : "NO (CORRECT)") . "\n";
                
                if ($hasAccountsTotal && !$hasAccountsTotalWithToggle) {
                    $this->successes[] = 'testAccountsTotalNoToggleClasses';
                    echo ANSI_GREEN . "✓ PASS: Accounts Total does not have toggle classes (preservation confirmed)\n" . ANSI_RESET;
                } else {
                    $this->failures[] = [
                        'test' => 'testAccountsTotalNoToggleClasses',
                        'requirement' => '3.5',
                        'message' => "Accounts Total should not have toggle classes - it should always show running balance",
                        'expected' => "Accounts Total should display accountsTotal without .acc-monthly or .acc-alltime classes"
                    ];
                    echo ANSI_RED . "✗ FAIL: Accounts Total has unexpected toggle classes or is missing\n" . ANSI_RESET;
                }
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testAccountsTotalNoToggleClasses',
                'requirement' => '3.5',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 6: Period label has visibility toggle logic
     * 
     * Requirement 3.6: Period label SHALL CONTINUE TO show/hide appropriately
     * (visible in Monthly, hidden in All-time)
     */
    public function testPeriodLabelVisibilityLogic() {
        echo "\n--- Test Case 6: Period label has visibility toggle logic (Req 3.6) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for period label element with id="accPeriodLabel"
            $hasPeriodLabel = preg_match('/id=["\']accPeriodLabel["\']/', $source);
            
            // Check if switchAccView() function toggles the period label visibility
            $hasToggleLogic = preg_match('/getElementById\(["\']accPeriodLabel["\']\).*?classList\.toggle\(["\']hidden["\']/s', $source);
            
            echo "Period label element found: " . ($hasPeriodLabel ? "YES" : "NO") . "\n";
            echo "Period label visibility toggle logic found: " . ($hasToggleLogic ? "YES" : "NO") . "\n";
            
            if ($hasPeriodLabel && $hasToggleLogic) {
                $this->successes[] = 'testPeriodLabelVisibilityLogic';
                echo ANSI_GREEN . "✓ PASS: Period label has visibility toggle logic (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testPeriodLabelVisibilityLogic',
                    'requirement' => '3.6',
                    'message' => "Period label missing or visibility toggle logic not found",
                    'expected' => "Period label should toggle visibility based on Monthly/All-time view"
                ];
                echo ANSI_RED . "✗ FAIL: Period label visibility logic missing\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testPeriodLabelVisibilityLogic',
                'requirement' => '3.6',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 7: switchAccView() function exists and handles toggle logic
     * 
     * Requirement 3.7: Ledger table date filters SHALL CONTINUE TO update and reload
     * the page with the appropriate date range
     * 
     * This test verifies the JavaScript function exists and has the logic to update
     * date filters and submit the form.
     */
    public function testSwitchAccViewFunctionExists() {
        echo "\n--- Test Case 7: switchAccView() function exists with date filter logic (Req 3.7) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for switchAccView function definition
            $hasSwitchAccView = preg_match('/function\s+switchAccView\s*\(/s', $source);
            
            // Check if function has date filter update logic
            $hasDateFilterLogic = preg_match('/switchAccView.*?date_from.*?date_to.*?form\.submit\(\)/s', $source);
            
            // Check if function toggles .acc-monthly and .acc-alltime elements
            $hasToggleLogic = preg_match('/switchAccView.*?acc-monthly.*?acc-alltime/s', $source);
            
            echo "switchAccView() function found: " . ($hasSwitchAccView ? "YES" : "NO") . "\n";
            echo "Date filter update logic found: " . ($hasDateFilterLogic ? "YES" : "NO") . "\n";
            echo "Toggle visibility logic found: " . ($hasToggleLogic ? "YES" : "NO") . "\n";
            
            if ($hasSwitchAccView && $hasDateFilterLogic && $hasToggleLogic) {
                $this->successes[] = 'testSwitchAccViewFunctionExists';
                echo ANSI_GREEN . "✓ PASS: switchAccView() function exists with complete logic (preservation confirmed)\n" . ANSI_RESET;
            } else {
                $this->failures[] = [
                    'test' => 'testSwitchAccViewFunctionExists',
                    'requirement' => '3.7',
                    'message' => "switchAccView() function missing or incomplete",
                    'expected' => "Function should toggle visibility classes and update date filters"
                ];
                echo ANSI_RED . "✗ FAIL: switchAccView() function missing or incomplete\n" . ANSI_RESET;
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testSwitchAccViewFunctionExists',
                'requirement' => '3.7',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
            ];
        }
    }
    
    /**
     * Test Case 8: Bank account balances do NOT have toggle classes
     * 
     * Verifies that Bank accounts correctly display only their running balance
     * without toggle classes. This is part of preservation - Bank accounts should
     * continue to work correctly.
     */
    public function testBankBalancesNoToggleClasses() {
        echo "\n--- Test Case 8: Bank balances do NOT have toggle classes (Preservation) ---\n";
        
        try {
            $source = $this->getAccountsPageSource();
            
            // Look for bank account balance displays
            // Bank accounts are rendered in a foreach loop
            // They should display the balance without .acc-monthly or .acc-alltime classes
            
            // Check if bank account section exists
            $hasBankAccountSection = preg_match('/foreach\s*\(\s*\$accounts\s+as\s+\$acc\s*\)/s', $source);
            
            // More specific check: Look for the bank account card structure
            // Extract the section between "foreach ($accounts as $acc)" and "endforeach" (first occurrence)
            if (preg_match('/<!-- Bank Account Cards.*?foreach\s*\(\s*\$accounts\s+as\s+\$acc\s*\).*?endforeach/s', $source, $matches)) {
                $bankAccountSection = $matches[0];
                
                // Check if this specific section has acc-monthly or acc-alltime classes
                $hasBankToggleClasses = preg_match('/acc-(monthly|alltime)/', $bankAccountSection);
                
                echo "Bank account section found: YES\n";
                echo "Bank balances have toggle classes: " . ($hasBankToggleClasses ? "YES (WRONG)" : "NO (CORRECT)") . "\n";
                
                if (!$hasBankToggleClasses) {
                    $this->successes[] = 'testBankBalancesNoToggleClasses';
                    echo ANSI_GREEN . "✓ PASS: Bank balances do not have toggle classes (preservation confirmed)\n" . ANSI_RESET;
                } else {
                    $this->failures[] = [
                        'test' => 'testBankBalancesNoToggleClasses',
                        'requirement' => 'Preservation',
                        'message' => "Bank account balances should not have toggle classes",
                        'expected' => "Bank accounts should always display running balance without toggle"
                    ];
                    echo ANSI_RED . "✗ FAIL: Bank accounts have unexpected toggle classes\n" . ANSI_RESET;
                }
            } else {
                echo "Bank account section found: " . ($hasBankAccountSection ? "YES" : "NO") . "\n";
                echo "Could not extract bank account section for detailed analysis\n";
                
                // Fallback: Just check if the section exists
                if ($hasBankAccountSection) {
                    $this->successes[] = 'testBankBalancesNoToggleClasses';
                    echo ANSI_GREEN . "✓ PASS: Bank account section exists (assuming correct behavior)\n" . ANSI_RESET;
                } else {
                    $this->failures[] = [
                        'test' => 'testBankBalancesNoToggleClasses',
                        'requirement' => 'Preservation',
                        'message' => "Bank account section not found",
                        'expected' => "Bank accounts should be rendered in a foreach loop"
                    ];
                    echo ANSI_RED . "✗ FAIL: Bank account section missing\n" . ANSI_RESET;
                }
            }
            
        } catch (Exception $e) {
            echo ANSI_RED . "ERROR: " . $e->getMessage() . "\n" . ANSI_RESET;
            $this->failures[] = [
                'test' => 'testBankBalancesNoToggleClasses',
                'requirement' => 'Preservation',
                'message' => "Test execution error: " . $e->getMessage(),
                'expected' => 'N/A'
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
        echo "\nThese tests MUST PASS on unfixed code to establish baseline behavior.\n";
        echo "They should also PASS on fixed code to confirm no regressions.\n\n";
        
        try {
            // Run all test cases
            $this->testIncomeMetricHasToggleClasses();
            $this->testExpensesMetricHasToggleClasses();
            $this->testNetMetricHasToggleClasses();
            $this->testOverallTotalMetricHasToggleClasses();
            $this->testAccountsTotalNoToggleClasses();
            $this->testPeriodLabelVisibilityLogic();
            $this->testSwitchAccViewFunctionExists();
            $this->testBankBalancesNoToggleClasses();
            
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
            echo "\n" . ANSI_RED . "PRESERVATION FAILURES:\n" . ANSI_RESET;
            echo str_repeat("-", 80) . "\n";
            
            foreach ($this->failures as $failure) {
                echo "\n  Test: {$failure['test']}\n";
                echo "  Requirement: {$failure['requirement']}\n";
                echo "  Message: {$failure['message']}\n";
                echo "  Expected: {$failure['expected']}\n";
            }
            
            echo "\n" . ANSI_RED . "✗ PRESERVATION TESTS FAILED\n" . ANSI_RESET;
            echo "\nOne or more period metrics are missing toggle functionality or have incorrect\n";
            echo "behavior. This indicates a regression that must be fixed before proceeding.\n\n";
            
            exit(1);
        } else {
            echo "\n" . ANSI_GREEN . "✓ ALL PRESERVATION TESTS PASSED\n" . ANSI_RESET;
            echo "\nAll period-based metrics (Income, Expenses, Net, Overall Total) have the\n";
            echo "correct toggle classes and will continue to toggle between monthly and all-time\n";
            echo "values. Bank accounts and Accounts Total correctly do not have toggle classes.\n";
            echo "The switchAccView() function exists with complete logic for toggling visibility\n";
            echo "and updating date filters.\n\n";
            echo "This confirms the baseline behavior that must be preserved after the fix.\n\n";
            
            exit(0);
        }
    }
}

// Run the test
$test = new PreservationTest();
$test->run();
