<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

// Use March 2026 since that's what the user generated
$prepareMonth = 3;
$prepareYear = 2026;

// Calculate billing period (16th-to-15th)
$payPeriodStart = sprintf('%04d-%02d-16', $prepareYear, $prepareMonth);
$payPeriodNextM = $prepareMonth === 12 ? 1 : $prepareMonth + 1;
$payPeriodNextY = $prepareMonth === 12 ? $prepareYear + 1 : $prepareYear;
$payPeriodEnd   = sprintf('%04d-%02d-15', $payPeriodNextY, $payPeriodNextM);

echo "<h1>Debug: Batch Staff Data for March 2026</h1>";
echo "<p>Billing Period: $payPeriodStart to $payPeriodEnd</p>";

// Fetch staff1 (user_id=2)
$stmt = $pdo->prepare("
    SELECT u.id AS user_id, u.name, s.salary AS basic_salary,
           s.role AS staff_role, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

echo "<h2>Staff Data from Database:</h2>";
echo "<pre>";
print_r($staff);
echo "</pre>";

if ($staff && ($staff['salary_type'] ?? null) === 'hourly') {
    echo "<h2>Calculating Hours Worked:</h2>";
    
    $hoursWorked = calculate_hours_worked($pdo, $staff['user_id'], $payPeriodStart, $payPeriodEnd);
    echo "<p>Hours Worked: $hoursWorked</p>";
    
    $hourlyRate = $staff['hourly_rate'] ?? 0;
    echo "<p>Hourly Rate: $hourlyRate</p>";
    
    $basicSalary = calculate_hourly_payment($hoursWorked, $hourlyRate);
    echo "<p>Basic Salary: $basicSalary</p>";
    
    // Add to staff array
    $staff['hours_worked'] = $hoursWorked;
    $staff['basic_salary'] = $basicSalary;
    
    echo "<h2>Staff Array After Calculation:</h2>";
    echo "<pre>";
    print_r($staff);
    echo "</pre>";
    
    echo "<h2>Display Logic Test:</h2>";
    $salaryType = $staff['salary_type'] ?? 'fixed';
    echo "<p>Salary Type: $salaryType</p>";
    echo "<p>Is Hourly? " . ($salaryType === 'hourly' ? 'YES' : 'NO') . "</p>";
    
    if ($salaryType === 'hourly') {
        $hoursWorked = $staff['hours_worked'] ?? 0.0;
        $hourlyRate = $staff['hourly_rate'] ?? 0.0;
        $belowThreshold = $hoursWorked < 1.0;
        
        echo "<p>Hours from array: $hoursWorked</p>";
        echo "<p>Rate from array: $hourlyRate</p>";
        echo "<p>Below threshold? " . ($belowThreshold ? 'YES' : 'NO') . "</p>";
        
        echo "<h3>HTML Output:</h3>";
        echo '<div class="text-right">';
        echo '<span class="text-mb-silver">$' . number_format($staff['basic_salary'], 2) . '</span>';
        echo '<p class="text-[10px] text-mb-subtle/70 mt-0.5">';
        echo number_format($hoursWorked, 2) . 'h × $' . number_format($hourlyRate, 2) . '/h';
        echo '</p>';
        if ($belowThreshold) {
            echo '<span class="inline-flex items-center gap-1 bg-orange-500/10 text-orange-400 border border-orange-500/20 rounded-full px-2 py-0.5 text-[10px] font-medium mt-1">';
            echo 'Below 1hr threshold';
            echo '</span>';
        }
        echo '</div>';
    }
}

echo "<h2>Attendance Records:</h2>";
$attStmt = $pdo->prepare("
    SELECT * FROM staff_attendance 
    WHERE user_id = 2 
      AND date BETWEEN ? AND ?
    ORDER BY date
");
$attStmt->execute([$payPeriodStart, $payPeriodEnd]);
$attendance = $attStmt->fetchAll();
echo "<pre>";
print_r($attendance);
echo "</pre>";
