<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();
require_once __DIR__ . '/includes/payroll_helpers.php';

$pdo = db();

// Simulate March 2026 batch preparation
$prepareMonth = 3;
$prepareYear = 2026;

$payPeriodStart = sprintf('%04d-%02d-16', $prepareYear, $prepareMonth);
$payPeriodNextM = $prepareMonth === 12 ? 1 : $prepareMonth + 1;
$payPeriodNextY = $prepareMonth === 12 ? $prepareYear + 1 : $prepareYear;
$payPeriodEnd   = sprintf('%04d-%02d-15', $payPeriodNextY, $payPeriodNextM);

// Fetch staff1
$stmt = $pdo->prepare("
    SELECT u.id AS user_id, u.name, s.salary AS basic_salary,
           s.role AS staff_role, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

// Calculate hours
$salaryType = $staff['salary_type'] ?? null;
if ($salaryType === 'hourly') {
    $hourlyRate = $staff['hourly_rate'] ?? null;
    if ($hourlyRate !== null) {
        $hoursWorked = calculate_hours_worked($pdo, $staff['user_id'], $payPeriodStart, $payPeriodEnd);
        $basicSalary = calculate_hourly_payment($hoursWorked, $hourlyRate);
        $staff['hours_worked'] = $hoursWorked;
        $staff['basic_salary'] = $basicSalary;
    }
}

// Now render EXACTLY as payroll/index.php does
$s = $staff;
$basic = (float) ($s['basic_salary'] ?? 0);

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'>";
echo "<script src='https://cdn.tailwindcss.com'></script>";
echo "</head><body class='bg-gray-900 text-white p-8'>";

echo "<h1 class='text-2xl mb-4'>Display Rendering Test</h1>";

echo "<h2 class='text-xl mb-2'>Data Check:</h2>";
echo "<pre class='bg-gray-800 p-4 rounded mb-4'>";
echo "salary_type: " . ($s['salary_type'] ?? 'NULL') . "\n";
echo "hours_worked: " . ($s['hours_worked'] ?? 'NULL') . "\n";
echo "hourly_rate: " . ($s['hourly_rate'] ?? 'NULL') . "\n";
echo "basic_salary: " . $basic . "\n";
echo "</pre>";

echo "<h2 class='text-xl mb-2'>Rendered Output (EXACT code from payroll/index.php):</h2>";
echo "<div class='bg-gray-800 p-4 rounded'>";

// EXACT code from payroll/index.php lines 805-820
$salaryType = $s['salary_type'] ?? 'fixed';
if ($salaryType === 'hourly'): 
    $hoursWorked = $s['hours_worked'] ?? 0.0;
    $hourlyRate = $s['hourly_rate'] ?? 0.0;
    $belowThreshold = $hoursWorked < 1.0;
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
<?php else: ?>
    $<?= number_format($basic, 2) ?>
<?php endif;

echo "</div>";

echo "<h2 class='text-xl mt-4 mb-2'>Condition Check:</h2>";
echo "<pre class='bg-gray-800 p-4 rounded'>";
echo "\$salaryType = '$salaryType'\n";
echo "Is 'hourly'? " . ($salaryType === 'hourly' ? 'YES' : 'NO') . "\n";
echo "Entered hourly block? " . ($salaryType === 'hourly' ? 'YES' : 'NO') . "\n";
echo "</pre>";

echo "</body></html>";
