<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();

$pdo = db();

echo "<h1>Verify Hours Display Fix</h1>";

// Check 1: hours_worked column exists
echo "<h2>Check 1: Database Schema</h2>";
$hasColumn = (bool) $pdo->query("SHOW COLUMNS FROM payroll LIKE 'hours_worked'")->fetchColumn();
if ($hasColumn) {
    echo "<p style='color: green;'>✓ hours_worked column exists in payroll table</p>";
} else {
    echo "<p style='color: red;'>✗ hours_worked column MISSING - run migration first!</p>";
    echo "<p>Run: <code>php apply_hours_worked_fix.php</code></p>";
}

// Check 2: staff1 configuration
echo "<h2>Check 2: Staff Configuration</h2>";
$stmt = $pdo->prepare("
    SELECT u.id, u.name, s.salary_type, s.hourly_rate 
    FROM users u 
    JOIN staff s ON s.id = u.staff_id 
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

if ($staff) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
    echo "<tr><td>User ID</td><td>{$staff['id']}</td><td>✓</td></tr>";
    echo "<tr><td>Name</td><td>{$staff['name']}</td><td>✓</td></tr>";
    echo "<tr><td>Salary Type</td><td>{$staff['salary_type']}</td><td>" . ($staff['salary_type'] === 'hourly' ? '✓' : '✗') . "</td></tr>";
    echo "<tr><td>Hourly Rate</td><td>\${$staff['hourly_rate']}</td><td>" . ($staff['hourly_rate'] > 0 ? '✓' : '✗') . "</td></tr>";
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ staff1 (user_id=2) not found!</p>";
}

// Check 3: Attendance data
echo "<h2>Check 3: Attendance Data (March 16 - April 15, 2026)</h2>";
$attStmt = $pdo->prepare("
    SELECT COUNT(*) as count, 
           SUM(TIMESTAMPDIFF(SECOND, punch_in, punch_out)) / 3600 as total_hours
    FROM staff_attendance 
    WHERE user_id = 2 
      AND date BETWEEN '2026-03-16' AND '2026-04-15'
      AND punch_in IS NOT NULL 
      AND punch_out IS NOT NULL
");
$attStmt->execute();
$attData = $attStmt->fetch();

if ($attData['count'] > 0) {
    echo "<p style='color: green;'>✓ Found {$attData['count']} attendance record(s)</p>";
    echo "<p>Total hours (without breaks): " . number_format($attData['total_hours'], 2) . "h</p>";
} else {
    echo "<p style='color: orange;'>⚠ No attendance records found for March 16 - April 15, 2026</p>";
    echo "<p>You may need to add test attendance data.</p>";
}

// Check 4: Existing payroll
echo "<h2>Check 4: Existing Payroll for March 2026</h2>";
$payStmt = $pdo->prepare("SELECT * FROM payroll WHERE month = 3 AND year = 2026 AND user_id = 2");
$payStmt->execute();
$payroll = $payStmt->fetch();

if ($payroll) {
    echo "<p style='color: orange;'>⚠ Payroll already exists for March 2026</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Basic Salary</td><td>\$" . number_format($payroll['basic_salary'], 2) . "</td></tr>";
    
    if ($hasColumn) {
        echo "<tr><td>Hours Worked</td><td>" . ($payroll['hours_worked'] !== null ? number_format($payroll['hours_worked'], 2) . 'h' : 'NULL') . "</td></tr>";
        
        if ($payroll['hours_worked'] === null) {
            echo "<tr><td colspan='2' style='background: #fff3cd; color: #856404;'>";
            echo "⚠ hours_worked is NULL - this payroll was created before the fix.<br>";
            echo "You need to delete and regenerate this payroll to see hours display.";
            echo "</td></tr>";
        }
    }
    
    echo "</table>";
    echo "<p><a href='delete_march_2026_payroll.php' style='background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Delete March 2026 Payroll</a></p>";
} else {
    echo "<p style='color: green;'>✓ No existing payroll for March 2026 - ready to generate!</p>";
}

// Check 5: Code verification
echo "<h2>Check 5: Code Verification</h2>";
$codeChecks = [
    'payroll/index.php contains hours_worked in INSERT' => strpos(file_get_contents('payroll/index.php'), 'hours_worked') !== false,
    'payroll/index.php contains salary_type in SELECT' => strpos(file_get_contents('payroll/index.php'), 's.salary_type') !== false,
];

foreach ($codeChecks as $check => $passed) {
    $status = $passed ? '✓' : '✗';
    $color = $passed ? 'green' : 'red';
    echo "<p style='color: $color;'>$status $check</p>";
}

// Summary
echo "<h2>Summary</h2>";
$allGood = $hasColumn && 
           ($staff && $staff['salary_type'] === 'hourly') && 
           $attData['count'] > 0 &&
           $codeChecks['payroll/index.php contains hours_worked in INSERT'] &&
           $codeChecks['payroll/index.php contains salary_type in SELECT'];

if ($allGood && !$payroll) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<h3>✓ All checks passed! Ready to test.</h3>";
    echo "<p>Next steps:</p>";
    echo "<ol>";
    echo "<li>Go to <a href='payroll/index.php'>Payroll page</a></li>";
    echo "<li>Generate payroll for March 2026</li>";
    echo "<li>Verify hours display in batch preparation</li>";
    echo "<li>Save payroll</li>";
    echo "<li>Verify hours display in saved payroll list</li>";
    echo "</ol>";
    echo "</div>";
} elseif ($allGood && $payroll && $payroll['hours_worked'] === null) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px;'>";
    echo "<h3>⚠ Almost ready - need to regenerate payroll</h3>";
    echo "<p>The fix is applied, but existing March 2026 payroll was created before the fix.</p>";
    echo "<p>Next steps:</p>";
    echo "<ol>";
    echo "<li>Delete existing March 2026 payroll</li>";
    echo "<li>Regenerate payroll for March 2026</li>";
    echo "<li>Verify hours display works</li>";
    echo "</ol>";
    echo "<p><a href='apply_hours_worked_fix.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Automated Fix →</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>✗ Some checks failed</h3>";
    echo "<p>Please review the checks above and fix any issues.</p>";
    if (!$hasColumn) {
        echo "<p><strong>Priority:</strong> Run the migration to add hours_worked column</p>";
        echo "<p><a href='apply_hours_worked_fix.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Automated Fix →</a></p>";
    }
    echo "</div>";
}
