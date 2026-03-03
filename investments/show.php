<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
auth_require_admin();
$pdo = db();
ledger_ensure_schema($pdo);

$id = (int) ($_REQUEST['id'] ?? 0);
$inv = $pdo->prepare("SELECT * FROM emi_investments WHERE id=?");
$inv->execute([$id]);
$inv = $inv->fetch();
if (!$inv) {
    flash('error', 'Investment not found.');
    redirect('index.php');
}

$bankAccounts = $pdo->query("SELECT id, name, balance FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();

// ── POST handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $scheduleId = (int) ($_POST['schedule_id'] ?? 0);

    // ── Mark Paid ──────────────────────────────────────────────
    if ($action === 'pay' && $scheduleId) {
        $bankId = (int) ($_POST['bank_account_id'] ?? 0);
        $paidDate = trim($_POST['paid_date'] ?? date('Y-m-d'));
        $notes = trim($_POST['pay_notes'] ?? '');

        if (!$bankId) {
            flash('error', 'Please select a bank account.');
            redirect("show.php?id=$id");
        }

        $row = $pdo->prepare("SELECT * FROM emi_schedules WHERE id=? AND investment_id=?");
        $row->execute([$scheduleId, $id]);
        $row = $row->fetch();
        if (!$row || $row['status'] === 'paid') {
            flash('error', 'Invalid or already paid.');
            redirect("show.php?id=$id");
        }

        $ledgerId = ledger_post(
            $pdo,
            'expense',
            'EMI Payment',
            (float) $row['amount'],
            'account',
            $bankId,
            'emi_schedule',
            $scheduleId,
            'emi_paid',
            "EMI #{$row['installment_no']} for: {$inv['title']}",
            (int) ($_SESSION['user']['id'] ?? 0),
            "emi_pay_{$scheduleId}",
            $paidDate . ' 00:00:00'
        );

        if ($ledgerId) {
            $pdo->prepare("UPDATE emi_schedules SET status='paid', paid_date=?, bank_account_id=?, ledger_entry_id=?, notes=? WHERE id=?")
                ->execute([$paidDate, $bankId, $ledgerId, $notes ?: null, $scheduleId]);
            flash('success', "EMI #{$row['installment_no']} marked as paid.");
        } else {
            flash('error', 'Payment failed — ledger entry could not be created.');
        }
        redirect("show.php?id=$id");
    }

    // ── Unmark Paid ────────────────────────────────────────────
    if ($action === 'unpay' && $scheduleId) {
        $row = $pdo->prepare("SELECT * FROM emi_schedules WHERE id=? AND investment_id=?");
        $row->execute([$scheduleId, $id]);
        $row = $row->fetch();
        if (!$row || $row['status'] !== 'paid') {
            flash('error', 'Not paid.');
            redirect("show.php?id=$id");
        }

        $pdo->beginTransaction();
        try {
            // Reverse ledger & restore bank
            if ($row['ledger_entry_id']) {
                $le = $pdo->prepare("SELECT * FROM ledger_entries WHERE id=?")->execute([$row['ledger_entry_id']]);
                $le = $pdo->prepare("SELECT * FROM ledger_entries WHERE id=?");
                $le->execute([$row['ledger_entry_id']]);
                $le = $le->fetch();
                if ($le && $le['bank_account_id']) {
                    $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id=?")->execute([$le['amount'], $le['bank_account_id']]);
                }
                $pdo->prepare("DELETE FROM ledger_entries WHERE id=?")->execute([$row['ledger_entry_id']]);
            }
            $pdo->prepare("UPDATE emi_schedules SET status='pending', paid_date=NULL, bank_account_id=NULL, ledger_entry_id=NULL, notes=NULL WHERE id=?")
                ->execute([$scheduleId]);
            $pdo->commit();
            flash('success', "EMI #{$row['installment_no']} marked as pending.");
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Unmark failed: ' . $e->getMessage());
        }
        redirect("show.php?id=$id");
    }
}

// ── Fetch schedule ────────────────────────────────────────────
$schedules = $pdo->prepare("
    SELECT s.*, ba.name AS bank_name
    FROM emi_schedules s
    LEFT JOIN bank_accounts ba ON ba.id = s.bank_account_id
    WHERE s.investment_id = ?
    ORDER BY s.installment_no
");
$schedules->execute([$id]);
$schedules = $schedules->fetchAll();

$totalEmis = count($schedules);
$paidEmis = count(array_filter($schedules, fn($s) => $s['status'] === 'paid'));
$amtPaid = array_sum(array_map(fn($s) => $s['status'] === 'paid' ? (float) $s['amount'] : 0, $schedules));
$progress = $totalEmis > 0 ? round($paidEmis / $totalEmis * 100) : 0;
$completed = $paidEmis >= $totalEmis && $totalEmis > 0;

$pageTitle = 'Investment: ' . $inv['title'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Investments</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">
            <?= e($inv['title']) ?>
        </span>
    </div>

    <?php if ($msg = getFlash('success')): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($msg = getFlash('error')): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <?= e($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Card -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-white text-xl font-light">
                        <?= e($inv['title']) ?>
                    </h2>
                    <?php if ($completed): ?>
                        <span
                            class="bg-green-500/10 text-green-400 border border-green-500/20 rounded-full px-2.5 py-0.5 text-xs">Completed</span>
                    <?php else: ?>
                        <span
                            class="bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-full px-2.5 py-0.5 text-xs">Ongoing</span>
                    <?php endif; ?>
                </div>
                <?php if ($inv['lender']): ?>
                    <p class="text-mb-subtle text-sm mt-1">Lender:
                        <?= e($inv['lender']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <a href="edit.php?id=<?= $id ?>"
                    class="text-mb-subtle hover:text-white text-xs px-3 py-1.5 border border-mb-subtle/20 rounded-lg hover:border-mb-subtle/40 transition-colors">Edit</a>
                <form method="POST" action="delete.php" onsubmit="return confirm('Delete this investment?')">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit"
                        class="text-red-400/60 hover:text-red-400 text-xs px-3 py-1.5 border border-red-500/10 hover:border-red-500/30 rounded-lg transition-colors">Delete</button>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
            <?php foreach ([
                ['Total Cost', '$' . number_format($inv['total_cost'], 2), 'text-mb-silver'],
                ['Down Payment', '$' . number_format($inv['down_payment'], 2), 'text-mb-silver'],
                ['Loan Amount', '$' . number_format($inv['loan_amount'], 2), 'text-mb-accent'],
                ['EMI / Month', '$' . number_format($inv['emi_amount'], 2), 'text-white'],
            ] as [$lbl, $val, $clr]): ?>
                <div class="bg-mb-black/40 rounded-lg p-3">
                    <p class="text-xs text-mb-subtle mb-0.5">
                        <?= $lbl ?>
                    </p>
                    <p class="text-sm font-semibold <?= $clr ?>">
                        <?= $val ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Progress -->
        <div class="mt-5">
            <div class="flex items-center justify-between text-xs text-mb-subtle mb-2">
                <span>
                    <?= $paidEmis ?> of
                    <?= $totalEmis ?> EMIs paid — $
                    <?= number_format($amtPaid, 2) ?> of $
                    <?= number_format($inv['loan_amount'], 2) ?>
                </span>
                <span class="font-medium <?= $completed ? 'text-green-400' : 'text-mb-accent' ?>">
                    <?= $progress ?>%
                </span>
            </div>
            <div class="w-full bg-mb-black rounded-full h-2">
                <div class="<?= $completed ? 'bg-green-500' : 'bg-mb-accent' ?> h-2 rounded-full transition-all"
                    style="width:<?= $progress ?>%"></div>
            </div>
        </div>
    </div>

    <!-- EMI Schedule Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h3 class="text-white font-light">EMI Schedule</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                    <tr class="text-mb-subtle text-xs uppercase">
                        <th class="px-6 py-3 text-left">#</th>
                        <th class="px-6 py-3 text-left">Due Date</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                        <th class="px-6 py-3 text-center">Status</th>
                        <th class="px-6 py-3 text-left">Paid Date</th>
                        <th class="px-6 py-3 text-left">Bank Account</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php foreach ($schedules as $s):
                        $isPaid = $s['status'] === 'paid';
                        $isOverdue = !$isPaid && $s['due_date'] < date('Y-m-d');
                        ?>
                        <tr class="hover:bg-mb-black/20 transition-colors <?= $isOverdue ? 'bg-red-500/5' : '' ?>">
                            <td class="px-6 py-4 text-mb-subtle font-mono">
                                <?= $s['installment_no'] ?>
                            </td>
                            <td class="px-6 py-4 text-mb-silver">
                                <?= date('d M Y', strtotime($s['due_date'])) ?>
                                <?php if ($isOverdue): ?>
                                    <span class="ml-1 text-xs text-red-400">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-white font-medium">$
                                <?= number_format($s['amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($isPaid): ?>
                                    <span
                                        class="bg-green-500/10 text-green-400 border border-green-500/20 rounded-full px-2.5 py-0.5 text-xs">Paid</span>
                                <?php elseif ($isOverdue): ?>
                                    <span
                                        class="bg-red-500/10 text-red-400 border border-red-500/20 rounded-full px-2.5 py-0.5 text-xs">Overdue</span>
                                <?php else: ?>
                                    <span
                                        class="bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 rounded-full px-2.5 py-0.5 text-xs">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-mb-subtle text-xs">
                                <?= $isPaid ? date('d M Y', strtotime($s['paid_date'])) : '—' ?>
                            </td>
                            <td class="px-6 py-4 text-mb-subtle text-xs">
                                <?= $isPaid ? e($s['bank_name'] ?? '—') : '—' ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($isPaid): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="unpay">
                                        <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                        <button type="submit"
                                            onclick="return confirm('Unmark this EMI as paid? This will reverse the ledger entry.')"
                                            class="text-xs text-red-400/60 hover:text-red-400 border border-red-500/10 hover:border-red-500/30 px-3 py-1.5 rounded-lg transition-colors">
                                            Unmark
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button"
                                        onclick="openPayModal(<?= $s['id'] ?>, <?= $s['installment_no'] ?>, '<?= number_format($s['amount'], 2) ?>', '<?= $s['due_date'] ?>')"
                                        class="text-xs bg-mb-accent text-white px-3 py-1.5 rounded-lg hover:bg-mb-accent/80 transition-colors">
                                        Mark Paid
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pay Modal -->
<div id="payModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between p-5 border-b border-mb-subtle/10">
            <h3 class="text-white font-medium">Mark EMI as Paid</h3>
            <button onclick="document.getElementById('payModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="schedule_id" id="payScheduleId">

            <div class="bg-mb-black/40 rounded-lg px-4 py-3">
                <p class="text-xs text-mb-subtle mb-0.5">Paying EMI</p>
                <p class="text-white font-medium" id="payLabel"></p>
                <p class="text-mb-accent font-bold text-lg" id="payAmount"></p>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Payment Date <span
                        class="text-red-400">*</span></label>
                <input type="date" name="paid_date" id="payDate" value="<?= date('Y-m-d') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Pay From Bank Account <span
                        class="text-red-400">*</span></label>
                <select name="bank_account_id" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value="">— Select account —</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= $ba['id'] ?>">
                            <?= e($ba['name']) ?> — $
                            <?= number_format($ba['balance'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Notes <span
                        class="text-mb-subtle text-xs">(optional)</span></label>
                <input type="text" name="pay_notes" placeholder="e.g. paid via online banking"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>

            <div class="flex items-center justify-end gap-3 pt-1">
                <button type="button" onclick="document.getElementById('payModal').classList.add('hidden')"
                    class="text-mb-subtle hover:text-white text-sm transition-colors px-3 py-2">Cancel</button>
                <button type="submit"
                    class="bg-green-600 hover:bg-green-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPayModal(scheduleId, installNo, amount, dueDate) {
        document.getElementById('payScheduleId').value = scheduleId;
        document.getElementById('payLabel').textContent = 'Installment #' + installNo + ' — Due: ' + dueDate;
        document.getElementById('payAmount').textContent = '$' + parseFloat(amount).toLocaleString('en', { minimumFractionDigits: 2 });
        document.getElementById('payDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('payModal').classList.remove('hidden');
    }
    document.getElementById('payModal').addEventListener('click', function (e) {
        if (e.target === this) this.classList.add('hidden');
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>