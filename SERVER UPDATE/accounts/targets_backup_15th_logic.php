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
$perPage = get_per_page($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1));
ledger_ensure_schema($pdo);

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$userId  = (int) ($_currentUser['id'] ?? 0);

$pdo->exec("CREATE TABLE IF NOT EXISTS monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period (period_start)
) ENGINE=InnoDB");

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
function period_label(string $s, string $e): string {
    return date('d M', strtotime($s)) . ' - ' . date('d M Y', strtotime($e));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (($_POST['action'] ?? '') === 'save_target') {
        $pS = trim($_POST['period_start'] ?? '');
        $pE = trim($_POST['period_end']   ?? '');
        $am = (float)($_POST['target_amount'] ?? 0);
        $no = trim($_POST['notes'] ?? '');
        if ($pS && $pE && $am > 0) {
            $pdo->prepare("INSERT INTO monthly_targets (period_start,period_end,target_amount,notes,created_by)
                VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE period_end=VALUES(period_end),
                target_amount=VALUES(target_amount),notes=VALUES(notes),created_by=VALUES(created_by),updated_at=?")
                ->execute([$pS, $pE, $am, $no ?: null, $userId, app_now_sql()]);
            flash('success', 'Target saved.');
        } else { flash('error', 'Enter a valid period and amount.'); }
        $bm = (int)date('m', strtotime($pS));
        $by = (int)date('Y', strtotime($pS));
        redirect("targets.php?m={$bm}&y={$by}");
    }
}

$today = date('Y-m-d');
$defP  = period_for_today();
$selM  = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m', strtotime($defP['start']));
$selY  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y', strtotime($defP['start']));
if ($selM < 1 || $selM > 12 || $selY < 2020 || $selY > 2099) {
    $selM = (int)date('m', strtotime($defP['start']));
    $selY = (int)date('Y', strtotime($defP['start']));
}
$p = period_from_my($selM, $selY);
$activePStart = $p['start']; $activePEnd = $p['end'];

$tS = $pdo->prepare("SELECT * FROM monthly_targets WHERE period_start=? LIMIT 1");
$tS->execute([$activePStart]);
$targetRow     = $tS->fetch();
$monthlyTarget = (float)($targetRow['target_amount'] ?? 0);
$periodDays    = (int)((strtotime($activePEnd) - strtotime($activePStart)) / 86400) + 1;
$dailyTarget   = ($periodDays > 0 && $monthlyTarget > 0) ? round($monthlyTarget / $periodDays, 2) : 0;

$aS = $pdo->prepare("SELECT DATE(posted_at) AS day,
    COALESCE(SUM(CASE WHEN txn_type='income' THEN amount WHEN txn_type='expense' THEN -amount ELSE 0 END),0) AS achieved
    FROM ledger_entries
    WHERE DATE(posted_at)>=? AND DATE(posted_at)<=?
      AND " . ledger_kpi_exclusion_clause() . "
    GROUP BY DATE(posted_at)");
$aS->execute([$activePStart, $activePEnd]);
$achByDay = [];
foreach ($aS->fetchAll() as $r) $achByDay[$r['day']] = (float)$r['achieved'];

$dailyRows = []; $totalAchieved = 0.0;
$cur = strtotime($activePStart); $endTs = strtotime($activePEnd);
while ($cur <= $endTs) {
    $ds  = date('Y-m-d', $cur);
    $ach = $achByDay[$ds] ?? 0.0;
    $totalAchieved += $ach;
    $isFut = $ds > $today; $isTod = $ds === $today;
    if ($isFut)                                      $st = 'upcoming';
    elseif ($dailyTarget > 0 && $ach >= $dailyTarget) $st = 'profit';
    elseif ($ach > 0)                                $st = 'loss';
    else                                              $st = $isTod ? 'loss' : 'loss';
    $dailyRows[] = ['date'=>$ds,'achieved'=>$ach,'gap'=>$ach-$dailyTarget,'status'=>$st,'is_today'=>$isTod,'is_future'=>$isFut];
    $cur = strtotime('+1 day', $cur);
}
$dailyRows = array_reverse($dailyRows);
$dailyTotal = count($dailyRows);
$dailyTotalPages = max(1, (int) ceil($dailyTotal / $perPage));
$page = min($page, $dailyTotalPages);
$dailyOffset = ($page - 1) * $perPage;
$dailyRowsPage = array_slice($dailyRows, $dailyOffset, $perPage);
$dailyPg = [
    'rows' => $dailyRowsPage,
    'total' => $dailyTotal,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => $dailyTotalPages,
];
$diff = $totalAchieved - $monthlyTarget;
$pct  = $monthlyTarget > 0 ? max(0, min(100, round($totalAchieved / $monthlyTarget * 100, 1))) : 0;
$curY = (int)date('Y');
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$success = getFlash('success'); $error = getFlash('error');
$pageTitle = 'Monthly Targets';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">

<?php if ($success): ?>
<div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><?= e($success) ?>
</div>
<?php endif; if ($error): ?>
<div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg><?= e($error) ?>
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h2 class="text-white text-lg font-light"><?= $months[$selM-1] ?> <?= $selY ?> Billing Period</h2>
        <p class="text-xs text-mb-subtle mt-0.5"><?= period_label($activePStart,$activePEnd) ?> &bull; <?= $periodDays ?> days</p>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <form method="GET" class="flex items-center gap-2">
            <!-- Month styled select -->
            <div class="relative">
                <select name="m" onchange="this.form.submit()"
                    class="appearance-none bg-mb-surface border border-mb-subtle/30 rounded-lg pl-3 pr-8 py-2 text-white text-sm focus:outline-none focus:border-mb-accent cursor-pointer">
                    <?php for ($i=1;$i<=12;$i++): ?>
                    <option value="<?=$i?>" <?=$i===$selM?'selected':''?> class="bg-[#1f1f1f] text-white"><?= $months[$i-1] ?></option>
                    <?php endfor; ?>
                </select>
                <svg class="w-4 h-4 text-mb-subtle absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
            <!-- Year styled select -->
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
        <?php if ($isAdmin): ?>
        <button onclick="document.getElementById('targetModal').classList.remove('hidden')"
            class="bg-mb-accent text-white px-4 py-2 rounded-full text-sm font-medium hover:bg-mb-accent/80 transition-colors flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <?= $monthlyTarget > 0 ? 'Edit Target' : 'Set Target' ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($monthlyTarget <= 0): ?>
<div class="flex items-center gap-3 bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 rounded-xl px-5 py-4 text-sm">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <span>No target set for <?= $months[$selM-1] ?> <?= $selY ?>.<?= $isAdmin?' <button onclick="document.getElementById(\'targetModal\').classList.remove(\'hidden\')" class="underline ml-1 font-medium">Set one now</button>':' Ask an admin to set a target.' ?></span>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Monthly Target</p>
        <p class="text-2xl font-light text-white"><?= $monthlyTarget>0?'$'.number_format($monthlyTarget,2):'—' ?></p>
        <p class="text-xs text-mb-subtle mt-1.5"><?= $dailyTarget>0?'$'.number_format($dailyTarget,2).'/day &bull; '.$periodDays.' days':$periodDays.' days' ?></p>
    </div>
    <div class="bg-mb-surface border border-<?= $totalAchieved>0?'green-500/20':($totalAchieved<0?'red-500/20':'mb-subtle/20') ?> rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Total Achieved</p>
        <p class="text-2xl font-light <?= $totalAchieved>0?'text-green-400':($totalAchieved<0?'text-red-400':'text-mb-subtle') ?>">$<?= number_format($totalAchieved,2) ?></p>
        <p class="text-xs text-mb-subtle mt-1.5"><?= $monthlyTarget>0?$pct.'% of target':'No target set' ?></p>
    </div>
    <?php if ($monthlyTarget>0): ?>
    <div class="<?= $diff>=0?'bg-green-500/5 border-green-500/20':'bg-red-500/5 border-red-500/20' ?> border rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2"><?= $diff>=0?'Surplus':'Deficit' ?></p>
        <p class="text-2xl font-light <?= $diff>=0?'text-green-400':'text-red-400' ?>"><?= $diff>=0?'+':'-' ?>$<?= number_format(abs($diff),2) ?></p>
        <p class="text-xs text-mb-subtle mt-1.5"><?= $diff>=0?'Ahead of target':'Behind target' ?></p>
    </div>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Progress</p>
        <p class="text-2xl font-light <?= $pct>=100?'text-green-400':($pct>=50?'text-yellow-400':'text-red-400') ?>"><?= $pct ?>%</p>
        <div class="mt-2.5 w-full h-1.5 bg-mb-black/60 rounded-full overflow-hidden">
            <div class="h-full rounded-full <?= $pct>=100?'bg-green-500':($pct>=50?'bg-yellow-400':'bg-red-500') ?>" style="width:<?= $pct ?>%"></div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 col-span-2 flex items-center gap-4">
        <svg class="w-8 h-8 text-mb-subtle/30 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        <div>
            <p class="text-white text-sm">Set a target to track daily progress</p>
            <p class="text-xs text-mb-subtle mt-0.5"><?= period_label($activePStart,$activePEnd) ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($activePEnd < $today && $monthlyTarget > 0): ?>
<div class="flex items-center justify-between border rounded-xl px-6 py-4 <?= $diff>=0?'bg-green-500/8 border-green-500/30 text-green-400':'bg-red-500/8 border-red-500/30 text-red-400' ?>">
    <div class="flex items-center gap-3">
        <span class="text-2xl"><?= $diff>=0?'🏆':'📉' ?></span>
        <div>
            <p class="font-medium text-sm"><?= $diff>=0?'Period Closed — Target Achieved!':'Period Closed — Target Missed' ?></p>
            <p class="text-xs opacity-70 mt-0.5"><?= period_label($activePStart,$activePEnd) ?></p>
        </div>
    </div>
    <div class="text-right">
        <p class="text-xl font-light"><?= $diff>=0?'+':'-' ?>$<?= number_format(abs($diff),2) ?></p>
        <p class="text-xs opacity-60"><?= $diff>=0?'Profit vs target':'Loss vs target' ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Daily Breakdown Table -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
        <h2 class="text-white font-light">Daily Breakdown</h2>
        <span class="text-xs text-mb-subtle"><?= period_label($activePStart,$activePEnd) ?> &bull; <?= $periodDays ?> days</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">Date</th>
                    <th class="px-6 py-3 text-right">Daily Target</th>
                    <th class="px-6 py-3 text-right">Achieved</th>
                    <th class="px-6 py-3 text-right">Gap</th>
                    <th class="px-6 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mb-subtle/10">
                <?php if (empty($dailyRowsPage)): ?>
                <tr><td colspan="5" class="px-6 py-12 text-center text-mb-subtle italic">No data.</td></tr>
                <?php else: foreach ($dailyRowsPage as $row):
                    [$sCls,$sLbl] = match($row['status']) {
                        'profit'   => ['bg-green-500/10 text-green-400 border-green-500/30',  '&#9650; Profit'],
                        'loss'     => ['bg-red-500/10 text-red-400 border-red-500/30',        '&#9660; Loss'],
                        default    => ['bg-mb-subtle/10 text-mb-subtle border-mb-subtle/20',  '&#8729; Upcoming'],
                    };
                    $gapClr = $row['is_future']?'text-mb-subtle':($row['gap']>=0?'text-green-400':'text-red-400');
                ?>
                <tr class="hover:bg-mb-black/30 transition-colors <?= $row['is_today']?'border-l-2 border-mb-accent':'' ?>">
                    <td class="px-6 py-3">
                        <span class="<?= $row['is_future']?'text-mb-subtle':'text-white' ?>"><?= date('D, d M Y', strtotime($row['date'])) ?></span>
                        <?php if ($row['is_today']): ?><span class="ml-2 text-[10px] bg-mb-accent/20 text-mb-accent border border-mb-accent/30 px-1.5 py-0.5 rounded-full font-medium">Today</span><?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-right text-mb-silver"><?= $dailyTarget>0?'$'.number_format($dailyTarget,2):'—' ?></td>
                    <td class="px-6 py-3 text-right font-medium <?= $row['achieved']>0?'text-white':($row['achieved']<0?'text-red-400':'text-mb-subtle') ?>"><?= $row['is_future']?'—':'$'.number_format($row['achieved'],2) ?></td>
                    <td class="px-6 py-3 text-right <?= $gapClr ?> font-medium">
                        <?php if ($row['is_future']||$dailyTarget<=0): ?><span class="text-mb-subtle">—</span>
                        <?php else: ?><?= $row['gap']>=0?'+':'-' ?>$<?= number_format(abs($row['gap']),2) ?><?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-center"><span class="px-2.5 py-1 rounded-full text-xs border <?= $sCls ?>"><?= $sLbl ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <?php if (!empty($dailyRows)&&$monthlyTarget>0): ?>
            <tfoot class="border-t-2 border-mb-subtle/20 bg-mb-black/40">
                <tr>
                    <td class="px-6 py-3 text-mb-silver font-medium text-xs uppercase tracking-wide">Period Total</td>
                    <td class="px-6 py-3 text-right text-mb-silver font-medium">$<?= number_format($monthlyTarget,2) ?></td>
                    <td class="px-6 py-3 text-right font-semibold <?= $totalAchieved>0?'text-green-400':'text-mb-subtle' ?>">$<?= number_format($totalAchieved,2) ?></td>
                    <td class="px-6 py-3 text-right font-semibold <?= $diff>=0?'text-green-400':'text-red-400' ?>"><?= $diff>=0?'+':'-' ?>$<?= number_format(abs($diff),2) ?></td>
                    <td class="px-6 py-3 text-center">
                        <span class="px-2.5 py-1 rounded-full text-xs border font-medium <?= $diff>=0?'bg-green-500/10 text-green-400 border-green-500/30':'bg-red-500/10 text-red-400 border-red-500/30' ?>">
                            <?= $diff>=0?'&#9650; Profit':'&#9660; Loss' ?>
                        </span>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php
$_tqp = array_filter(
    [
        'm' => $selM,
        'y' => $selY,
    ],
    static fn($value) => $value !== null && $value !== ''
);
echo render_pagination($dailyPg, $_tqp);
?>
</div>

<?php if ($isAdmin): ?>
<div id="targetModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-mb-subtle/10">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3"><?= $monthlyTarget>0?'Edit':'Set' ?> Target — <?= $months[$selM-1] ?> <?= $selY ?></h3>
            <button onclick="document.getElementById('targetModal').classList.add('hidden')" class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="save_target">
            <input type="hidden" name="period_start" value="<?= e($activePStart) ?>">
            <input type="hidden" name="period_end"   value="<?= e($activePEnd) ?>">
            <div class="bg-mb-black/40 rounded-lg px-4 py-3">
                <p class="text-xs text-mb-subtle uppercase tracking-wide mb-0.5">Period</p>
                <p class="text-white text-sm"><?= period_label($activePStart,$activePEnd) ?> <span class="text-mb-subtle">(<?= $periodDays ?> days)</span></p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Target Amount ($) <span class="text-red-400">*</span></label>
                <input type="number" name="target_amount" id="modalAmt" step="0.01" min="1" required
                       value="<?= $monthlyTarget>0?e($monthlyTarget):'' ?>" placeholder="e.g. 50000.00"
                       class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                <p id="dailyHint" class="text-xs text-mb-accent mt-1.5 <?= $dailyTarget>0?'':'hidden' ?>"><?= $dailyTarget>0?'~ $'.number_format($dailyTarget,2).'/day':'' ?></p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Notes <span class="text-mb-subtle text-xs">(optional)</span></label>
                <textarea name="notes" rows="2" placeholder="e.g. Peak season, Ramadan..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm resize-none"><?= e($targetRow['notes']??'') ?></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" onclick="document.getElementById('targetModal').classList.add('hidden')" class="text-mb-silver hover:text-white text-sm px-4 py-2 transition-colors">Cancel</button>
                <button type="submit" class="bg-mb-accent text-white px-6 py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Save Target</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('targetModal').addEventListener('click',function(e){if(e.target===this)this.classList.add('hidden');});
(function(){
    var inp=document.getElementById('modalAmt'),hint=document.getElementById('dailyHint'),days=<?= (int)$periodDays ?>;
    if(!inp)return;
    inp.addEventListener('input',function(){
        var v=parseFloat(this.value)||0;
        if(v>0&&days>0){hint.textContent='~ $'+(v/days).toFixed(2)+'/day';hint.classList.remove('hidden');}
        else{hint.classList.add('hidden');}
    });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
