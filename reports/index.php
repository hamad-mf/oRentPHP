<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$_currentUser = current_user();
if (!auth_has_perm('view_finances') && ($_currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Access denied.');
    redirect('../index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';

// Period calculation functions (15th to 15th next month)
function period_from_my(int $m, int $y): array {
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-15', $nY, $nM)];
}
function period_for_today(): array {
    $d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y');
    if ($d >= 15) return period_from_my($m, $y);
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return period_from_my($pm, $py);
}

// Get selected month/year (default to current period)
$defP = period_for_today();
$selM = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m', strtotime($defP['start']));
$selY = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y', strtotime($defP['start']));

// Validate
if ($selM < 1 || $selM > 12) $selM = (int)date('m', strtotime($defP['start']));
if ($selY < 2020 || $selY > 2099) $selY = (int)date('Y', strtotime($defP['start']));

// Calculate period (15th to 15th inclusive)
$p = period_from_my($selM, $selY);
$startDate = $p['start'];
$endDate = $p['end'] . ' 23:59:59';
$periodDays = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;

// Query daily income and expense
$dailyData = [];

// Get income by day (reservation related)
$incomeStmt = $pdo->prepare("
    SELECT DATE(posted_at) as day, SUM(amount) as total
    FROM ledger_entries
    WHERE txn_type = 'income'
        AND DATE(posted_at) >= ? AND DATE(posted_at) <= ?
        AND " . ledger_non_voided_clause() . "
    GROUP BY DATE(posted_at)
");
$incomeStmt->execute([$startDate, $endDate]);
$incomeByDay = [];
foreach ($incomeStmt->fetchAll() as $row) {
    $incomeByDay[$row['day']] = (float)$row['total'];
}

// Get expense by day
$expenseStmt = $pdo->prepare("
    SELECT DATE(posted_at) as day, SUM(amount) as total
    FROM ledger_entries
    WHERE txn_type = 'expense'
        AND DATE(posted_at) >= ? AND DATE(posted_at) <= ?
        AND " . ledger_non_voided_clause() . "
    GROUP BY DATE(posted_at)
");
$expenseStmt->execute([$startDate, $endDate]);
$expenseByDay = [];
foreach ($expenseStmt->fetchAll() as $row) {
    $expenseByDay[$row['day']] = (float)$row['total'];
}

// Build daily rows for period (15th to 14th)
$dailyRows = [];
$totalSales = 0;
$totalExpense = 0;

$currentDate = $startDate;
while (strtotime($currentDate) <= strtotime($endDate)) {
    $sales = $incomeByDay[$currentDate] ?? 0;
    $expense = $expenseByDay[$currentDate] ?? 0;
    $balance = $sales - $expense;
    
    $dailyRows[] = [
        'date' => $currentDate,
        'sales' => $sales,
        'expense' => $expense,
        'balance' => $balance,
        'is_today' => $currentDate === date('Y-m-d')
    ];
    
    $totalSales += $sales;
    $totalExpense += $expense;
    
    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

$netBalance = $totalSales - $totalExpense;
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$curY = (int)date('Y');

$pageTitle = 'Monthly Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-white text-lg font-light">Monthly Reports</h2>
        <p class="text-xs text-mb-subtle mt-0.5"><?= date('d M', strtotime($startDate)) ?> - <?= date('d M Y', strtotime($endDate)) ?> &bull; <?= $periodDays ?> days</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <!-- Month select -->
        <div class="relative">
            <select name="m" onchange="this.form.submit()"
                class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                <?php for ($i=1;$i<=12;$i++): ?>
                <option value="<?=$i?>" <?=$i===$selM?'selected':''?> class="bg-[#1f1f1f] text-white"><?= $months[$i-1] ?></option>
                <?php endfor; ?>
            </select>
            <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <!-- Year select -->
        <div class="relative">
            <select name="y" onchange="this.form.submit()"
                class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                <?php for ($yr=$curY;$yr>=2024;$yr--): ?>
                <option value="<?=$yr?>" <?=$yr===$selY?'selected':''?> class="bg-[#1f1f1f] text-white"><?=$yr?></option>
                <?php endfor; ?>
            </select>
            <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-mb-surface border border-green-500/20 rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Sales</p>
        <p class="text-2xl font-light text-green-400">$<?= number_format($totalSales, 2) ?></p>
        <p class="text-xs text-mb-subtle mt-1.5">Income from deliveries, returns, extensions</p>
    </div>
    <div class="bg-mb-surface border border-red-500/20 rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Expenses</p>
        <p class="text-2xl font-light text-red-400">$<?= number_format($totalExpense, 2) ?></p>
        <p class="text-xs text-mb-subtle mt-1.5">All recorded expenses</p>
    </div>
    <div class="bg-mb-surface border <?= $netBalance >= 0 ? 'border-green-500/20' : 'border-red-500/20' ?> rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Net Balance</p>
        <p class="text-2xl font-light <?= $netBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
            <?= $netBalance >= 0 ? '+' : '-' ?>$<?= number_format(abs($netBalance), 2) ?>
        </p>
        <p class="text-xs text-mb-subtle mt-1.5"><?= $netBalance >= 0 ? 'Profit for period' : 'Loss for period' ?></p>
    </div>
</div>

<!-- Daily Breakdown Table -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
        <h2 class="text-white font-light">Daily Breakdown</h2>
        <span class="text-xs text-mb-subtle"><?= date('d/m', strtotime($startDate)) ?> - <?= date('d/m/Y', strtotime($endDate)) ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">Date</th>
                    <th class="px-6 py-3 text-right">Sales</th>
                    <th class="px-6 py-3 text-right">Expense</th>
                    <th class="px-6 py-3 text-right">Balance</th>
                    <th class="px-6 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mb-subtle/10">
                <?php foreach ($dailyRows as $row): 
                    $isToday = $row['is_today'];
                    $hasData = $row['sales'] > 0 || $row['expense'] > 0;
                    $statusClass = $row['balance'] >= 0 ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30';
                    $statusLabel = $row['balance'] >= 0 ? 'Profit' : 'Loss';
                ?>
                <tr class="hover:bg-mb-black/30 transition-colors <?= $isToday ? 'border-l-2 border-mb-accent' : '' ?>">
                    <td class="px-6 py-3">
                        <span class="<?= $hasData ? 'text-white' : 'text-mb-subtle' ?>">
                            <?= date('d/m/Y', strtotime($row['date'])) ?>
                        </span>
                        <?php if ($isToday): ?>
                            <span class="ml-2 text-[10px] bg-mb-accent/20 text-mb-accent border border-mb-accent/30 px-1.5 py-0.5 rounded-full font-medium">Today</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-right <?= $row['sales'] > 0 ? 'text-green-400' : 'text-mb-subtle' ?>">
                        <?= $row['sales'] > 0 ? '$' . number_format($row['sales'], 2) : '—' ?>
                    </td>
                    <td class="px-6 py-3 text-right <?= $row['expense'] > 0 ? 'text-red-400' : 'text-mb-subtle' ?>">
                        <?= $row['expense'] > 0 ? '$' . number_format($row['expense'], 2) : '—' ?>
                    </td>
                    <td class="px-6 py-3 text-right font-medium <?= $row['balance'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        <?= $row['balance'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($row['balance']), 2) ?>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <?php if ($hasData): ?>
                            <span class="px-2.5 py-1 rounded-full text-xs border <?= $statusClass ?>"><?= $statusLabel ?></span>
                        <?php else: ?>
                            <span class="text-mb-subtle">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="border-t-2 border-mb-subtle/20 bg-mb-black/40">
                <tr>
                    <td class="px-6 py-3 text-mb-silver font-medium text-xs uppercase tracking-wide">Month Total</td>
                    <td class="px-6 py-3 text-right text-green-400 font-semibold">$<?= number_format($totalSales, 2) ?></td>
                    <td class="px-6 py-3 text-right text-red-400 font-semibold">$<?= number_format($totalExpense, 2) ?></td>
                    <td class="px-6 py-3 text-right font-semibold <?= $netBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        <?= $netBalance >= 0 ? '+' : '-' ?>$<?= number_format(abs($netBalance), 2) ?>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="px-2.5 py-1 rounded-full text-xs border font-medium <?= $netBalance >= 0 ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30' ?>">
                            <?= $netBalance >= 0 ? 'Profit' : 'Loss' ?>
                        </span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
