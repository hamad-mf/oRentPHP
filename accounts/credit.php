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
$userId  = (int) ($_currentUser['id'] ?? 0);
$includeVoided = (string)($_GET['include_voided'] ?? '') === '1';

// Handle prioritize_income URL parameter and preference
if (isset($_GET['prioritize_income_submitted'])) {
    // Form was submitted, check if checkbox was checked
    $prioritizeIncome = isset($_GET['prioritize_income']) && $_GET['prioritize_income'] === '1';
    set_credit_prioritize_income($pdo, $prioritizeIncome);
} else {
    // No form submission, load saved preference
    $prioritizeIncome = get_credit_prioritize_income($pdo);
}

$accounts = ledger_get_accounts($pdo);
$activeAccounts = array_values(array_filter($accounts, static function ($account) {
    return (int) ($account['is_active'] ?? 0) === 1;
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'add_payment') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $receiveMode = trim((string) ($_POST['receive_mode'] ?? 'cash'));
        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $note = trim((string) ($_POST['description'] ?? ''));

        $allowedModes = ['cash', 'account'];
        if (!in_array($receiveMode, $allowedModes, true)) {
            flash('error', 'Invalid payment mode selected.');
            redirect('credit.php');
        }
        if ($amount <= 0) {
            flash('error', 'Payment amount must be greater than zero.');
            redirect('credit.php');
        }

        $resolvedBankAccountId = null;
        if ($receiveMode === 'account') {
            $resolvedBankAccountId = ledger_get_active_bank_account_id($pdo, $bankAccountId > 0 ? $bankAccountId : null);
            if ($resolvedBankAccountId === null) {
                flash('error', 'Please select an active bank account.');
                redirect('credit.php');
            }
        }

        $modeLabel = $receiveMode === 'account' ? 'Bank' : 'Cash';
        $extraText = $note !== '' ? (' - ' . $note) : '';
        $creditDescription = 'Credit payment settled via ' . $modeLabel . $extraText;
        $receiptDescription = 'Payment received against credit via ' . $modeLabel . $extraText;
        $postedAt = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            $creditIncomeNow = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
            $creditExpenseNow = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
            $availableCredit = $creditIncomeNow - $creditExpenseNow;

            if ($availableCredit <= 0) {
                throw new RuntimeException('No outstanding credit balance is available for payment.');
            }
            if ($amount > ($availableCredit + 0.00001)) {
                throw new RuntimeException('Amount cannot be greater than current credit balance of $' . number_format($availableCredit, 2) . '.');
            }

            $insertStmt = $pdo->prepare("INSERT INTO ledger_entries
                (txn_type, category, description, amount, payment_mode, bank_account_id,
                 source_type, source_id, source_event, posted_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Reduce outstanding credit.
            $insertStmt->execute([
                'expense',
                'Credit Payment Settled',
                $creditDescription,
                $amount,
                'credit',
                null,
                'manual',
                null,
                'credit_payment_settlement',
                $postedAt,
                $userId > 0 ? $userId : null,
            ]);

            // Add received funds to cash/bank stream.
            $insertStmt->execute([
                'income',
                'Credit Payment Received',
                $receiptDescription,
                $amount,
                $receiveMode,
                $resolvedBankAccountId,
                'manual',
                null,
                'credit_payment_received',
                $postedAt,
                $userId > 0 ? $userId : null,
            ]);

            if ($resolvedBankAccountId !== null) {
                $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")
                    ->execute([$amount, $resolvedBankAccountId]);
            }

            $pdo->commit();
            app_log('ACTION', "Ledger: credit payment settled amount=$amount mode=$receiveMode user_id=$userId");
            flash('success', 'Payment added successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log('ERROR', 'Credit payment add failed: ' . $e->getMessage());
            $errorMessage = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Could not add payment. Please try again.';
            flash('error', $errorMessage);
        }

        redirect('credit.php');
    }
}

$creditIncome  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
$creditExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
$creditBalance = $creditIncome - $creditExpense;
$creditClients = (int) $pdo->query("SELECT COUNT(DISTINCT c.id) FROM ledger_entries le JOIN reservations r ON le.source_type='reservation' AND le.source_id=r.id JOIN clients c ON c.id=r.client_id WHERE le.payment_mode='credit' AND le.voided_at IS NULL")->fetchColumn();
$creditBalanceLimit = max(0, $creditBalance);
$canAddPayment = $creditBalanceLimit > 0;

$voidFilter = $includeVoided ? '' : ' AND le.voided_at IS NULL';
$baseSql = " FROM ledger_entries le LEFT JOIN reservations r ON le.source_type='reservation' AND le.source_id = r.id LEFT JOIN clients c ON c.id = r.client_id LEFT JOIN users u ON u.id = le.created_by WHERE le.payment_mode = 'credit'{$voidFilter}";

// Conditional ORDER BY clause based on prioritization toggle
$orderClause = $prioritizeIncome 
    ? " ORDER BY CASE WHEN le.txn_type = 'income' THEN 0 ELSE 1 END, le.posted_at DESC, le.id DESC"
    : " ORDER BY le.id DESC, le.posted_at DESC";

$selectSql = "SELECT le.*, r.id AS res_id, r.status AS res_status, c.name AS client_name, c.id AS client_id, c.phone AS client_phone, u.name AS posted_by_name" . $baseSql . $orderClause;
$countSql  = "SELECT COUNT(*)" . $baseSql;
$pg = paginate_query($pdo, $selectSql, $countSql, [], $page, $perPage);
$entries = $pg['rows'];

$success = getFlash('success');
$error = getFlash('error');
$pageTitle = 'Credit Transactions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-[90rem] mx-auto">
    <?php if ($success): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <a href="index.php" class="inline-flex items-center gap-2 text-mb-subtle hover:text-white transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
        Back to Accounts
    </a>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Credit Income</p>
            <p class="text-xl font-light text-amber-400">$<?= number_format($creditIncome, 2) ?></p>
        </div>
        <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Credit Expenses</p>
            <p class="text-xl font-light text-red-400">$<?= number_format($creditExpense, 2) ?></p>
        </div>
        <div class="bg-mb-accent/5 border border-mb-accent/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Net Credit</p>
            <p class="text-xl font-light <?= $creditBalance >= 0 ? 'text-amber-400' : 'text-red-400' ?>">$<?= number_format($creditBalance, 2) ?></p>
        </div>
        <div class="bg-purple-500/5 border border-purple-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Clients on Credit</p>
            <p class="text-xl font-light text-purple-400"><?= $creditClients ?></p>
        </div>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-mb-subtle/10">
            <h2 class="text-white font-light">Credit Transactions</h2>
            <div class="flex items-center gap-3">
                <span class="text-xs text-mb-subtle"><?= $pg['total'] ?> entries</span>
                <form method="GET" class="inline-flex items-center gap-2">
                    <label class="inline-flex items-center gap-2 text-xs text-mb-subtle">
                        <input type="checkbox" name="include_voided" value="1" <?= $includeVoided ? 'checked' : '' ?>
                            onchange="this.form.submit()"
                            class="accent-mb-accent w-4 h-4 rounded border border-mb-subtle/30 bg-mb-black">
                        Show voided
                    </label>
                </form>
                <form method="GET" class="inline-flex items-center gap-2">
                    <input type="hidden" name="prioritize_income_submitted" value="1">
                    <label class="inline-flex items-center gap-2 text-xs text-mb-subtle">
                        <input type="checkbox" name="prioritize_income" value="1" <?= $prioritizeIncome ? 'checked' : '' ?>
                            onchange="this.form.submit()"
                            class="accent-mb-accent w-4 h-4 rounded border border-mb-subtle/30 bg-mb-black">
                        Prioritize income
                    </label>
                    <?php if ($includeVoided): ?>
                        <input type="hidden" name="include_voided" value="1">
                    <?php endif; ?>
                </form>
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
                        <th class="px-6 py-3">Reservation</th>
                        <th class="px-6 py-3">Event</th>
                        <th class="px-6 py-3">Description</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="9" class="px-6 py-12 text-center text-mb-subtle italic">No credit transactions found.</td></tr>
                    <?php else:
                        foreach ($entries as $row):
                            $isIncome = $row['txn_type'] === 'income';
                            $isVoided = !empty($row['voided_at']);
                            $rowAmount = (float) $row['amount'];
                            $prefillAmount = min($creditBalanceLimit, $rowAmount);
                            $canRowSettle = $canAddPayment && $isIncome && $prefillAmount > 0 && !$isVoided;
                            $amtColor = $isIncome ? 'text-amber-400' : 'text-red-400';
                            $typeBg   = $isIncome ? 'bg-amber-500/10 text-amber-400' : 'bg-red-500/10 text-red-400';
                    ?>
                        <tr class="hover:bg-mb-black/30 transition-colors<?= $isVoided ? ' opacity-60' : '' ?>">
                            <td class="px-6 py-3 text-mb-silver whitespace-nowrap"><?= date('d M Y', strtotime($row['posted_at'])) ?></td>
                            <td class="px-6 py-3">
                                <span class="<?= $typeBg ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= strtoupper($row['txn_type']) ?></span>
                                <?php if ($isVoided): ?>
                                    <span class="ml-2 bg-red-500/15 text-red-400 text-[10px] px-2 py-0.5 rounded-full font-medium">VOID</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 <?= $isVoided ? 'text-mb-subtle line-through' : 'text-white' ?>"><?= e($row['category']) ?></td>
                            <td class="px-6 py-3"><?php if (!empty($row['client_name'])): ?><a href="../clients/show.php?id=<?= (int)$row['client_id'] ?>" class="text-mb-accent hover:underline text-sm"><?= e($row['client_name']) ?></a><?php if (!empty($row['client_phone'])): ?><p class="text-[10px] text-mb-subtle"><?= e($row['client_phone']) ?></p><?php endif; ?><?php else: ?><span class="text-mb-subtle">&mdash;</span><?php endif; ?></td>
                            <td class="px-6 py-3"><?php if ($row['source_type'] === 'reservation' && !empty($row['res_id'])): ?><a href="../reservations/show.php?id=<?= (int)$row['res_id'] ?>" class="text-xs text-mb-accent hover:underline">Res #<?= (int)$row['res_id'] ?></a><?php if (!empty($row['res_status'])): ?> <span class="text-[10px] text-mb-subtle capitalize">(<?= e($row['res_status']) ?>)</span><?php endif; ?><?php elseif ($row['source_type'] === 'manual'): ?><span class="text-xs text-mb-subtle">Manual</span><?php else: ?><span class="text-xs text-mb-subtle capitalize"><?= e($row['source_type']) ?></span><?php endif; ?></td>
                            <td class="px-6 py-3"><?php if (!empty($row['source_event'])): ?><span class="text-xs text-mb-silver capitalize bg-mb-black/50 px-2 py-0.5 rounded"><?= e($row['source_event']) ?></span><?php else: ?><span class="text-mb-subtle">&mdash;</span><?php endif; ?></td>
                            <td class="px-6 py-3 text-mb-subtle max-w-xs truncate">
                                <?= e($row['description'] ?? '') ?>
                                <?php if ($isVoided): ?>
                                    <div class="text-xs text-red-400 mt-1">
                                        Voided <?= date('d M Y H:i', strtotime($row['voided_at'])) ?>
                                        <?= !empty($row['void_reason']) ? ' — ' . e($row['void_reason']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right <?= $amtColor ?> font-medium whitespace-nowrap<?= $isVoided ? ' line-through' : '' ?>"><?= $isIncome ? '+' : '-' ?>$<?= number_format($rowAmount, 2) ?></td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <?php if ($canRowSettle): ?>
                                    <button type="button"
                                        onclick="openCreditPaymentModal(<?= json_encode(round($prefillAmount, 2)) ?>)"
                                        class="text-xs px-3 py-1.5 rounded-full border bg-mb-accent/15 text-mb-accent border-mb-accent/30 hover:bg-mb-accent/25 transition-colors">
                                        Add Payment
                                    </button>
                                <?php else: ?>
                                    <span class="text-mb-subtle">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$paginationParams = [];
if ($includeVoided) {
    $paginationParams['include_voided'] = '1';
}
if ($prioritizeIncome) {
    $paginationParams['prioritize_income'] = '1';
}
echo render_pagination($pg, $paginationParams);
?>

<div id="creditPaymentModal" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h3 class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Add Payment</h3>
            <button type="button" onclick="closeCreditPaymentModal()" class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" class="space-y-4" id="creditPaymentForm">
            <input type="hidden" name="action" value="add_payment">
            <input type="hidden" id="creditPaymentMax" value="<?= number_format($creditBalanceLimit, 2, '.', '') ?>">

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Amount ($) <span class="text-red-400">*</span></label>
                <input type="number"
                       id="creditPaymentAmount"
                       name="amount"
                       step="0.01"
                       min="0.01"
                       max="<?= number_format($creditBalanceLimit, 2, '.', '') ?>"
                       required
                       placeholder="0.00"
                       class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                <p class="text-xs text-mb-subtle mt-1">
                    Maximum allowed: $<?= number_format($creditBalanceLimit, 2) ?>
                </p>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Receive In <span class="text-red-400">*</span></label>
                <select name="receive_mode" id="creditPaymentMode" onchange="toggleCreditPaymentBankField()"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <option value="cash">Cash</option>
                    <option value="account">Bank</option>
                </select>
            </div>

            <div id="creditPaymentBankWrap" class="hidden">
                <label class="block text-sm text-mb-silver mb-1.5">Bank Account <span class="text-red-400">*</span></label>
                <select name="bank_account_id"
                        id="creditPaymentBankAccount"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <option value="">Select bank account</option>
                    <?php foreach ($activeAccounts as $acc): ?>
                        <option value="<?= (int) $acc['id'] ?>">
                            <?= e($acc['name']) ?>
                            <?= !empty($acc['bank_name']) ? (' - ' . e($acc['bank_name'])) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($activeAccounts)): ?>
                    <p class="text-xs text-red-400 mt-1">No active bank account found. Add one in Accounts first.</p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Notes</label>
                <textarea name="description" rows="2" placeholder="Optional"
                          class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm resize-none"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeCreditPaymentModal()" class="text-mb-silver hover:text-white text-sm px-4 py-2">
                    Cancel
                </button>
                <button type="submit"
                        class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium"
                        <?= $canAddPayment ? '' : 'disabled' ?>>
                    Save Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCreditPaymentBankField() {
    const modeEl = document.getElementById('creditPaymentMode');
    const wrapEl = document.getElementById('creditPaymentBankWrap');
    const bankEl = document.getElementById('creditPaymentBankAccount');
    if (!modeEl || !wrapEl || !bankEl) return;

    const isBankMode = modeEl.value === 'account';
    wrapEl.classList.toggle('hidden', !isBankMode);
    if (isBankMode) {
        bankEl.setAttribute('required', 'required');
    } else {
        bankEl.removeAttribute('required');
        bankEl.value = '';
    }
}

function validateCreditPaymentAmount() {
    const amountEl = document.getElementById('creditPaymentAmount');
    const maxEl = document.getElementById('creditPaymentMax');
    if (!amountEl || !maxEl) return;

    const maxAmount = parseFloat(maxEl.value || '0');
    const enteredAmount = parseFloat(amountEl.value || '0');
    if (enteredAmount > maxAmount) {
        amountEl.setCustomValidity('Amount cannot exceed $' + maxAmount.toFixed(2));
    } else {
        amountEl.setCustomValidity('');
    }
}

function openCreditPaymentModal(prefillAmount) {
    const modal = document.getElementById('creditPaymentModal');
    const modeEl = document.getElementById('creditPaymentMode');
    const bankEl = document.getElementById('creditPaymentBankAccount');
    const amountEl = document.getElementById('creditPaymentAmount');
    const maxEl = document.getElementById('creditPaymentMax');
    const maxAmount = maxEl ? parseFloat(maxEl.value || '0') : 0;
    if (!modal || maxAmount <= 0) return;

    if (modeEl) modeEl.value = 'cash';
    if (bankEl) bankEl.value = '';
    if (amountEl) {
        const parsedPrefill = parseFloat(prefillAmount);
        if (!Number.isNaN(parsedPrefill) && parsedPrefill > 0) {
            const safePrefill = Math.min(parsedPrefill, maxAmount);
            amountEl.value = safePrefill.toFixed(2);
        } else {
            amountEl.value = '';
        }
        amountEl.setCustomValidity('');
    }
    validateCreditPaymentAmount();
    toggleCreditPaymentBankField();
    modal.classList.remove('hidden');
}

function closeCreditPaymentModal() {
    const modal = document.getElementById('creditPaymentModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

(function () {
    const amountEl = document.getElementById('creditPaymentAmount');
    if (amountEl) {
        amountEl.addEventListener('input', validateCreditPaymentAmount);
        amountEl.addEventListener('change', validateCreditPaymentAmount);
    }

    const modal = document.getElementById('creditPaymentModal');
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeCreditPaymentModal();
            }
        });
    }

    toggleCreditPaymentBankField();
})();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
