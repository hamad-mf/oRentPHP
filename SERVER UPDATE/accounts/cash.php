<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$_currentUser = current_user();
if (!auth_has_perm('view_finances') && ($_currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Access denied.');
    redirect('../index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
ledger_ensure_schema($pdo);
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));

$cashIncome  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income'")->fetchColumn();
$cashExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense'")->fetchColumn();
$cashBalance = $cashIncome - $cashExpense;

$baseSql = " FROM ledger_entries le LEFT JOIN reservations r ON le.source_type='reservation' AND le.source_id = r.id LEFT JOIN clients c ON c.id = r.client_id LEFT JOIN users u ON u.id = le.created_by WHERE le.payment_mode = 'cash'";
$selectSql = "SELECT le.*, r.id AS res_id, r.status AS res_status, c.name AS client_name, u.name AS posted_by_name" . $baseSql . " ORDER BY le.id DESC, le.posted_at DESC";
$countSql  = "SELECT COUNT(*)" . $baseSql;
$pg = paginate_query($pdo, $selectSql, $countSql, [], $page, $perPage);
$entries = $pg['rows'];

$pageTitle = 'Cash Transactions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-mb-subtle hover:text-white transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
        Back to Accounts
    </a>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-green-500/5 border border-green-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Cash Income</p>
            <p class="text-xl font-light text-green-400">$<?= number_format($cashIncome, 2) ?></p>
        </div>
        <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Cash Expenses</p>
            <p class="text-xl font-light text-red-400">$<?= number_format($cashExpense, 2) ?></p>
        </div>
        <div class="bg-mb-accent/5 border border-mb-accent/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Net Cash Balance</p>
            <p class="text-xl font-light <?= $cashBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">$<?= number_format($cashBalance, 2) ?></p>
        </div>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-mb-subtle/10">
            <h2 class="text-white font-light">Cash Transactions</h2>
            <span class="text-xs text-mb-subtle"><?= $pg['total'] ?> entries</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-mb-black text-mb-subtle uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3">Client</th>
                        <th class="px-6 py-3">Source</th>
                        <th class="px-6 py-3">Description</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-mb-subtle italic">No cash transactions found.</td></tr>
                    <?php else:
                        foreach ($entries as $row):
                            $isIncome = $row['txn_type'] === 'income';
                            $amtColor = $isIncome ? 'text-green-400' : 'text-red-400';
                            $typeBg   = $isIncome ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400';
                    ?>
                        <tr class="hover:bg-mb-black/30 transition-colors">
                            <td class="px-6 py-3 text-mb-silver whitespace-nowrap"><?= date('d M Y', strtotime($row['posted_at'])) ?></td>
                            <td class="px-6 py-3"><span class="<?= $typeBg ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= strtoupper($row['txn_type']) ?></span></td>
                            <td class="px-6 py-3 text-white"><?= e($row['category']) ?></td>
                            <td class="px-6 py-3 text-mb-silver"><?php if (!empty($row['client_name'])): ?><?= e($row['client_name']) ?><?php else: ?><span class="text-mb-subtle">&mdash;</span><?php endif; ?></td>
                            <td class="px-6 py-3"><?php if ($row['source_type'] === 'reservation' && !empty($row['res_id'])): ?><a href="../reservations/show.php?id=<?= (int)$row['res_id'] ?>" class="text-xs text-mb-accent hover:underline">Res #<?= (int)$row['res_id'] ?></a><?php if (!empty($row['source_event'])): ?> <span class="text-[10px] text-mb-subtle capitalize">(<?= e($row['source_event']) ?>)</span><?php endif; ?><?php elseif ($row['source_type'] === 'manual'): ?><span class="text-xs text-mb-subtle">Manual</span><?php else: ?><span class="text-xs text-mb-subtle capitalize"><?= e($row['source_type']) ?></span><?php endif; ?></td>
                            <td class="px-6 py-3 text-mb-subtle max-w-xs truncate"><?= e($row['description'] ?? '') ?></td>
                            <td class="px-6 py-3 text-right <?= $amtColor ?> font-medium whitespace-nowrap"><?= $isIncome ? '+' : '-' ?>$<?= number_format((float)$row['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
echo render_pagination($pg);
require_once __DIR__ . '/../includes/footer.php';
?>