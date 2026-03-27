<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

auth_check();
$pdo = db();
$me = current_user();
$userId = (int) ($me['id'] ?? 0);
if ($userId <= 0) {
    redirect('../index.php');
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = get_per_page($pdo);

$hasIncentiveTable = false;
$schemaNotice = '';
$historyPg = [
    'rows' => [],
    'total' => 0,
    'page' => 1,
    'per_page' => $perPage,
    'total_pages' => 1,
];
$summary = [
    'total_entries' => 0,
    'total_amount' => 0.0,
];

try {
    $hasIncentiveTable = (bool) $pdo->query("SHOW TABLES LIKE 'staff_incentives'")->fetchColumn();
    if ($hasIncentiveTable) {
        $sumStmt = $pdo->prepare("SELECT COUNT(*) AS total_entries, COALESCE(SUM(amount),0) AS total_amount FROM staff_incentives WHERE user_id = ?");
        $sumStmt->execute([$userId]);
        $sum = $sumStmt->fetch() ?: [];
        $summary['total_entries'] = (int) ($sum['total_entries'] ?? 0);
        $summary['total_amount'] = (float) ($sum['total_amount'] ?? 0);

        $rowsSql = "SELECT id, month, year, amount, note, created_at
                    FROM staff_incentives
                    WHERE user_id = ?
                    ORDER BY year DESC, month DESC, id DESC";
        $countSql = "SELECT COUNT(*) FROM staff_incentives WHERE user_id = ?";
        $historyPg = paginate_query($pdo, $rowsSql, $countSql, [$userId], $page, $perPage);
    } else {
        $schemaNotice = 'Incentive tracking is not enabled yet.';
    }
} catch (Throwable $e) {
    app_log('ERROR', 'Staff incentive history query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'staff/incentive_history.php',
        'user_id' => $userId,
    ]);
    $schemaNotice = 'Could not load incentive history right now.';
}

$pageTitle = 'My Incentive History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="../index.php" class="hover:text-white transition-colors">Dashboard</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">My Incentive History</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Total Incentives</p>
            <p class="text-white text-3xl font-light"><?= (int) ($summary['total_entries'] ?? 0) ?></p>
        </div>
        <div class="bg-mb-surface border border-green-500/30 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Total Received</p>
            <p class="text-green-400 text-3xl font-light">$<?= number_format((float) ($summary['total_amount'] ?? 0), 2) ?></p>
        </div>
    </div>

    <?php if ($schemaNotice !== ''): ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 text-mb-subtle text-sm">
            <?= e($schemaNotice) ?>
        </div>
    <?php else: ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-mb-black text-mb-silver uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-3 font-medium">Given At</th>
                            <th class="px-5 py-3 font-medium">Payroll Period</th>
                            <th class="px-5 py-3 font-medium">Amount</th>
                            <th class="px-5 py-3 font-medium">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10 text-sm">
                        <?php if (empty($historyPg['rows'])): ?>
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-mb-subtle italic">
                                    No incentive records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historyPg['rows'] as $row): ?>
                                <?php
                                $monthNo = (int) ($row['month'] ?? 0);
                                $yearNo = (int) ($row['year'] ?? 0);
                                if ($monthNo >= 1 && $monthNo <= 12 && $yearNo > 0) {
                                    $monthNext = $monthNo === 12 ? 1 : $monthNo + 1;
                                    $periodLabel = '15 ' . date('M', mktime(0,0,0,$monthNo,1)) . ' – 14 ' . date('M', mktime(0,0,0,$monthNext,1)) . ' ' . $yearNo;
                                } else {
                                    $periodLabel = 'M' . $monthNo . ' ' . $yearNo;
                                }
                                ?>
                                <tr class="hover:bg-mb-black/20 transition-colors">
                                    <td class="px-5 py-3 text-mb-silver"><?= $row['created_at'] ? date('d M Y h:i A', strtotime($row['created_at'])) : '-' ?></td>
                                    <td class="px-5 py-3 text-mb-silver"><?= e($periodLabel) ?></td>
                                    <td class="px-5 py-3 text-green-400">$<?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                                    <td class="px-5 py-3 text-mb-subtle"><?= e($row['note'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?= render_pagination($historyPg) ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
