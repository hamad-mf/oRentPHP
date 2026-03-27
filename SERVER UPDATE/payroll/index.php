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
$advanceSchemaReady = false;
$advanceSchemaError = null;
$hasOvertimeCol = false;

try {
    $hasAdvanceTable = (bool) $pdo->query("SHOW TABLES LIKE 'payroll_advances'")->fetchColumn();
    $hasAdvanceDeducted = (bool) $pdo->query("SHOW COLUMNS FROM payroll LIKE 'advance_deducted'")->fetchColumn();
    $hasPayableSalary = (bool) $pdo->query("SHOW COLUMNS FROM payroll LIKE 'payable_salary'")->fetchColumn();
    $advanceSchemaReady = $hasAdvanceTable && $hasAdvanceDeducted && $hasPayableSalary;
} catch (Throwable $e) {
    app_log('ERROR', 'Payroll: advance schema readiness check failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

    $advanceSchemaError = $e->getMessage();
}

// Runtime migration: overtime_pay column
try {
    $hasOvertimeCol = (bool) $pdo->query("SHOW COLUMNS FROM payroll LIKE 'overtime_pay'")->fetchColumn();
    if (!$hasOvertimeCol) {
        $pdo->exec("ALTER TABLE payroll ADD COLUMN overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER incentive");
        $hasOvertimeCol = true;
    }
} catch (Throwable $e) {
    app_log('ERROR', 'Payroll: overtime_pay migration failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
}

$getAdvanceOutstandingMap = function (array $userIds) use ($pdo, $advanceSchemaReady): array {
    $map = [];
    if (!$advanceSchemaReady || empty($userIds)) {
        return $map;
    }

    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (empty($userIds)) {
        return $map;
    }

    $ph = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("
        SELECT user_id, COALESCE(SUM(remaining_amount), 0) AS outstanding
        FROM payroll_advances
        WHERE user_id IN ($ph)
          AND remaining_amount > 0
          AND status IN ('pending', 'partially_recovered')
        GROUP BY user_id
    ");
    $stmt->execute($userIds);
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['user_id']] = (float) $row['outstanding'];
    }
    return $map;
};

//  ”  ”  Step 1: Prepare (show batch form)  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'prepare_payroll') {
    $prepareMonth = (int) ($_POST['month'] ?? date('n'));
    $prepareYear = (int) ($_POST['year'] ?? date('Y'));

    // Compute 15th-to-15th billing period for this month/year
    $payPeriodStart = sprintf('%04d-%02d-15', $prepareYear, $prepareMonth);
    $payPeriodNextM = $prepareMonth === 12 ? 1 : $prepareMonth + 1;
    $payPeriodNextY = $prepareMonth === 12 ? $prepareYear + 1 : $prepareYear;
    $payPeriodEnd   = sprintf('%04d-%02d-14', $payPeriodNextY, $payPeriodNextM);

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
              AND DATE(COALESCE(closed_at, updated_at)) BETWEEN ? AND ?
            GROUP BY assigned_staff_id
        ");
        $clStmt->execute(array_merge($userIds, [$payPeriodStart, $payPeriodEnd]));
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
            "SELECT user_id, COUNT(*) AS cnt FROM staff_activity_log WHERE user_id IN($ph2) AND action = 'delivery' AND DATE(created_at) BETWEEN ? AND ? GROUP BY user_id"
        );
        $dStmt->execute(array_merge($userIds2, [$payPeriodStart, $payPeriodEnd]));
        foreach ($dStmt->fetchAll() as $row) {
            $deliveriesMap[$row['user_id']] = (int) $row['cnt'];
        }
    }

    // Fetch staff incentives from staff_incentives table for the month/year
    $staffIncentivesMap = [];
    $hasIncentiveTable = false;
    try {
        $hasIncentiveTable = (bool) $pdo->query("SHOW TABLES LIKE 'staff_incentives'")->fetchColumn();
        if ($hasIncentiveTable && !empty($batchStaff)) {
            $userIds3 = array_column($batchStaff, 'user_id');
            $ph3 = implode(',', array_fill(0, count($userIds3), '?'));
            $incStmt = $pdo->prepare(
                "SELECT user_id, COALESCE(SUM(amount),0) AS total FROM staff_incentives WHERE user_id IN($ph3) AND month = ? AND year = ? GROUP BY user_id"
            );
            $incStmt->execute(array_merge($userIds3, [$prepareMonth, $prepareYear]));
            foreach ($incStmt->fetchAll() as $row) {
                $staffIncentivesMap[$row['user_id']] = (float) $row['total'];
            }
        }
    } catch (Throwable $e) {
        $hasIncentiveTable = false;
    }

    $advanceOutstandingMap = $getAdvanceOutstandingMap(array_column($batchStaff, 'user_id'));
    foreach ($batchStaff as &$staffRow) {
        $staffRow['advance_outstanding'] = (float) ($advanceOutstandingMap[$staffRow['user_id']] ?? 0);
    }
    unset($staffRow);

    // ── Overtime calculation ──────────────────────────────────────────────
    settings_ensure_table($pdo);
    $otThresholdHours = (float) settings_get($pdo, 'att_overtime_threshold_hours', '12');
    $otRatePerHour    = (float) settings_get($pdo, 'att_overtime_rate_per_hour', '0');
    $otThresholdSecs  = (int) ($otThresholdHours * 3600);
    $overtimeMap = []; // user_id => ['total_extra_secs' => X, 'overtime_pay' => Y]

    if ($otRatePerHour > 0 && !empty($batchStaff)) {
        $firstDay = $payPeriodStart;
        $lastDay  = $payPeriodEnd;
        $userIdsOt = array_column($batchStaff, 'user_id');
        $phOt = implode(',', array_fill(0, count($userIdsOt), '?'));

        // Fetch all attendance for month
        $attStmt = $pdo->prepare("SELECT * FROM staff_attendance WHERE user_id IN ($phOt) AND date BETWEEN ? AND ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL");
        $attStmt->execute(array_merge($userIdsOt, [$firstDay, $lastDay]));
        $allAtt = $attStmt->fetchAll();

        // Fetch all breaks for those attendance records
        $attIdToUser = [];
        $attIds = [];
        foreach ($allAtt as $a) {
            $attIds[] = (int)$a['id'];
            $attIdToUser[(int)$a['id']] = (int)$a['user_id'];
        }
        $breaksByAtt = [];
        if ($attIds) {
            $phBr = implode(',', array_map('intval', $attIds));
            $brStmt = $pdo->query("SELECT * FROM attendance_breaks WHERE attendance_id IN ($phBr) AND break_end IS NOT NULL");
            foreach ($brStmt->fetchAll() as $b) $breaksByAtt[(int)$b['attendance_id']][] = $b;
        }

        // Calculate per-user overtime
        foreach ($allAtt as $a) {
            $uid = (int)$a['user_id'];
            $workSecs = strtotime($a['punch_out']) - strtotime($a['punch_in']);
            foreach ($breaksByAtt[(int)$a['id']] ?? [] as $b) {
                $workSecs -= (strtotime($b['break_end']) - strtotime($b['break_start']));
            }
            $workSecs = max(0, $workSecs);
            $extraSecs = max(0, $workSecs - $otThresholdSecs);
            if ($extraSecs > 0) {
                if (!isset($overtimeMap[$uid])) $overtimeMap[$uid] = ['total_extra_secs' => 0, 'overtime_pay' => 0];
                $overtimeMap[$uid]['total_extra_secs'] += $extraSecs;
            }
        }

        // Calculate pay from total seconds
        foreach ($overtimeMap as $uid => &$otData) {
            $otData['overtime_pay'] = round(($otData['total_extra_secs'] / 3600) * $otRatePerHour, 2);
        }
        unset($otData);
    }

    // Attach overtime to batchStaff rows
    foreach ($batchStaff as &$staffRow) {
        $ot = $overtimeMap[$staffRow['user_id']] ?? null;
        $staffRow['overtime_extra_secs'] = $ot ? $ot['total_extra_secs'] : 0;
        $staffRow['overtime_pay']        = $ot ? $ot['overtime_pay'] : 0;
    }
    unset($staffRow);
    // Don't redirect - render the batch form below
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
            $manualIncentive = (float) ($incentives[$uid] ?? 0);
            $tableIncentive = 0.0;
            if ($hasIncentiveTable) {
                try {
                    $incQ = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM staff_incentives WHERE user_id = ? AND month = ? AND year = ?");
                    $incQ->execute([$uid, $month, $year]);
                    $tableIncentive = (float) $incQ->fetchColumn();
                } catch (Throwable $e) {
                    $tableIncentive = 0.0;
                }
            }
            $incentive = $manualIncentive + $tableIncentive;
            $overtimePay = (float) ($_POST['overtime'][$uid] ?? 0);
            $net = $basic + $incentive + $overtimePay;

            if ($advanceSchemaReady) {
                $pdo->prepare("
                    INSERT INTO payroll (user_id, month, year, basic_salary, incentive, overtime_pay, allowances, deductions, advance_deducted, net_salary, payable_salary, notes, created_by, status)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, ?, 'Pending')
                ")->execute([$uid, $month, $year, $basic, $incentive, $overtimePay, $net, $net, $notesList[$uid] ?? null, current_user()['id']]);
            } else {
                $pdo->prepare("
                    INSERT INTO payroll (user_id, month, year, basic_salary, incentive, overtime_pay, allowances, deductions, net_salary, notes, created_by, status)
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, 'Pending')
                ")->execute([$uid, $month, $year, $basic, $incentive, $overtimePay, $net, $notesList[$uid] ?? null, current_user()['id']]);
            }
            $count++;
        }

        flash('success', "Payroll generated for $count staff member" . ($count !== 1 ? 's' : '') . ".");
        app_log('ACTION', "Payroll generated for month=$month year=$year by user#" . current_user()['id']);
    }
    redirect('index.php?month=' . $month . '&year=' . $year);
}

//  ”  ”  Step 2.5: Give staff advance  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'give_advance') {
    $payrollId = (int) ($_POST['payroll_id'] ?? 0);
    $bankAcctId = (int) ($_POST['bank_account_id'] ?? 0);
    $advanceAmount = (float) ($_POST['advance_amount'] ?? 0);
    $advanceNote = trim($_POST['advance_note'] ?? '');

    if (!$advanceSchemaReady) {
        flash('error', 'Advance feature is not enabled yet. Run the payroll advances migration first.');
        redirect('index.php');
    }

    $pr = $pdo->prepare("SELECT p.*, u.name AS staff_name FROM payroll p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
    $pr->execute([$payrollId]);
    $pay = $pr->fetch();

    if (!$pay) {
        flash('error', 'Payroll record not found.');
        redirect('index.php');
    }
    if ($advanceAmount <= 0) {
        flash('error', 'Advance amount must be greater than 0.');
        redirect('index.php?month=' . $pay['month'] . '&year=' . $pay['year']);
    }
    if ($bankAcctId <= 0) {
        flash('error', 'Please select a bank account for the advance payment.');
        redirect('index.php?month=' . $pay['month'] . '&year=' . $pay['year']);
    }

    $acc = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND is_active = 1");
    $acc->execute([$bankAcctId]);
    if (!$acc->fetch()) {
        flash('error', 'Selected bank account is invalid or inactive.');
        redirect('index.php?month=' . $pay['month'] . '&year=' . $pay['year']);
    }

    try {
        $pdo->beginTransaction();

        $prLock = $pdo->prepare("SELECT p.*, u.name AS staff_name FROM payroll p JOIN users u ON u.id = p.user_id WHERE p.id = ? FOR UPDATE");
        $prLock->execute([$payrollId]);
        $payLocked = $prLock->fetch();
        if (!$payLocked) {
            throw new RuntimeException('Payroll record not found.');
        }
        $pay = $payLocked;

        $advLockStmt = $pdo->prepare("
            SELECT remaining_amount
            FROM payroll_advances
            WHERE user_id = ?
              AND remaining_amount > 0
              AND status IN ('pending', 'partially_recovered')
            FOR UPDATE
        ");
        $advLockStmt->execute([(int) $pay['user_id']]);

        $outstandingAdvance = 0.0;
        foreach ($advLockStmt->fetchAll() as $advRow) {
            $outstandingAdvance += (float) ($advRow['remaining_amount'] ?? 0);
        }

        $netSalary = (float) ($pay['net_salary'] ?? 0);
        $alreadyDeductible = min($netSalary, $outstandingAdvance);
        $availableAdvanceLimit = round(max(0, $netSalary - $alreadyDeductible), 2);

        if ($advanceAmount > $availableAdvanceLimit + 0.00001) {
            throw new RuntimeException('Advance exceeds allowed limit for this payroll. Max allowed now: $' . number_format($availableAdvanceLimit, 2));
        }

        $nowSql = app_now_sql();

        $advStmt = $pdo->prepare("
            INSERT INTO payroll_advances
            (user_id, payroll_id, month, year, amount, remaining_amount, status, note, given_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
        ");
        $advStmt->execute([
            (int) $pay['user_id'],
            (int) $pay['id'],
            (int) $pay['month'],
            (int) $pay['year'],
            $advanceAmount,
            $advanceAmount,
            $advanceNote !== '' ? $advanceNote : null,
            $nowSql,
            current_user()['id'],
        ]);
        $advanceId = (int) $pdo->lastInsertId();

        $monthName = date('F', mktime(0, 0, 0, (int) $pay['month'], 1));
        $desc = "Staff Advance - {$pay['staff_name']} ($monthName {$pay['year']})";

        $pdo->prepare("INSERT INTO ledger_entries
            (txn_type, category, description, amount, payment_mode, bank_account_id,
             source_type, source_id, source_event, posted_at, created_by)
            VALUES ('expense', 'Staff Advance', ?, ?, 'account', ?, 'payroll_advance', ?, 'advance_payment', ?, ?)")
            ->execute([$desc, $advanceAmount, $bankAcctId, $advanceId, $nowSql, current_user()['id']]);
        $ledgerId = (int) $pdo->lastInsertId();

        $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id = ?")
            ->execute([$advanceAmount, $bankAcctId]);

        $pdo->prepare("UPDATE payroll_advances SET bank_account_id = ?, ledger_entry_id = ? WHERE id = ?")
            ->execute([$bankAcctId, $ledgerId, $advanceId]);

        $pdo->commit();
        flash('success', "Advance of $" . number_format($advanceAmount, 2) . " paid to {$pay['staff_name']}.");
        app_log('ACTION', "Payroll advance#{$advanceId} paid for payroll#{$payrollId} amount={$advanceAmount}");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Advance payment failed: ' . $e->getMessage());
        app_log('ERROR', 'Payroll advance payment failed: ' . $e->getMessage());
    }

    redirect('index.php?month=' . $pay['month'] . '&year=' . $pay['year']);
}

//  ”  ”  Step 3: Mark as Paid + create ledger entry  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay') {
    $payrollId = (int) ($_POST['payroll_id'] ?? 0);
    $bankAcctId = (int) ($_POST['bank_account_id'] ?? 0);
    $redirectMonth = (int) date('n');
    $redirectYear = (int) date('Y');

    try {
        $pdo->beginTransaction();

        $pr = $pdo->prepare("SELECT p.*, u.name AS staff_name FROM payroll p JOIN users u ON u.id = p.user_id WHERE p.id = ? FOR UPDATE");
        $pr->execute([$payrollId]);
        $pay = $pr->fetch();

        if (!$pay) {
            throw new RuntimeException('Payroll record not found.');
        }
        $redirectMonth = (int) $pay['month'];
        $redirectYear = (int) $pay['year'];

        if ($pay['status'] !== 'Pending') {
            throw new RuntimeException('This payroll record is already paid.');
        }

        $nowSql = app_now_sql();
        $netSalary = (float) $pay['net_salary'];
        $advanceDeducted = 0.0;

        if ($advanceSchemaReady) {
            $remainingToRecover = $netSalary;
            $advStmt = $pdo->prepare("
                SELECT id, remaining_amount
                FROM payroll_advances
                WHERE user_id = ?
                  AND month = ?
                  AND year = ?
                  AND remaining_amount > 0
                  AND status IN ('pending', 'partially_recovered')
                ORDER BY given_at ASC, id ASC
                FOR UPDATE
            ");
            $advStmt->execute([(int) $pay['user_id'], (int) $pay['month'], (int) $pay['year']]);
            $advRows = $advStmt->fetchAll();

            foreach ($advRows as $adv) {
                if ($remainingToRecover <= 0) {
                    break;
                }

                $currentRemaining = (float) $adv['remaining_amount'];
                if ($currentRemaining <= 0) {
                    continue;
                }

                $recoverAmount = min($currentRemaining, $remainingToRecover);
                if ($recoverAmount <= 0) {
                    continue;
                }

                $newRemaining = round($currentRemaining - $recoverAmount, 2);
                $newStatus = $newRemaining <= 0 ? 'recovered' : 'partially_recovered';
                $recoveredAt = $newRemaining <= 0 ? $nowSql : null;

                $pdo->prepare("UPDATE payroll_advances SET remaining_amount = ?, status = ?, recovered_at = ? WHERE id = ?")
                    ->execute([$newRemaining, $newStatus, $recoveredAt, (int) $adv['id']]);

                $advanceDeducted += $recoverAmount;
                $remainingToRecover -= $recoverAmount;
            }
        }

        $advanceDeducted = round($advanceDeducted, 2);
        $payableSalary = round(max(0, $netSalary - $advanceDeducted), 2);

        $monthName = date('F', mktime(0, 0, 0, (int) $pay['month'], 1));
        $desc = "Salary - {$pay['staff_name']} ($monthName {$pay['year']})";
        $ledgerId = null;
        $paidFromAccountId = null;

        if ($payableSalary > 0) {
            if ($bankAcctId <= 0) {
                throw new RuntimeException('Please select a bank account.');
            }
            $acc = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND is_active = 1 FOR UPDATE");
            $acc->execute([$bankAcctId]);
            if (!$acc->fetch()) {
                throw new RuntimeException('Selected bank account is invalid or inactive.');
            }

            $pdo->prepare("INSERT INTO ledger_entries
                (txn_type, category, description, amount, payment_mode, bank_account_id,
                 source_type, source_id, source_event, posted_at, created_by)
                VALUES ('expense', 'Salary', ?, ?, 'account', ?, 'payroll', ?, 'salary_payment', ?, ?)")
                ->execute([$desc, $payableSalary, $bankAcctId, $payrollId, $nowSql, current_user()['id']]);
            $ledgerId = (int) $pdo->lastInsertId();

            $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id = ?")
                ->execute([$payableSalary, $bankAcctId]);

            $paidFromAccountId = $bankAcctId;
        }

        if ($advanceSchemaReady) {
            $pdo->prepare("UPDATE payroll
                           SET status='Paid', payment_date=?, paid_from_account_id=?, ledger_entry_id=?, advance_deducted=?, payable_salary=?
                           WHERE id=?")
                ->execute([$nowSql, $paidFromAccountId, $ledgerId, $advanceDeducted, $payableSalary, $payrollId]);
        } else {
            $pdo->prepare("UPDATE payroll SET status='Paid', payment_date=?, paid_from_account_id=?, ledger_entry_id=? WHERE id=?")
                ->execute([$nowSql, $paidFromAccountId, $ledgerId, $payrollId]);
        }

        $pdo->commit();
        flash('success', "Salary settled for {$pay['staff_name']}. Net $" . number_format($netSalary, 2) . ", advance adjusted $" . number_format($advanceDeducted, 2) . ", paid now $" . number_format($payableSalary, 2) . ".");
        app_log('ACTION', "Payroll#$payrollId paid for user_id={$pay['user_id']} net={$netSalary} advance={$advanceDeducted} payable={$payableSalary}");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Payment failed: ' . $e->getMessage());
        app_log('ERROR', 'Payroll payment failed: ' . $e->getMessage());
    }

    redirect('index.php?month=' . $redirectMonth . '&year=' . $redirectYear);
}

//  ”  ”  Read payroll list  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));

$listPeriodStart = sprintf('%04d-%02d-15', $year, $month);
$listPeriodNextM = $month === 12 ? 1 : $month + 1;
$listPeriodNextY = $month === 12 ? $year + 1 : $year;
$listPeriodEnd   = sprintf('%04d-%02d-14', $listPeriodNextY, $listPeriodNextM);

$_pySql  = "SELECT p.*, u.name AS staff_name, s.role AS staff_role, ba.name AS paid_from_name FROM payroll p JOIN users u ON u.id=p.user_id JOIN staff s ON s.id=u.staff_id LEFT JOIN bank_accounts ba ON ba.id=p.paid_from_account_id WHERE p.month=? AND p.year=? ORDER BY u.name";
$_pyCnt  = "SELECT COUNT(*) FROM payroll p JOIN users u ON u.id=p.user_id JOIN staff s ON s.id=u.staff_id WHERE p.month=? AND p.year=?";
$pgPayroll   = paginate_query($pdo, $_pySql, $_pyCnt, [$month, $year], $page, $perPage);
$payrollRows = $pgPayroll['rows'];

$advanceOutstandingByUser = $getAdvanceOutstandingMap(array_column($payrollRows, 'user_id'));

// Totals + display fields
$totalBasic = 0.0;
$totalIncentive = 0.0;
$totalNet = 0.0;
$totalOvertime = 0.0;
$totalAdvance = 0.0;
$totalPayable = 0.0;
$totalPaid = 0.0;
foreach ($payrollRows as &$row) {
    $basic = (float) ($row['basic_salary'] ?? 0);
    $incentive = (float) ($row['incentive'] ?? 0);
    $net = (float) ($row['net_salary'] ?? 0);
    $isPaid = ($row['status'] ?? '') === 'Paid';

    if ($advanceSchemaReady) {
        $storedAdvance = isset($row['advance_deducted']) ? (float) $row['advance_deducted'] : 0.0;
        $storedPayable = isset($row['payable_salary']) ? (float) $row['payable_salary'] : max(0, $net - $storedAdvance);

        if ($isPaid) {
            $advanceDeducted = max(0, $storedAdvance);
            $payableSalary = max(0, $storedPayable);
            $outstandingAdvance = 0.0;
        } else {
            $outstandingAdvance = max(0, (float) ($advanceOutstandingByUser[$row['user_id']] ?? 0));
            $advanceDeducted = min($net, $outstandingAdvance);
            $payableSalary = max(0, $net - $advanceDeducted);
        }
    } else {
        $outstandingAdvance = 0.0;
        $advanceDeducted = 0.0;
        $payableSalary = $net;
    }

    $row['display_outstanding_advance'] = $outstandingAdvance;
    $row['display_advance_deducted'] = round($advanceDeducted, 2);
    $row['display_payable_salary'] = round($payableSalary, 2);

    $totalBasic += $basic;
    $totalIncentive += $incentive;
    $totalOvertime += (float) ($row['overtime_pay'] ?? 0);
    $totalNet += $net;
    $totalAdvance += $row['display_advance_deducted'];
    $totalPayable += $row['display_payable_salary'];
    if ($isPaid) {
        $totalPaid += $row['display_payable_salary'];
    }
}
unset($row);

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
    <?php if (!$advanceSchemaReady): ?>
        <div class="flex items-center gap-3 bg-yellow-500/10 border border-yellow-500/30 text-yellow-300 rounded-xl px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7.938 4h15.876c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L2.33 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                Payroll advances are disabled because the latest migration is not applied.
                <?php if ($advanceSchemaError): ?>
                    <span class="text-yellow-200/80">Schema check error: <?= e($advanceSchemaError) ?></span>
                <?php endif; ?>
            </div>
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
                                <th class="px-6 py-3 text-right">Overtime</th>
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
                                $tableIncentive = $staffIncentivesMap[$s['user_id']] ?? 0;
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
                                <td class="px-6 py-4 text-right"><input type="number" name="incentive[<?= $s['user_id'] ?>]" class="incentive-input bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1.5 text-white text-sm w-28 text-right focus:outline-none focus:border-mb-accent" value="<?= $autoIncentive + $tableIncentive ?>" min="0" step="0.01" data-basic="<?= $basic ?>" data-auto="<?= $autoIncentive + $tableIncentive ?>" data-table="<?= $tableIncentive ?>" data-overtime="<?= $s['overtime_pay'] ?>" oninput="updateNet(this)"><?php if($tableIncentive > 0):?><p class="text-[10px] text-green-400/70 mt-0.5">+<?= $tableIncentive ?> from profile</p><?php endif;?></td>
                                <td class="px-6 py-4 text-right">
                                    <input type="hidden" name="overtime[<?= $s['user_id'] ?>]" value="<?= $s['overtime_pay'] ?>">
                                    <?php if ($s['overtime_pay'] > 0): ?>
                                    <span class="text-purple-400 font-medium">$<?= number_format($s['overtime_pay'], 2) ?></span>
                                    <p class="text-[10px] text-purple-300/70 mt-0.5"><?= floor($s['overtime_extra_secs']/3600) ?>h <?= floor(($s['overtime_extra_secs']%3600)/60) ?>m extra</p>
                                    <?php else: ?>
                                    <span class="text-mb-subtle/40 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right text-mb-accent font-medium net-cell">$<?= number_format($basic + $autoIncentive + $s['overtime_pay'], 2) ?></td>
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
                                <td class="px-6 py-3 text-right text-purple-400" id="totalOvertimeFooter">$<?= number_format(array_sum(array_column($batchStaff, 'overtime_pay')), 2) ?></td>
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
                        <?php for ($m = 1; $m <= 12; $m++):
                            $mn = $m === 12 ? 1 : $m + 1;
                        ?>
                            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                                15 <?= date('M', mktime(0,0,0,$m,1)) ?> – 14 <?= date('M', mktime(0,0,0,$mn,1)) ?>
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
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <?php foreach ([
                    ['Total Basic', '$' . number_format($totalBasic, 2), 'text-mb-silver'],
                    ['Total Incentive', '$' . number_format($totalIncentive, 2), 'text-blue-400'],
                    ['Total Overtime', '$' . number_format($totalOvertime, 2), 'text-purple-400'],
                    ['Total Net', '$' . number_format($totalNet, 2), 'text-mb-accent'],
                    ['Advance Adj.', '$' . number_format($totalAdvance, 2), 'text-orange-400'],
                    ['Total Payable', '$' . number_format($totalPayable, 2), 'text-green-400'],
                    ['Total Paid', '$' . number_format($totalPaid, 2), 'text-emerald-300'],
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
                    <p class="text-mb-subtle mt-1">
                        No payroll for <span class="text-white"><?= date('F', mktime(0, 0, 0, $month, 1)) ?> <?= $year ?></span> billing period.<br>
                        <span class="text-xs opacity-60">(<?= date('d M', strtotime($listPeriodStart)) ?> - <?= date('d M Y', strtotime($listPeriodEnd)) ?>)</span>
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
                                <th class="px-6 py-4 text-right">Overtime</th>
                                <th class="px-6 py-4 text-right">Net Salary</th>
                                <th class="px-6 py-4 text-right">Advance</th>
                                <th class="px-6 py-4 text-right">Payable</th>
                                <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 text-left whitespace-nowrap">Paid From</th>
                                <th class="px-6 py-4 text-left whitespace-nowrap">Action</th>
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
                                        <?= e($row['staff_role'] ?? '--') ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-mb-silver">$
                                        <?= number_format($row['basic_salary'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-blue-400">+$
                                        <?= number_format($row['incentive'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-purple-400 font-medium">
                                        <?php if ((float)($row['overtime_pay'] ?? 0) > 0): ?>
                                        +$<?= number_format((float)($row['overtime_pay'] ?? 0), 2) ?>
                                        <?php else: ?>
                                        <span class="text-mb-subtle/40">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-mb-accent font-semibold">$
                                        <?= number_format($row['net_salary'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-orange-400 font-medium">
                                        -$<?= number_format((float) ($row['display_advance_deducted'] ?? 0), 2) ?>
                                        <?php if ($row['status'] === 'Pending' && (float) ($row['display_outstanding_advance'] ?? 0) > 0): ?>
                                            <p class="text-[11px] text-orange-300/80 mt-0.5">
                                                Outstanding: $<?= number_format((float) $row['display_outstanding_advance'], 2) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-green-400 font-semibold">$
                                        <?= number_format((float) ($row['display_payable_salary'] ?? $row['net_salary']), 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($row['status'] === 'Paid'): ?>
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs border bg-green-500/10 text-green-400 border-green-500/30 whitespace-nowrap">
                                                Paid <?= $row['payment_date'] ? date('d M', strtotime($row['payment_date'])) : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs border bg-yellow-500/10 text-yellow-400 border-yellow-500/30 whitespace-nowrap">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-mb-subtle text-xs whitespace-nowrap">
                                        <?= e($row['paid_from_name'] ?? '--') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <div class="flex flex-wrap items-center gap-2">

                                                <button type="button"
                                                    onclick="openPayModal(<?= (int) $row['id'] ?>, '<?= addslashes($row['staff_name']) ?>', <?= (float) $row['net_salary'] ?>, <?= (float) ($row['display_advance_deducted'] ?? 0) ?>, <?= (float) ($row['display_payable_salary'] ?? $row['net_salary']) ?>)"
                                                    class="text-xs bg-mb-accent/15 text-mb-accent border border-mb-accent/30 px-3 py-1.5 rounded-full hover:bg-mb-accent/25 transition-colors">
                                                    Pay Now
                                                </button>
                                            </div>
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
                        <?php for ($m = 1; $m <= 12; $m++):
                            $mn = $m === 12 ? 1 : $m + 1;
                        ?>
                            <option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>>
                                15 <?= date('M', mktime(0,0,0,$m,1)) ?> – 14 <?= date('M', mktime(0,0,0,$mn,1)) ?>
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
                <div class="mt-2 space-y-1 text-xs">
                    <div class="flex items-center justify-between text-mb-silver">
                        <span>Net Salary</span>
                        <span id="payModalNet"></span>
                    </div>
                    <div class="flex items-center justify-between text-orange-300">
                        <span>Advance Deducted</span>
                        <span id="payModalAdvance"></span>
                    </div>
                    <div class="flex items-center justify-between text-mb-accent font-semibold border-t border-mb-subtle/20 pt-1 mt-1">
                        <span>Payable Now</span>
                        <span id="payModalPayable"></span>
                    </div>
                </div>
                <p class="text-mb-accent font-semibold text-lg mt-2" id="payModalAmount"></p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Pay From Bank Account <span
                        class="text-red-400">*</span></label>
                <select name="bank_account_id" id="payModalBankAccount" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value=""> -- Select account --</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= $ba['id'] ?>">
                            <?= e($ba['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="payModalBankHint" class="hidden text-xs text-mb-subtle mt-1">No bank payout needed. Salary is fully adjusted by advances.</p>
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

<!-- Advance modal removed: give advances from Staff Profile -->

<script>
    // Backdrop close
    ['generateModal', 'payModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', function (e) { if (e.target === this) this.classList.add('hidden'); });
    });

    function formatMoney(amount) {
        return '$' + (parseFloat(amount) || 0).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Pay modal
    function openPayModal(id, name, netSalary, advanceDeducted, payableNow) {
        document.getElementById('payModalId').value = id;
        document.getElementById('payModalName').textContent = name;
        document.getElementById('payModalNet').textContent = formatMoney(netSalary);
        document.getElementById('payModalAdvance').textContent = '-' + formatMoney(advanceDeducted);
        document.getElementById('payModalPayable').textContent = formatMoney(payableNow);
        document.getElementById('payModalAmount').textContent = 'Payable: ' + formatMoney(payableNow);
        const needsBank = (parseFloat(payableNow) || 0) > 0;
        const bankSelect = document.getElementById('payModalBankAccount');
        const bankHint = document.getElementById('payModalBankHint');
        if (bankSelect) {
            bankSelect.required = needsBank;
            bankSelect.disabled = !needsBank;
            if (!needsBank) { bankSelect.value = ''; }
        }
        if (bankHint) { bankHint.classList.toggle('hidden', needsBank); }
        document.getElementById('payModal').classList.remove('hidden');
    }

    // Give Advance: use Staff Profile page

    // Batch: update single row net
    // data-basic = basic salary | data-auto = lead auto incentive (pre-computed)
    function updateNet(input) {
        const manualInc = parseFloat(input.value) || 0;
        const basic = parseFloat(input.dataset.basic) || 0;
        const overtime = parseFloat(input.dataset.overtime) || 0;
        const cell = input.closest('tr').querySelector('.net-cell');
        cell.textContent = '$' + (basic + manualInc + overtime).toLocaleString('en', { minimumFractionDigits: 2 });
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
        let totalInc = 0, totalOt = 0, totalNet = 0;
        document.querySelectorAll('.incentive-input').forEach(inp => {
            const basic = parseFloat(inp.dataset.basic) || 0;
            const inc = parseFloat(inp.value) || 0;
            const ot = parseFloat(inp.dataset.overtime) || 0;
            totalInc += inc;
            totalOt += ot;
            totalNet += basic + inc + ot;
        });
        const ti = document.getElementById('totalIncentiveFooter');
        const tn = document.getElementById('totalNetFooter');
        if (ti) ti.textContent = '$' + totalInc.toLocaleString('en', { minimumFractionDigits: 2 });
        const to = document.getElementById('totalOvertimeFooter');
        if (to) to.textContent = '$' + totalOt.toLocaleString('en', { minimumFractionDigits: 2 });
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
