<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/investment_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

$pdo = db();
$perPage = max(12, get_per_page($pdo));
$page = max(1, (int) ($_GET['page'] ?? 1));
ledger_ensure_schema($pdo);
investment_ensure_schema($pdo);

// Fetch all EMIs with paid count
$listSql = "SELECT
                i.*,
                COUNT(s.id) AS total_emis,
                SUM(s.status = 'paid') AS paid_emis,
                SUM(CASE WHEN s.status = 'paid' THEN s.amount ELSE 0 END) AS amount_paid
            FROM emi_investments i
            LEFT JOIN emi_schedules s ON s.investment_id = i.id
            GROUP BY i.id
            ORDER BY i.created_at DESC";
$countSql = "SELECT COUNT(*) FROM emi_investments";
$pgInvest = paginate_query($pdo, $listSql, $countSql, [], $page, $perPage);
$investments = $pgInvest['rows'];

$pageTitle = 'EMI Management';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <?php if ($msg = getFlash('success')): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($msg = getFlash('error')): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-white font-light text-xl">EMI Management</h2>
            <p class="text-mb-subtle text-sm mt-0.5">Track vehicle purchases and EMI payments</p>
        </div>
        <a href="create.php"
            class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
            </svg>
            Add Investment
        </a>
    </div>

    <?php if (empty($investments)): ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl py-20 text-center">
            <svg class="w-14 h-14 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-mb-subtle">No EMIs yet.</p>
            <a href="create.php"
                class="inline-block mt-4 bg-mb-accent text-white px-5 py-2 rounded-full text-sm hover:bg-mb-accent/80 transition-colors">Add
                your first investment</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4">
            <?php foreach ($investments as $inv):
                $paidEmis = (int) $inv['paid_emis'];
                $totalEmis = (int) $inv['total_emis'];
                $amtPaid = (float) $inv['amount_paid'];
                $loanAmt = (float) $inv['loan_amount'];
                $progress = $totalEmis > 0 ? round($paidEmis / $totalEmis * 100) : 0;
                $completed = $paidEmis >= $totalEmis && $totalEmis > 0;
                $remaining = $loanAmt - $amtPaid;
                ?>
                <div
                    class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 hover:border-mb-subtle/40 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-white font-medium"><?= e($inv['title']) ?></h3>
                                <?php if ($completed): ?>
                                    <span
                                        class="bg-green-500/10 text-green-400 border border-green-500/20 rounded-full px-2.5 py-0.5 text-xs">Completed</span>
                                <?php else: ?>
                                    <span
                                        class="bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-full px-2.5 py-0.5 text-xs">Ongoing</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($inv['lender']): ?>
                                <p class="text-mb-subtle text-sm mt-0.5">Lender: <?= e($inv['lender']) ?></p>
                            <?php endif; ?>

                            <!-- Progress bar -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-xs text-mb-subtle mb-1">
                                    <span><?= $paidEmis ?> / <?= $totalEmis ?> EMIs paid</span>
                                    <span><?= $progress ?>%</span>
                                </div>
                                <div class="w-full bg-mb-black rounded-full h-1.5">
                                    <div class="<?= $completed ? 'bg-green-500' : 'bg-mb-accent' ?> h-1.5 rounded-full transition-all"
                                        style="width:<?= $progress ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Amounts -->
                        <div class="text-right shrink-0">
                            <p class="text-xs text-mb-subtle">EMI</p>
                            <p class="text-white font-semibold text-lg">$<?= number_format($inv['emi_amount'], 2) ?></p>
                            <p class="text-xs text-mb-subtle mt-1">Remaining: <span
                                    class="text-red-400">$<?= number_format(max(0, $remaining), 2) ?></span></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mt-4 pt-4 border-t border-mb-subtle/10">
                        <div class="text-xs text-mb-subtle">
                            Total: <span class="text-mb-silver">$<?= number_format($inv['total_cost'], 2) ?></span>
                        </div>
                        <div class="text-xs text-mb-subtle">
                            Down Payment: <span class="text-mb-silver">$<?= number_format($inv['down_payment'], 2) ?></span>
                        </div>
                        <div class="text-xs text-mb-subtle">
                            Loan: <span class="text-mb-silver">$<?= number_format($inv['loan_amount'], 2) ?></span>
                        </div>
                        <div class="ml-auto flex items-center gap-2">
                            <a href="show.php?id=<?= $inv['id'] ?>"
                                class="text-mb-accent hover:text-mb-accent/80 text-xs font-medium transition-colors px-3 py-1.5 border border-mb-accent/30 rounded-lg hover:border-mb-accent/60">View
                                EMI Schedule</a>
                            <a href="edit.php?id=<?= $inv['id'] ?>"
                                class="text-mb-subtle hover:text-white text-xs transition-colors px-3 py-1.5 border border-mb-subtle/20 rounded-lg hover:border-mb-subtle/40">Edit</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
echo render_pagination($pgInvest, []);
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
