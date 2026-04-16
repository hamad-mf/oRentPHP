<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();

$pdo = db();

echo "<h1>One-Click Fix for Hours Display</h1>";
echo "<hr>";

$issues = [];
$fixes = [];

// Check 1: Staff1 configuration
echo "<h2>Step 1: Checking Staff1 Configuration</h2>";
$stmt = $pdo->prepare("
    SELECT u.id, u.name, s.id AS staff_id, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

if (!$staff) {
    echo "<p style='color: red;'>✗ ERROR: Staff with user_id=2 not found!</p>";
    exit;
}

if ($staff['salary_type'] !== 'hourly' || $staff['hourly_rate'] != 500.00) {
    echo "<p style='color: orange;'>⚠ Issue found: Staff1 is not configured as hourly with \$500/hour</p>";
    $issues[] = 'staff_config';
    
    // Fix it
    $updateStmt = $pdo->prepare("UPDATE staff SET salary_type = 'hourly', hourly_rate = 500.00 WHERE id = ?");
    if ($updateStmt->execute([$staff['staff_id']])) {
        echo "<p style='color: green;'>✓ Fixed: Staff1 set to hourly with \$500/hour</p>";
        $fixes[] = 'staff_config';
    } else {
        echo "<p style='color: red;'>✗ Failed to update staff configuration</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Staff1 is correctly configured as hourly with \$500/hour</p>";
}

// Check 2: Existing payroll
echo "<h2>Step 2: Checking for Existing March 2026 Payroll</h2>";
$payrollStmt = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE month = 3 AND year = 2026");
$payrollStmt->execute();
$count = $payrollStmt->fetchColumn();

if ($count > 0) {
    echo "<p style='color: orange;'>⚠ Issue found: March 2026 payroll already exists ($count records)</p>";
    echo "<p>This prevents you from generating a new batch.</p>";
    $issues[] = 'existing_payroll';
    
    // Ask user if they want to delete
    echo "<form method='post' style='margin: 20px 0;'>";
    echo "<input type='hidden' name='delete_payroll' value='1'>";
    echo "<button type='submit' style='background: #d9534f; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Delete March 2026 Payroll</button>";
    echo "<p style='color: #666; font-size: 12px;'>This will delete all payroll records for March 2026 so you can generate a fresh batch.</p>";
    echo "</form>";
    
    if (isset($_POST['delete_payroll'])) {
        $deleteStmt = $pdo->prepare("DELETE FROM payroll WHERE month = 3 AND year = 2026");
        if ($deleteStmt->execute()) {
            echo "<p style='color: green;'>✓ Fixed: Deleted March 2026 payroll</p>";
            $fixes[] = 'existing_payroll';
            $count = 0;
        } else {
            echo "<p style='color: red;'>✗ Failed to delete payroll</p>";
        }
    }
} else {
    echo "<p style='color: green;'>✓ No existing March 2026 payroll</p>";
}

// Check 3: Attendance data
echo "<h2>Step 3: Checking Attendance Data</h2>";
$attStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM staff_attendance 
    WHERE user_id = 2 
      AND date BETWEEN '2026-03-16' AND '2026-04-15'
      AND punch_in IS NOT NULL 
      AND punch_out IS NOT NULL
");
$attStmt->execute();
$attCount = $attStmt->fetchColumn();

if ($attCount == 0) {
    echo "<p style='color: orange;'>⚠ Warning: No attendance records found for March 2026</p>";
    echo "<p>Hours will show as 0.00h</p>";
} else {
    echo "<p style='color: green;'>✓ Found $attCount attendance records for March 2026</p>";
}

// Summary
echo "<h2>Summary</h2>";

if (empty($issues)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='margin-top: 0;'>✓ All Checks Passed!</h3>";
    echo "<p>Everything is configured correctly. To see the hours display:</p>";
    echo "<ol>";
    echo "<li>Go to the Payroll page</li>";
    echo "<li>Select <strong>March 2026</strong></li>";
    echo "<li>Click <strong>Generate Payroll</strong></li>";
    echo "<li>Look in the <strong>Basic Salary</strong> column for staff1</li>";
    echo "<li>You should see: <strong>\$32,250.00</strong> with <strong>64.50h × \$500.00/h</strong> below it</li>";
    echo "</ol>";
    echo "<p><strong>Important:</strong> Make sure to hard refresh your browser (Ctrl+Shift+R) before generating the batch.</p>";
    echo "</div>";
} else {
    if (count($fixes) == count($issues)) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='margin-top: 0;'>✓ All Issues Fixed!</h3>";
        echo "<p>All problems have been resolved. Now:</p>";
        echo "<ol>";
        echo "<li>Hard refresh your browser (Ctrl+Shift+R)</li>";
        echo "<li>Go to the Payroll page</li>";
        echo "<li>Select March 2026</li>";
        echo "<li>Click Generate Payroll</li>";
        echo "<li>Look for hours display in the Basic Salary column</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='margin-top: 0;'>⚠ Some Issues Remain</h3>";
        echo "<p>Please review the steps above and complete any required actions.</p>";
        if (in_array('existing_payroll', $issues) && !in_array('existing_payroll', $fixes)) {
            echo "<p><strong>Action Required:</strong> Click the 'Delete March 2026 Payroll' button above to remove existing payroll.</p>";
        }
        echo "</div>";
    }
}

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Hard refresh your browser:</strong> Press Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)</li>";
echo "<li><strong>Go to Payroll page:</strong> Navigate to payroll/index.php</li>";
echo "<li><strong>Select March 2026</strong> from the month/year dropdown</li>";
echo "<li><strong>Click 'Generate Payroll'</strong> button</li>";
echo "<li><strong>Look for staff1</strong> in the batch table</li>";
echo "<li><strong>Check the Basic Salary column</strong> - you should see the hours breakdown in small gray text</li>";
echo "</ol>";

echo "<hr>";
echo "<p><em>If you still don't see the hours display after following these steps, run diagnose_hours_display_final.php for a detailed diagnostic report.</em></p>";
