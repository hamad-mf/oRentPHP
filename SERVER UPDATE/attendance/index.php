<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();

auth_require_admin();

// ── Ensure table exists ────────────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      INT UNSIGNED NOT NULL,
        date         DATE NOT NULL,
        punch_in     DATETIME DEFAULT NULL,
        punch_out    DATETIME DEFAULT NULL,
        pin_warning  TINYINT(1) NOT NULL DEFAULT 0,
        pout_warning TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_user_date (user_id, date),
        KEY idx_date (date),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
}

// ── Date filter (IST) ─────────────────────────────────────────────────────
$ist = new DateTimeZone('Asia/Kolkata');
$todayIst = (new DateTime('now', $ist))->format('Y-m-d');
$filterDate = trim($_GET['date'] ?? $todayIst);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = $todayIst;
}

// ── Fetch all active non-admin staff ──────────────────────────────────────
$staff = $pdo->query(
    "SELECT id, name FROM users WHERE role != 'admin' AND is_active = 1 ORDER BY name ASC"
)->fetchAll();

// ── Fetch attendance for the selected date ────────────────────────────────
$attStmt = $pdo->prepare(
    'SELECT * FROM staff_attendance WHERE date = ?'
);
$attStmt->execute([$filterDate]);
$attMap = [];
foreach ($attStmt->fetchAll() as $row) {
    $attMap[$row['user_id']] = $row;
}

// ── Format IST display time ────────────────────────────────────────────────
function fmt_ist(string $dt): string
{
    if (!$dt)
        return '—';
    $d = new DateTime($dt, new DateTimeZone('Asia/Kolkata'));
    return $d->format('h:i A');
}

$pageTitle = 'Attendance';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-white text-xl font-light">Attendance</h2>
            <p class="text-mb-subtle text-sm mt-0.5">
                <?= count($staff) ?> active staff member
                <?= count($staff) !== 1 ? 's' : '' ?>
            </p>
        </div>
        <a href="../settings/attendance.php"
            class="text-sm border border-mb-subtle/20 text-mb-silver px-4 py-2 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors">
            ⚙️ Punch Window Settings
        </a>
    </div>

    <!-- Date Filter -->
    <form method="GET"
        class="flex items-center gap-3 bg-mb-surface border border-mb-subtle/20 rounded-xl px-4 py-3 w-fit">
        <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <input type="date" name="date" value="<?= e($filterDate) ?>" onchange="this.form.submit()"
            class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
        <?php if ($filterDate !== $todayIst): ?>
            <a href="index.php" class="text-xs text-mb-subtle hover:text-white transition-colors flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Today
            </a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center gap-2">
            <span class="text-white text-sm font-medium">
                <?= date('d M Y', strtotime($filterDate)) ?>
            </span>
            <?php if ($filterDate === $todayIst): ?>
                <span class="text-[10px] bg-mb-accent/20 text-mb-accent px-2 py-0.5 rounded-full">Today</span>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-mb-subtle/10 text-mb-subtle text-xs uppercase tracking-wider">
                        <th class="text-left px-6 py-3">Staff</th>
                        <th class="text-left px-6 py-3">Punch In</th>
                        <th class="text-left px-6 py-3">Punch Out</th>
                        <th class="text-left px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php if (empty($staff)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-mb-subtle/50 text-xs">No active staff found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($staff as $s):
                        $att = $attMap[$s['id']] ?? null;
                        $pinTime = $att['punch_in'] ?? null;
                        $poutTime = $att['punch_out'] ?? null;
                        $pinWarn = ($att['pin_warning'] ?? 0) == 1;
                        $poutWarn = ($att['pout_warning'] ?? 0) == 1;

                        if (!$att) {
                            $statusBadge = '<span class="text-[10px] bg-red-500/10 text-red-400/80 px-2 py-0.5 rounded-full">Absent</span>';
                        } elseif ($pinTime && $poutTime) {
                            $statusBadge = '<span class="text-[10px] bg-green-500/15 text-green-400 px-2 py-0.5 rounded-full">✓ Present</span>';
                        } else {
                            $statusBadge = '<span class="text-[10px] bg-yellow-500/15 text-yellow-400 px-2 py-0.5 rounded-full">⏰ In Progress</span>';
                        }
                        ?>
                        <tr class="hover:bg-mb-black/20 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-7 h-7 rounded-full bg-mb-accent/10 border border-mb-accent/20 flex items-center justify-center text-[11px] font-semibold text-mb-accent flex-shrink-0">
                                        <?= strtoupper(substr($s['name'], 0, 2)) ?>
                                    </div>
                                    <span class="text-white">
                                        <?= e($s['name']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($pinTime): ?>
                                    <span class="text-white">
                                        <?= fmt_ist($pinTime) ?>
                                    </span>
                                    <?php if ($pinWarn): ?>
                                        <span class="ml-1 text-[10px] text-yellow-400" title="Outside punch-in window">⚠️</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-mb-subtle/50">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($poutTime): ?>
                                    <span class="text-white">
                                        <?= fmt_ist($poutTime) ?>
                                    </span>
                                    <?php if ($poutWarn): ?>
                                        <span class="ml-1 text-[10px] text-yellow-400" title="Outside punch-out window">⚠️</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-mb-subtle/50">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= $statusBadge ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary row -->
    <?php if (!empty($staff)):
        $present = 0;
        $inProgress = 0;
        $absent = 0;
        foreach ($staff as $s) {
            $att = $attMap[$s['id']] ?? null;
            if (!$att) {
                $absent++;
            } elseif ($att['punch_in'] && $att['punch_out']) {
                $present++;
            } else {
                $inProgress++;
            }
        }
        ?>
        <div class="flex gap-3 flex-wrap">
            <div class="bg-green-500/10 border border-green-500/20 rounded-xl px-5 py-3 flex items-center gap-2">
                <span class="text-green-400 font-semibold text-lg">
                    <?= $present ?>
                </span>
                <span class="text-green-400/70 text-sm">Present</span>
            </div>
            <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl px-5 py-3 flex items-center gap-2">
                <span class="text-yellow-400 font-semibold text-lg">
                    <?= $inProgress ?>
                </span>
                <span class="text-yellow-400/70 text-sm">In Progress</span>
            </div>
            <div class="bg-red-500/10 border border-red-500/20 rounded-xl px-5 py-3 flex items-center gap-2">
                <span class="text-red-400 font-semibold text-lg">
                    <?= $absent ?>
                </span>
                <span class="text-red-400/70 text-sm">Absent</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>