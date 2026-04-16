<?php
/**
 * Test script for hourly salary calculation feature
 * Task 4.1: Update payroll batch preparation in payroll/index.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

echo "=== Hourly Salary Calculation Test ===\n\n";

// Test 1: Verify calculate_hours_worked function exists
echo "Test 1: Function existence check\n";
if (function_exists('calculate_hours_worked')) {
    echo "✓ calculate_hours_worked() function exists\n";
} else {
    echo "✗ calculate_hours_worked() function NOT found\n";
}

if (function_exists('calculate_hourly_payment')) {
    echo "✓ calculate_hourly_payment() function exists\n";
} else {
    echo "✗ calculate_hourly_payment() function NOT found\n";
}
echo "\n";

// Test 2: Check if staff table has required columns
echo "Test 2: Database schema check\n";
try {
    $hasSalaryType = (bool) $pdo->query("SHOW COLUMNS FROM staff LIKE 'salary_type'")->fetchColumn();
    $hasHourlyRate = (bool) $pdo->query("SHOW COLUMNS FROM staff LIKE 'hourly_rate'")->fetchColumn();
    
    if ($hasSalaryType) {
        echo "✓ staff.salary_type column exists\n";
    } else {
        echo "✗ staff.salary_type column NOT found\n";
    }
    
    if ($hasHourlyRate) {
        echo "✓ staff.hourly_rate column exists\n";
    } else {
        echo "✗ staff.hourly_rate column NOT found\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test calculate_hourly_payment function
echo "Test 3: calculate_hourly_payment() function tests\n";

// Test 3.1: Above threshold
$result = calculate_hourly_payment(10.0, 15.0);
$expected = 150.00;
if (abs($result - $expected) < 0.01) {
    echo "✓ Above threshold (10h × $15/h = $150.00): PASS\n";
} else {
    echo "✗ Above threshold: FAIL (expected $expected, got $result)\n";
}

// Test 3.2: Below threshold
$result = calculate_hourly_payment(0.5, 15.0);
$expected = 0.00;
if (abs($result - $expected) < 0.01) {
    echo "✓ Below threshold (0.5h < 1.0h = $0.00): PASS\n";
} else {
    echo "✗ Below threshold: FAIL (expected $expected, got $result)\n";
}

// Test 3.3: Exactly at threshold
$result = calculate_hourly_payment(1.0, 15.0);
$expected = 15.00;
if (abs($result - $expected) < 0.01) {
    echo "✓ At threshold (1.0h × $15/h = $15.00): PASS\n";
} else {
    echo "✗ At threshold: FAIL (expected $expected, got $result)\n";
}

// Test 3.4: Rounding test
$result = calculate_hourly_payment(7.3833, 20.0);
$expected = 147.67;
if (abs($result - $expected) < 0.01) {
    echo "✓ Rounding (7.3833h × $20/h = $147.67): PASS\n";
} else {
    echo "✗ Rounding: FAIL (expected $expected, got $result)\n";
}
echo "\n";

// Test 4: Test calculate_hours_worked with sample data
echo "Test 4: calculate_hours_worked() integration test\n";
try {
    // Check if there are any staff members
    $stmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE salary_type = 'hourly'");
    $hourlyStaffCount = $stmt->fetchColumn();
    
    if ($hourlyStaffCount > 0) {
        echo "✓ Found $hourlyStaffCount hourly staff member(s) in database\n";
        
        // Get one hourly staff member
        $stmt = $pdo->query("SELECT u.id, u.name, s.hourly_rate FROM users u JOIN staff s ON s.id = u.staff_id WHERE s.salary_type = 'hourly' LIMIT 1");
        $staff = $stmt->fetch();
        
        if ($staff) {
            echo "  Testing with staff: {$staff['name']} (hourly_rate: \${$staff['hourly_rate']})\n";
            
            // Calculate hours for current month
            $month = (int) date('n');
            $year = (int) date('Y');
            $startDate = sprintf('%04d-%02d-15', $year, $month);
            $nextMonth = $month === 12 ? 1 : $month + 1;
            $nextYear = $month === 12 ? $year + 1 : $year;
            $endDate = sprintf('%04d-%02d-14', $nextYear, $nextMonth);
            
            $hours = calculate_hours_worked($pdo, $staff['id'], $startDate, $endDate);
            $payment = calculate_hourly_payment($hours, $staff['hourly_rate']);
            
            echo "  Hours worked: " . number_format($hours, 4) . "h\n";
            echo "  Payment: $" . number_format($payment, 2) . "\n";
            echo "✓ calculate_hours_worked() executed successfully\n";
        }
    } else {
        echo "ℹ No hourly staff members found in database (this is OK for testing)\n";
    }
} catch (Exception $e) {
    echo "✗ Error testing calculate_hours_worked(): " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Verify payroll/index.php includes payroll_helpers.php
echo "Test 5: Integration check\n";
$payrollIndexContent = file_get_contents(__DIR__ . '/payroll/index.php');
if (strpos($payrollIndexContent, 'payroll_helpers.php') !== false) {
    echo "✓ payroll/index.php includes payroll_helpers.php\n";
} else {
    echo "✗ payroll/index.php does NOT include payroll_helpers.php\n";
}

if (strpos($payrollIndexContent, 'calculate_hours_worked') !== false) {
    echo "✓ payroll/index.php calls calculate_hours_worked()\n";
} else {
    echo "✗ payroll/index.php does NOT call calculate_hours_worked()\n";
}

if (strpos($payrollIndexContent, 'calculate_hourly_payment') !== false) {
    echo "✓ payroll/index.php calls calculate_hourly_payment()\n";
} else {
    echo "✗ payroll/index.php does NOT call calculate_hourly_payment()\n";
}

if (strpos($payrollIndexContent, 'salary_type') !== false) {
    echo "✓ payroll/index.php queries salary_type\n";
} else {
    echo "✗ payroll/index.php does NOT query salary_type\n";
}

if (strpos($payrollIndexContent, 'hourly_rate') !== false) {
    echo "✓ payroll/index.php queries hourly_rate\n";
} else {
    echo "✗ payroll/index.php does NOT query hourly_rate\n";
}

echo "\n=== Test Complete ===\n";
