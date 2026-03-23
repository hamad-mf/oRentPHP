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

$hasAdvanceTable = false;
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
    'total_remaining' => 0.0,
];

try {
    $hasAdvanceTable = (bool) $pdo->query("SHOW TABLES LIKE 'payroll_advances'")->fetchColumn();
    if ($hasAdvanceTable) {
        $sumStmt = $pdo->prepare("SELECT COUNT(*) AS total_entries, COALESCE(SUM(amount),0) AS total_amount, COALESCE(SUM(remaining_amount),0) AS total_remaining FROM payroll_advances WHERE user_id = ?");
        $sumStmt->execute([$userId]);
        $sum = $sumStmt->fetch() ?: [];
        $summary['total_entries'] = (int) ($sum['total_entries'] ?? 0);
        $summary['total_amount'] = (float) ($sum['total_amount'] ?? 0);
        $summary['total_remaining'] = (float) ($sum['total_remaining'] ?? 0);

        $rowsSql = "SELECT id, month, year, amount, remaining_amount, status, note, given_at
                    FROM payroll_advances
                    WHERE user_id = ?
                    ORDER BY given_at DESC, id DESC";
        $countSql = "SELECT COUNT(*) FROM payroll_advances WHERE user_id = ?";
        $historyPg = paginate_query($pdo, $rowsSql, $countSql, [$userId], $page, $perPage);
    } else {
        $schemaNotice = 'Advance tracking is not enabled yet.';
    }
} catch (Throwable $e) {
    app_log('ERROR', 'Staff advance history query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'staff/advance_history.php',
        'user_id' => $userId,
    ]);
    $schemaNotice = 'Could not load advance history right now.';
}

$pageTitle = 'My Advance History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="../index.php" class="hover:text-white transition-colors">Dashboard</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">My Advance History</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Total Advances</p>
            <p class="text-white text-3xl font-light"><?= (int) ($summary['total_entries'] ?? 0) ?></p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Total Received</p>
            <p class="text-white text-3xl font-light">$<?= number_format((float) ($summary['total_amount'] ?? 0), 2) ?></p>
        </div>
        <div class="bg-mb-surface border <?= (float) ($summary['total_remaining'] ?? 0) > 0 ? 'border-orange-500/30' : 'border-mb-subtle/20' ?> rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Outstanding</p>
            <p class="<?= (float) ($summary['total_remaining'] ?? 0) > 0 ? 'text-orange-300' : 'text-green-400' ?> text-3xl font-light">$<?= number_format((float) ($summary['total_remaining'] ?? 0), 2) ?></p>
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
                            <th class="px-5 py-3 font-medium">Payroll Month</th>
                            <th class="px-5 py-3 font-medium">Amount</th>
                            <th class="px-5 py-3 font-medium">Remaining</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10 text-sm">
                        <?php if (empty($historyPg['rows'])): ?>
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-mb-subtle italic">
                                    No advance records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historyPg['rows'] as $row): ?>
                                <?php
                                $monthNo = (int) ($row['month'] ?? 0);
                                $monthLabel = ($monthNo >= 1 && $monthNo <= 12) ? date('M', mktime(0, 0, 0, $monthNo, 1)) : ('M' . max(0, $monthNo));
                                $periodLabel = $monthLabel . ' ' . (int) ($row['year'] ?? 0);
                                $status = (string) ($row['status'] ?? 'pending');
                                $statusLabel = ucwords(str_replace('_', ' ', $status));
                                $statusClass = match ($status) {
                                    'recovered' => 'bg-green-500/15 text-green-400 border-green-500/30',
                                    'partially_recovered' => 'bg-orange-500/15 text-orange-300 border-orange-500/30',
                                    default => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                                };
                                ?>
                                <tr class="hover:bg-mb-black/20 transition-colors">
                                    <td class="px-5 py-3 text-mb-silver"><?= $row['given_at'] ? date('d M Y h:i A', strtotime($row['given_at'])) : '-' ?></td>
                                    <td class="px-5 py-3 text-mb-silver"><?= e($periodLabel) ?></td>
                                    <td class="px-5 py-3 text-white">$<?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                                    <td class="px-5 py-3 <?= (float) ($row['remaining_amount'] ?? 0) > 0 ? 'text-orange-300' : 'text-green-400' ?>">
                                        $<?= number_format((float) ($row['remaining_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs <?= $statusClass ?>">
                                            <?= e($statusLabel) ?>
                                        </span>
                                    </td>
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
