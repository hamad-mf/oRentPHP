<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();

$pdo = db();

echo "<h1>Fix Staff1 Hourly Display Issue</h1>";
echo "<hr>";

// Step 1: Check current staff1 configuration
echo "<h2>Step 1: Current Configuration</h2>";
$stmt = $pdo->prepare("
    SELECT u.id AS user_id, u.name, u.username, s.id AS staff_id,
           s.salary AS basic_salary, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

if (!$staff) {
    echo "<p style='color: red;'>ERROR: Staff with user_id=2 not found!</p>";
    exit;
}

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>User ID</td><td>{$staff['user_id']}</td></tr>";
echo "<tr><td>Name</td><td>{$staff['name']}</td></tr>";
echo "<tr><td>Username</td><td>{$staff['username']}</td></tr>";
echo "<tr><td>Staff ID</td><td>{$staff['staff_id']}</td></tr>";
echo "<tr><td>Basic Salary</td><td>\${$staff['basic_salary']}</td></tr>";
echo "<tr><td>Salary Type</td><td><strong>" . ($staff['salary_type'] ?? 'NULL') . "</strong></td></tr>";
echo "<tr><td>Hourly Rate</td><td>\$" . ($staff['hourly_rate'] ?? 'NULL') . "</td></tr>";
echo "</table>";

// Step 2: Check if salary_type is 'hourly'
echo "<h2>Step 2: Diagnosis</h2>";
$salaryType = $staff['salary_type'] ?? null;
$hourlyRate = $staff['hourly_rate'] ?? null;

if ($salaryType === 'hourly' && $hourlyRate !== null) {
    echo "<p style='color: green;'>✓ Staff is correctly configured as hourly with rate \$$hourlyRate</p>";
    echo "<p>The display should be working. If you're not seeing hours, try:</p>";
    echo "<ul>";
    echo "<li>Hard refresh the payroll page (Ctrl+Shift+R)</li>";
    echo "<li>Check browser console for JavaScript errors</li>";
    echo "<li>Inspect the element to see if CSS is hiding it</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ ISSUE FOUND: Staff is NOT configured as hourly</p>";
    echo "<p>Current salary_type: <strong>" . ($salaryType ?? 'NULL') . "</strong></p>";
    echo "<p>Current hourly_rate: <strong>" . ($hourlyRate ?? 'NULL') . "</strong></p>";
    
    // Step 3: Fix the issue
    echo "<h2>Step 3: Applying Fix</h2>";
    
    $updateStmt = $pdo->prepare("
        UPDATE staff 
        SET salary_type = 'hourly',
            hourly_rate = 500.00
        WHERE id = ?
    ");
    
    if ($updateStmt->execute([$staff['staff_id']])) {
        echo "<p style='color: green;'>✓ Successfully updated staff to hourly with \$500/hour rate</p>";
        
        // Verify the update
        $verifyStmt = $pdo->prepare("
            SELECT salary_type, hourly_rate 
            FROM staff 
            WHERE id = ?
        ");
        $verifyStmt->execute([$staff['staff_id']]);
        $updated = $verifyStmt->fetch();
        
        echo "<h2>Step 4: Verification</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>New Value</th></tr>";
        echo "<tr><td>Salary Type</td><td><strong>{$updated['salary_type']}</strong></td></tr>";
        echo "<tr><td>Hourly Rate</td><td>\${$updated['hourly_rate']}</td></tr>";
        echo "</table>";
        
        echo "<p style='color: green; font-weight: bold;'>✓ Fix applied successfully!</p>";
        echo "<p>Now go to the payroll page and generate the March 2026 batch. You should see the hours display.</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to update staff record</p>";
    }
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Go to Payroll page</li>";
echo "<li>Select March 2026</li>";
echo "<li>Click 'Generate Payroll'</li>";
echo "<li>Look for staff1 in the batch table</li>";
echo "<li>You should see: <strong>\$32,250.00</strong> with <strong>64.50h × \$500.00/h</strong> below it</li>";
echo "</ol>";
