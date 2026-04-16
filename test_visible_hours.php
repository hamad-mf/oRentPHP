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

$s = $staff;
$basic = (float) ($s['basic_salary'] ?? 0);

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'>";
echo "<script src='https://cdn.tailwindcss.com'></script>";
echo "</head><body class='bg-gray-900 text-white p-8'>";

echo "<h1 class='text-2xl mb-4'>Visibility Test - Different Font Sizes</h1>";

$salaryType = $s['salary_type'] ?? 'fixed';
if ($salaryType === 'hourly'): 
    $hoursWorked = $s['hours_worked'] ?? 0.0;
    $hourlyRate = $s['hourly_rate'] ?? 0.0;
    $belowThreshold = $hoursWorked < 1.0;
?>

<div class="space-y-8">
    <div class="bg-gray-800 p-6 rounded">
        <h2 class="text-lg mb-4">Original (10px, 70% opacity gray):</h2>
        <div class="text-right">
            <span class="text-gray-300">$<?= number_format($basic, 2) ?></span>
            <p class="text-[10px] text-gray-400/70 mt-0.5">
                <?= number_format($hoursWorked, 2) ?>h × $<?= number_format($hourlyRate, 2) ?>/h
            </p>
        </div>
    </div>

    <div class="bg-gray-800 p-6 rounded">
        <h2 class="text-lg mb-4">Larger (12px, 100% opacity gray):</h2>
        <div class="text-right">
            <span class="text-gray-300">$<?= number_format($basic, 2) ?></span>
            <p class="text-xs text-gray-400 mt-0.5">
                <?= number_format($hoursWorked, 2) ?>h × $<?= number_format($hourlyRate, 2) ?>/h
            </p>
        </div>
    </div>

    <div class="bg-gray-800 p-6 rounded">
        <h2 class="text-lg mb-4">Even Larger (14px, white):</h2>
        <div class="text-right">
            <span class="text-gray-300">$<?= number_format($basic, 2) ?></span>
            <p class="text-sm text-white mt-0.5">
                <?= number_format($hoursWorked, 2) ?>h × $<?= number_format($hourlyRate, 2) ?>/h
            </p>
        </div>
    </div>

    <div class="bg-gray-800 p-6 rounded">
        <h2 class="text-lg mb-4">Bright Color (12px, cyan):</h2>
        <div class="text-right">
            <span class="text-gray-300">$<?= number_format($basic, 2) ?></span>
            <p class="text-xs text-cyan-400 mt-0.5">
                <?= number_format($hoursWorked, 2) ?>h × $<?= number_format($hourlyRate, 2) ?>/h
            </p>
        </div>
    </div>
</div>

<?php endif; ?>

<div class="mt-8 p-4 bg-yellow-900/20 border border-yellow-500/30 rounded">
    <p class="text-yellow-400 font-semibold mb-2">Diagnosis:</p>
    <p class="text-gray-300">The original styling uses 10px font with 70% opacity gray text. This is VERY hard to see, especially on dark backgrounds. If you can barely see the first example above, that's your issue!</p>
</div>

</body></html>
