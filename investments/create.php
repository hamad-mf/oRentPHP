<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
auth_require_admin();
$pdo = db();
ledger_ensure_schema($pdo);

$bankAccounts = $pdo->query("SELECT id, name, balance FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $lender = trim($_POST['lender'] ?? '');
    $totalCost = (float) ($_POST['total_cost'] ?? 0);
    $downPayment = (float) ($_POST['down_payment'] ?? 0);
    $emiAmount = (float) ($_POST['emi_amount'] ?? 0);
    $tenureMonths = (int) ($_POST['tenure_months'] ?? 0);
    $startDate = trim($_POST['start_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $dpAccountId = (int) ($_POST['dp_account_id'] ?? 0);

    if (!$title)
        $errors['title'] = 'Title is required.';
    if ($totalCost <= 0)
        $errors['total_cost'] = 'Total cost must be greater than 0.';
    if ($emiAmount <= 0)
        $errors['emi_amount'] = 'EMI amount must be greater than 0.';
    if ($tenureMonths <= 0)
        $errors['tenure_months'] = 'Tenure must be at least 1 month.';
    if (!$startDate)
        $errors['start_date'] = 'Start date is required.';
    if ($downPayment > 0 && !$dpAccountId)
        $errors['dp_account_id'] = 'Select the bank account for down payment.';

    $loanAmount = max(0, $totalCost - $downPayment);

    // Step 1: Insert investment + schedule in one transaction
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO emi_investments
                (title, lender, total_cost, down_payment, loan_amount, emi_amount, tenure_months, start_date, notes)
                VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$title, $lender ?: null, $totalCost, $downPayment, $loanAmount, $emiAmount, $tenureMonths, $startDate, $notes ?: null]);
        $investmentId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO emi_schedules (investment_id, installment_no, due_date, amount) VALUES (?,?,?,?)");
        $baseDate = new DateTime($startDate);
        for ($i = 1; $i <= $tenureMonths; $i++) {
            $dueDate = clone $baseDate;
            $dueDate->modify('+' . ($i - 1) . ' months');
            $stmt->execute([$investmentId, $i, $dueDate->format('Y-m-d'), $emiAmount]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $errors['general'] = 'Could not save investment: ' . $e->getMessage();
    }

    // Step 2: Record down payment ledger entry (ledger_post manages its own transaction)
    if (empty($errors) && $downPayment > 0 && $dpAccountId) {
        $dpLedgerId = ledger_post(
            $pdo,
            'expense',
            'Investment Down Payment',
            $downPayment,
            'account',
            $dpAccountId,
            'emi_investment',
            $investmentId,
            'down_payment',
            "Down payment for: $title",
            (int) ($_SESSION['user']['id'] ?? 0),
            "inv_dp_{$investmentId}",
            date('Y-m-d H:i:s')
        );
        if ($dpLedgerId) {
            $pdo->prepare("UPDATE emi_investments SET down_payment_account_id=?, down_payment_ledger_id=? WHERE id=?")
                ->execute([$dpAccountId, $dpLedgerId, $investmentId]);
        }
    }

    if (empty($errors)) {
        flash('success', "Investment \"$title\" created with $tenureMonths EMI installments.");
        redirect('show.php?id=' . $investmentId);
    }
}

$pageTitle = 'Add Investment';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Investments</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Add Investment</span>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-red-400 text-sm">
            <?= e($errors['general']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Basic Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Investment Details</h3>

            <div>
                <label class="block text-sm text-mb-silver mb-2">Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>"
                    placeholder="e.g. Toyota Camry 2026"
                    class="w-full bg-mb-black border <?= isset($errors['title']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                <?php if (isset($errors['title'])): ?>
                    <p class="text-red-400 text-xs mt-1">
                        <?= e($errors['title']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-2">Lender / Bank <span
                        class="text-mb-subtle text-xs">(optional)</span></label>
                <input type="text" name="lender" value="<?= e($_POST['lender'] ?? '') ?>"
                    placeholder="e.g. Al Rajhi Bank"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Total Cost <span
                            class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-mb-subtle text-sm">$</span>
                        <input type="number" name="total_cost" value="<?= e($_POST['total_cost'] ?? '') ?>"
                            placeholder="0.00" step="0.01" min="0"
                            class="w-full bg-mb-black border <?= isset($errors['total_cost']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg pl-7 pr-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors"
                            oninput="calcLoan()">
                    </div>
                    <?php if (isset($errors['total_cost'])): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['total_cost']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Down Payment</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-mb-subtle text-sm">$</span>
                        <input type="number" name="down_payment" id="downPayment"
                            value="<?= e($_POST['down_payment'] ?? '0') ?>" placeholder="0.00" step="0.01" min="0"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors"
                            oninput="calcLoan()">
                    </div>
                </div>
            </div>

            <!-- Loan amount display -->
            <div class="bg-mb-black/40 rounded-lg px-4 py-3 flex items-center justify-between">
                <span class="text-mb-subtle text-sm">Loan Amount (Total − Down Payment)</span>
                <span class="text-mb-accent font-semibold text-lg" id="loanDisplay">$0.00</span>
            </div>

            <!-- Down payment bank account (shown when down payment > 0) -->
            <div id="dpAccountWrap" class="<?= (float) ($_POST['down_payment'] ?? 0) > 0 ? '' : 'hidden' ?>">
                <label class="block text-sm text-mb-silver mb-2">Pay Down Payment From <span
                        class="text-red-400">*</span></label>
                <select name="dp_account_id"
                    class="w-full bg-mb-black border <?= isset($errors['dp_account_id']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value="">— Select bank account —</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= $ba['id'] ?>" <?= ($_POST['dp_account_id'] ?? '') == $ba['id'] ? 'selected' : '' ?>>
                            <?= e($ba['name']) ?> — $
                            <?= number_format($ba['balance'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['dp_account_id'])): ?>
                    <p class="text-red-400 text-xs mt-1">
                        <?= e($errors['dp_account_id']) ?>
                    </p>
                <?php endif; ?>
                <p class="text-xs text-mb-subtle mt-1">Down payment will be recorded as a ledger expense immediately.
                </p>
            </div>
        </div>

        <!-- EMI Details -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">EMI Schedule</h3>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Monthly EMI <span
                            class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-mb-subtle text-sm">$</span>
                        <input type="number" name="emi_amount" value="<?= e($_POST['emi_amount'] ?? '') ?>"
                            placeholder="0.00" step="0.01" min="0"
                            class="w-full bg-mb-black border <?= isset($errors['emi_amount']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg pl-7 pr-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                    </div>
                    <?php if (isset($errors['emi_amount'])): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['emi_amount']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Tenure (months) <span
                            class="text-red-400">*</span></label>
                    <input type="number" name="tenure_months" value="<?= e($_POST['tenure_months'] ?? '') ?>"
                        placeholder="e.g. 36" min="1" max="360"
                        class="w-full bg-mb-black border <?= isset($errors['tenure_months']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                    <?php if (isset($errors['tenure_months'])): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['tenure_months']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">First EMI Date <span
                            class="text-red-400">*</span></label>
                    <input type="date" name="start_date" value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>"
                        class="w-full bg-mb-black border <?= isset($errors['start_date']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                    <?php if (isset($errors['start_date'])): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['start_date']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes <span
                        class="text-mb-subtle text-xs">(optional)</span></label>
                <textarea name="notes" rows="2" placeholder="Additional notes..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors resize-none"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Create Investment
            </button>
        </div>
    </form>
</div>

<script>
    function calcLoan() {
        const total = parseFloat(document.querySelector('[name=total_cost]').value) || 0;
        const down = parseFloat(document.getElementById('downPayment').value) || 0;
        const loan = Math.max(0, total - down);
        document.getElementById('loanDisplay').textContent = '$' + loan.toLocaleString('en', { minimumFractionDigits: 2 });
        document.getElementById('dpAccountWrap').classList.toggle('hidden', down <= 0);
    }
    calcLoan();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>