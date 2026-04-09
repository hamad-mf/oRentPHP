<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$_currentUser = current_user();
if (!auth_has_perm('view_finances') && ($_currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Access denied. You do not have permission to view this page.');
    redirect('../index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';

// Period calculation functions (15th to 14th next month)
function period_from_my(int $m, int $y): array {
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}

function period_for_today(): array {
    $d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y');
    if ($d >= 15) return period_from_my($m, $y);
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return period_from_my($pm, $py);
}

// Calculate vehicle income for a period
function vfr_calculate_vehicle_income(PDO $pdo, string $periodStart, string $periodEnd): array {
    try {
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
                AND le.category NOT LIKE '%Security Deposit%'
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
    } catch (PDOException $e) {
        app_log('ERROR', 'Failed to calculate vehicle income: ' . $e->getMessage());
        return [];
    }
}

// Calculate vehicle expenses for a period
function vfr_calculate_vehicle_expenses(PDO $pdo, string $periodStart, string $periodEnd): array {
    try {
        $exclusionClause = ledger_kpi_exclusion_clause('le');
        
        // Use subquery to resolve vehicle_id first, then GROUP BY
        // This avoids MySQL GROUP BY issues with CASE expressions
        $sql = "
            SELECT 
                vehicle_id,
                SUM(amount) AS total_expense
            FROM (
                SELECT 
                    CASE 
                        WHEN le.source_type = 'reservation' THEN r.vehicle_id
                        WHEN le.source_type = 'vehicle_expense' THEN le.source_id
                    END AS vehicle_id,
                    le.amount
                FROM ledger_entries le
                LEFT JOIN reservations r 
                    ON le.source_type = 'reservation' 
                    AND le.source_id = r.id
                WHERE le.txn_type = 'expense'
                    AND $exclusionClause
                    AND le.category NOT LIKE '%Security Deposit%'
                    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
                    AND (
                        (le.source_type = 'reservation' AND r.vehicle_id IS NOT NULL)
                        OR le.source_type = 'vehicle_expense'
                    )
            ) AS expenses
            WHERE vehicle_id IS NOT NULL
            GROUP BY vehicle_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['vehicle_id']) {
                $result[(int)$row['vehicle_id']] = (float)$row['total_expense'];
            }
        }
        
        return $result;
    } catch (PDOException $e) {
        app_log('ERROR', 'Failed to calculate vehicle expenses: ' . $e->getMessage());
        return [];
    }
}

// Get active vehicles (excluding sold)
function vfr_get_active_vehicles(PDO $pdo): array {
    try {
        $sql = "
            SELECT id, brand, model, license_plate
            FROM vehicles
            WHERE status != 'sold'
            ORDER BY brand, model
        ";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        app_log('ERROR', 'Failed to get active vehicles: ' . $e->getMessage());
        return [];
    }
}

// Get detailed income entries for a vehicle
function vfr_get_vehicle_income_details(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd): array {
    try {
        $exclusionClause = ledger_kpi_exclusion_clause('le');
        
        $sql = "
            SELECT 
                le.id, le.amount, le.description, le.category, 
                le.source_event, le.source_id, le.payment_mode, le.posted_at,
                c.name AS client_name,
                r.id AS reservation_id
            FROM ledger_entries le
            INNER JOIN reservations r 
                ON le.source_type = 'reservation' 
                AND le.source_id = r.id
            LEFT JOIN clients c ON r.client_id = c.id
            WHERE le.txn_type = 'income'
                AND $exclusionClause
                AND le.category NOT LIKE '%Security Deposit%'
                AND r.vehicle_id = :vehicle_id
                AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
            ORDER BY le.posted_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'vehicle_id' => $vehicleId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        app_log('ERROR', 'Failed to get vehicle income details: ' . $e->getMessage());
        return [];
    }
}

// Get detailed expense entries for a vehicle
function vfr_get_vehicle_expense_details(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd): array {
    try {
        $exclusionClause = ledger_kpi_exclusion_clause('le');
        
        // Use LEFT JOIN to include BOTH reservation-linked AND direct vehicle expenses
        $sql = "
            SELECT 
                le.id, le.amount, le.description, le.category,
                le.source_event, le.payment_mode, le.posted_at
            FROM ledger_entries le
            LEFT JOIN reservations r 
                ON le.source_type = 'reservation' 
                AND le.source_id = r.id
            WHERE le.txn_type = 'expense'
                AND $exclusionClause
                AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
                AND (
                    (le.source_type = 'reservation' AND r.vehicle_id = :vehicle_id1)
                    OR (le.source_type = 'vehicle_expense' AND le.source_id = :vehicle_id2)
                )
            ORDER BY le.posted_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'vehicle_id1' => $vehicleId,
            'vehicle_id2' => $vehicleId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        app_log('ERROR', 'Failed to get vehicle expense details for vehicle ' . $vehicleId . ': ' . $e->getMessage());
        return [];
    }
}

// Humanize event labels
function fmt_event(string $e): string {
    return match($e) {
        'delivery'          => 'Delivery',
        'return'            => 'Return',
        'extension'         => 'Extension',
        'extension_deposit' => 'Extension (Deposit)',
        'advance'           => 'Advance Payment',
        'damage'            => 'Damage Charge',
        'km_overage'        => 'KM Overage',
        'additional'        => 'Additional Charge',
        default             => ucwords(str_replace('_', ' ', $e)),
    };
}

// Calculate expense by category for a period
function vfr_calculate_expense_by_category(PDO $pdo, string $periodStart, string $periodEnd): array {
    try {
        $exclusionClause = ledger_kpi_exclusion_clause('le');
        
        $sql = "
            SELECT 
                le.category,
                SUM(le.amount) AS total_amount
            FROM ledger_entries le
            WHERE le.txn_type = 'expense'
                AND $exclusionClause
                AND le.category NOT LIKE '%Security Deposit%'
                AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
                AND le.category IS NOT NULL
                AND le.category != ''
            GROUP BY le.category
            ORDER BY total_amount DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['category']] = (float)$row['total_amount'];
        }
        
        return $result;
    } catch (PDOException $e) {
        app_log('ERROR', 'Failed to calculate expense by category: ' . $e->getMessage());
        return [];
    }
}

// Get selected month/year (default to current period)
$defP = period_for_today();
$selM = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m', strtotime($defP['start']));
$selY = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y', strtotime($defP['start']));

// Validate
if ($selM < 1 || $selM > 12) $selM = (int)date('m', strtotime($defP['start']));
if ($selY < 2020 || $selY > 2099) $selY = (int)date('Y', strtotime($defP['start']));

// Calculate period (15th to 14th inclusive)
$p = period_from_my($selM, $selY);
$startDate = $p['start'];
$endDate = $p['end'];
$periodDays = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;

// Get vehicle data
$vehicles = vfr_get_active_vehicles($pdo);
$vehicleIncome = vfr_calculate_vehicle_income($pdo, $startDate, $endDate);
$vehicleExpenses = vfr_calculate_vehicle_expenses($pdo, $startDate, $endDate);

// Get expense by category data
$expenseByCategory = vfr_calculate_expense_by_category($pdo, $startDate, $endDate);

// Get all possible expense categories (custom + system)
$customCategories = expense_categories_get_list($pdo);
$systemCategories = [
    'Vehicle Expense',
    'Service',
    'Tyre',
    'Spare Parts',
    'Staff Advance',
    'Reservation Cancellation Refund',
    'Security Deposit Returned',
    'Security Deposit',
    'Investment Down Payment',
    'EMI Payment',
    'Transfer Out'
];

// Merge and deduplicate all categories
$allPossibleCategories = array_unique(array_merge($customCategories, $systemCategories));
sort($allPossibleCategories);

// Build complete category list with amounts (including zeros)
$completeCategoryData = [];
foreach ($allPossibleCategories as $cat) {
    $completeCategoryData[$cat] = $expenseByCategory[$cat] ?? 0;
}

$categoryCount = count($completeCategoryData);

// Build vehicle rows with income, expense, and balance
$vehicleRows = [];
$totalIncome = 0;
$totalExpense = 0;

foreach ($vehicles as $vehicle) {
    $vehicleId = (int)$vehicle['id'];
    $income = $vehicleIncome[$vehicleId] ?? 0;
    $expense = $vehicleExpenses[$vehicleId] ?? 0;
    $balance = $income - $expense;
    
    $vehicleRows[] = [
        'id' => $vehicleId,
        'name' => trim($vehicle['brand'] . ' ' . $vehicle['model'] . ' · ' . $vehicle['license_plate']),
        'income' => $income,
        'expense' => $expense,
        'balance' => $balance
    ];
    
    $totalIncome += $income;
    $totalExpense += $expense;
}

$netBalance = $totalIncome - $totalExpense;

// Prepare drill-down data for all vehicles
$vehicleIncomeDetails = [];
$vehicleExpenseDetails = [];

foreach ($vehicles as $vehicle) {
    $vehicleId = (int)$vehicle['id'];
    $vehicleIncomeDetails[$vehicleId] = vfr_get_vehicle_income_details($pdo, $vehicleId, $startDate, $endDate);
    $vehicleExpenseDetails[$vehicleId] = vfr_get_vehicle_expense_details($pdo, $vehicleId, $startDate, $endDate);
}

// Debug output for Mercedes
if (isset($vehicleExpenseDetails[1])) {
    echo "<!-- DEBUG: Mercedes expense details count = " . count($vehicleExpenseDetails[1]) . " -->\n";
    if (!empty($vehicleExpenseDetails[1])) {
        echo "<!-- DEBUG: First expense: " . json_encode($vehicleExpenseDetails[1][0]) . " -->\n";
    }
}

$success = getFlash('success');
$error   = getFlash('error');
$pageTitle = 'Vehicle Financial Report';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">

    <?php if ($success): ?><div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg><?= e($error) ?></div><?php endif; ?>

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-white text-lg font-light">Vehicle Financial Report</h2>
            <p class="text-xs text-mb-subtle mt-0.5"><?= date('d M', strtotime($startDate)) ?> - <?= date('d M Y', strtotime($endDate)) ?> &bull; <?= $periodDays ?> days</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <!-- Month select -->
            <div class="relative">
                <select name="m" onchange="this.form.submit()"
                    class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                    <?php for ($i=1;$i<=12;$i++):
                        $iN = $i === 12 ? 1 : $i + 1;
                        $iLabel = '15 ' . date('M', mktime(0,0,0,$i,1)) . ' – 14 ' . date('M', mktime(0,0,0,$iN,1));
                    ?>
                    <option value="<?=$i?>" <?=$i===$selM?'selected':''?> class="bg-[#1f1f1f] text-white"><?= $iLabel ?></option>
                    <?php endfor; ?>
                </select>
                <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <!-- Year select -->
            <div class="relative">
                <select name="y" onchange="this.form.submit()"
                    class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                    <?php $curY = (int)date('Y'); for ($yr=$curY;$yr>=2024;$yr--): ?>
                    <option value="<?=$yr?>" <?=$yr===$selY?'selected':''?> class="bg-[#1f1f1f] text-white"><?=$yr?></option>
                    <?php endfor; ?>
                </select>
                <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-mb-surface border border-green-500/20 rounded-xl p-5">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Income</p>
            <p class="text-2xl font-light text-green-400">$<?= number_format($totalIncome, 2) ?></p>
        </div>
        <div class="bg-mb-surface border border-red-500/20 rounded-xl p-5">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Expenses</p>
            <p class="text-2xl font-light text-red-400">$<?= number_format($totalExpense, 2) ?></p>
        </div>
        <div class="bg-mb-surface border <?= $netBalance >= 0 ? 'border-green-500/20' : 'border-red-500/20' ?> rounded-xl p-5">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Net Balance</p>
            <p class="text-2xl font-light <?= $netBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                <?= $netBalance >= 0 ? '+' : '-' ?>$<?= number_format(abs($netBalance), 2) ?>
            </p>
        </div>
        <button type="button" onclick="openCategoryModal()" class="bg-gradient-to-br from-amber-500/20 to-orange-500/20 border-2 border-amber-500/40 rounded-xl p-5 hover:border-amber-500/60 hover:from-amber-500/30 hover:to-orange-500/30 transition-all cursor-pointer text-left shadow-lg shadow-amber-500/10">
            <div class="flex items-center gap-2 mb-2">
                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p class="text-xs text-amber-300 uppercase tracking-wider font-semibold">Expense Categories</p>
            </div>
            <p class="text-3xl font-light text-amber-400 mb-1"><?= $categoryCount ?></p>
            <p class="text-xs text-amber-300/80 flex items-center gap-1">
                Click to view breakdown
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </p>
        </button>
    </div>

    <!-- Vehicle Breakdown Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h2 class="text-white font-light">Vehicle Breakdown</h2>
        </div>
        <?php if (empty($vehicleRows)): ?>
            <div class="p-6 text-center text-mb-subtle">
                No vehicles found
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3 text-left">Vehicle</th>
                            <th class="px-6 py-3 text-right">Income</th>
                            <th class="px-6 py-3 text-right">Expense</th>
                            <th class="px-6 py-3 text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($vehicleRows as $row): ?>
                        <tr class="hover:bg-mb-black/30 transition-colors">
                            <td class="px-6 py-3 text-white"><?= e($row['name']) ?></td>
                            <td class="px-6 py-3 text-right">
                                <?php if ($row['income'] > 0): ?>
                                    <button type="button" onclick="openVehiclePanel(<?= $row['id'] ?>, 'income')" class="text-green-400 hover:text-green-300 hover:underline transition-colors cursor-pointer">
                                        $<?= number_format($row['income'], 2) ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-mb-subtle">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <?php if ($row['expense'] > 0): ?>
                                    <button type="button" onclick="openVehiclePanel(<?= $row['id'] ?>, 'expense')" class="text-red-400 hover:text-red-300 hover:underline transition-colors cursor-pointer">
                                        $<?= number_format($row['expense'], 2) ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-mb-subtle">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right font-medium <?= $row['balance'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $row['balance'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($row['balance']), 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-t-2 border-mb-subtle/20 bg-mb-black/40">
                        <tr>
                            <td class="px-6 py-3 text-mb-silver font-medium text-xs uppercase tracking-wide">Total</td>
                            <td class="px-6 py-3 text-right text-green-400 font-semibold">$<?= number_format($totalIncome, 2) ?></td>
                            <td class="px-6 py-3 text-right text-red-400 font-semibold">$<?= number_format($totalExpense, 2) ?></td>
                            <td class="px-6 py-3 text-right font-semibold <?= $netBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $netBalance >= 0 ? '+' : '-' ?>$<?= number_format(abs($netBalance), 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Drill-down slide panel -->
    <div id="detailPanel" class="hidden fixed inset-0 z-50 flex justify-end">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closePanel()"></div>
        <!-- Panel -->
        <div class="relative w-full max-w-lg bg-[#141414] border-l border-mb-subtle/20 flex flex-col h-full overflow-hidden shadow-2xl">
            <!-- Header -->
            <div id="panelHeader" class="flex-shrink-0 border-b border-mb-subtle/10">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h3 id="panelTitle" class="text-white font-medium"></h3>
                        <p id="panelSubtitle" class="text-xs text-mb-subtle mt-0.5"></p>
                    </div>
                    <button onclick="closePanel()" class="text-mb-subtle hover:text-white transition-colors p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <!-- Tab Switcher (only for expense mode) -->
                <div id="panelTabs" class="hidden px-6 pb-3">
                    <div class="inline-flex bg-mb-black/40 rounded-lg p-1">
                        <button onclick="switchPanelView('details')" id="tabDetails" class="px-4 py-1.5 text-sm rounded-md transition-colors text-white bg-mb-accent">
                            Details
                        </button>
                        <button onclick="switchPanelView('categories')" id="tabCategories" class="px-4 py-1.5 text-sm rounded-md transition-colors text-mb-subtle hover:text-white">
                            By Category
                        </button>
                    </div>
                </div>
            </div>
            <!-- Search -->
            <div class="px-6 py-3 border-b border-mb-subtle/10 flex-shrink-0">
                <input id="panelSearch" type="text" placeholder="Search..."
                    oninput="filterPanel()"
                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-1.5 pl-4 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
            </div>
            <!-- Entries list -->
            <div id="panelList" class="flex-1 overflow-y-auto divide-y divide-mb-subtle/10"></div>
            <!-- Category table (hidden by default) -->
            <div id="panelCategoryView" class="hidden flex-1 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-wider sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left">#</th>
                            <th class="px-6 py-3 text-left">Category</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="panelCategoryTableBody" class="divide-y divide-mb-subtle/10">
                    </tbody>
                </table>
            </div>
            <!-- Footer total -->
            <div id="panelFooter" class="px-6 py-3 border-t border-mb-subtle/10 flex items-center justify-between flex-shrink-0 bg-mb-black/40">
                <span class="text-xs text-mb-subtle uppercase tracking-wide">Total</span>
                <span id="panelTotal" class="font-semibold text-white"></span>
            </div>
        </div>
    </div>

    <!-- Category Breakdown Modal -->
    <div id="categoryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeCategoryModal()"></div>
        <!-- Modal -->
        <div class="relative w-full max-w-6xl bg-[#141414] border border-mb-subtle/20 rounded-2xl flex flex-col max-h-[90vh] overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between px-8 py-6 border-b border-mb-subtle/10 flex-shrink-0">
                <div>
                    <h2 class="text-white text-xl font-light">Expense by Category</h2>
                    <p id="categoryModalSubtitle" class="text-xs text-mb-subtle mt-1"></p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Month/Year Pickers -->
                    <form id="categoryPeriodForm" class="flex items-center gap-2">
                        <div class="relative">
                            <select id="categoryMonth" name="m" onchange="updateCategoryPeriod()"
                                class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                                <?php for ($i=1;$i<=12;$i++):
                                    $iN = $i === 12 ? 1 : $i + 1;
                                    $iLabel = '15 ' . date('M', mktime(0,0,0,$i,1)) . ' – 14 ' . date('M', mktime(0,0,0,$iN,1));
                                ?>
                                <option value="<?=$i?>" <?=$i===$selM?'selected':''?> class="bg-[#1f1f1f] text-white"><?= $iLabel ?></option>
                                <?php endfor; ?>
                            </select>
                            <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                        <div class="relative">
                            <select id="categoryYear" name="y" onchange="updateCategoryPeriod()"
                                class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                                <?php $curY = (int)date('Y'); for ($yr=$curY;$yr>=2024;$yr--): ?>
                                <option value="<?=$yr?>" <?=$yr===$selY?'selected':''?> class="bg-[#1f1f1f] text-white"><?=$yr?></option>
                                <?php endfor; ?>
                            </select>
                            <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </form>
                    <button onclick="closeCategoryModal()" class="text-mb-subtle hover:text-white transition-colors p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <!-- Search -->
            <div class="px-8 py-4 border-b border-mb-subtle/10 flex-shrink-0">
                <input id="categorySearch" type="text" placeholder="Search categories..."
                    oninput="filterCategories()"
                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-2 pl-4 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
            </div>
            <!-- Category table -->
            <div class="flex-1 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-wider sticky top-0">
                        <tr>
                            <th class="px-8 py-3 text-left">#</th>
                            <th class="px-8 py-3 text-left">Category</th>
                            <th class="px-8 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody" class="divide-y divide-mb-subtle/10">
                    </tbody>
                </table>
            </div>
            <!-- Footer total -->
            <div class="px-8 py-5 border-t border-mb-subtle/10 flex items-center justify-between flex-shrink-0 bg-mb-black/40">
                <span class="text-sm text-mb-silver font-medium uppercase tracking-wide">Total</span>
                <span id="categoryTotal" class="text-xl font-semibold text-red-400"></span>
            </div>
        </div>
    </div>

<?php
// Encode drill-down data as JSON for JavaScript
$incomeDataByVehicle = [];
$expenseDataByVehicle = [];

foreach ($vehicles as $vehicle) {
    $vehicleId = (int)$vehicle['id'];
    $vehicleName = trim($vehicle['brand'] . ' ' . $vehicle['model'] . ' · ' . $vehicle['license_plate']);
    
    // Format income entries
    $incomeDataByVehicle[$vehicleId] = [
        'name' => $vehicleName,
        'entries' => array_map(fn($e) => [
            'date'        => date('d/m/Y', strtotime($e['posted_at'])),
            'time'        => date('H:i', strtotime($e['posted_at'])),
            'amount'      => (float)$e['amount'],
            'event'       => $e['source_event'] ? fmt_event($e['source_event']) : ($e['category'] ?? ''),
            'client'      => $e['client_name'] ?? '',
            'description' => $e['description'] ?? '',
            'method'      => $e['payment_mode'] ?? '',
            'res_id'      => (int)$e['reservation_id'],
        ], $vehicleIncomeDetails[$vehicleId])
    ];
    
    // Format expense entries
    $expenseDataByVehicle[$vehicleId] = [
        'name' => $vehicleName,
        'entries' => array_map(fn($e) => [
            'date'        => date('d/m/Y', strtotime($e['posted_at'])),
            'time'        => date('H:i', strtotime($e['posted_at'])),
            'amount'      => (float)$e['amount'],
            'event'       => $e['source_event'] ? fmt_event($e['source_event']) : '',
            'category'    => $e['category'] ?? '',
            'description' => $e['description'] ?? '',
            'method'      => $e['payment_mode'] ?? '',
        ], $vehicleExpenseDetails[$vehicleId] ?? [])
    ];
}

$incomeJson = json_encode($incomeDataByVehicle, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$expenseJson = json_encode($expenseDataByVehicle, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$categoryJson = json_encode($completeCategoryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Debug: Output Mercedes expense data as HTML comment
if (isset($expenseDataByVehicle[1])) {
    echo "<!-- Mercedes Expense Data: " . json_encode($expenseDataByVehicle[1], JSON_PRETTY_PRINT) . " -->\n";
    echo "<!-- Mercedes Raw Details Count: " . count($vehicleExpenseDetails[1] ?? []) . " -->\n";
}
?>
<script>
const INCOME_DATA = <?= $incomeJson ?>;
const EXPENSE_DATA = <?= $expenseJson ?>;
const CATEGORY_DATA = <?= $categoryJson ?>;
let currentMode = 'income';
let currentData = [];
let currentPanelView = 'details'; // 'details' or 'categories'
let allCategories = [];

// Debug: Log expense data for Mercedes
console.log('Mercedes (ID 1) expense data:', EXPENSE_DATA[1]);

// Prepare category data for modal
function prepareCategoryData() {
    allCategories = [];
    for (const [category, amount] of Object.entries(CATEGORY_DATA)) {
        allCategories.push({ category, amount });
    }
    // Sort by amount descending
    allCategories.sort((a, b) => b.amount - a.amount);
}

prepareCategoryData();

function openVehiclePanel(vehicleId, mode) {
    currentMode = mode;
    currentPanelView = 'details'; // Reset to details view
    const data = mode === 'income' ? INCOME_DATA[vehicleId] : EXPENSE_DATA[vehicleId];
    
    console.log(`Opening panel for vehicle ${vehicleId} in ${mode} mode:`, data);
    
    if (!data) {
        console.error('No data found for vehicle:', vehicleId);
        return;
    }
    
    currentData = data.entries;
    console.log(`Entries count: ${currentData.length}`, currentData);
    
    document.getElementById('panelSearch').value = '';
    document.getElementById('panelTitle').textContent = data.name + ' — ' + (mode === 'income' ? 'Income' : 'Expenses');
    document.getElementById('panelSubtitle').textContent = currentData.length + ' entr' + (currentData.length === 1 ? 'y' : 'ies');
    
    // Show/hide tabs based on mode (only show for expenses)
    const tabsEl = document.getElementById('panelTabs');
    if (mode === 'expense') {
        tabsEl.classList.remove('hidden');
        switchPanelView('details'); // Start with details view
    } else {
        tabsEl.classList.add('hidden');
    }
    
    renderPanel(currentData);
    document.getElementById('detailPanel').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function switchPanelView(view) {
    currentPanelView = view;
    
    const detailsTab = document.getElementById('tabDetails');
    const categoriesTab = document.getElementById('tabCategories');
    const listView = document.getElementById('panelList');
    const categoryView = document.getElementById('panelCategoryView');
    const searchBar = document.getElementById('panelSearch').parentElement;
    
    if (view === 'details') {
        // Show details view
        detailsTab.classList.add('bg-mb-accent', 'text-white');
        detailsTab.classList.remove('text-mb-subtle');
        categoriesTab.classList.remove('bg-mb-accent', 'text-white');
        categoriesTab.classList.add('text-mb-subtle');
        
        listView.classList.remove('hidden');
        categoryView.classList.add('hidden');
        searchBar.classList.remove('hidden');
        
        renderPanel(currentData);
    } else {
        // Show category view
        categoriesTab.classList.add('bg-mb-accent', 'text-white');
        categoriesTab.classList.remove('text-mb-subtle');
        detailsTab.classList.remove('bg-mb-accent', 'text-white');
        detailsTab.classList.add('text-mb-subtle');
        
        listView.classList.add('hidden');
        categoryView.classList.remove('hidden');
        searchBar.classList.add('hidden');
        
        renderPanelCategoryView();
    }
}

function renderPanelCategoryView() {
    const tbody = document.getElementById('panelCategoryTableBody');
    const totalEl = document.getElementById('panelTotal');
    
    // Group current data by category
    const categoryTotals = {};
    currentData.forEach(e => {
        const cat = e.category || 'Uncategorized';
        categoryTotals[cat] = (categoryTotals[cat] || 0) + e.amount;
    });
    
    // Get all possible categories from the global list
    const allCategoryNames = allCategories.map(c => c.category);
    
    // Build complete list with all categories (including zeros)
    const categories = allCategoryNames.map(cat => ({
        category: cat,
        amount: categoryTotals[cat] || 0
    }));
    
    // Sort by amount (highest to lowest)
    categories.sort((a, b) => b.amount - a.amount);
    
    if (!categories.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-16 text-center text-mb-subtle text-sm">No categories found.</td></tr>';
        totalEl.textContent = '$0.00';
        return;
    }
    
    let total = 0;
    let html = '';
    
    categories.forEach((c, index) => {
        total += c.amount;
        const rowClass = index % 2 === 0 ? 'bg-mb-surface/30' : '';
        
        html += `
        <tr class="${rowClass} hover:bg-mb-black/30 transition-colors">
            <td class="px-6 py-3 text-mb-subtle">${index + 1}</td>
            <td class="px-6 py-3 text-white">${esc(c.category)}</td>
            <td class="px-6 py-3 text-right text-red-400 font-medium">$${c.amount.toFixed(2)}</td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
    totalEl.textContent = '$' + total.toFixed(2);
}

function closePanel() {
    document.getElementById('detailPanel').classList.add('hidden');
    document.body.style.overflow = '';
}

function filterPanel() {
    const q = document.getElementById('panelSearch').value.toLowerCase();
    if (!q) { renderPanel(currentData); return; }
    renderPanel(currentData.filter(e =>
        (e.client||'').toLowerCase().includes(q) ||
        (e.event||'').toLowerCase().includes(q) ||
        (e.category||'').toLowerCase().includes(q) ||
        (e.description||'').toLowerCase().includes(q) ||
        (e.date||'').includes(q)
    ));
}

function renderPanel(entries) {
    const list = document.getElementById('panelList');
    const totalEl = document.getElementById('panelTotal');
    const isIncome = currentMode === 'income';
    const colorClass = isIncome ? 'text-green-400' : 'text-red-400';

    if (!entries.length) {
        list.innerHTML = '<div class="py-16 text-center text-mb-subtle text-sm">No entries found.</div>';
        totalEl.textContent = '$0.00';
        totalEl.className = 'font-semibold ' + colorClass;
        return;
    }

    let total = 0;
    let html = '';
    entries.forEach(e => {
        total += e.amount;
        const resLink = e.res_id
            ? `<a href="../reservations/show.php?id=${e.res_id}" class="text-mb-accent hover:underline text-xs" target="_blank">View Reservation →</a>`
            : '';
        const label = isIncome
            ? (e.event || 'Income')
            : (e.event || 'Expense');
        const sub = isIncome
            ? [e.client ? `<span class="text-white">${esc(e.client)}</span>` : '', e.description ? esc(e.description) : ''].filter(Boolean).join(' &bull; ')
            : [e.description ? esc(e.description) : ''].filter(Boolean).join('');
        const method = e.method ? `<span class="text-[10px] bg-mb-surface border border-mb-subtle/20 rounded px-1.5 py-0.5 text-mb-subtle">${esc(e.method)}</span>` : '';
        const categoryTag = !isIncome && e.category ? `<span class="text-[10px] bg-amber-500/10 border border-amber-500/30 rounded px-1.5 py-0.5 text-amber-400">${esc(e.category)}</span>` : '';

        html += `
        <div class="px-6 py-3.5 hover:bg-mb-black/30 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-mb-silver">${esc(label)}</span>
                        ${categoryTag}
                        ${method}
                    </div>
                    ${sub ? `<p class="text-xs text-mb-subtle mt-0.5 truncate">${sub}</p>` : ''}
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-[11px] text-mb-subtle">${esc(e.date)} ${esc(e.time)}</span>
                        ${resLink}
                    </div>
                </div>
                <span class="text-sm font-medium ${colorClass} flex-shrink-0">$${e.amount.toFixed(2)}</span>
            </div>
        </div>`;
    });

    list.innerHTML = html;
    totalEl.textContent = '$' + total.toFixed(2);
    totalEl.className = 'font-semibold ' + colorClass;
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Category Modal Functions
function openCategoryModal() {
    const periodText = '<?= date('d M', strtotime($startDate)) ?> - <?= date('d M Y', strtotime($endDate)) ?>';
    document.getElementById('categoryModalSubtitle').textContent = periodText + ' • ' + allCategories.length + ' categories';
    document.getElementById('categorySearch').value = '';
    
    // Set current month/year in selectors
    document.getElementById('categoryMonth').value = '<?= $selM ?>';
    document.getElementById('categoryYear').value = '<?= $selY ?>';
    
    renderCategories(allCategories);
    document.getElementById('categoryModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function updateCategoryPeriod() {
    const month = document.getElementById('categoryMonth').value;
    const year = document.getElementById('categoryYear').value;
    
    // Reload page with new period and flag to reopen modal
    window.location.href = '?m=' + month + '&y=' + year + '&openCategoryModal=1';
}

function filterCategories() {
    const q = document.getElementById('categorySearch').value.toLowerCase();
    if (!q) {
        renderCategories(allCategories);
        return;
    }
    const filtered = allCategories.filter(c => c.category.toLowerCase().includes(q));
    renderCategories(filtered);
}

function renderCategories(categories) {
    const tbody = document.getElementById('categoryTableBody');
    const totalEl = document.getElementById('categoryTotal');
    
    if (!categories.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-8 py-16 text-center text-mb-subtle text-sm">No categories found.</td></tr>';
        totalEl.textContent = '$0.00';
        return;
    }
    
    let total = 0;
    let html = '';
    
    categories.forEach((c, index) => {
        total += c.amount;
        const rowClass = index % 2 === 0 ? 'bg-mb-surface/30' : '';
        
        html += `
        <tr class="${rowClass} hover:bg-mb-black/30 transition-colors">
            <td class="px-8 py-3 text-mb-subtle">${index + 1}</td>
            <td class="px-8 py-3 text-white">${esc(c.category)}</td>
            <td class="px-8 py-3 text-right text-red-400 font-medium">$${c.amount.toFixed(2)}</td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
    totalEl.textContent = '$' + total.toFixed(2);
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCategoryModal(); });

// Auto-open category modal if URL parameter is present
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('openCategoryModal') === '1') {
        openCategoryModal();
    }
});
</script>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
