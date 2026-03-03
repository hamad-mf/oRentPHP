<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
// current_user() reads from $_SESSION — must be called before header.php
// so that POST handlers have the correct user during form submissions
$_currentUser = current_user();
if (!auth_has_perm('view_finances') && ($_currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Access denied.');
    redirect('../index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
$pdo = db();
ledger_ensure_schema($pdo);

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$userId = (int) ($_currentUser['id'] ?? 0);

// ── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add manual income / expense ──
    if (in_array($action, ['add_income', 'add_expense'], true)) {
        $txnType = $action === 'add_income' ? 'income' : 'expense';
        $category = trim($_POST['category'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $mode = trim($_POST['payment_mode'] ?? 'cash');
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $date = trim($_POST['posted_at'] ?? date('Y-m-d'));
        $bankAccountId = $bankAccountId > 0 ? $bankAccountId : null;

        if ($mode !== 'account') {
            $bankAccountId = null;
        } else {
            if ($bankAccountId === null) {
                flash('error', 'Please select a bank account for account-mode entries.');
                redirect('index.php');
            }
            $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
            $chk->execute([$bankAccountId]);
            if ((int) $chk->fetchColumn() === 0) {
                flash('error', 'Selected bank account is invalid or inactive.');
                redirect('index.php');
            }
        }

        if ($category && $amount > 0) {
            $postedAt = date('Y-m-d H:i:s', strtotime($date . ' ' . date('H:i:s')));
            ledger_post_manual($pdo, $txnType, $category, $amount, $mode, $desc, $userId, $postedAt, $bankAccountId);
            flash('success', ucfirst($txnType) . ' entry added.');
        } else {
            flash('error', 'Category and a positive amount are required.');
        }
        redirect('index.php');
    }

    // ── Delete manual entry ──
    if ($action === 'delete_entry' && $isAdmin) {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        if (ledger_delete_manual_entry($pdo, $entryId, $userId)) {
            flash('success', 'Entry deleted.');
        } else {
            flash('error', 'Could not delete — may be a system entry.');
        }
        redirect('index.php');
    }

    // ── Save bank account (create or edit) ──
    if ($action === 'save_account' && $isAdmin) {
        $accId = (int) ($_POST['account_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $accNumber = trim($_POST['account_number'] ?? '');

        if (!$name) {
            flash('error', 'Account name required.');
            redirect('index.php');
        }

        if ($accId) {
            $pdo->prepare("UPDATE bank_accounts SET name=?, bank_name=?, account_number=? WHERE id=?")
                ->execute([$name, $bankName ?: null, $accNumber ?: null, $accId]);
            flash('success', 'Account updated.');
        } else {
            $pdo->prepare("INSERT INTO bank_accounts (name, bank_name, account_number) VALUES (?, ?, ?)")
                ->execute([$name, $bankName ?: null, $accNumber ?: null]);
            flash('success', 'Bank account created.');
        }
        redirect('index.php');
    }
}

// ── Filters ───────────────────────────────────────────────────────────────
$fType = $_GET['type'] ?? '';
$fAccount = (int) ($_GET['account'] ?? 0);
$fDateFrom = $_GET['date_from'] ?? date('Y-m-01');
$fDateTo = $_GET['date_to'] ?? date('Y-m-d');
$fSource = $_GET['source'] ?? '';

$filters = [
    'type' => $fType,
    'account_id' => $fAccount ?: null,
    'date_from' => $fDateFrom,
    'date_to' => $fDateTo,
    'source' => $fSource,
];

$accounts = ledger_get_accounts($pdo);
$activeAccounts = array_values(array_filter($accounts, fn($a) => (int) ($a['is_active'] ?? 0) === 1));
$entries = ledger_get_entries($pdo, $filters);
$totals = ledger_get_totals($pdo, $fDateFrom, $fDateTo);

$totalIncome = (float) $totals['total_income'];
$totalExpense = (float) $totals['total_expense'];
$netBalance = $totalIncome - $totalExpense;

$totalCash = 0;
$totalBank = 0;
foreach ($accounts as $acc) {
    if (stripos($acc['name'], 'cash') !== false)
        $totalCash += (float) $acc['balance'];
    else
        $totalBank += (float) $acc['balance'];
}

$pageTitle = 'Accounts & Ledger';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">

    <!-- Flash messages rendered by header.php -->

    <!-- ── Summary Cards ─────────────────────────────────── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php
        $cards = [
            ['Income', '$' . number_format($totalIncome, 2), 'text-green-400', 'border-green-500/20', 'bg-green-500/5'],
            ['Expenses', '$' . number_format($totalExpense, 2), 'text-red-400', 'border-red-500/20', 'bg-red-500/5'],
            ['Net', '$' . number_format($netBalance, 2), $netBalance >= 0 ? 'text-mb-accent' : 'text-red-400', 'border-mb-accent/20', 'bg-mb-accent/5'],
            ['Accounts Total', '$' . number_format(array_sum(array_column($accounts, 'balance')), 2), 'text-yellow-400', 'border-yellow-500/20', 'bg-yellow-500/5'],
        ];
        foreach ($cards as [$label, $val, $clr, $bdr, $bg]): ?>
            <div class="<?= $bg ?> border <?= $bdr ?> rounded-xl p-4">
                <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">
                    <?= $label ?> <span class="text-[10px] opacity-60">(period)</span>
                </p>
                <p class="text-xl font-light <?= $clr ?>">
                    <?= $val ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Bank Accounts Row ─────────────────────────────── -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-mb-subtle/10">
            <h2 class="text-white font-light">Bank Account</h2>
            <?php if ($isAdmin): ?>
                <button onclick="openAccountModal(null)"
                    class="text-xs bg-mb-accent/15 text-mb-accent border border-mb-accent/30 px-3 py-1.5 rounded-full hover:bg-mb-accent/25 transition-colors">
                    + Add Account
                </button>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 p-6">
            <?php foreach ($accounts as $acc): ?>
                <div
                    class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-white font-medium">
                            <?= e($acc['name']) ?>
                        </p>
                        <?php if ($acc['bank_name']): ?>
                            <p class="text-xs text-mb-subtle mt-0.5">
                                <?= e($acc['bank_name']) ?>
                                <?= $acc['account_number'] ? ' — ' . e($acc['account_number']) : '' ?>
                            </p>
                        <?php endif; ?>
                        <p
                            class="text-lg font-light mt-2 <?= (float) $acc['balance'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                            $
                            <?= number_format((float) $acc['balance'], 2) ?>
                        </p>
                    </div>
                    <?php if ($isAdmin): ?>
                        <button onclick='openAccountModal(<?= json_encode($acc) ?>)'
                            class="text-mb-subtle hover:text-white transition-colors mt-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Ledger Table ──────────────────────────────────── -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div
            class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between px-6 py-4 border-b border-mb-subtle/10">
            <h2 class="text-white font-light">Ledger</h2>
            <div class="flex flex-wrap gap-2 items-center">
                <!-- Filters -->
                <form method="GET" class="flex flex-wrap gap-2 items-center">
                    <select name="type" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Types</option>
                        <option value="income" <?= $fType === 'income' ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= $fType === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select>
                    <select name="source" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Sources</option>
                        <option value="reservation" <?= $fSource === 'reservation' ? 'selected' : '' ?>>Reservations
                        </option>
                        <option value="manual" <?= $fSource === 'manual' ? 'selected' : '' ?>>Manual</option>
                    </select>
                    <input type="date" name="date_from" value="<?= e($fDateFrom) ?>"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-mb-accent">
                    <input type="date" name="date_to" value="<?= e($fDateTo) ?>"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-mb-accent">
                    <select name="account" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Accounts</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>" <?= $fAccount == $acc['id'] ? 'selected' : '' ?>>
                                <?= e($acc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"
                        class="bg-mb-accent/15 text-mb-accent border border-mb-accent/30 px-3 py-1.5 rounded-lg text-xs hover:bg-mb-accent/25 transition-colors">
                        Apply
                    </button>
                </form>
                <!-- Add buttons -->
                <button onclick="openEntryModal('income')"
                    class="text-xs bg-green-500/10 text-green-400 border border-green-500/20 px-3 py-1.5 rounded-full hover:bg-green-500/20 transition-colors whitespace-nowrap">+
                    Income</button>
                <button onclick="openEntryModal('expense')"
                    class="text-xs bg-red-500/10 text-red-400 border border-red-500/20 px-3 py-1.5 rounded-full hover:bg-red-500/20 transition-colors whitespace-nowrap">+
                    Expense</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-mb-black text-mb-subtle uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3">Description</th>
                        <th class="px-6 py-3">Mode</th>
                        <th class="px-6 py-3">Account</th>
                        <th class="px-6 py-3">Source</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-mb-subtle italic">No entries for this period.
                            </td>
                        </tr>
                    <?php else:
                        foreach ($entries as $row):
                            $isIncome = $row['txn_type'] === 'income';
                            $isManual = $row['source_type'] === 'manual';
                            $amtColor = $isIncome ? 'text-green-400' : 'text-red-400';
                            $typeLabel = strtoupper($row['txn_type']);
                            $typeBg = $isIncome ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400';
                            ?>
                            <tr class="hover:bg-mb-black/30 transition-colors group">
                                <td class="px-6 py-3 text-mb-silver whitespace-nowrap">
                                    <?= date('d M Y', strtotime($row['posted_at'])) ?>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="<?= $typeBg ?> text-xs px-2 py-0.5 rounded-full font-medium">
                                        <?= $typeLabel ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-white">
                                    <?= e($row['category']) ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle max-w-xs truncate">
                                    <?= e($row['description'] ?? '') ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle capitalize">
                                    <?= e($row['payment_mode'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle">
                                    <?= e($row['account_name'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-3">
                                    <?php if ($isManual): ?>
                                        <span class="text-xs text-mb-subtle">Manual</span>
                                    <?php elseif ($row['source_type'] === 'reservation'): ?>
                                        <a href="<?= $root ?>reservations/show.php?id=<?= $row['source_id'] ?>"
                                            class="text-xs text-mb-accent hover:underline">Res #
                                            <?= $row['source_id'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-mb-subtle">
                                            <?= e($row['source_type']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-right <?= $amtColor ?> font-medium whitespace-nowrap">
                                    <?= $isIncome ? '+' : '-' ?>$
                                    <?= number_format((float) $row['amount'], 2) ?>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <?php if ($isManual && $isAdmin): ?>
                                        <form method="POST"
                                            onsubmit="return confirm('Delete this entry and reverse bank balance?')">
                                            <input type="hidden" name="action" value="delete_entry">
                                            <input type="hidden" name="entry_id" value="<?= $row['id'] ?>">
                                            <button type="submit"
                                                class="opacity-0 group-hover:opacity-100 text-mb-subtle hover:text-red-400 transition-all">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-mb-subtle/30 text-xs" title="System entry — cannot delete">🔒</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals footer -->
        <?php if (!empty($entries)): ?>
            <div class="flex justify-end gap-8 px-6 py-4 border-t border-mb-subtle/10 text-sm">
                <span class="text-green-400">Income: +$
                    <?= number_format(array_sum(array_map(fn($r) => $r['txn_type'] === 'income' ? (float) $r['amount'] : 0, $entries)), 2) ?>
                </span>
                <span class="text-red-400">Expenses: -$
                    <?= number_format(array_sum(array_map(fn($r) => $r['txn_type'] === 'expense' ? (float) $r['amount'] : 0, $entries)), 2) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Add Income/Expense Modal ──────────────────────────────────────── -->
<div id="entryModal" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
        <h3 id="entryModalTitle" class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Add Entry</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" id="entryAction" value="add_income">
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Category <span class="text-red-400">*</span></label>
                <input type="text" name="category" required placeholder="e.g. Fuel, Rent, Salary…"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Amount ($) <span
                            class="text-red-400">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Date</label>
                    <input type="date" name="posted_at" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Payment Mode</label>
                <select name="payment_mode" id="entryPaymentMode" onchange="toggleEntryBankField()"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <option value="cash">Cash</option>
                    <option value="account">Account (Bank)</option>
                    <option value="credit">Credit</option>
                </select>
            </div>
            <div id="entryBankWrap" class="hidden">
                <label class="block text-sm text-mb-silver mb-1.5">Bank Account <span class="text-red-400">*</span></label>
                <select name="bank_account_id" id="entryBankAccount"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <option value="">Select bank account</option>
                    <?php foreach ($activeAccounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?>
                            <?= !empty($acc['bank_name']) ? ' - ' . e($acc['bank_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($activeAccounts)): ?>
                    <p class="text-xs text-red-400 mt-1">No active bank account exists. Create one before using account mode.
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Description</label>
                <textarea name="description" rows="2" placeholder="Optional notes…"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('entryModal').classList.add('hidden')"
                    class="text-mb-silver hover:text-white text-sm px-4 py-2">Cancel</button>
                <button type="submit" id="entrySubmitBtn"
                    class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Bank Account Modal ───────────────────────────────────────────── -->
<div id="accountModal" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
        <h3 id="accountModalTitle" class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Account</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_account">
            <input type="hidden" name="account_id" id="accountId" value="0">
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Account Name <span
                        class="text-red-400">*</span></label>
                <input type="text" name="name" id="accountName" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Bank Name</label>
                <input type="text" name="bank_name" id="accountBankName" placeholder="Optional"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Account Number</label>
                <input type="text" name="account_number" id="accountNumber" placeholder="Optional"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('accountModal').classList.add('hidden')"
                    class="text-mb-silver hover:text-white text-sm px-4 py-2">Cancel</button>
                <button type="submit"
                    class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleEntryBankField() {
        const modeEl = document.getElementById('entryPaymentMode');
        const wrapEl = document.getElementById('entryBankWrap');
        const bankEl = document.getElementById('entryBankAccount');
        if (!modeEl || !wrapEl || !bankEl) return;

        const isAccountMode = modeEl.value === 'account';
        wrapEl.classList.toggle('hidden', !isAccountMode);
        if (isAccountMode) {
            bankEl.setAttribute('required', 'required');
        } else {
            bankEl.removeAttribute('required');
            bankEl.value = '';
        }
    }

    function openEntryModal(type) {
        const modal = document.getElementById('entryModal');
        const title = document.getElementById('entryModalTitle');
        const action = document.getElementById('entryAction');
        const btn = document.getElementById('entrySubmitBtn');
        if (type === 'income') {
            title.textContent = 'Add Income';
            title.className = 'text-white text-lg font-light border-l-2 border-green-500 pl-3';
            action.value = 'add_income';
            btn.className = 'bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-500 transition-colors text-sm font-medium';
        } else {
            title.textContent = 'Add Expense';
            title.className = 'text-white text-lg font-light border-l-2 border-red-500 pl-3';
            action.value = 'add_expense';
            btn.className = 'bg-red-600 text-white px-6 py-2 rounded-full hover:bg-red-500 transition-colors text-sm font-medium';
        }
        const modeEl = document.getElementById('entryPaymentMode');
        if (modeEl) {
            modeEl.value = 'cash';
        }
        toggleEntryBankField();
        modal.classList.remove('hidden');
    }

    function openAccountModal(acc) {
        const modal = document.getElementById('accountModal');
        document.getElementById('accountModalTitle').textContent = acc ? 'Edit Account' : 'New Account';
        document.getElementById('accountId').value = acc ? acc.id : 0;
        document.getElementById('accountName').value = acc ? acc.name : '';
        document.getElementById('accountBankName').value = acc ? (acc.bank_name || '') : '';
        document.getElementById('accountNumber').value = acc ? (acc.account_number || '') : '';
        modal.classList.remove('hidden');
    }

    // Close modals on backdrop click
    ['entryModal', 'accountModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });
    toggleEntryBankField();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
