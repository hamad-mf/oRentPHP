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

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$userId = (int) ($_currentUser['id'] ?? 0);

//  ”  ”  Handle POST actions  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'cash_transfer' && $isAdmin) {
        $toId = (int) ($_POST['to_account_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $date = trim($_POST['posted_at'] ?? date('Y-m-d'));
        $postedAt = date('Y-m-d H:i:s', strtotime($date . ' ' . date('H:i:s')));
        $transferError = '';
        if (ledger_transfer_cash_to_bank($pdo, $toId, $amount, $desc ?: null, $userId, $postedAt, $transferError)) {
            flash('success', 'Cash transferred to bank account.');
        } else {
            flash('error', $transferError ?: 'Transfer failed.');
        }
        redirect('cash.php');
    }
}

$cashIncome  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income'")->fetchColumn();
$cashExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense'")->fetchColumn();
$cashBalance = $cashIncome - $cashExpense;

$accounts = ledger_get_accounts($pdo);
$activeAccounts = array_values(array_filter($accounts, fn($a) => (int) ($a['is_active'] ?? 0) === 1));
$canTransfer = $isAdmin && $cashBalance > 0 && !empty($activeAccounts);

$baseSql = " FROM ledger_entries le LEFT JOIN reservations r ON le.source_type='reservation' AND le.source_id = r.id LEFT JOIN clients c ON c.id = r.client_id LEFT JOIN users u ON u.id = le.created_by WHERE le.payment_mode = 'cash'";
$selectSql = "SELECT le.*, r.id AS res_id, r.status AS res_status, c.name AS client_name, u.name AS posted_by_name" . $baseSql . " ORDER BY le.id DESC, le.posted_at DESC";
$countSql  = "SELECT COUNT(*)" . $baseSql;
$pg = paginate_query($pdo, $selectSql, $countSql, [], $page, $perPage);
$entries = $pg['rows'];

$success = getFlash('success');
$error   = getFlash('error');

$pageTitle = 'Cash Transactions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    <a href="index.php" class="inline-flex items-center gap-2 text-mb-subtle hover:text-white transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
        Back to Accounts
    </a>

    <?php if ($success): ?><div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg><?= e($error) ?></div><?php endif; ?>

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
            <div class="flex items-center gap-2">
                <span class="text-xs text-mb-subtle"><?= $pg['total'] ?> entries</span>
                <?php if ($isAdmin): ?>
                    <button type="button" onclick="<?= $canTransfer ? 'openCashTransferModal()' : '' ?>"
                        class="text-xs bg-blue-500/15 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-full hover:bg-blue-500/25 transition-colors flex items-center gap-1 <?= $canTransfer ? '' : 'opacity-50 cursor-not-allowed' ?>"
                        <?= $canTransfer ? '' : 'disabled' ?>>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Transfer to Bank
                    </button>
                <?php endif; ?>
            </div>
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

    <?php if ($isAdmin): ?>
    <div id="cashTransferModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light">Transfer Cash to Bank</h3>
                <button type="button" onclick="closeCashTransferModal()" class="text-mb-subtle hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="post" onsubmit="return validateCashTransfer()">
                <input type="hidden" name="action" value="cash_transfer">
                <div class="p-5 space-y-4">
                    <div>
                        <label class="text-xs text-mb-subtle uppercase tracking-wider">Destination Bank Account</label>
                        <select name="to_account_id" class="mt-1 w-full bg-mb-black/40 border border-mb-subtle/20 rounded px-3 py-2 text-white" required>
                            <option value="">Select bank account</option>
                            <?php foreach ($activeAccounts as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>"><?= e($acc['name']) ?><?= !empty($acc['bank_name']) ? ' - ' . e($acc['bank_name']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($activeAccounts)): ?>
                            <p class="text-xs text-red-400 mt-1">No active bank account exists. Create one in Accounts first.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="text-xs text-mb-subtle uppercase tracking-wider">Amount</label>
                        <input id="cashTransferAmount" type="number" step="0.01" min="0.01" max="<?= max(0, (float) $cashBalance) ?>"
                            name="amount" class="mt-1 w-full bg-mb-black/40 border border-mb-subtle/20 rounded px-3 py-2 text-white" required>
                        <p class="text-xs text-mb-subtle mt-1">Available cash: $<?= number_format($cashBalance, 2) ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-mb-subtle uppercase tracking-wider">Date</label>
                        <input type="date" name="posted_at" value="<?= date('Y-m-d') ?>"
                            class="mt-1 w-full bg-mb-black/40 border border-mb-subtle/20 rounded px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="text-xs text-mb-subtle uppercase tracking-wider">Description (Optional)</label>
                        <input type="text" name="description" placeholder="Cash transfer"
                            class="mt-1 w-full bg-mb-black/40 border border-mb-subtle/20 rounded px-3 py-2 text-white">
                    </div>
                    <p id="cashTransferError" class="text-xs text-red-400 hidden"></p>
                </div>
                <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-mb-subtle/10">
                    <button type="button" onclick="closeCashTransferModal()"
                        class="text-xs bg-mb-black/40 text-mb-subtle border border-mb-subtle/20 px-3 py-1.5 rounded-full hover:text-white transition-colors">Cancel</button>
                    <button type="submit"
                        class="text-xs bg-blue-500/15 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-full hover:bg-blue-500/25 transition-colors"
                        <?= $canTransfer ? '' : 'disabled' ?>>Transfer</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
echo render_pagination($pg);
if ($isAdmin):
?>
<script>
(function () {
    var modal = document.getElementById('cashTransferModal');
    if (!modal) return;

    window.openCashTransferModal = function () {
        modal.classList.remove('hidden');
    };

    window.closeCashTransferModal = function () {
        modal.classList.add('hidden');
    };

    var cashTransferBalance = <?= json_encode((float) $cashBalance) ?>;
    window.validateCashTransfer = function () {
        var amountEl = document.getElementById('cashTransferAmount');
        var errEl = document.getElementById('cashTransferError');
        if (!amountEl || !errEl) return true;

        var amount = parseFloat(amountEl.value || '0');
        if (amount <= 0) {
            errEl.textContent = 'Please enter a valid amount.';
            errEl.classList.remove('hidden');
            return false;
        }
        if (amount > cashTransferBalance) {
            errEl.textContent = 'Amount exceeds available cash balance.';
            errEl.classList.remove('hidden');
            return false;
        }
        errEl.classList.add('hidden');
        return true;
    };
})();
</script>
<?php
endif;
require_once __DIR__ . '/../includes/footer.php';
?>
