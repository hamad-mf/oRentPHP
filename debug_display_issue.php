<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

echo "<h1>Debug: Display Issue Investigation</h1>";

// Simulate the batch preparation for March 2026
$prepareMonth = 3;
$prepareYear = 2026;

$payPeriodStart = sprintf('%04d-%02d-16', $prepareYear, $prepareMonth);
$payPeriodNextM = $prepareMonth === 12 ? 1 : $prepareMonth + 1;
$payPeriodNextY = $prepareMonth === 12 ? $prepareYear + 1 : $prepareYear;
$payPeriodEnd   = sprintf('%04d-%02d-15', $payPeriodNextY, $payPeriodNextM);

echo "<p>Billing Period: $payPeriodStart to $payPeriodEnd</p>";

// Fetch staff1 (user_id=2) - EXACT same query as payroll/index.php
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

// Simulate the hourly calculation logic - EXACT same as payroll/index.php lines 240-274
$salaryType = $staff['salary_type'] ?? null;

if ($salaryType === null) {
    echo "<p style='color: orange;'>WARNING: salary_type is NULL, defaulting to 'fixed'</p>";
    $salaryType = 'fixed';
}

echo "<h2>Salary Type Check:</h2>";
echo "<p>salary_type from DB: '" . ($staff['salary_type'] ?? 'NULL') . "'</p>";
echo "<p>salary_type after null check: '$salaryType'</p>";
echo "<p>Is hourly? " . ($salaryType === 'hourly' ? 'YES' : 'NO') . "</p>";

if ($salaryType === 'hourly') {
    $hourlyRate = $staff['hourly_rate'] ?? null;
    
    if ($hourlyRate === null) {
        echo "<p style='color: red;'>ERROR: hourly_rate is NULL</p>";
        $staff['hours_worked'] = 0.0;
        $staff['basic_salary'] = 0.00;
    } else {
        $hoursWorked = calculate_hours_worked($pdo, $staff['user_id'], $payPeriodStart, $payPeriodEnd);
        $basicSalary = calculate_hourly_payment($hoursWorked, $hourlyRate);
        
        $staff['hours_worked'] = $hoursWorked;
        $staff['basic_salary'] = $basicSalary;
        
        echo "<h2>Hourly Calculation:</h2>";
        echo "<p>Hours Worked: $hoursWorked</p>";
        echo "<p>Hourly Rate: $hourlyRate</p>";
        echo "<p>Basic Salary: $basicSalary</p>";
    }
} else {
    $staff['hours_worked'] = null;
    echo "<p>Staff is FIXED salary type, hours_worked set to NULL</p>";
}

echo "<h2>Final Staff Array (what's in \$batchStaff):</h2>";
echo "<pre>";
print_r($staff);
echo "</pre>";

// Now simulate the display logic - EXACT same as payroll/index.php lines 805-820
echo "<h2>Display Logic Simulation:</h2>";

$s = $staff; // This is what happens in the foreach loop
$basic = (float) ($s['basic_salary'] ?? 0);

echo "<p>\$basic = $basic</p>";

$salaryType = $s['salary_type'] ?? 'fixed';
echo "<p>\$salaryType = '$salaryType'</p>";

if ($salaryType === 'hourly') {
    echo "<p style='color: green;'>✓ Entered hourly display block</p>";
    
    $hoursWorked = $s['hours_worked'] ?? 0.0;
    $hourlyRate = $s['hourly_rate'] ?? 0.0;
    $belowThreshold = $hoursWorked < 1.0;
    
    echo "<p>\$hoursWorked = $hoursWorked</p>";
    echo "<p>\$hourlyRate = $hourlyRate</p>";
    echo "<p>\$belowThreshold = " . ($belowThreshold ? 'true' : 'false') . "</p>";
    
    echo "<h3>HTML Output:</h3>";
    echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f5f5f5;'>";
    ?>
    <div class="text-right">
        <span class="text-mb-silver">$<?= number_format($basic, 2) ?></span>
        <p class="text-[10px] text-mb-subtle/70 mt-0.5">
            <?= number_format($hoursWorked, 2) ?>h × $<?= number_format($hourlyRate, 2) ?>/h
        </p>
        <?php if ($belowThreshold): ?>
            <span class="inline-flex items-center gap-1 bg-orange-500/10 text-orange-400 border border-orange-500/20 rounded-full px-2 py-0.5 text-[10px] font-medium mt-1">
                Below 1hr threshold
            </span>
        <?php endif; ?>
    </div>
    <?php
    echo "</div>";
} else {
    echo "<p style='color: red;'>✗ Did NOT enter hourly display block - showing fixed salary</p>";
    echo "<p>Would display: \$" . number_format($basic, 2) . "</p>";
}

echo "<h2>Conclusion:</h2>";
if ($salaryType === 'hourly' && isset($staff['hours_worked'])) {
    echo "<p style='color: green; font-weight: bold;'>✓ Everything is working correctly! The hours should be displaying.</p>";
    echo "<p>If you're not seeing the hours in the actual payroll page, the issue might be:</p>";
    echo "<ul>";
    echo "<li>CSS hiding the text (check browser inspector)</li>";
    echo "<li>JavaScript interfering with display</li>";
    echo "<li>Browser cache (try hard refresh: Ctrl+Shift+R)</li>";
    echo "<li>Different staff member being viewed</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Issue found: Staff is not configured as hourly or data is missing</p>";
}
