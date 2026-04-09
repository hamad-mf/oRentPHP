<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
// current_user() reads from $_SESSION  " must be called before header.php
// so that POST handlers have the correct user during form submissions
$_currentUser = current_user();
if (!auth_has_perm('view_finances') && ($_currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Access denied.');
    redirect('../index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));
ledger_ensure_schema($pdo);

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$userId = (int) ($_currentUser['id'] ?? 0);
$configuredExpenseCategories = expense_categories_get_list($pdo);

//  "  "  Handle POST actions  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  " 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    //  "  "  Add manual income / expense  "  " 
    if (in_array($action, ['add_income', 'add_expense'], true)) {
        $txnType = $action === 'add_income' ? 'income' : 'expense';
        $category = trim($_POST['category'] ?? '');
        if ($action === 'add_expense' && $category === '') {
            $category = $configuredExpenseCategories[0] ?? 'Manual Expense';
        }
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

    //  "  "  Delete manual entry  "  " 
    if ($action === 'delete_entry' && $isAdmin) {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        if (ledger_delete_manual_entry($pdo, $entryId, $userId)) {
            flash('success', 'Entry deleted.');
        } else {
            flash('error', 'Could not delete  " may be a system entry.');
        }
        redirect('index.php');
    }

    //  "  "  Void entry (soft)  "  "
    if ($action === 'void_entry' && $isAdmin) {
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $reason = trim($_POST['void_reason'] ?? '');
        if ($entryId <= 0 || $reason === '') {
            flash('error', 'Void reason is required.');
        } elseif (ledger_void_entry($pdo, $entryId, $userId, $reason)) {
            flash('success', 'Entry voided.');
        } else {
            flash('error', 'Could not void entry.');
        }
        redirect('index.php');
    }

    //  "  "  Save bank account (create or edit)  "  " 
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

    //  "  "  Transfer funds between accounts  "  " 
    if ($action === 'transfer_funds' && $isAdmin) {
        $fromId = (int) ($_POST['from_account_id'] ?? 0);
        $toId = (int) ($_POST['to_account_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $date = trim($_POST['posted_at'] ?? date('Y-m-d'));
        $postedAt = date('Y-m-d H:i:s', strtotime($date . ' ' . date('H:i:s')));
        $transferError = '';
        
        // Frontend validation checks (Requirements 4.1-4.6)
        
        // Validate amount > 0 (Requirement 4.3)
        if ($amount <= 0) {
            flash('error', 'Transfer amount must be greater than zero.');
            redirect('index.php');
        }
        
        // Validate source != destination (Requirements 4.1, 4.2)
        if ($fromId === $toId) {
            flash('error', 'Cannot transfer to the same account.');
            redirect('index.php');
        }
        
        // Validate bank accounts exist and are active (Requirement 4.6)
        // For Cash-to-Bank: validate destination bank account
        if ($fromId === 0 && $toId > 0) {
            $stmt = $pdo->prepare("SELECT id, is_active FROM bank_accounts WHERE id = ? LIMIT 1");
            $stmt->execute([$toId]);
            $destAcct = $stmt->fetch();
            if (!$destAcct || !(int) $destAcct['is_active']) {
                flash('error', 'Destination account not found or inactive.');
                redirect('index.php');
            }
        }
        
        // For Bank-to-Cash: validate source bank account
        if ($fromId > 0 && $toId === 0) {
            $stmt = $pdo->prepare("SELECT id, balance, is_active FROM bank_accounts WHERE id = ? LIMIT 1");
            $stmt->execute([$fromId]);
            $sourceAcct = $stmt->fetch();
            if (!$sourceAcct || !(int) $sourceAcct['is_active']) {
                flash('error', 'Source account not found or inactive.');
                redirect('index.php');
            }
        }
        
        // For Bank-to-Bank: validate both accounts
        if ($fromId > 0 && $toId > 0) {
            $stmt = $pdo->prepare("SELECT id, balance, is_active FROM bank_accounts WHERE id IN (?, ?)");
            $stmt->execute([$fromId, $toId]);
            $accts = [];
            foreach ($stmt->fetchAll() as $row) {
                $accts[(int) $row['id']] = $row;
            }
            if (!isset($accts[$fromId]) || !(int) $accts[$fromId]['is_active']) {
                flash('error', 'Source account not found or inactive.');
                redirect('index.php');
            }
            if (!isset($accts[$toId]) || !(int) $accts[$toId]['is_active']) {
                flash('error', 'Destination account not found or inactive.');
                redirect('index.php');
            }
        }
        
        // Validate sufficient balance for source account (Requirements 4.4, 4.5)
        if ($fromId === 0) {
            // Cash-to-Bank: validate cash balance
            $cashIncome  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
            $cashExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
            $cashBalance = $cashIncome - $cashExpense;
            if ($cashBalance < $amount) {
                flash('error', 'Insufficient cash balance (Balance: $' . number_format($cashBalance, 2) . ').');
                redirect('index.php');
            }
        } elseif ($fromId > 0) {
            // Bank-to-Cash or Bank-to-Bank: validate bank balance
            $stmt = $pdo->prepare("SELECT balance FROM bank_accounts WHERE id = ? LIMIT 1");
            $stmt->execute([$fromId]);
            $sourceAcct = $stmt->fetch();
            if ($sourceAcct) {
                $bankBalance = (float) $sourceAcct['balance'];
                if ($bankBalance < $amount) {
                    flash('error', 'Insufficient balance in source account (Balance: $' . number_format($bankBalance, 2) . ').');
                    redirect('index.php');
                }
            }
        }
        
        // Route to appropriate transfer function based on account types
        // Cash Account is represented by ID = 0
        if ($fromId === 0 && $toId === 0) {
            // Cash-to-Cash: Invalid transfer (already validated above)
            flash('error', 'Cannot transfer to the same account.');
            redirect('index.php');
        } elseif ($fromId === 0) {
            // Cash-to-Bank: Use ledger_transfer_cash_to_bank()
            if (ledger_transfer_cash_to_bank($pdo, $toId, $amount, $desc ?: null, $userId, $postedAt, $transferError)) {
                flash('success', 'Transfer completed successfully.');
            } else {
                flash('error', $transferError ?: 'Transfer failed.');
            }
            redirect('index.php');
        } elseif ($toId === 0) {
            // Bank-to-Cash: Use ledger_transfer_bank_to_cash()
            if (ledger_transfer_bank_to_cash($pdo, $fromId, $amount, $desc ?: null, $userId, $postedAt, $transferError)) {
                flash('success', 'Transfer completed successfully.');
            } else {
                flash('error', $transferError ?: 'Transfer failed.');
            }
            redirect('index.php');
        } else {
            // Bank-to-Bank: Use existing ledger_transfer()
            if (ledger_transfer($pdo, $fromId, $toId, $amount, $desc ?: null, $userId, $postedAt, $transferError)) {
                flash('success', 'Transfer completed successfully.');
            } else {
                flash('error', $transferError ?: 'Transfer failed.');
            }
            redirect('index.php');
        }
    }
}


$fType = $_GET['type'] ?? '';
$fAccount = trim((string) ($_GET['account'] ?? ''));
$hasFilters = !empty($_GET);
$fDateFrom = $hasFilters ? trim((string)($_GET['date_from'] ?? '')) : date('Y-m-01');
$fDateTo = $hasFilters ? trim((string)($_GET['date_to'] ?? '')) : date('Y-m-d');
$fSource = $_GET['source'] ?? '';
$fCategory = trim($_GET['category'] ?? '');
$includeVoided = (string)($_GET['include_voided'] ?? '') === '1';

$accountFilter = null;
if ($fAccount === 'cash' || $fAccount === 'credit') {
    $accountFilter = $fAccount;
} elseif (ctype_digit($fAccount) && (int) $fAccount > 0) {
    $fAccount = (string) ((int) $fAccount);
    $accountFilter = (int) $fAccount;
}

$filters = [
    'type' => $fType,
    'account' => $accountFilter,
    'date_from' => $fDateFrom,
    'date_to' => $fDateTo,
    'source' => $fSource,
    'category' => $fCategory,
    'include_voided' => $includeVoided ? 1 : 0,
];

$accounts = ledger_get_accounts($pdo);
$activeAccounts = array_values(array_filter($accounts, fn($a) => (int) ($a['is_active'] ?? 0) === 1));
$systemExpenseCategories = [
    'Vehicle Expense',
    'Staff Advance',
    'Salary',
    'Reservation Cancellation Refund',
    'Security Deposit Returned',
    'Investment Down Payment',
    'EMI Payment',
    'Transfer Out',
];
$existingExpenseCategoriesStmt = $pdo->query("SELECT DISTINCT category FROM ledger_entries WHERE txn_type = 'expense' AND voided_at IS NULL ORDER BY category ASC");
$existingExpenseCategories = $existingExpenseCategoriesStmt ? array_map(static function ($row) {
    return trim((string) ($row['category'] ?? ''));
}, $existingExpenseCategoriesStmt->fetchAll()) : [];
$expenseCategoryOptions = array_values(array_filter(array_unique(array_merge($configuredExpenseCategories, $systemExpenseCategories, $existingExpenseCategories)), static function ($category) {
    return $category !== '';
}));
$_lq = ledger_build_query($filters);
$_pgLedger = paginate_query($pdo, $_lq['select'] . $_lq['base'] . $_lq['order'], $_lq['count'] . $_lq['base'], $_lq['params'], $page, $perPage);
$entries  = $_pgLedger['rows'];
usort($entries, static function (array $a, array $b): int {
    $aId = (int) ($a['id'] ?? 0);
    $bId = (int) ($b['id'] ?? 0);
    if ($aId !== $bId) {
        return $bId <=> $aId;
    }
    $aTs = strtotime((string) ($a['posted_at'] ?? '')) ?: 0;
    $bTs = strtotime((string) ($b['posted_at'] ?? '')) ?: 0;
    return $bTs <=> $aTs;
});
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

// Cash & Credit virtual balances (from ledger_entries.payment_mode)
$cashIncome  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
$cashExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
$cashBalance = $cashIncome - $cashExpense;
$creditIncome = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
$creditExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
$creditBalance = $creditIncome - $creditExpense;
$accountsTotal = (float) array_sum(array_column($accounts, 'balance'));
$overallTotal = $accountsTotal + $cashBalance + $creditBalance;

// Monthly 15th-to-15th calculations (IST)
$_istA = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$_dA=(int)$_istA->format('d'); $_mnA=(int)$_istA->format('n'); $_yrA=(int)$_istA->format('Y');
if($_dA>=15){ $accMPS=sprintf('%04d-%02d-15',$_yrA,$_mnA); $_n=$_mnA===12?1:$_mnA+1; $_y=$_mnA===12?$_yrA+1:$_yrA; $accMPE=sprintf('%04d-%02d-15',$_y,$_n); }
else{ $_p=$_mnA===1?12:$_mnA-1; $_y=$_mnA===1?$_yrA-1:$_yrA; $accMPS=sprintf('%04d-%02d-15',$_y,$_p); $accMPE=sprintf('%04d-%02d-15',$_yrA,$_mnA); }
$accPeriodLabel=date('d M',strtotime($accMPS)).' – '.date('d M',strtotime($accMPE));
$_mq=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
$_mq->execute([$accMPS,$accMPE]); $mCashI=(float)$_mq->fetchColumn();
$_mq=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
$_mq->execute([$accMPS,$accMPE]); $mCashE=(float)$_mq->fetchColumn();
$mCashBalance=$mCashI-$mCashE;
$_mq=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
$_mq->execute([$accMPS,$accMPE]); $mCreditI=(float)$_mq->fetchColumn();
$_mq=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
$_mq->execute([$accMPS,$accMPE]); $mCreditE=(float)$_mq->fetchColumn();
$mCreditBalance=$mCreditI-$mCreditE;

// Monthly top-card totals (income, expense, net, accounts total, overall)
$_mTotals = ledger_get_totals($pdo, $accMPS, $accMPE);
$mTotalIncome  = (float) $_mTotals['total_income'];
$mTotalExpense = (float) $_mTotals['total_expense'];
$mNetBalance   = $mTotalIncome - $mTotalExpense;
// accounts total doesn't change by period (running balance), but overall does
$mOverallTotal = $accountsTotal + $mCashBalance + $mCreditBalance;

$success = getFlash('success');
$error   = getFlash('error');
$pageTitle = 'Accounts & Ledger';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">

    <?php if ($success): ?><div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg><?= e($error) ?></div><?php endif; ?>

    <!--  "  "  Summary Cards  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  -->
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-1 bg-mb-black/60 border border-mb-subtle/20 rounded-full p-0.5">
            <button id="accViewMonthly" onclick="switchAccView('monthly')"
                class="text-xs px-3 py-1 rounded-full transition-colors font-medium bg-mb-accent text-white">
                Monthly
            </button>
            <button id="accViewAlltime" onclick="switchAccView('alltime')"
                class="text-xs px-3 py-1 rounded-full transition-colors font-medium text-mb-subtle hover:text-white">
                All-time
            </button>
        </div>
        <span id="accPeriodLabel" class="text-xs text-mb-subtle/70 tabular-nums"><?= e($accPeriodLabel) ?></span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Income -->
        <div class="bg-green-500/5 border border-green-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Income <span class="text-[10px] opacity-60">(period)</span></p>
            <p class="text-xl font-light text-green-400">
                <span class="acc-monthly"><?= '$' . number_format($mTotalIncome, 2) ?></span>
                <span class="acc-alltime hidden"><?= '$' . number_format($totalIncome, 2) ?></span>
            </p>
        </div>
        <!-- Expenses -->
        <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Expenses <span class="text-[10px] opacity-60">(period)</span></p>
            <p class="text-xl font-light text-red-400">
                <span class="acc-monthly"><?= '$' . number_format($mTotalExpense, 2) ?></span>
                <span class="acc-alltime hidden"><?= '$' . number_format($totalExpense, 2) ?></span>
            </p>
        </div>
        <!-- Net -->
        <div class="bg-mb-accent/5 border border-mb-accent/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Net <span class="text-[10px] opacity-60">(period)</span></p>
            <p class="text-xl font-light">
                <span class="acc-monthly <?= $mNetBalance >= 0 ? 'text-mb-accent' : 'text-red-400' ?>"><?= '$' . number_format($mNetBalance, 2) ?></span>
                <span class="acc-alltime hidden <?= $netBalance >= 0 ? 'text-mb-accent' : 'text-red-400' ?>"><?= '$' . number_format($netBalance, 2) ?></span>
            </p>
        </div>
        <!-- Accounts Total (always running balance, no period toggle) -->
        <div class="bg-yellow-500/5 border border-yellow-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Accounts Total <span class="text-[10px] opacity-60">(bank accounts)</span></p>
            <p class="text-xl font-light text-yellow-400"><?= '$' . number_format($accountsTotal, 2) ?></p>
        </div>
        <!-- Overall Total -->
        <div class="bg-cyan-500/5 border border-cyan-500/20 rounded-xl p-4">
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">Overall Total <span class="text-[10px] opacity-60">(accounts + cash + credit)</span></p>
            <p class="text-xl font-light text-cyan-300">
                <span class="acc-monthly"><?= '$' . number_format($mOverallTotal, 2) ?></span>
                <span class="acc-alltime hidden"><?= '$' . number_format($overallTotal, 2) ?></span>
            </p>
        </div>
    </div>

    <!--  "  "  Bank Accounts Row  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-mb-subtle/10">
            <h2 class="text-white font-light">Accounts</h2>
            <?php if ($isAdmin): ?>
            <div class="flex items-center gap-2">
                <button onclick="openTransferModal()"
                    class="text-xs bg-blue-500/15 text-blue-400 border border-blue-500/30 px-3 py-1.5 rounded-full hover:bg-blue-500/25 transition-colors flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transfer
                </button>
                <button onclick="openAccountModal(null)"
                    class="text-xs bg-mb-accent/15 text-mb-accent border border-mb-accent/30 px-3 py-1.5 rounded-full hover:bg-mb-accent/25 transition-colors">
                    + Add Account
                </button>
            </div>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 p-6">
            <!-- Bank Account Cards (always show real balance, no toggle needed) -->
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
                                <?= $acc['account_number'] ? '  " ' . e($acc['account_number']) : '' ?>
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

            <!-- Cash Account Card -->
            <a href="cash.php"
                class="bg-mb-black/40 border border-green-500/20 rounded-xl p-4 flex items-start justify-between gap-3 hover:border-green-500/40 transition-colors group cursor-pointer">
                <div>
                    <p class="text-white font-medium">Cash Account</p>
                    <p class="text-xs text-mb-subtle mt-0.5">All cash transactions</p>
                    <!-- Monthly value (default shown) -->
                    <p id="cashValMonthly"
                        class="acc-monthly text-lg font-light mt-2 <?= $mCashBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        $<?= number_format($mCashBalance, 2) ?>
                    </p>
                    <!-- All-time value (hidden by default) -->
                    <p id="cashValAlltime"
                        class="acc-alltime hidden text-lg font-light mt-2 <?= $cashBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        $<?= number_format($cashBalance, 2) ?>
                    </p>
                </div>
                <svg class="w-4 h-4 text-mb-subtle group-hover:text-green-400 transition-colors mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/>
                </svg>
            </a>

            <!-- Credit Account Card -->
            <a href="credit.php"
                class="bg-mb-black/40 border border-amber-500/20 rounded-xl p-4 flex items-start justify-between gap-3 hover:border-amber-500/40 transition-colors group cursor-pointer">
                <div>
                    <p class="text-white font-medium">Credit Account</p>
                    <p class="text-xs text-mb-subtle mt-0.5">Unpaid / credit transactions</p>
                    <!-- Monthly value (default shown) -->
                    <p id="creditValMonthly"
                        class="acc-monthly text-lg font-light mt-2 text-amber-400">
                        $<?= number_format($mCreditBalance, 2) ?>
                    </p>
                    <!-- All-time value (hidden by default) -->
                    <p id="creditValAlltime"
                        class="acc-alltime hidden text-lg font-light mt-2 text-amber-400">
                        $<?= number_format($creditBalance, 2) ?>
                    </p>
                </div>
                <svg class="w-4 h-4 text-mb-subtle group-hover:text-amber-400 transition-colors mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    <!--  "  "  Ledger Table  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <div class="flex items-center flex-wrap gap-3">
                <h2 class="text-white font-light leading-none m-0 p-0 shrink-0" style="margin:0;padding:0;line-height:1">Ledger</h2>
                <!-- Filters -->
                <form method="GET" id="ledgerFilterForm" class="flex flex-wrap gap-2 items-center ml-auto">
                    <select name="type" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 h-10 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Types</option>
                        <option value="income" <?= $fType === 'income' ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= $fType === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select>
                    <select name="source" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 h-10 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Sources</option>
                        <option value="reservation" <?= $fSource === 'reservation' ? 'selected' : '' ?>>Reservations</option>
                        <option value="manual" <?= $fSource === 'manual' ? 'selected' : '' ?>>Manual</option>
                    </select>
                    <select name="category" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 h-10 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Categories</option>
                        <?php foreach ($expenseCategoryOptions as $categoryOption): ?>
                            <option value="<?= e($categoryOption) ?>" <?= $fCategory === $categoryOption ? 'selected' : '' ?>>
                                <?= e($categoryOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" value="<?= e($fDateFrom) ?>"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 h-10 text-white text-xs focus:outline-none focus:border-mb-accent">
                    <input type="date" name="date_to" value="<?= e($fDateTo) ?>"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 h-10 text-white text-xs focus:outline-none focus:border-mb-accent">
                    <select name="account" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 h-10 text-white text-xs focus:outline-none focus:border-mb-accent">
                        <option value="">All Accounts</option>
                        <option value="cash" <?= $fAccount === 'cash' ? 'selected' : '' ?>>
                            Cash Account
                        </option>
                        <option value="credit" <?= $fAccount === 'credit' ? 'selected' : '' ?>>
                            Credit Account
                        </option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>" <?= $fAccount === (string) ((int) $acc['id']) ? 'selected' : '' ?>>
                                <?= e($acc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="inline-flex items-center gap-2 text-xs text-mb-subtle">
                        <input type="checkbox" name="include_voided" value="1" <?= $includeVoided ? 'checked' : '' ?>
                            onchange="this.form.submit()"
                            class="accent-mb-accent w-4 h-4 rounded border border-mb-subtle/30 bg-mb-black">
                        Show voided
                    </label>
                    <button type="submit"
                        class="bg-mb-accent/15 text-mb-accent border border-mb-accent/30 px-3 h-10 rounded-lg text-xs hover:bg-mb-accent/25 transition-colors">
                        Apply
                    </button>
                       <!-- Add buttons -->
                <button type="button" onclick="openEntryModal('income')"
                    class="text-xs bg-green-500/10 text-green-400 border border-green-500/20 px-4 h-10 rounded-full hover:bg-green-500/20 transition-colors whitespace-nowrap shrink-0">+ Income</button>
                <button type="button" onclick="openEntryModal('expense')"
                    class="text-xs bg-red-500/10 text-red-400 border border-red-500/20 px-4 h-10 rounded-full hover:bg-red-500/20 transition-colors whitespace-nowrap shrink-0">+ Expense</button>
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
                            $isVoided = !empty($row['voided_at']);
                            $isTransfer = ($row['source_type'] ?? '') === 'transfer'
                                || in_array((string) ($row['source_event'] ?? ''), ['transfer_out', 'transfer_in'], true);
                            $amtColor = $isIncome ? 'text-green-400' : 'text-red-400';
                            $typeLabel = strtoupper($row['txn_type']);
                            $typeBg = $isIncome ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400';
                            ?>
                            <tr class="hover:bg-mb-black/30 transition-colors group<?= $isVoided ? ' opacity-60' : '' ?>">
                                <td class="px-6 py-3 text-mb-silver whitespace-nowrap">
                                    <?= date('d M Y', strtotime($row['posted_at'])) ?>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="<?= $typeBg ?> text-xs px-2 py-0.5 rounded-full font-medium">
                                        <?= $typeLabel ?>
                                    </span>
                                    <?php if ($isVoided): ?>
                                        <span class="ml-2 bg-red-500/15 text-red-400 text-[10px] px-2 py-0.5 rounded-full font-medium">VOID</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 <?= $isVoided ? 'text-mb-subtle line-through' : 'text-white' ?>">
                                    <?= e($row['category']) ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle max-w-xs truncate">
                                    <?= e($row['description'] ?? '') ?>
                                    <?php if ($isVoided): ?>
                                        <div class="text-xs text-red-400 mt-1">
                                            Voided <?= date('d M Y H:i', strtotime($row['voided_at'])) ?>
                                            <?= !empty($row['void_reason']) ? ' — ' . e($row['void_reason']) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle capitalize">
                                    <?= e($row['payment_mode'] ?? ' "') ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle">
                                    <?= e($row['account_name'] ?? ' "') ?>
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
                                <td class="px-6 py-3 text-right <?= $amtColor ?> font-medium whitespace-nowrap<?= $isVoided ? ' line-through' : '' ?>">
                                    <?= $isIncome ? '+' : '-' ?>$
                                    <?= number_format((float) $row['amount'], 2) ?>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($isAdmin && !$isVoided && !$isTransfer): ?>
                                            <form method="POST" id="voidForm<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="action" value="void_entry">
                                                <input type="hidden" name="entry_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="void_reason" value="">
                                                <button type="button" onclick="promptVoid(<?= (int) $row['id'] ?>)"
                                                    class="opacity-0 group-hover:opacity-100 text-mb-subtle hover:text-yellow-400 transition-all"
                                                    title="Void entry">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                            d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($isManual && $isAdmin && !$isVoided): ?>
                                            <form method="POST"
                                                onsubmit="return confirm('Delete this entry and reverse bank balance?')">
                                                <input type="hidden" name="action" value="delete_entry">
                                                <input type="hidden" name="entry_id" value="<?= $row['id'] ?>">
                                                <button type="submit"
                                                    class="opacity-0 group-hover:opacity-100 text-mb-subtle hover:text-red-400 transition-all"
                                                    title="Delete manual entry">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php elseif ($isVoided): ?>
                                            <span class="text-mb-subtle/30 text-xs" title="Entry voided">—</span>
                                        <?php else: ?>
                                            <span class="text-mb-subtle/30 text-xs" title="System entry  — cannot delete">🔒</span>
                                        <?php endif; ?>
                                    </div>
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
                    <?= number_format(array_sum(array_map(fn($r) => ($r['txn_type'] === 'income' && empty($r['voided_at']) && !ledger_is_security_deposit_event($r['source_event'] ?? null)) ? (float) $r['amount'] : 0, $entries)), 2) ?>
                </span>
                <span class="text-red-400">Expenses: -$
                    <?= number_format(array_sum(array_map(fn($r) => ($r['txn_type'] === 'expense' && empty($r['voided_at']) && !ledger_is_security_deposit_event($r['source_event'] ?? null)) ? (float) $r['amount'] : 0, $entries)), 2) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $_aqp = array_filter(['type'=>$fType,'account'=>($fAccount!==''?$fAccount:null),'date_from'=>$fDateFrom,'date_to'=>$fDateTo,'source'=>$fSource,'category'=>$fCategory,'include_voided'=>($includeVoided ? '1' : null)], fn($v)=>$v!==null&&$v!=='');
    echo render_pagination($_pgLedger, $_aqp);
    ?>
</div>

<!--  "  "  Add Income/Expense Modal  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  -->
<div id="entryModal" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
        <h3 id="entryModalTitle" class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Add Entry</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" id="entryAction" value="add_income">
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Category <span class="text-red-400">*</span></label>
                <input type="text" id="entryCategoryText" name="category" required placeholder="e.g. Sales, Rent, Misc income"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                <select id="entryCategorySelect" name="category" disabled
                    class="hidden w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <?php foreach ($configuredExpenseCategories as $categoryOption): ?>
                        <option value="<?= e($categoryOption) ?>">
                            <?= e($categoryOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="entryCategoryHelp" class="hidden text-xs text-mb-subtle mt-1">
                    Configure this list in Settings > Expense Categories.
                </p>
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
                <label class="block text-sm text-mb-silver mb-1.5">Bank Account <span
                        class="text-red-400">*</span></label>
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
                    <p class="text-xs text-red-400 mt-1">No active bank account exists. Create one before using account
                        mode.
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Description</label>
                <textarea name="description" rows="2" placeholder="Optional notes ¦"
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

<!--  "  "  Bank Account Modal  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  "  -->
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
    // ── Accounts view toggle (Monthly / All-time) ──────────────────────────
    // Monthly period dates from PHP
    var _monthlyFrom = '<?= e($accMPS) ?>';
    var _monthlyTo   = '<?= e($accMPE) ?>';
    var _isInitialLoad = true;

    function switchAccView(v) {
        var isMonthly = v === 'monthly';

        // Toggle all monthly/alltime spans (summary cards + cash/credit cards)
        document.querySelectorAll('.acc-monthly').forEach(function(el) {
            el.classList.toggle('hidden', !isMonthly);
        });
        document.querySelectorAll('.acc-alltime').forEach(function(el) {
            el.classList.toggle('hidden', isMonthly);
        });

        // Period label visibility
        document.getElementById('accPeriodLabel').classList.toggle('hidden', !isMonthly);

        // Button active styles
        var btnM = document.getElementById('accViewMonthly');
        var btnA = document.getElementById('accViewAlltime');
        if (isMonthly) {
            btnM.className = 'text-xs px-3 py-1 rounded-full transition-colors font-medium bg-mb-accent text-white';
            btnA.className = 'text-xs px-3 py-1 rounded-full transition-colors font-medium text-mb-subtle hover:text-white';
        } else {
            btnA.className = 'text-xs px-3 py-1 rounded-full transition-colors font-medium bg-mb-accent text-white';
            btnM.className = 'text-xs px-3 py-1 rounded-full transition-colors font-medium text-mb-subtle hover:text-white';
        }

        // Update ledger date filters and resubmit (skip on initial page load)
        if (!_isInitialLoad) {
            var form = document.getElementById('ledgerFilterForm');
            if (form) {
                var dateFrom = form.querySelector('input[name="date_from"]');
                var dateTo   = form.querySelector('input[name="date_to"]');
                if (dateFrom && dateTo) {
                    if (isMonthly) {
                        dateFrom.value = _monthlyFrom;
                        dateTo.value   = _monthlyTo;
                    } else {
                        dateFrom.value = '';
                        dateTo.value   = '';
                    }
                    form.submit();
                    return;
                }
            }
        }
    }

    // Detect current view from URL dates
    (function() {
        var params = new URLSearchParams(window.location.search);
        var df = params.get('date_from') || '';
        var dt = params.get('date_to') || '';
        // If both dates are empty, we're in all-time mode
        if (df === '' && dt === '' && params.has('date_from')) {
            switchAccView('alltime');
        } else {
            switchAccView('monthly');
        }
        _isInitialLoad = false;
    })();

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
        const categoryText = document.getElementById('entryCategoryText');
        const categorySelect = document.getElementById('entryCategorySelect');
        const categoryHelp = document.getElementById('entryCategoryHelp');
        if (type === 'income') {
            title.textContent = 'Add Income';
            title.className = 'text-white text-lg font-light border-l-2 border-green-500 pl-3';
            action.value = 'add_income';
            btn.className = 'bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-500 transition-colors text-sm font-medium';
            if (categoryText && categorySelect && categoryHelp) {
                categoryText.classList.remove('hidden');
                categoryText.disabled = false;
                categoryText.required = true;
                categoryText.placeholder = 'e.g. Sales, Rent, Misc income';

                categorySelect.classList.add('hidden');
                categorySelect.disabled = true;
                categorySelect.required = false;

                categoryHelp.classList.add('hidden');
            }
        } else {
            title.textContent = 'Add Expense';
            title.className = 'text-white text-lg font-light border-l-2 border-red-500 pl-3';
            action.value = 'add_expense';
            btn.className = 'bg-red-600 text-white px-6 py-2 rounded-full hover:bg-red-500 transition-colors text-sm font-medium';
            if (categoryText && categorySelect && categoryHelp) {
                categoryText.classList.add('hidden');
                categoryText.disabled = true;
                categoryText.required = false;

                categorySelect.classList.remove('hidden');
                categorySelect.disabled = false;
                categorySelect.required = true;
                if (!categorySelect.value && categorySelect.options.length > 0) {
                    categorySelect.selectedIndex = 0;
                }

                categoryHelp.classList.remove('hidden');
            }
        }
        const modeEl = document.getElementById('entryPaymentMode');
        if (modeEl) {
            modeEl.value = 'cash';
        }
        toggleEntryBankField();
        modal.classList.remove('hidden');
    }

    function promptVoid(entryId) {
        const form = document.getElementById('voidForm' + entryId);
        if (!form) return false;
        const proceed = confirm('Void this entry? This will remove it from totals and reverse any account balance impact.');
        if (!proceed) return false;
        const reason = prompt('Reason for voiding this entry?');
        if (!reason || !reason.trim()) return false;
        const input = form.querySelector('input[name="void_reason"]');
        if (input) {
            input.value = reason.trim();
        }
        form.submit();
        return false;
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
    ['entryModal', 'accountModal', 'transferModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', function (e) {
            if (e.target === this) this.classList.add('hidden');
        });
    });
    toggleEntryBankField();

    // -- Transfer Modal JS --
    var _transferListenerAdded = false;
    function openTransferModal() {
        var modal = document.getElementById('transferModal');
        modal.classList.remove('hidden');
        updateTransferTo();
        if (!_transferListenerAdded) {
            _transferListenerAdded = true;
            document.getElementById('transferFrom').addEventListener('change', function () {
                var fromAcc = transferAccountsData.find(function(a){ return a.id === parseInt(this.value)||0; }.bind(this));
                var balEl = document.getElementById('transferFromBalance');
                balEl.textContent = fromAcc ? 'Available: $' + parseFloat(fromAcc.balance).toFixed(2) : '';
                updateTransferTo();
            });
        }
    }
    function updateTransferTo() {
        var fromValue = document.getElementById('transferFrom').value;
        var fromId = fromValue === '' ? null : parseInt(fromValue);
        var fromAcc = fromId !== null ? transferAccountsData.find(function(a){ return a.id === fromId; }) : null;
        var balEl = document.getElementById('transferFromBalance');
        balEl.textContent = fromAcc ? 'Available: $' + parseFloat(fromAcc.balance).toFixed(2) : '';
        var toSel = document.getElementById('transferTo');
        var toVal = toSel.value;
        toSel.innerHTML = '';
        var blank = document.createElement('option'); blank.value=''; blank.textContent='-- Select destination --'; toSel.appendChild(blank);
        transferAccountsData.forEach(function(a) {
            // Filter out source account from destination dropdown
            // This includes Cash Account (ID = 0) when Cash is selected as source
            if (fromId !== null && a.id === fromId) return;
            var opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = a.name + ' ($' + parseFloat(a.balance).toFixed(2) + ')';
            toSel.appendChild(opt);
        });
        if (toVal) toSel.value = toVal;
    }
</script>
<!-- Transfer Modal -->
<div id="transferModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-5 border-b border-mb-subtle/10">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <h2 class="text-white font-medium">Transfer Funds</h2>
            </div>
            <button onclick="document.getElementById('transferModal').classList.add('hidden')" class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="transfer_funds">
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">From Account <span class="text-red-400">*</span></label>
                <select id="transferFrom" name="from_account_id" required onchange="updateTransferTo()"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
                    <option value="">-- Select source account --</option>
                    <option value="0">Cash Account ($<?= number_format($cashBalance, 2) ?>)</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> ($<?= number_format($acc['balance'],2) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p id="transferFromBalance" class="text-xs text-blue-400 mt-1 font-medium"></p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">To Account <span class="text-red-400">*</span></label>
                <select id="transferTo" name="to_account_id" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
                    <option value="">-- Select destination account --</option>
                    <option value="0">Cash Account ($<?= number_format($cashBalance, 2) ?>)</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> ($<?= number_format($acc['balance'],2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Amount <span class="text-red-400">*</span></label>
                <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500 placeholder-mb-subtle">
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Description <span class="text-mb-subtle text-xs">(optional)</span></label>
                <input type="text" name="description" placeholder="e.g. Monthly allocation"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500 placeholder-mb-subtle">
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Date</label>
                <input type="date" name="posted_at" value="<?= date('Y-m-d') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex items-center justify-end gap-3 pt-1">
                <button type="button" onclick="document.getElementById('transferModal').classList.add('hidden')"
                    class="text-mb-subtle hover:text-white text-sm transition-colors px-3 py-2">Cancel</button>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transfer
                </button>
            </div>
        </form>
    </div>
</div>
<script>
var transferAccountsData = <?php
    // Add Cash Account (ID = 0) to the beginning of the array
    $tData = [
        ['id' => 0, 'name' => 'Cash Account', 'balance' => $cashBalance]
    ];
    // Add all bank accounts
    $tData = array_merge($tData, array_values(array_map(function($a){ 
        return ['id'=>(int)$a['id'],'name'=>$a['name'],'balance'=>(float)$a['balance']]; 
    }, $accounts)));
    echo json_encode($tData);
?>;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>  