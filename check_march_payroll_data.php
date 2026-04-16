<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();

$pdo = db();

echo "<h1>March 2026 Payroll Data Check</h1>";

// Check if payroll exists for March 2026
$stmt = $pdo->prepare("SELECT * FROM payroll WHERE month = 3 AND year = 2026 AND user_id = 2");
$stmt->execute();
$payroll = $stmt->fetch();

if ($payroll) {
    echo "<h2>Payroll Record Found:</h2>";
    echo "<pre>";
    print_r($payroll);
    echo "</pre>";
    
    echo "<h2>Analysis:</h2>";
    echo "<p>Basic Salary: $" . number_format($payroll['basic_salary'], 2) . "</p>";
    echo "<p>This should be calculated from hours × hourly_rate</p>";
    
    // Get staff details
    $staffStmt = $pdo->prepare("
        SELECT s.salary_type, s.hourly_rate 
        FROM users u 
        JOIN staff s ON s.id = u.staff_id 
        WHERE u.id = 2
    ");
    $staffStmt->execute();
    $staff = $staffStmt->fetch();
    
    echo "<h2>Staff Configuration:</h2>";
    echo "<pre>";
    print_r($staff);
    echo "</pre>";
    
    if ($staff && $staff['salary_type'] === 'hourly') {
        $expectedHours = $payroll['basic_salary'] / $staff['hourly_rate'];
        echo "<p>Expected Hours (basic_salary / hourly_rate): " . number_format($expectedHours, 2) . "h</p>";
    }
} else {
    echo "<p style='color: red;'>No payroll record found for March 2026, user_id=2</p>";
    echo "<p>You need to generate the payroll batch first.</p>";
}

// Check attendance data
echo "<h2>Attendance Data (March 16 - April 15, 2026):</h2>";
$attStmt = $pdo->prepare("
    SELECT * FROM staff_attendance 
    WHERE user_id = 2 
      AND date BETWEEN '2026-03-16' AND '2026-04-15'
    ORDER BY date
");
$attStmt->execute();
$attendance = $attStmt->fetchAll();

if (empty($attendance)) {
    echo "<p style='color: orange;'>No attendance records found for this period.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Date</th><th>Punch In</th><th>Punch Out</th><th>Hours</th></tr>";
    foreach ($attendance as $att) {
        $hours = 0;
        if ($att['punch_in'] && $att['punch_out']) {
            $hours = (strtotime($att['punch_out']) - strtotime($att['punch_in'])) / 3600;
        }
        echo "<tr>";
        echo "<td>" . $att['date'] . "</td>";
        echo "<td>" . ($att['punch_in'] ?? 'NULL') . "</td>";
        echo "<td>" . ($att['punch_out'] ?? 'NULL') . "</td>";
        echo "<td>" . number_format($hours, 2) . "h</td>";
        echo "</tr>";
    }
    echo "</table>";
}
