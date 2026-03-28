<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

auth_check();

// Get database connection
$pdo = db();

// Permission check: view_finances OR admin
$user = current_user();
$isAdmin = $user['role'] === 'admin';
$canView = $isAdmin || auth_has_perm('view_finances');

if (!$canView) {
    flash('error', 'Access denied. You do not have permission to view this page.');
    redirect('../index.php');
    exit;
}

// Period calculation functions (15th to 14th next month)
function vt_period_from_my(int $m, int $y): array
{
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}

function vt_period_for_today(): array
{
    $d = (int) date('d');
    $m = (int) date('m');
    $y = (int) date('Y');
    if ($d >= 15) {
        return vt_period_from_my($m, $y);
    }
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return vt_period_from_my($pm, $py);
}

/**
 * Calculate actual income per vehicle for a given period.
 * Returns associative array: [vehicle_id => total_income]
 * 
 * @param PDO $pdo Database connection
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @param string $periodEnd Period end date (YYYY-MM-DD)
 * @return array Associative array of vehicle_id => income amount
 */
function vt_calculate_vehicle_income(PDO $pdo, string $periodStart, string $periodEnd): array
{
    $exclusionClause = ledger_kpi_exclusion_clause('le');
    
    $sql = "
        SELECT 
            r.vehicle_id,
            SUM(le.amount) AS total_income
        FROM ledger_entries le
        INNER JOIN reservations r 
            ON le.source_type = 'reservation' 
            AND le.source_id = r.id
        WHERE le.txn_type = 'income'
            AND $exclusionClause
            AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
            AND r.vehicle_id IS NOT NULL
        GROUP BY r.vehicle_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'period_start' => $periodStart,
        'period_end' => $periodEnd
    ]);
    
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[(int)$row['vehicle_id']] = (float)$row['total_income'];
    }
    
    return $result;
}

/**
 * Save or update a vehicle target for a specific period.
 * If amount is 0 or empty, deletes the target.
 * 
 * @param PDO $pdo Database connection
 * @param int $vehicleId Vehicle ID
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @param string $periodEnd Period end date (YYYY-MM-DD)
 * @param float $amount Target amount (>= 0)
 * @param string|null $notes Optional notes
 * @param int $userId Current user ID
 * @return bool Success status
 */
function vt_save_vehicle_target(
    PDO $pdo,
    int $vehicleId,
    string $periodStart,
    string $periodEnd,
    float $amount,
    ?string $notes,
    int $userId
): bool {
    try {
        // If amount is 0 or empty, delete the target
        if ($amount <= 0) {
            return vt_delete_vehicle_target($pdo, $vehicleId, $periodStart);
        }
        
        // Upsert target
        $sql = "
            INSERT INTO vehicle_monthly_targets 
                (vehicle_id, period_start, period_end, target_amount, notes, created_by)
            VALUES 
                (:vehicle_id, :period_start, :period_end, :target_amount, :notes, :created_by)
            ON DUPLICATE KEY UPDATE
                target_amount = VALUES(target_amount),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'vehicle_id' => $vehicleId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'target_amount' => $amount,
            'notes' => $notes,
            'created_by' => $userId
        ]);
        
        if ($result) {
            app_log('ACTION', "Vehicle target saved: vehicle_id=$vehicleId, period=$periodStart, amount=$amount");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error saving vehicle target: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a vehicle target for a specific period.
 * 
 * @param PDO $pdo Database connection
 * @param int $vehicleId Vehicle ID
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @return bool Success status
 */
function vt_delete_vehicle_target(PDO $pdo, int $vehicleId, string $periodStart): bool
{
    try {
        $sql = "DELETE FROM vehicle_monthly_targets WHERE vehicle_id = :vehicle_id AND period_start = :period_start";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'vehicle_id' => $vehicleId,
            'period_start' => $periodStart
        ]);
        
        if ($result) {
            app_log('ACTION', "Vehicle target deleted: vehicle_id=$vehicleId, period=$periodStart");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error deleting vehicle target: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a single vehicle target for a specific period.
 * 
 * @param PDO $pdo Database connection
 * @param int $vehicleId Vehicle ID
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @return array|null Target data or null if not found
 */
function vt_get_vehicle_target(PDO $pdo, int $vehicleId, string $periodStart): ?array
{
    $sql = "
        SELECT * 
        FROM vehicle_monthly_targets 
        WHERE vehicle_id = :vehicle_id AND period_start = :period_start
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'vehicle_id' => $vehicleId,
        'period_start' => $periodStart
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Get all vehicle targets for a specific period.
 * Returns associative array: [vehicle_id => target_data]
 * 
 * @param PDO $pdo Database connection
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @return array Associative array of vehicle_id => target data
 */
function vt_get_all_targets(PDO $pdo, string $periodStart): array
{
    $sql = "
        SELECT * 
        FROM vehicle_monthly_targets 
        WHERE period_start = :period_start
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['period_start' => $periodStart]);
    
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[(int)$row['vehicle_id']] = $row;
    }
    
    return $result;
}

/**
 * Bulk set same amount for multiple vehicles.
 * 
 * @param PDO $pdo Database connection
 * @param array $vehicleIds Array of vehicle IDs
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @param string $periodEnd Period end date (YYYY-MM-DD)
 * @param float $amount Target amount for each vehicle
 * @param int $userId Current user ID
 * @return int Count of successfully updated vehicles
 */
function vt_bulk_set_same_amount(
    PDO $pdo,
    array $vehicleIds,
    string $periodStart,
    string $periodEnd,
    float $amount,
    int $userId
): int {
    if (empty($vehicleIds)) {
        return 0;
    }
    
    try {
        $pdo->beginTransaction();
        
        $count = 0;
        foreach ($vehicleIds as $vehicleId) {
            if (vt_save_vehicle_target($pdo, (int)$vehicleId, $periodStart, $periodEnd, $amount, null, $userId)) {
                $count++;
            }
        }
        
        $pdo->commit();
        app_log('ACTION', "Bulk set same amount: $count vehicles, amount=$amount, period=$periodStart");
        
        return $count;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in bulk set same amount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Bulk distribute total amount equally among vehicles.
 * 
 * @param PDO $pdo Database connection
 * @param array $vehicleIds Array of vehicle IDs
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @param string $periodEnd Period end date (YYYY-MM-DD)
 * @param float $totalAmount Total amount to distribute
 * @param int $userId Current user ID
 * @return int Count of successfully updated vehicles
 */
function vt_bulk_distribute_equally(
    PDO $pdo,
    array $vehicleIds,
    string $periodStart,
    string $periodEnd,
    float $totalAmount,
    int $userId
): int {
    if (empty($vehicleIds)) {
        return 0;
    }
    
    $perVehicleAmount = round($totalAmount / count($vehicleIds), 2);
    
    return vt_bulk_set_same_amount($pdo, $vehicleIds, $periodStart, $periodEnd, $perVehicleAmount, $userId);
}

/**
 * Bulk distribute total amount proportionally based on vehicle daily rates.
 * 
 * @param PDO $pdo Database connection
 * @param array $vehicleIds Array of vehicle IDs
 * @param string $periodStart Period start date (YYYY-MM-DD)
 * @param string $periodEnd Period end date (YYYY-MM-DD)
 * @param float $totalAmount Total amount to distribute
 * @param int $userId Current user ID
 * @return int Count of successfully updated vehicles
 */
function vt_bulk_distribute_proportionally(
    PDO $pdo,
    array $vehicleIds,
    string $periodStart,
    string $periodEnd,
    float $totalAmount,
    int $userId
): int {
    if (empty($vehicleIds)) {
        return 0;
    }
    
    try {
        // Get daily rates for all vehicles
        $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
        $sql = "SELECT id, daily_rate FROM vehicles WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vehicleIds);
        
        $vehicles = [];
        $totalRate = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vehicles[(int)$row['id']] = (float)$row['daily_rate'];
            $totalRate += (float)$row['daily_rate'];
        }
        
        if ($totalRate <= 0) {
            return 0;
        }
        
        // Calculate proportional amounts
        $pdo->beginTransaction();
        
        $count = 0;
        foreach ($vehicleIds as $vehicleId) {
            $vid = (int)$vehicleId;
            if (!isset($vehicles[$vid])) {
                continue;
            }
            
            $proportion = $vehicles[$vid] / $totalRate;
            $amount = round($totalAmount * $proportion, 2);
            
            if (vt_save_vehicle_target($pdo, $vid, $periodStart, $periodEnd, $amount, null, $userId)) {
                $count++;
            }
        }
        
        $pdo->commit();
        app_log('ACTION', "Bulk distribute proportionally: $count vehicles, total=$totalAmount, period=$periodStart");
        
        return $count;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in bulk distribute proportionally: " . $e->getMessage());
        return 0;
    }
}

// Get period from URL or default to current (needed for POST handler)
$selM = isset($_GET['m']) ? (int)$_GET['m'] : null;
$selY = isset($_GET['y']) ? (int)$_GET['y'] : null;

// Validate and default to current period
if ($selM === null || $selY === null || $selM < 1 || $selM > 12 || $selY < 2020 || $selY > 2099) {
    $currentPeriod = vt_period_for_today();
    $selM = (int)date('m', strtotime($currentPeriod['start']));
    $selY = (int)date('Y', strtotime($currentPeriod['start']));
}

$period = vt_period_from_my($selM, $selY);
$periodStart = $period['start'];
$periodEnd = $period['end'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Only admin can save targets
    if (!$isAdmin) {
        flash('error', 'Only administrators can modify targets.');
        redirect("vehicle_targets.php?m=$selM&y=$selY");
        exit;
    }
    
    if ($action === 'save_target') {
        // Validate inputs
        $vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
        $targetAmount = isset($_POST['target_amount']) ? (float)$_POST['target_amount'] : 0;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        
        if ($vehicleId <= 0) {
            flash('error', 'Invalid vehicle.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        if ($targetAmount < 0) {
            flash('error', 'Target amount cannot be negative.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        // Check if vehicle exists and is not sold
        $checkSql = "SELECT id, status FROM vehicles WHERE id = :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute(['id' => $vehicleId]);
        $vehicle = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            flash('error', 'Vehicle not found.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        if ($vehicle['status'] === 'sold') {
            flash('error', 'Cannot set target for sold vehicle.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        // Save target
        $success = vt_save_vehicle_target(
            $pdo,
            $vehicleId,
            $periodStart,
            $periodEnd,
            $targetAmount,
            $notes,
            $user['id']
        );
        
        if ($success) {
            if ($targetAmount > 0) {
                flash('success', 'Target saved successfully.');
            } else {
                flash('success', 'Target removed successfully.');
            }
        } else {
            flash('error', 'Failed to save target. Please try again.');
        }
        
        redirect("vehicle_targets.php?m=$selM&y=$selY");
        exit;
    }
    
    if ($action === 'save_bulk') {
        // Validate inputs
        $vehicleIds = isset($_POST['vehicle_ids']) ? $_POST['vehicle_ids'] : [];
        $method = isset($_POST['method']) ? trim($_POST['method']) : '';
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        
        if (empty($vehicleIds) || !is_array($vehicleIds)) {
            flash('error', 'Please select at least one vehicle.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        if (!in_array($method, ['same', 'equal', 'proportional'])) {
            flash('error', 'Invalid distribution method.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        if ($amount <= 0) {
            flash('error', 'Amount must be greater than zero.');
            redirect("vehicle_targets.php?m=$selM&y=$selY");
            exit;
        }
        
        // Convert vehicle IDs to integers
        $vehicleIds = array_map('intval', $vehicleIds);
        
        // Call appropriate bulk function
        $count = 0;
        switch ($method) {
            case 'same':
                $count = vt_bulk_set_same_amount($pdo, $vehicleIds, $periodStart, $periodEnd, $amount, $user['id']);
                break;
            case 'equal':
                $count = vt_bulk_distribute_equally($pdo, $vehicleIds, $periodStart, $periodEnd, $amount, $user['id']);
                break;
            case 'proportional':
                $count = vt_bulk_distribute_proportionally($pdo, $vehicleIds, $periodStart, $periodEnd, $amount, $user['id']);
                break;
        }
        
        if ($count > 0) {
            flash('success', "Successfully updated targets for $count vehicle(s).");
        } else {
            flash('error', 'Failed to update targets. Please try again.');
        }
        
        redirect("vehicle_targets.php?m=$selM&y=$selY");
        exit;
    }
}

// Calculate number of days in period
$days = (strtotime($periodEnd) - strtotime($periodStart)) / 86400 + 1;

// Format period label
$startDate = new DateTime($periodStart);
$endDate = new DateTime($periodEnd);
$periodLabel = $startDate->format('d M') . ' – ' . $endDate->format('d M Y');

// Fetch all active vehicles (excluding sold)
$vehiclesSql = "
    SELECT 
        id,
        CONCAT(brand, ' ', model, ' (', license_plate, ')') AS vehicle_name,
        daily_rate
    FROM vehicles
    WHERE status != 'sold'
    ORDER BY brand, model
";
$vehiclesStmt = $pdo->query($vehiclesSql);
$vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all targets for this period
$targets = vt_get_all_targets($pdo, $periodStart);

// Calculate income for all vehicles
$incomeData = vt_calculate_vehicle_income($pdo, $periodStart, $periodEnd);

// Build display rows
$displayRows = [];
$totalTarget = 0;
$totalActual = 0;

foreach ($vehicles as $vehicle) {
    $vehicleId = (int)$vehicle['id'];
    $targetAmount = isset($targets[$vehicleId]) ? (float)$targets[$vehicleId]['target_amount'] : 0;
    $actualIncome = isset($incomeData[$vehicleId]) ? $incomeData[$vehicleId] : 0;
    $balance = $targetAmount - $actualIncome;
    $achievementPct = $targetAmount > 0 ? ($actualIncome / $targetAmount) * 100 : 0;
    
    // Determine status indicator
    $status = 'none';
    if ($targetAmount > 0) {
        if ($achievementPct >= 100) {
            $status = 'success';
        } elseif ($achievementPct >= 50) {
            $status = 'warning';
        } else {
            $status = 'danger';
        }
    }
    
    $displayRows[] = [
        'vehicle_id' => $vehicleId,
        'vehicle_name' => $vehicle['vehicle_name'],
        'daily_rate' => (float)$vehicle['daily_rate'],
        'target_amount' => $targetAmount,
        'actual_income' => $actualIncome,
        'balance' => $balance,
        'achievement_pct' => $achievementPct,
        'status' => $status,
        'notes' => isset($targets[$vehicleId]) ? $targets[$vehicleId]['notes'] : null
    ];
    
    $totalTarget += $targetAmount;
    $totalActual += $actualIncome;
}

$totalBalance = $totalTarget - $totalActual;
$overallPct = $totalTarget > 0 ? ($totalActual / $totalTarget) * 100 : 0;

// Helper function to format currency
function vt_format_currency(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

// Helper function to get status color classes
function vt_status_classes(string $status): string
{
    switch ($status) {
        case 'success':
            return 'bg-green-500/10 text-green-400 border-green-500/30';
        case 'warning':
            return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30';
        case 'danger':
            return 'bg-red-500/10 text-red-400 border-red-500/30';
        default:
            return 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/20';
    }
}

$success = getFlash('success');
$error = getFlash('error');
$pageTitle = 'Vehicle Targets';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-white">Vehicle Targets</h1>
                    <p class="text-sm text-mb-subtle mt-1">
                        <?= e($periodLabel) ?> (<?= $days ?> days)
                    </p>
                </div>
                <a href="hope_window.php?m=<?= $selM ?>&y=<?= $selY ?>" 
                   class="text-sm text-mb-accent hover:text-mb-accent/80">
                    ← Back to Hope Window
                </a>
            </div>
        </div>

        <!-- Period Selector -->
        <div class="mb-6">
            <form method="GET" class="flex items-center gap-3">
                <select name="m" class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-base font-medium min-w-[200px]">
                    <?php
                    foreach (range(1, 12) as $mVal):
                        $mNext = $mVal === 12 ? 1 : $mVal + 1;
                        $mLabel = '15 ' . date('M', mktime(0,0,0,$mVal,1)) . ' – 14 ' . date('M', mktime(0,0,0,$mNext,1));
                    ?>
                        <option value="<?= $mVal ?>" <?= $selM === $mVal ? 'selected' : '' ?>><?= $mLabel ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="y" class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-base font-medium min-w-[120px]">
                    <?php
                    $currentYear = (int)date('Y');
                    for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++):
                    ?>
                        <option value="<?= $y ?>" <?= $selY === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="bg-mb-accent hover:bg-mb-accent/80 text-white px-6 py-3 rounded-lg text-base font-medium">
                    Go
                </button>
            </form>
        </div>

        <!-- Bulk Operations Buttons -->
        <?php if ($isAdmin && !empty($displayRows)): ?>
        <div class="mb-6 flex items-center gap-3">
            <button 
                onclick="openBulkModal('all')"
                class="bg-mb-accent hover:bg-mb-accent/80 text-white px-4 py-2 rounded text-sm">
                Set All Targets
            </button>
            <button 
                id="bulk-selected-btn"
                onclick="openBulkModal('selected')"
                disabled
                class="bg-mb-surface border border-mb-accent/50 text-white px-4 py-2 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:enabled:bg-mb-accent/20">
                Set Targets for Selected
            </button>
            <span id="selected-count" class="text-sm text-mb-subtle"></span>
        </div>
        <?php endif; ?>

        <!-- Vehicle List Table -->
        <?php if (empty($displayRows)): ?>
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-8 text-center">
                <p class="text-mb-subtle">No vehicles found. Add vehicles to start tracking targets.</p>
            </div>
        <?php else: ?>
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-mb-bg/50 border-b border-mb-subtle/20">
                            <tr>
                                <?php if ($isAdmin): ?>
                                <th class="px-4 py-3 text-center text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    <input 
                                        type="checkbox" 
                                        id="select-all"
                                        onchange="toggleSelectAll(this)"
                                        class="rounded border-mb-subtle/30 bg-mb-bg text-mb-accent focus:ring-mb-accent focus:ring-offset-0">
                                </th>
                                <?php endif; ?>
                                <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    Vehicle
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    Target
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    Actual
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    Balance
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    Achievement
                                </th>
                                <?php if ($isAdmin): ?>
                                <th class="px-4 py-3 text-center text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                    Actions
                                </th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mb-subtle/10">
                            <?php foreach ($displayRows as $row): ?>
                            <tr class="hover:bg-mb-bg/30 transition-colors">
                                <?php if ($isAdmin): ?>
                                <td class="px-4 py-3 text-center">
                                    <input 
                                        type="checkbox" 
                                        class="vehicle-checkbox rounded border-mb-subtle/30 bg-mb-bg text-mb-accent focus:ring-mb-accent focus:ring-offset-0"
                                        data-vehicle-id="<?= $row['vehicle_id'] ?>"
                                        data-vehicle-name="<?= e($row['vehicle_name']) ?>"
                                        data-daily-rate="<?= $row['daily_rate'] ?>"
                                        onchange="updateSelectedCount()">
                                </td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-sm text-white">
                                    <?= e($row['vehicle_name']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-white">
                                    <?= vt_format_currency($row['target_amount']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-white">
                                    <?= vt_format_currency($row['actual_income']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-right <?= $row['balance'] < 0 ? 'text-green-400' : 'text-white' ?>">
                                    <?= vt_format_currency($row['balance']) ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= vt_status_classes($row['status']) ?>">
                                        <?= number_format($row['achievement_pct'], 1) ?>%
                                    </span>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td class="px-4 py-3 text-center">
                                    <button 
                                        onclick="openEditModal(<?= $row['vehicle_id'] ?>, '<?= e($row['vehicle_name']) ?>', <?= $row['target_amount'] ?>, '<?= e($row['notes'] ?? '') ?>')"
                                        class="text-mb-accent hover:text-mb-accent/80 text-sm">
                                        Edit Target
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-mb-bg/50 border-t-2 border-mb-accent/30">
                            <tr>
                                <?php if ($isAdmin): ?>
                                <td class="px-4 py-3"></td>
                                <?php endif; ?>
                                <td class="px-4 py-3 text-sm font-bold text-white">
                                    Total
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-right text-white">
                                    <?= vt_format_currency($totalTarget) ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-right text-white">
                                    <?= vt_format_currency($totalActual) ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-right <?= $totalBalance < 0 ? 'text-green-400' : 'text-white' ?>">
                                    <?= vt_format_currency($totalBalance) ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?= vt_status_classes($overallPct >= 100 ? 'success' : ($overallPct >= 50 ? 'warning' : 'danger')) ?>">
                                        <?= number_format($overallPct, 1) ?>%
                                    </span>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td class="px-4 py-3"></td>
                                <?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Target Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-bold text-white mb-4">Edit Vehicle Target</h3>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="save_target">
                <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                
                <div class="mb-4">
                    <label class="block text-sm text-mb-subtle mb-2">Vehicle</label>
                    <p class="text-white" id="edit_vehicle_name"></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm text-mb-subtle mb-2">Period</label>
                    <p class="text-white"><?= e($periodLabel) ?></p>
                </div>
                
                <div class="mb-4">
                    <label for="edit_target_amount" class="block text-sm text-mb-subtle mb-2">
                        Target Amount <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="number" 
                        name="target_amount" 
                        id="edit_target_amount"
                        step="0.01"
                        min="0"
                        required
                        style="color: #ffffff !important; -webkit-text-fill-color: #ffffff !important; background-color: #000000 !important;"
                        class="w-full border border-mb-subtle/20 rounded px-3 py-2 text-white focus:outline-none focus:border-mb-accent"
                        placeholder="0.00">
                    <p class="text-xs text-mb-subtle mt-1">Enter 0 to remove the target</p>
                </div>
                
                <div class="mb-6">
                    <label for="edit_notes" class="block text-sm text-mb-subtle mb-2">Notes (Optional)</label>
                    <textarea 
                        name="notes" 
                        id="edit_notes"
                        rows="3"
                        style="background-color: #1a1a1a !important; color: #ffffff !important;"
                        class="w-full border border-mb-subtle/20 rounded px-3 py-2 text-white focus:outline-none focus:border-mb-accent"
                        placeholder="Optional notes..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button 
                        type="submit"
                        class="flex-1 bg-mb-accent hover:bg-mb-accent/80 text-white px-4 py-2 rounded font-medium">
                        Save Target
                    </button>
                    <button 
                        type="button"
                        onclick="closeEditModal()"
                        class="flex-1 bg-mb-bg hover:bg-mb-bg/80 text-white px-4 py-2 rounded font-medium border border-mb-subtle/20">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Operations Modal -->
    <div id="bulkModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-bold text-white mb-4" id="bulk-modal-title">Set Targets</h3>
            
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="save_bulk">
                <input type="hidden" name="method" id="bulk_method" value="same">
                <div id="bulk-vehicle-ids"></div>
                
                <div class="mb-4">
                    <label class="block text-sm text-mb-subtle mb-2">Period</label>
                    <p class="text-white"><?= e($periodLabel) ?></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm text-mb-subtle mb-2">Vehicles</label>
                    <p class="text-white" id="bulk-vehicle-count"></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm text-mb-subtle mb-2">Distribution Method</label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 border border-mb-subtle/20 rounded cursor-pointer hover:bg-mb-bg/30">
                            <input 
                                type="radio" 
                                name="method_radio" 
                                value="same"
                                checked
                                onchange="updateBulkMethod('same')"
                                class="mt-1 rounded-full border-mb-subtle/30 bg-mb-bg text-mb-accent focus:ring-mb-accent focus:ring-offset-0">
                            <div class="flex-1">
                                <p class="text-white font-medium">Set Same Amount</p>
                                <p class="text-xs text-mb-subtle">Each vehicle gets the same target amount</p>
                            </div>
                        </label>
                        
                        <label class="flex items-start gap-3 p-3 border border-mb-subtle/20 rounded cursor-pointer hover:bg-mb-bg/30">
                            <input 
                                type="radio" 
                                name="method_radio" 
                                value="equal"
                                onchange="updateBulkMethod('equal')"
                                class="mt-1 rounded-full border-mb-subtle/30 bg-mb-bg text-mb-accent focus:ring-mb-accent focus:ring-offset-0">
                            <div class="flex-1">
                                <p class="text-white font-medium">Distribute Equally</p>
                                <p class="text-xs text-mb-subtle">Total amount divided equally among vehicles</p>
                            </div>
                        </label>
                        
                        <label class="flex items-start gap-3 p-3 border border-mb-subtle/20 rounded cursor-pointer hover:bg-mb-bg/30">
                            <input 
                                type="radio" 
                                name="method_radio" 
                                value="proportional"
                                onchange="updateBulkMethod('proportional')"
                                class="mt-1 rounded-full border-mb-subtle/30 bg-mb-bg text-mb-accent focus:ring-mb-accent focus:ring-offset-0">
                            <div class="flex-1">
                                <p class="text-white font-medium">Distribute Proportionally</p>
                                <p class="text-xs text-mb-subtle">Total amount distributed based on daily rates</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="bulk_amount" class="block text-sm text-mb-subtle mb-2">
                        <span id="bulk-amount-label">Amount per Vehicle</span> <span class="text-red-400">*</span>
                    </label>
                    <input 
                        type="number" 
                        name="amount" 
                        id="bulk_amount"
                        step="0.01"
                        min="0.01"
                        required
                        oninput="updateBulkPreview()"
                        style="color: #ffffff !important; -webkit-text-fill-color: #ffffff !important; background-color: #000000 !important;"
                        class="w-full border border-mb-subtle/20 rounded px-3 py-2 text-white focus:outline-none focus:border-mb-accent"
                        placeholder="0.00">
                </div>
                
                <div class="mb-6 bg-mb-bg/50 border border-mb-subtle/20 rounded p-4">
                    <p class="text-sm text-mb-subtle mb-2">Preview</p>
                    <div id="bulk-preview" class="text-sm text-white space-y-1">
                        <p>Enter an amount to see preview</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button 
                        type="submit"
                        class="flex-1 bg-mb-accent hover:bg-mb-accent/80 text-white px-4 py-2 rounded font-medium">
                        Apply Targets
                    </button>
                    <button 
                        type="button"
                        onclick="closeBulkModal()"
                        class="flex-1 bg-mb-bg hover:bg-mb-bg/80 text-white px-4 py-2 rounded font-medium border border-mb-subtle/20">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Force white text color for number inputs in modals with black background */
        #editModal input[type="number"],
        #bulkModal input[type="number"] {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            background-color: #000000 !important;
        }
        /* Fix textarea background in edit modal */
        #editModal textarea,
        #bulkModal textarea {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
    </style>

    <script>
        // Vehicle data for bulk operations
        const vehicleData = <?= json_encode(array_map(function($row) {
            return [
                'id' => $row['vehicle_id'],
                'name' => $row['vehicle_name'],
                'daily_rate' => $row['daily_rate']
            ];
        }, $displayRows)) ?>;
        
        // Selection tracking
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.vehicle-checkbox:checked');
            const count = checkboxes.length;
            const countSpan = document.getElementById('selected-count');
            const bulkBtn = document.getElementById('bulk-selected-btn');
            
            if (count > 0) {
                countSpan.textContent = `${count} vehicle(s) selected`;
                bulkBtn.disabled = false;
            } else {
                countSpan.textContent = '';
                bulkBtn.disabled = true;
            }
        }
        
        function toggleSelectAll(checkbox) {
            const vehicleCheckboxes = document.querySelectorAll('.vehicle-checkbox');
            vehicleCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }
        
        // Edit modal functions
        function openEditModal(vehicleId, vehicleName, currentTarget, currentNotes) {
            document.getElementById('edit_vehicle_id').value = vehicleId;
            document.getElementById('edit_vehicle_name').textContent = vehicleName;
            document.getElementById('edit_target_amount').value = currentTarget;
            document.getElementById('edit_notes').value = currentNotes || '';
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editForm').reset();
        }
        
        // Bulk operations modal functions
        let bulkMode = 'all'; // 'all' or 'selected'
        
        function openBulkModal(mode) {
            bulkMode = mode;
            
            // Get selected vehicles
            let selectedVehicles = [];
            if (mode === 'all') {
                selectedVehicles = vehicleData;
                document.getElementById('bulk-modal-title').textContent = 'Set All Targets';
            } else {
                const checkboxes = document.querySelectorAll('.vehicle-checkbox:checked');
                checkboxes.forEach(cb => {
                    const vehicleId = parseInt(cb.dataset.vehicleId);
                    const vehicle = vehicleData.find(v => v.id === vehicleId);
                    if (vehicle) {
                        selectedVehicles.push(vehicle);
                    }
                });
                document.getElementById('bulk-modal-title').textContent = 'Set Targets for Selected';
            }
            
            if (selectedVehicles.length === 0) {
                alert('No vehicles selected');
                return;
            }
            
            // Store selected vehicles
            window.bulkSelectedVehicles = selectedVehicles;
            
            // Update vehicle count
            document.getElementById('bulk-vehicle-count').textContent = 
                `${selectedVehicles.length} vehicle(s)`;
            
            // Clear vehicle IDs container and add hidden inputs
            const idsContainer = document.getElementById('bulk-vehicle-ids');
            idsContainer.innerHTML = '';
            selectedVehicles.forEach(v => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'vehicle_ids[]';
                input.value = v.id;
                idsContainer.appendChild(input);
            });
            
            // Reset form
            document.getElementById('bulkForm').reset();
            document.querySelector('input[name="method_radio"][value="same"]').checked = true;
            document.getElementById('bulk_method').value = 'same';
            document.getElementById('bulk_amount').value = '';
            updateBulkMethod('same');
            
            // Show modal
            document.getElementById('bulkModal').classList.remove('hidden');
        }
        
        function closeBulkModal() {
            document.getElementById('bulkModal').classList.add('hidden');
            document.getElementById('bulkForm').reset();
        }
        
        function updateBulkMethod(method) {
            document.getElementById('bulk_method').value = method;
            
            const amountLabel = document.getElementById('bulk-amount-label');
            if (method === 'same') {
                amountLabel.textContent = 'Amount per Vehicle';
            } else {
                amountLabel.textContent = 'Total Amount';
            }
            
            updateBulkPreview();
        }
        
        function updateBulkPreview() {
            const method = document.getElementById('bulk_method').value;
            const amount = parseFloat(document.getElementById('bulk_amount').value) || 0;
            const vehicles = window.bulkSelectedVehicles || [];
            const previewDiv = document.getElementById('bulk-preview');
            
            if (amount <= 0 || vehicles.length === 0) {
                previewDiv.innerHTML = '<p>Enter an amount to see preview</p>';
                return;
            }
            
            let preview = '';
            
            if (method === 'same') {
                // Same amount for each
                preview = `<p class="font-medium mb-2">Each vehicle: ₹${amount.toFixed(2)}</p>`;
                preview += `<p class="text-mb-subtle">Total: ₹${(amount * vehicles.length).toFixed(2)}</p>`;
            } else if (method === 'equal') {
                // Distribute equally
                const perVehicle = amount / vehicles.length;
                preview = `<p class="font-medium mb-2">Each vehicle: ₹${perVehicle.toFixed(2)}</p>`;
                preview += `<p class="text-mb-subtle">Total: ₹${amount.toFixed(2)}</p>`;
            } else if (method === 'proportional') {
                // Distribute proportionally by daily rate
                const totalRate = vehicles.reduce((sum, v) => sum + parseFloat(v.daily_rate), 0);
                
                if (totalRate <= 0) {
                    preview = '<p class="text-red-400">Cannot distribute proportionally: vehicles have no daily rates</p>';
                } else {
                    preview = '<p class="font-medium mb-2">Per vehicle (by daily rate):</p>';
                    preview += '<div class="space-y-1 max-h-40 overflow-y-auto">';
                    
                    vehicles.forEach(v => {
                        const proportion = parseFloat(v.daily_rate) / totalRate;
                        const vehicleAmount = amount * proportion;
                        preview += `<p class="text-xs"><span class="text-mb-subtle">${v.name}:</span> ₹${vehicleAmount.toFixed(2)}</p>`;
                    });
                    
                    preview += '</div>';
                    preview += `<p class="text-mb-subtle mt-2">Total: ₹${amount.toFixed(2)}</p>`;
                }
            }
            
            previewDiv.innerHTML = preview;
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
                closeBulkModal();
            }
        });
        
        // Close modals on background click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        document.getElementById('bulkModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBulkModal();
            }
        });
    </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
