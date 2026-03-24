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

// ── Detail entries for drill-down panel ───────────────────────────────────
// Income: join reservations → clients + vehicles for context
$incomeDetailStmt = $pdo->prepare("
    SELECT le.id, le.amount, le.description, le.category, le.source_event,
           le.source_type, le.source_id, le.payment_mode, le.posted_at,
           c.name  AS client_name,
           v.license_plate,
           v.brand, v.model
    FROM ledger_entries le
    LEFT JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id
    LEFT JOIN clients      c ON r.client_id  = c.id
    LEFT JOIN vehicles     v ON r.vehicle_id = v.id
    WHERE le.txn_type = 'income'
      AND DATE(le.posted_at) >= ? AND DATE(le.posted_at) <= ?
      AND " . ledger_non_voided_clause('le') . "
    ORDER BY le.posted_at DESC
");
$incomeDetailStmt->execute([$startDate, $endDate]);
$incomeEntries = $incomeDetailStmt->fetchAll(PDO::FETCH_ASSOC);

// Expense: no reservation join needed
$expenseDetailStmt = $pdo->prepare("
    SELECT le.id, le.amount, le.description, le.category,
           le.source_event, le.source_type, le.source_id,
           le.payment_mode, le.posted_at
    FROM ledger_entries le
    WHERE le.txn_type = 'expense'
      AND DATE(le.posted_at) >= ? AND DATE(le.posted_at) <= ?
      AND " . ledger_non_voided_clause('le') . "
    ORDER BY le.posted_at DESC
");
$expenseDetailStmt->execute([$startDate, $endDate]);
$expenseEntries = $expenseDetailStmt->fetchAll(PDO::FETCH_ASSOC);

// Humanise source_event labels
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
    <div class="bg-mb-surface border border-green-500/20 rounded-xl p-5 cursor-pointer hover:border-green-500/50 transition-colors group"
         onclick="openPanel('sales')">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Sales</p>
        <p class="text-2xl font-light text-green-400">$<?= number_format($totalSales, 2) ?></p>
        <p class="text-xs text-mb-subtle mt-1.5 group-hover:text-mb-silver transition-colors">Click to see breakdown →</p>
    </div>
    <div class="bg-mb-surface border border-red-500/20 rounded-xl p-5 cursor-pointer hover:border-red-500/50 transition-colors group"
         onclick="openPanel('expenses')">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Expenses</p>
        <p class="text-2xl font-light text-red-400">$<?= number_format($totalExpense, 2) ?></p>
        <p class="text-xs text-mb-subtle mt-1.5 group-hover:text-mb-silver transition-colors">Click to see breakdown →</p>
    </div>
    <div class="bg-mb-surface border <?= $netBalance >= 0 ? 'border-green-500/20' : 'border-red-500/20' ?> rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Net Balance</p>
        <p class="text-2xl font-light <?= $netBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
            <?= $netBalance >= 0 ? '+' : '-' ?>$<?= number_format(abs($netBalance), 2) ?>
        </p>
        <p class="text-xs text-mb-subtle mt-1.5"><?= $netBalance >= 0 ? 'Profit for period' : 'Loss for period' ?></p>
    </div>
</div>

<!-- Drill-down slide panel -->
<div id="detailPanel" class="hidden fixed inset-0 z-50 flex justify-end">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closePanel()"></div>
    <!-- Panel -->
    <div class="relative w-full max-w-lg bg-[#141414] border-l border-mb-subtle/20 flex flex-col h-full overflow-hidden shadow-2xl">
        <!-- Header -->
        <div id="panelHeader" class="flex items-center justify-between px-6 py-4 border-b border-mb-subtle/10 flex-shrink-0">
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
        <!-- Search -->
        <div class="px-6 py-3 border-b border-mb-subtle/10 flex-shrink-0">
            <input id="panelSearch" type="text" placeholder="Search..."
                oninput="filterPanel()"
                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-1.5 pl-4 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
        </div>
        <!-- Entries list -->
        <div id="panelList" class="flex-1 overflow-y-auto divide-y divide-mb-subtle/10"></div>
        <!-- Footer total -->
        <div id="panelFooter" class="px-6 py-3 border-t border-mb-subtle/10 flex items-center justify-between flex-shrink-0 bg-mb-black/40">
            <span class="text-xs text-mb-subtle uppercase tracking-wide">Total</span>
            <span id="panelTotal" class="font-semibold text-white"></span>
        </div>
    </div>
</div>

<?php
// Encode entries as JSON for JS
$incomeJson  = json_encode(array_map(fn($e) => [
    'date'        => date('d/m/Y', strtotime($e['posted_at'])),
    'time'        => date('H:i', strtotime($e['posted_at'])),
    'amount'      => (float)$e['amount'],
    'event'       => $e['source_event'] ? fmt_event($e['source_event']) : ($e['category'] ?? ''),
    'client'      => $e['client_name'] ?? '',
    'vehicle'     => trim(($e['brand'] ?? '') . ' ' . ($e['model'] ?? '') . ($e['license_plate'] ? ' · ' . $e['license_plate'] : '')),
    'plate'       => $e['license_plate'] ?? '',
    'description' => $e['description'] ?? '',
    'method'      => $e['payment_mode'] ?? '',
    'res_id'      => $e['source_type'] === 'reservation' ? (int)$e['source_id'] : null,
], $incomeEntries), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$expenseJson = json_encode(array_map(fn($e) => [
    'date'        => date('d/m/Y', strtotime($e['posted_at'])),
    'time'        => date('H:i', strtotime($e['posted_at'])),
    'amount'      => (float)$e['amount'],
    'event'       => $e['source_event'] ? fmt_event($e['source_event']) : '',
    'category'    => $e['category'] ?? '',
    'description' => $e['description'] ?? '',
    'method'      => $e['payment_mode'] ?? '',
], $expenseEntries), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<script>
const INCOME_DATA  = <?= $incomeJson ?>;
const EXPENSE_DATA = <?= $expenseJson ?>;
let currentMode = 'sales';
let currentData = [];

function openPanel(mode) {
    currentMode = mode;
    currentData = mode === 'sales' ? INCOME_DATA : EXPENSE_DATA;
    document.getElementById('panelSearch').value = '';
    document.getElementById('panelTitle').textContent = mode === 'sales' ? 'Sales Breakdown' : 'Expense Breakdown';
    document.getElementById('panelSubtitle').textContent = mode === 'sales'
        ? INCOME_DATA.length + ' entries'
        : EXPENSE_DATA.length + ' entries';
    renderPanel(currentData);
    document.getElementById('detailPanel').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
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
        (e.vehicle||'').toLowerCase().includes(q) ||
        (e.plate||'').toLowerCase().includes(q) ||
        (e.event||'').toLowerCase().includes(q) ||
        (e.category||'').toLowerCase().includes(q) ||
        (e.description||'').toLowerCase().includes(q) ||
        (e.date||'').includes(q)
    ));
}

function renderPanel(entries) {
    const list = document.getElementById('panelList');
    const totalEl = document.getElementById('panelTotal');
    const isSales = currentMode === 'sales';
    const colorClass = isSales ? 'text-green-400' : 'text-red-400';

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
        const label = isSales
            ? (e.event || 'Income')
            : (e.category || e.event || 'Expense');
        const sub = isSales
            ? [e.client ? `<span class="text-white">${esc(e.client)}</span>` : '', e.vehicle ? esc(e.vehicle) : ''].filter(Boolean).join(' &bull; ')
            : [e.description ? esc(e.description) : ''].filter(Boolean).join('');
        const method = e.method ? `<span class="text-[10px] bg-mb-surface border border-mb-subtle/20 rounded px-1.5 py-0.5 text-mb-subtle">${esc(e.method)}</span>` : '';

        html += `
        <div class="px-6 py-3.5 hover:bg-mb-black/30 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-mb-silver">${esc(label)}</span>
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

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });

function openPanelForDay(mode, dateStr) {
    // dateStr is 'YYYY-MM-DD', panel entries use 'DD/MM/YYYY'
    const parts = dateStr.split('-');
    const displayDate = parts[2] + '/' + parts[1] + '/' + parts[0]; // DD/MM/YYYY
    currentMode = mode;
    const allData = mode === 'sales' ? INCOME_DATA : EXPENSE_DATA;
    currentData = allData.filter(e => e.date === displayDate);
    document.getElementById('panelSearch').value = '';
    const label = mode === 'sales' ? 'Sales' : 'Expenses';
    document.getElementById('panelTitle').textContent = label + ' — ' + parts[2] + '/' + parts[1] + '/' + parts[0];
    document.getElementById('panelSubtitle').textContent = currentData.length + ' entr' + (currentData.length === 1 ? 'y' : 'ies');
    renderPanel(currentData);
    document.getElementById('detailPanel').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
</script>

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
                    <td class="px-6 py-3 text-right">
                        <?php if ($row['sales'] > 0): ?>
                            <button type="button"
                                onclick="openPanelForDay('sales', '<?= $row['date'] ?>')"
                                class="text-green-400 hover:text-green-300 hover:underline transition-colors cursor-pointer">
                                $<?= number_format($row['sales'], 2) ?>
                            </button>
                        <?php else: ?>
                            <span class="text-mb-subtle">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <?php if ($row['expense'] > 0): ?>
                            <button type="button"
                                onclick="openPanelForDay('expenses', '<?= $row['date'] ?>')"
                                class="text-red-400 hover:text-red-300 hover:underline transition-colors cursor-pointer">
                                $<?= number_format($row['expense'], 2) ?>
                            </button>
                        <?php else: ?>
                            <span class="text-mb-subtle">—</span>
                        <?php endif; ?>
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
