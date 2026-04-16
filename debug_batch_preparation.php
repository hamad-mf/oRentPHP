<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

echo "<h1>Debug: Batch Preparation Data</h1>";

// Simulate the batch preparation for March 2026
$prepareMonth = 3;
$prepareYear = 2026;

$payPeriodStart = sprintf('%04d-%02d-16', $prepareYear, $prepareMonth);
$payPeriodNextM = $prepareMonth === 12 ? 1 : $prepareMonth + 1;
$payPeriodNextY = $prepareMonth === 12 ? $prepareYear + 1 : $prepareYear;
$payPeriodEnd   = sprintf('%04d-%02d-15', $payPeriodNextY, $payPeriodNextM);

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

echo "<h2>Staff Data from Query:</h2>";
echo "<pre>";
print_r($staff);
echo "</pre>";

if ($staff && ($staff['salary_type'] ?? null) === 'hourly') {
    echo "<h2>Calculating Hours:</h2>";
    
    $hoursWorked = calculate_hours_worked($pdo, $staff['user_id'], $payPeriodStart, $payPeriodEnd);
    echo "<p>Hours Worked: $hoursWorked</p>";
    
    $hourlyRate = $staff['hourly_rate'] ?? 0;
    echo "<p>Hourly Rate: $$hourlyRate</p>";
    
    $basicSalary = calculate_hourly_payment($hoursWorked, $hourlyRate);
    echo "<p>Basic Salary: $$basicSalary</p>";
    
    // This is what should be in the batch array
    $staff['hours_worked'] = $hoursWorked;
    $staff['basic_salary'] = $basicSalary;
    
    echo "<h2>Final Staff Array (what should be in \$batchStaff):</h2>";
    echo "<pre>";
    print_r($staff);
    echo "</pre>";
    
    echo "<h2>Display Check:</h2>";
    $salaryType = $staff['salary_type'] ?? 'fixed';
    echo "<p>Salary Type: '$salaryType'</p>";
    echo "<p>Is hourly? " . ($salaryType === 'hourly' ? 'YES' : 'NO') . "</p>";
    echo "<p>hours_worked isset? " . (isset($staff['hours_worked']) ? 'YES' : 'NO') . "</p>";
    echo "<p>hours_worked value: " . ($staff['hours_worked'] ?? 'NOT SET') . "</p>";
}
