<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #333; }
h2 { color: #666; margin-top: 30px; }
table { border-collapse: collapse; margin: 10px 0; }
table td, table th { border: 1px solid #ddd; padding: 8px; text-align: left; }
table th { background-color: #f2f2f2; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.code { background: #f5f5f5; padding: 10px; border-left: 3px solid #333; margin: 10px 0; }
</style>";

echo "<h1>Hours Display Diagnostic Report</h1>";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// Test 1: Check staff1 configuration
echo "<h2>Test 1: Staff1 Configuration</h2>";
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.username, u.is_active,
           s.id AS staff_id, s.salary, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

if (!$staff) {
    echo "<p class='error'>✗ FAIL: Staff with user_id=2 not found!</p>";
    exit;
}

echo "<table>";
echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
echo "<tr><td>User ID</td><td>{$staff['id']}</td><td class='success'>✓</td></tr>";
echo "<tr><td>Name</td><td>{$staff['name']}</td><td class='success'>✓</td></tr>";
echo "<tr><td>Is Active</td><td>" . ($staff['is_active'] ? 'Yes' : 'No') . "</td><td class='" . ($staff['is_active'] ? 'success' : 'error') . "'>" . ($staff['is_active'] ? '✓' : '✗') . "</td></tr>";
echo "<tr><td>Salary Type</td><td><strong>" . ($staff['salary_type'] ?? 'NULL') . "</strong></td><td class='" . (($staff['salary_type'] ?? null) === 'hourly' ? 'success' : 'error') . "'>" . (($staff['salary_type'] ?? null) === 'hourly' ? '✓' : '✗') . "</td></tr>";
echo "<tr><td>Hourly Rate</td><td>\$" . ($staff['hourly_rate'] ?? 'NULL') . "</td><td class='" . (($staff['hourly_rate'] ?? null) !== null ? 'success' : 'error') . "'>" . (($staff['hourly_rate'] ?? null) !== null ? '✓' : '✗') . "</td></tr>";
echo "</table>";

if (($staff['salary_type'] ?? null) !== 'hourly') {
    echo "<p class='error'>✗ CRITICAL: Staff1 is NOT configured as hourly!</p>";
    echo "<p>Run: <code>php check_and_fix_staff1.php</code></p>";
    exit;
}

echo "<p class='success'>✓ PASS: Staff1 is correctly configured as hourly</p>";

// Test 2: Check attendance data
echo "<h2>Test 2: Attendance Data for March 2026</h2>";
$payPeriodStart = '2026-03-16';
$payPeriodEnd = '2026-04-15';

$attStmt = $pdo->prepare("
    SELECT date, punch_in, punch_out,
           TIMESTAMPDIFF(SECOND, punch_in, punch_out) / 3600 AS hours
    FROM staff_attendance
    WHERE user_id = 2
      AND date BETWEEN ? AND ?
      AND punch_in IS NOT NULL
      AND punch_out IS NOT NULL
    ORDER BY date
");
$attStmt->execute([$payPeriodStart, $payPeriodEnd]);
$attendance = $attStmt->fetchAll();

if (empty($attendance)) {
    echo "<p class='warning'>⚠ WARNING: No attendance records found for March 16 - April 15, 2026</p>";
    echo "<p>Hours will show as 0.00h</p>";
} else {
    echo "<table>";
    echo "<tr><th>Date</th><th>Punch In</th><th>Punch Out</th><th>Hours</th></tr>";
    $totalHours = 0;
    foreach ($attendance as $att) {
        $hours = (float) $att['hours'];
        $totalHours += $hours;
        echo "<tr><td>{$att['date']}</td><td>{$att['punch_in']}</td><td>{$att['punch_out']}</td><td>" . number_format($hours, 2) . "h</td></tr>";
    }
    echo "<tr><th colspan='3'>Total</th><th>" . number_format($totalHours, 2) . "h</th></tr>";
    echo "</table>";
    echo "<p class='success'>✓ PASS: Found " . count($attendance) . " attendance records, total " . number_format($totalHours, 2) . " hours</p>";
}

// Test 3: Calculate hours using the actual function
echo "<h2>Test 3: Hours Calculation</h2>";
$hoursWorked = calculate_hours_worked($pdo, 2, $payPeriodStart, $payPeriodEnd);
$hourlyRate = (float) $staff['hourly_rate'];
$basicSalary = calculate_hourly_payment($hoursWorked, $hourlyRate);

echo "<table>";
echo "<tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Hours Worked (calculated)</td><td>" . number_format($hoursWorked, 2) . "h</td></tr>";
echo "<tr><td>Hourly Rate</td><td>\$" . number_format($hourlyRate, 2) . "/h</td></tr>";
echo "<tr><td>Basic Salary</td><td>\$" . number_format($basicSalary, 2) . "</td></tr>";
echo "<tr><td>Below Threshold?</td><td>" . ($hoursWorked < 1.0 ? 'Yes (will show $0.00)' : 'No') . "</td></tr>";
echo "</table>";

if ($hoursWorked >= 1.0) {
    echo "<p class='success'>✓ PASS: Hours >= 1.0, salary will be calculated and hours will display</p>";
} else {
    echo "<p class='warning'>⚠ WARNING: Hours < 1.0, salary will be $0.00 with threshold badge</p>";
}

// Test 4: Check if March 2026 payroll already exists
echo "<h2>Test 4: Existing Payroll Check</h2>";
$payrollStmt = $pdo->prepare("SELECT * FROM payroll WHERE month = 3 AND year = 2026");
$payrollStmt->execute();
$existingPayroll = $payrollStmt->fetchAll();

if (empty($existingPayroll)) {
    echo "<p class='success'>✓ PASS: No existing payroll for March 2026</p>";
    echo "<p>You can generate a new batch and see the hours display.</p>";
} else {
    echo "<p class='warning'>⚠ WARNING: March 2026 payroll already exists (" . count($existingPayroll) . " records)</p>";
    echo "<p>You are viewing SAVED payroll, not generating a NEW batch.</p>";
    echo "<p>To see hours in batch preparation, you must:</p>";
    echo "<ol>";
    echo "<li>Delete existing payroll: <code>DELETE FROM payroll WHERE month = 3 AND year = 2026;</code></li>";
    echo "<li>Generate a NEW batch for March 2026</li>";
    echo "</ol>";
    
    // Check if the saved payroll has hours_worked
    $savedStmt = $pdo->prepare("SELECT hours_worked FROM payroll WHERE month = 3 AND year = 2026 AND user_id = 2");
    $savedStmt->execute();
    $savedPayroll = $savedStmt->fetch();
    
    if ($savedPayroll) {
        echo "<p>Saved payroll for staff1 has hours_worked = " . ($savedPayroll['hours_worked'] ?? 'NULL') . "</p>";
        if ($savedPayroll['hours_worked'] !== null) {
            echo "<p class='success'>✓ The saved payroll DOES have hours_worked, so hours should display in the saved list</p>";
        } else {
            echo "<p class='error'>✗ The saved payroll does NOT have hours_worked (NULL), hours will not display</p>";
            echo "<p>This payroll was saved before the hours_worked column was added.</p>";
        }
    }
}

// Test 5: Simulate the display logic
echo "<h2>Test 5: Display Logic Simulation</h2>";
echo "<p>This simulates what the payroll batch preparation screen should show:</p>";

$s = [
    'user_id' => $staff['id'],
    'name' => $staff['name'],
    'basic_salary' => $basicSalary,
    'salary_type' => $staff['salary_type'],
    'hourly_rate' => $staff['hourly_rate'],
    'hours_worked' => $hoursWorked,
];

$basic = (float) ($s['basic_salary'] ?? 0);
$salaryType = $s['salary_type'] ?? 'fixed';

echo "<div class='code'>";
echo "<strong>Staff: {$s['name']}</strong><br>";
echo "Salary Type: $salaryType<br>";

if ($salaryType === 'hourly') {
    $hoursWorked = $s['hours_worked'] ?? 0.0;
    $hourlyRate = $s['hourly_rate'] ?? 0.0;
    $belowThreshold = $hoursWorked < 1.0;
    
    echo "<div style='text-align: right; margin-top: 10px;'>";
    echo "<span style='font-size: 16px; color: #333;'>\$" . number_format($basic, 2) . "</span><br>";
    echo "<span style='font-size: 10px; color: #999;'>" . number_format($hoursWorked, 2) . "h × \$" . number_format($hourlyRate, 2) . "/h</span><br>";
    
    if ($belowThreshold) {
        echo "<span style='font-size: 10px; color: orange; border: 1px solid orange; padding: 2px 5px; border-radius: 10px;'>Below 1hr threshold</span>";
    }
    echo "</div>";
    
    echo "<p class='success'>✓ PASS: Hours display code executed successfully</p>";
} else {
    echo "<div style='text-align: right; margin-top: 10px;'>";
    echo "<span style='font-size: 16px; color: #333;'>\$" . number_format($basic, 2) . "</span>";
    echo "</div>";
    echo "<p class='error'>✗ FAIL: Salary type is not 'hourly', hours will not display</p>";
}
echo "</div>";

// Final Summary
echo "<h2>Final Summary</h2>";
echo "<table>";
echo "<tr><th>Check</th><th>Status</th><th>Action Required</th></tr>";

$checks = [
    ['Staff1 configured as hourly', ($staff['salary_type'] ?? null) === 'hourly', 'Run check_and_fix_staff1.php'],
    ['Hourly rate set', ($staff['hourly_rate'] ?? null) !== null, 'Set hourly_rate in staff table'],
    ['Attendance data exists', !empty($attendance), 'Add attendance records for March 2026'],
    ['Hours >= 1.0', $hoursWorked >= 1.0, 'Add more attendance or accept $0.00 display'],
    ['No existing payroll', empty($existingPayroll), 'Delete existing payroll or test with different month'],
];

foreach ($checks as $check) {
    $status = $check[1] ? "<span class='success'>✓ PASS</span>" : "<span class='error'>✗ FAIL</span>";
    $action = $check[1] ? 'None' : $check[2];
    echo "<tr><td>{$check[0]}</td><td>$status</td><td>$action</td></tr>";
}
echo "</table>";

$allPass = array_reduce($checks, function($carry, $check) { return $carry && $check[1]; }, true);

if ($allPass) {
    echo "<h3 class='success'>✓ ALL CHECKS PASSED</h3>";
    echo "<p>The hours display should be working. If you still don't see it:</p>";
    echo "<ol>";
    echo "<li>Hard refresh the browser (Ctrl+Shift+R)</li>";
    echo "<li>Make sure you're on the BATCH PREPARATION screen (not saved payroll list)</li>";
    echo "<li>Generate a NEW batch for March 2026</li>";
    echo "<li>Look in the 'Basic Salary' column for small gray text below the amount</li>";
    echo "<li>Use browser inspector (F12) to check if the element exists but is hidden by CSS</li>";
    echo "</ol>";
} else {
    echo "<h3 class='error'>✗ ISSUES FOUND</h3>";
    echo "<p>Fix the failed checks above, then test again.</p>";
}

echo "<hr>";
echo "<p><em>End of diagnostic report</em></p>";
