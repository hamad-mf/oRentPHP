<?php
// Debug script to check what's in the batchStaff array
require_once __DIR__ . '/config/db.php';
auth_require_admin();
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

// Simulate the payroll batch preparation for March 2026
$prepareMonth = 3;
$prepareYear = 2026;

// Compute 16th-to-15th billing period
$payPeriodStart = sprintf('%04d-%02d-16', $prepareYear, $prepareMonth);
$payPeriodNextM = $prepareMonth === 12 ? 1 : $prepareMonth + 1;
$payPeriodNextY = $prepareMonth === 12 ? $prepareYear + 1 : $prepareYear;
$payPeriodEnd   = sprintf('%04d-%02d-15', $payPeriodNextY, $payPeriodNextM);

echo "<h2>Debug: Batch Staff Array for March 2026</h2>";
echo "<p>Billing Period: $payPeriodStart to $payPeriodEnd</p>";

// Fetch staff1 only
$stmt = $pdo->prepare("
    SELECT u.id AS user_id, u.name, s.salary AS basic_salary,
           s.role AS staff_role, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.is_active = 1 AND u.username = 'staff1'
");
$stmt->execute();
$staff = $stmt->fetch();

if (!$staff) {
    echo "<p>ERROR: staff1 not found!</p>";
    exit;
}

echo "<h3>Before hourly calculation:</h3>";
echo "<pre>";
print_r($staff);
echo "</pre>";

// Apply hourly calculation logic
$salaryType = $staff['salary_type'] ?? null;

if ($salaryType === 'hourly') {
    $hourlyRate = $staff['hourly_rate'] ?? null;
    
    if ($hourlyRate === null) {
        echo "<p>ERROR: hourly_rate is NULL!</p>";
    } else {
        $hoursWorked = calculate_hours_worked($pdo, $staff['user_id'], $payPeriodStart, $payPeriodEnd);
        $basicSalary = calculate_hourly_payment($hoursWorked, $hourlyRate);
        
        $staff['hours_worked'] = $hoursWorked;
        $staff['basic_salary'] = $basicSalary;
        
        echo "<h3>After hourly calculation:</h3>";
        echo "<pre>";
        print_r($staff);
        echo "</pre>";
        
        echo "<h3>Calculation Details:</h3>";
        echo "<p>Hours Worked: $hoursWorked</p>";
        echo "<p>Hourly Rate: $hourlyRate</p>";
        echo "<p>Basic Salary: $basicSalary</p>";
        echo "<p>Calculation: $hoursWorked × $hourlyRate = $basicSalary</p>";
    }
} else {
    echo "<p>ERROR: salary_type is '$salaryType', not 'hourly'!</p>";
}
?>
