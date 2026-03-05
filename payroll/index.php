<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

$pdo = db();

$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));
ledger_ensure_schema($pdo);

$action = $_POST['action'] ?? '';
$batchStaff = [];
$prepareMonth = (int) date('n');
$prepareYear = (int) date('Y');

//  ”  ”  Step 1: Prepare (show batch form)  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'prepare_payroll') {
    $prepareMonth = (int) ($_POST['month'] ?? date('n'));
    $prepareYear = (int) ($_POST['year'] ?? date('Y'));

    // Block duplicates
    $chk = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE month = ? AND year = ?");
    $chk->execute([$prepareMonth, $prepareYear]);
    if ($chk->fetchColumn() > 0) {
        flash('error', 'Payroll for ' . date('F', mktime(0, 0, 0, $prepareMonth, 1)) . " $prepareYear has already been generated.");
        redirect('index.php');
    }

    // Fetch all active staff with their salary from the staff table
    $stmt = $pdo->prepare("
        SELECT u.id AS user_id, u.name, s.salary AS basic_salary,
               s.role AS staff_role
        FROM users u
        JOIN staff s ON s.id = u.staff_id
        WHERE u.is_active = 1
        ORDER BY u.name
    ");
    $stmt->execute();
    $batchStaff = $stmt->fetchAll();

    if (empty($batchStaff)) {
        flash('error', 'No active staff members found.');
        redirect('index.php');
    }

    // Read incentive-per-lead setting
    settings_ensure_table($pdo);
    $incentivePerLead = (float) settings_get($pdo, 'lead_incentive_per_lead', '0');

    // Fetch closed won leads count per user for the month/year
    $closedLeadsMap = [];
    if (!empty($batchStaff)) {
        $userIds = array_column($batchStaff, 'user_id');
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $clStmt = $pdo->prepare("
            SELECT assigned_staff_id AS user_id, COUNT(*) AS cnt
            FROM leads
            WHERE assigned_staff_id IN ($ph)
              AND status = 'closed_won'
              AND MONTH(COALESCE(closed_at, updated_at)) = ? AND YEAR(COALESCE(closed_at, updated_at)) = ?
            GROUP BY assigned_staff_id
        ");
        $clStmt->execute(array_merge($userIds, [$prepareMonth, $prepareYear]));
        foreach ($clStmt->fetchAll() as $row) {
            $closedLeadsMap[$row['user_id']] = (int) $row['cnt'];
        }
    }
    // Read delivery incentive setting
    $deliveryIncentivePer = (float) settings_get($pdo, 'delivery_incentive_per_delivery', '0');

    // Fetch delivery count per user for this month/year from staff_activity_log
    $deliveriesMap = [];
    if (!empty($batchStaff)) {
        $userIds2 = array_column($batchStaff, 'user_id');
        $ph2 = implode(',', array_fill(0, count($userIds2), '?'));
        $dStmt = $pdo->prepare(
            "SELECT user_id, COUNT(*) AS cnt FROM staff_activity_log WHERE user_id IN($ph2) AND action = 'delivery' AND MONTH(created_at) = ? AND YEAR(created_at) = ? GROUP BY user_id"
        );
        $dStmt->execute(array_merge($userIds2, [$prepareMonth, $prepareYear]));
        foreach ($dStmt->fetchAll() as $row) {
            $deliveriesMap[$row['user_id']] = (int) $row['cnt'];
        }
    }
    // Don't redirect  ” render the batch form below
}

//  ”  ”  Step 2: Save generated payroll  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_payroll') {
    $month = (int) ($_POST['month'] ?? 0);
    $year = (int) ($_POST['year'] ?? 0);

    $chk = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE month = ? AND year = ?");
    $chk->execute([$month, $year]);
    if ($chk->fetchColumn() > 0) {
        flash('error', "Payroll for $month/$year already generated.");
    } else {
        $staffIds = $_POST['staff'] ?? [];
        $incentives = $_POST['incentive'] ?? [];
        $notesList = $_POST['notes'] ?? [];
        $count = 0;

        foreach ($staffIds as $uid) {
            $uid = (int) $uid;
            $s = $pdo->prepare("SELECT s.salary FROM users u JOIN staff s ON s.id = u.staff_id WHERE u.id = ?");
            $s->execute([$uid]);
            $row = $s->fetch();
            if (!$row)
                continue;

            $basic = (float) ($row['salary'] ?? 0);
            $incentive = (float) ($incentives[$uid] ?? 0);
            $net = $basic + $incentive;

            $pdo->prepare("
                INSERT INTO payroll (user_id, month, year, basic_salary, incentive, allowances, deductions, net_salary, notes, created_by, status)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, 'Pending')
            ")->execute([$uid, $month, $year, $basic, $incentive, $net, $notesList[$uid] ?? null, current_user()['id']]);
            $count++;
        }

        flash('success', "Payroll generated for $count staff member" . ($count !== 1 ? 's' : '') . ".");
        app_log('ACTION', "Payroll generated for month=$month year=$year by user#" . current_user()['id']);
    }
    redirect('index.php?month=' . $month . '&year=' . $year);
}

//  ”  ”  Step 3: Mark as Paid + create ledger entry  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay') {
    $payrollId = (int) ($_POST['payroll_id'] ?? 0);
    $bankAcctId = (int) ($_POST['bank_account_id'] ?? 0);

    $pr = $pdo->prepare("SELECT p.*, u.name AS staff_name FROM payroll p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
    $pr->execute([$payrollId]);
    $pay = $pr->fetch();

    if ($pay && $pay['status'] === 'Pending' && $bankAcctId) {
        try {
            $pdo->beginTransaction();

            $monthName = date('F', mktime(0, 0, 0, $pay['month'], 1));
            $desc = "Salary  “ {$pay['staff_name']} ($monthName {$pay['year']})";
            $nowSql = app_now_sql();

            // Post to ledger as expense
            $ledgerId = null;
            $pdo->prepare("INSERT INTO ledger_entries
                (txn_type, category, description, amount, payment_mode, bank_account_id,
                 source_type, source_id, source_event, posted_at, created_by)
                VALUES ('expense', 'Salary', ?, ?, 'account', ?, 'payroll', ?, 'salary_payment', ?, ?)")
                ->execute([$desc, $pay['net_salary'], $bankAcctId, $payrollId, $nowSql, current_user()['id']]);
            $ledgerId = (int) $pdo->lastInsertId();

            // Deduct bank balance
            $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id = ?")
                ->execute([$pay['net_salary'], $bankAcctId]);

            // Mark payroll as Paid
            $pdo->prepare("UPDATE payroll SET status='Paid', payment_date=?, paid_from_account_id=?, ledger_entry_id=? WHERE id=?")
                ->execute([$nowSql, $bankAcctId, $ledgerId, $payrollId]);

            $pdo->commit();
            flash('success', "Salary paid to {$pay['staff_name']} and ledger entry created.");
            app_log('ACTION', "Payroll#$payrollId paid for user_id={$pay['user_id']} amount={$pay['net_salary']}");
        } catch (Throwable $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            flash('error', 'Payment failed: ' . $e->getMessage());
            app_log('ERROR', 'Payroll payment failed: ' . $e->getMessage());
        }
    } else {
        flash('error', 'Invalid payroll record or already paid.');
    }
    redirect('index.php?month=' . ($pay['month'] ?? date('n')) . '&year=' . ($pay['year'] ?? date('Y')));
}

//  ”  ”  Read payroll list  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));

$_pySql  = "SELECT p.*, u.name AS staff_name, s.role AS staff_role, ba.name AS paid_from_name FROM payroll p JOIN users u ON u.id=p.user_id JOIN staff s ON s.id=u.staff_id LEFT JOIN bank_accounts ba ON ba.id=p.paid_from_account_id WHERE p.month=? AND p.year=? ORDER BY u.name";
$_pyCnt  = "SELECT COUNT(*) FROM payroll p JOIN users u ON u.id=p.user_id JOIN staff s ON s.id=u.staff_id WHERE p.month=? AND p.year=?";
$pgPayroll   = paginate_query($pdo, $_pySql, $_pyCnt, [$month, $year], $page, $perPage);
$payrollRows = $pgPayroll[
'rows'
];

// Totals
$totalBasic = array_sum(array_column($payrollRows, 'basic_salary'));
$totalIncentive = array_sum(array_column($payrollRows, 'incentive'));
$totalNet = array_sum(array_column($payrollRows, 'net_salary'));
$totalPaid = array_sum(array_map(fn($r) => $r['status'] === 'Paid' ? $r['net_salary'] : 0, $payrollRows));

// Bank accounts for pay modal
$bankAccounts = $pdo->query("SELECT id, name FROM bank_accounts WHERE is_active = 1 ORDER BY name")->fetchAll();

$success = getFlash('success');
$error = getFlash('error');
$pageTitle = 'Payroll';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">

    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($batchStaff)): ?>
        <!--  ”  ”  Batch Entry View  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-mb-subtle/10">
                <div>
                    <h2 class="text-white font-light">Batch Entry  ”
                        <?= date('F', mktime(0, 0, 0, $prepareMonth, 1)) . " $prepareYear" ?>
                    </h2>
                    <p class="text-xs text-mb-subtle mt-0.5">Review salaries and set incentives before finalising</p>
                </div>
                <a href="index.php" class="text-xs text-mb-subtle hover:text-white transition-colors">   • Cancel</a>
            </div>

            <!-- Batch Incentive Tools -->
            <div class="px-6 pt-5 pb-3 border-b border-mb-subtle/10 bg-mb-black/20">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <p class="text-xs text-mb-subtle uppercase tracking-wider">Batch Incentive Tools</p>
                    <p class="text-xs text-mb-subtle uppercase tracking-wider">Batch Incentive Tools</p>
                    <?php if ($incentivePerLead > 0): ?>
                        <p class="text-xs text-blue-400">Lead: <strong>$<?= number_format($incentivePerLead, 2) ?>/lead</strong> &nbsp;&middot;&nbsp; auto-filled</p>
                    <?php endif; ?>
                    <?php if (!empty($deliveryIncentivePer) && $deliveryIncentivePer > 0): ?>
                        <p class="text-xs text-orange-400">Delivery: <strong>$<?= number_format($deliveryIncentivePer, 2) ?>/delivery</strong> &nbsp;&middot;&nbsp; auto-filled</p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-4">
                    <div>
                        <label class="block text-xs text-mb-silver mb-1">Total Pool (split equally)</label>
                        <div class="flex items-center gap-2">
                            <span class="text-mb-subtle text-sm">$</span>
                            <input type="number" id="totalPool" min="0" step="0.01" placeholder="0.00"
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm w-36 focus:outline-none focus:border-mb-accent"
                                oninput="distributePool()">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-mb-silver mb-1">Fixed Amount (per person)</label>
                        <div class="flex items-center gap-2">
                            <span class="text-mb-subtle text-sm">$</span>
                            <input type="number" id="fixedAmount" min="0" step="0.01" placeholder="0.00"
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm w-36 focus:outline-none focus:border-mb-accent"
                                oninput="applyFixed()">
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" class="p-0">
                <input type="hidden" name="action" value="save_payroll">
                <input type="hidden" name="month" value="<?= $prepareMonth ?>">
                <input type="hidden" name="year" value="<?= $prepareYear ?>">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                            <tr class="text-mb-subtle text-xs uppercase">
                                <th class="px-6 py-3 text-left">Staff</th>
                                <th class="px-6 py-3 text-left">Role</th>
                                <th class="px-6 py-3 text-center">Leads Won</th>
                                <th class="px-6 py-3 text-center">Deliveries</th>
                                <th class="px-6 py-3 text-right">Basic Salary</th>
                                <th class="px-6 py-3 text-right">Incentive</th>
                                <th class="px-6 py-3 text-right">Net (Est.)</th>
                                <th class="px-6 py-3 text-left">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mb-subtle/10">
                            <?php foreach ($batchStaff as $s):
                                $basic         = (float) ($s['basic_salary'] ?? 0);
                                $closedLeads   = $closedLeadsMap[$s['user_id']] ?? 0;
                                $deliveries    = $deliveriesMap[$s['user_id']] ?? 0;
                                $leadBonus     = round($closedLeads * $incentivePerLead, 2);
                                $delivBonusPer = $deliveryIncentivePer ?? 0;
                                $deliveryBonus = round($deliveries * $delivBonusPer, 2);
                                $autoIncentive = round($leadBonus + $deliveryBonus, 2);
                            ?>
                            <tr class="hover:bg-mb-black/20 transition-colors">
                                <input type="hidden" name="staff[]" value="<?= $s['user_id'] ?>">
                                <td class="px-6 py-4 font-medium text-white"><?= e($s['name']) ?></td>
                                <td class="px-6 py-4 text-mb-subtle"><?= e($s['staff_role'] ?? '-') ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($closedLeads > 0): ?><span class="inline-flex items-center gap-1 bg-green-500/10 text-green-400 border border-green-500/20 rounded-full px-2.5 py-0.5 text-xs font-medium"><?= $closedLeads ?> won</span><?php if ($incentivePerLead > 0): ?><p class="text-xs text-blue-400/70 mt-0.5">+$<?= number_format($leadBonus, 2) ?></p><?php endif; ?><?php else: ?><span class="text-mb-subtle/40 text-xs">-</span><?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($deliveries > 0): ?><span class="inline-flex items-center gap-1 bg-orange-500/10 text-orange-400 border border-orange-500/20 rounded-full px-2.5 py-0.5 text-xs font-medium"><?= $deliveries ?> del</span><?php if ($delivBonusPer > 0): ?><p class="text-xs text-orange-400/70 mt-0.5">+$<?= number_format($deliveryBonus, 2) ?></p><?php endif; ?><?php else: ?><span class="text-mb-subtle/40 text-xs">-</span><?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right text-mb-silver">$<?= number_format($basic, 2) ?></td>
                                <td class="px-6 py-4 text-right"><input type="number" name="incentive[<?= $s['user_id'] ?>]" class="incentive-input bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1.5 text-white text-sm w-28 text-right focus:outline-none focus:border-mb-accent" value="<?= $autoIncentive ?>" min="0" step="0.01" data-basic="<?= $basic ?>" data-auto="<?= $autoIncentive ?>" oninput="updateNet(this)"></td>
                                <td class="px-6 py-4 text-right text-mb-accent font-medium net-cell">$<?= number_format($basic + $autoIncentive, 2) ?></td>
                                <td class="px-6 py-4"><input type="text" name="notes[<?= $s['user_id'] ?>]" placeholder="optional note" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1.5 text-white text-sm w-40 focus:outline-none focus:border-mb-accent placeholder-mb-subtle"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-t border-mb-subtle/10 bg-mb-black/30">
                            <tr class="text-mb-silver text-xs">
                                <td colspan="3" class="px-6 py-3 font-medium"><?= count($batchStaff) ?> staff members</td>
                                <td class="px-6 py-3"></td>
                                <td class="px-6 py-3 text-right">$<?= number_format(array_sum(array_column($batchStaff, 'basic_salary')), 2) ?></td>
                                <td class="px-6 py-3 text-right" id="totalIncentiveFooter">$0.00</td>
                                <td class="px-6 py-3 text-right font-semibold text-mb-accent" id="totalNetFooter">$<?= number_format(array_sum(array_column($batchStaff, 'basic_salary')), 2) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-3 p-6 border-t border-mb-subtle/10">
                    <a href="index.php"
                        class="text-mb-subtle hover:text-white text-sm transition-colors px-4 py-2">Cancel</a>
                    <button type="submit"
                        class="bg-mb-accent text-white px-6 py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">
                        Finalize & Save Payroll
                    </button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!--  ”  ”  Normal Payroll List View  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  -->

        <!-- Header + Generate button -->
        <div class="flex items-center justify-between">
            <h2 class="text-white font-light text-xl">Payroll</h2>
            <button onclick="document.getElementById('generateModal').classList.remove('hidden')"
                class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                Generate Payroll
            </button>
        </div>

        <!-- Month filter -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl px-6 py-4">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-mb-subtle uppercase tracking-wider">Month</label>
                    <select name="month" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-mb-subtle uppercase tracking-wider">Year</label>
                    <select name="year" onchange="this.form.submit()"
                        class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                        <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if (!empty($payrollRows)): ?>
            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ([
                    ['Total Basic', '$' . number_format($totalBasic, 2), 'text-mb-silver'],
                    ['Total Incentive', '$' . number_format($totalIncentive, 2), 'text-blue-400'],
                    ['Total Net', '$' . number_format($totalNet, 2), 'text-mb-accent'],
                    ['Total Paid', '$' . number_format($totalPaid, 2), 'text-green-400'],
                ] as [$label, $val, $clr]): ?>
                    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-4">
                        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-1">
                            <?= $label ?>
                        </p>
                        <p class="text-xl font-light <?= $clr ?>">
                            <?= $val ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Payroll table -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <?php if (empty($payrollRows)): ?>
                <div class="py-20 text-center">
                    <svg class="w-12 h-12 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-mb-subtle">No payroll for
                        <?= date('F', mktime(0, 0, 0, $month, 1)) ?>
                        <?= $year ?>.
                    </p>
                    <button onclick="document.getElementById('generateModal').classList.remove('hidden')"
                        class="mt-4 text-mb-accent hover:text-white text-sm transition-colors">Generate Now</button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                            <tr class="text-mb-subtle text-xs uppercase">
                                <th class="px-6 py-4 text-left">Staff</th>
                                <th class="px-6 py-4 text-left">Role</th>
                                <th class="px-6 py-4 text-right">Basic</th>
                                <th class="px-6 py-4 text-right">Incentive</th>
                                <th class="px-6 py-4 text-right">Net Salary</th>
                                <th class="px-6 py-4 text-left">Status</th>
                                <th class="px-6 py-4 text-left">Paid From</th>
                                <th class="px-6 py-4 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mb-subtle/10">
                            <?php foreach ($payrollRows as $row): ?>
                                <tr class="hover:bg-mb-black/20 transition-colors">
                                    <td class="px-6 py-4 font-medium text-white">
                                        <?= e($row['staff_name']) ?>
                                        <?php if ($row['notes']): ?>
                                            <p class="text-xs text-mb-subtle">
                                                <?= e($row['notes']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-mb-subtle">
                                        <?= e($row['staff_role'] ?? ' ”') ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-mb-silver">$
                                        <?= number_format($row['basic_salary'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-blue-400">+$
                                        <?= number_format($row['incentive'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-mb-accent font-semibold">$
                                        <?= number_format($row['net_salary'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['status'] === 'Paid'): ?>
                                            <span
                                                class="px-2.5 py-1 rounded-full text-xs border bg-green-500/10 text-green-400 border-green-500/30">
                                                    Paid
                                                <?= $row['payment_date'] ? date('d M', strtotime($row['payment_date'])) : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="px-2.5 py-1 rounded-full text-xs border bg-yellow-500/10 text-yellow-400 border-yellow-500/30">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-mb-subtle text-xs">
                                        <?= e($row['paid_from_name'] ?? ' ”') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <button type="button"
                                                onclick="openPayModal(<?= $row['id'] ?>, '<?= addslashes($row['staff_name']) ?>', <?= $row['net_salary'] ?>)"
                                                class="text-xs bg-mb-accent/15 text-mb-accent border border-mb-accent/30 px-3 py-1.5 rounded-full hover:bg-mb-accent/25 transition-colors">
                                                Pay Now
                                            </button>
                                        <?php else: ?>
                                            <a href="../accounts/index.php"
                                                class="text-xs text-mb-subtle hover:text-white transition-colors">View Ledger</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!--  ”  ”  Generate Payroll Modal  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  -->
<div id="generateModal"
    class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between p-5 border-b border-mb-subtle/10">
            <h2 class="text-white font-medium">Generate Payroll</h2>
            <button onclick="document.getElementById('generateModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="prepare_payroll">
            <p class="text-sm text-mb-subtle">Select month and year to generate payroll for all active staff.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Month</label>
                    <select name="month"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Year</label>
                    <select name="year"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                        <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === (int) date('Y') ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('generateModal').classList.add('hidden')"
                    class="text-mb-subtle hover:text-white text-sm transition-colors px-3 py-2">Cancel</button>
                <button type="submit"
                    class="bg-mb-accent text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-mb-accent/80 transition-colors">
                    Generate
                </button>
            </div>
        </form>
    </div>
</div>

<!--  ”  ”  Pay Modal  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  -->
<div id="payModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between p-5 border-b border-mb-subtle/10">
            <h2 class="text-white font-medium">Record Salary Payment</h2>
            <button onclick="document.getElementById('payModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="payroll_id" id="payModalId">
            <div class="bg-mb-black/40 rounded-lg px-4 py-3">
                <p class="text-xs text-mb-subtle mb-0.5">Paying salary to</p>
                <p class="text-white font-medium" id="payModalName"></p>
                <p class="text-mb-accent font-semibold text-lg mt-1" id="payModalAmount"></p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Pay From Bank Account <span
                        class="text-red-400">*</span></label>
                <select name="bank_account_id" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value=""> ” Select account  ”</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= $ba['id'] ?>">
                            <?= e($ba['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center justify-end gap-3">
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
    // Backdrop close
    ['generateModal', 'payModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); });
    });

    // Pay modal
    function openPayModal(id, name, amount) {
        document.getElementById('payModalId').value = id;
        document.getElementById('payModalName').textContent = name;
        document.getElementById('payModalAmount').textContent = '$' + parseFloat(amount).toLocaleString('en', { minimumFractionDigits: 2 });
        document.getElementById('payModal').classList.remove('hidden');
    }

    // Batch: update single row net
    // data-basic = basic salary | data-auto = lead auto incentive (pre-computed)
    function updateNet(input) {
        const manualInc = parseFloat(input.value) || 0;
        const basic = parseFloat(input.dataset.basic) || 0;
        const cell = input.closest('tr').querySelector('.net-cell');
        cell.textContent = '$' + (basic + manualInc).toLocaleString('en', { minimumFractionDigits: 2 });
        updateFooterTotals();
    }

    // Batch: distribute total pool equally (added ON TOP of each person's lead auto incentive)
    function distributePool() {
        const total = parseFloat(document.getElementById('totalPool').value) || 0;
        const inputs = document.querySelectorAll('.incentive-input');
        const share = inputs.length ? (total / inputs.length) : 0;
        inputs.forEach(inp => {
            const autoAmt = parseFloat(inp.dataset.auto) || 0;
            inp.value = (autoAmt + share).toFixed(2);
            updateNet(inp);
        });
        document.getElementById('fixedAmount').value = '';
        updateFooterTotals();
    }

    // Batch: fixed amount per person (added ON TOP of each person's lead auto incentive)
    function applyFixed() {
        const fixed = parseFloat(document.getElementById('fixedAmount').value) || 0;
        const inputs = document.querySelectorAll('.incentive-input');
        inputs.forEach(inp => {
            const autoAmt = parseFloat(inp.dataset.auto) || 0;
            inp.value = (autoAmt + fixed).toFixed(2);
            updateNet(inp);
        });
        document.getElementById('totalPool').value = '';
        updateFooterTotals();
    }

    // Update footer totals
    function updateFooterTotals() {
        let totalInc = 0, totalNet = 0;
        document.querySelectorAll('.incentive-input').forEach(inp => {
            const basic = parseFloat(inp.dataset.basic) || 0;
            const inc = parseFloat(inp.value) || 0;
            totalInc += inc;
            totalNet += basic + inc;
        });
        const ti = document.getElementById('totalIncentiveFooter');
        const tn = document.getElementById('totalNetFooter');
        if (ti) ti.textContent = '$' + totalInc.toLocaleString('en', { minimumFractionDigits: 2 });
        if (tn) tn.textContent = '$' + totalNet.toLocaleString('en', { minimumFractionDigits: 2 });
    }
</script>


<?php
echo render_pagination(
$pgPayroll
, ['month'=>
$month
,'year'=>
$year
]);
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
