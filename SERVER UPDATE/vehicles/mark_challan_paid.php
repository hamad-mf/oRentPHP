<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$pdo = db();

$challanId        = (int)   ($_POST['id']                 ?? 0);
$vehicleId        = (int)   ($_POST['vehicle_id']         ?? 0);
$paidBy           = trim($_POST['paid_by']           ?? 'company');   // 'company' | 'customer'
$paymentMode      = trim($_POST['payment_mode']      ?? '');          // cash | account | credit (company only)
$bankAccountId    = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
$paymentDate      = trim($_POST['payment_date']      ?? '');          // company paid date
$customerNotes    = trim($_POST['customer_notes']    ?? '');
$customerPaidDate = trim($_POST['customer_paid_date'] ?? '');
$redirectTo       = trim($_POST['redirect_to']       ?? '');
$currentUser      = current_user();

// ── Validation ────────────────────────────────────────────────────────────────

if ($challanId <= 0) {
    flash('error', 'Invalid challan request.');
    redirect($redirectTo === 'challans' ? 'challans.php' : 'index.php');
}

if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to pay challans.');
    redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
}

$validPaidBy = ['company', 'customer'];
if (!in_array($paidBy, $validPaidBy, true)) {
    flash('error', 'Invalid paid_by value.');
    redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
}

// ── Fetch challan ─────────────────────────────────────────────────────────────

try {
    $query = $vehicleId > 0
        ? 'SELECT * FROM vehicle_challans WHERE id = ? AND vehicle_id = ?'
        : 'SELECT * FROM vehicle_challans WHERE id = ?';
    $params = $vehicleId > 0 ? [$challanId, $vehicleId] : [$challanId];

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $challan = $stmt->fetch();

    if (!$challan) {
        flash('error', 'Challan not found.');
        redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
    }

    // Use vehicle_id from the challan record (covers the case where it wasn't posted)
    $vehicleId = (int)$challan['vehicle_id'];

    // ── CUSTOMER PAID ─────────────────────────────────────────────────────────

    if ($paidBy === 'customer') {
        $paidDateValue = ($customerPaidDate !== '') ? $customerPaidDate : date('Y-m-d');

        // Append note
        $existingNotes = trim((string)($challan['notes'] ?? ''));
        $noteAppend    = 'Customer paid on ' . date('d M Y', strtotime($paidDateValue));
        if ($customerNotes !== '') {
            $noteAppend .= ': ' . $customerNotes;
        }
        $newNotes = $existingNotes !== '' ? $existingNotes . "\n" . $noteAppend : $noteAppend;

        // Try updating with new columns; fall back gracefully if migration not yet run
        try {
            $upd = $pdo->prepare('UPDATE vehicle_challans SET status="paid", paid_by="customer", paid_date=?, notes=? WHERE id=?');
            $upd->execute([$paidDateValue, $newNotes, $challanId]);
        } catch (Throwable $ex) {
            // Columns may not exist yet — fall back to status-only update
            $upd = $pdo->prepare('UPDATE vehicle_challans SET status="paid", notes=? WHERE id=?');
            $upd->execute([$newNotes, $challanId]);
        }

        app_log('ACTION', "Challan marked as customer paid (ID: $challanId, Amount: {$challan['amount']}) for vehicle ID: $vehicleId");
        flash('success', 'Challan marked as paid by customer.');

        redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
    }

    // ── COMPANY PAID ─────────────────────────────────────────────────────────

    ledger_ensure_schema($pdo);

    $validModes = ['cash', 'credit', 'account'];
    if (!in_array($paymentMode, $validModes, true)) {
        flash('error', 'Invalid payment mode.');
        redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
    }

    // Resolve bank account
    $resolvedBankId = null;
    if ($paymentMode === 'account') {
        if ($bankAccountId && $bankAccountId > 0) {
            $baStmt = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND is_active = 1 LIMIT 1");
            $baStmt->execute([$bankAccountId]);
            if ($baStmt->fetch()) {
                $resolvedBankId = $bankAccountId;
            }
        }
        if (!$resolvedBankId) {
            $resolvedBankId = ledger_resolve_bank_account_id($pdo, 'account', null);
        }
        if (!$resolvedBankId) {
            flash('error', 'No active bank account found. Please add a bank account first.');
            redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
        }
    }

    // Parse payment date
    $postedAt = null;
    if ($paymentDate !== '') {
        $dateCheck = DateTime::createFromFormat('Y-m-d', $paymentDate);
        if ($dateCheck && $dateCheck->format('Y-m-d') === $paymentDate) {
            $postedAt = $paymentDate . ' ' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('H:i:s');
        } else {
            $postedAt = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        }
    } else {
        $postedAt = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    }

    // Update challan status — try with new columns first, fall back if needed
    try {
        $paidDateValue = $paymentDate !== '' ? $paymentDate : date('Y-m-d');
        $upd = $pdo->prepare('UPDATE vehicle_challans SET status="paid", paid_by="company", paid_date=?, payment_mode=? WHERE id=?');
        $upd->execute([$paidDateValue, $paymentMode, $challanId]);
    } catch (Throwable $ex) {
        $upd = $pdo->prepare('UPDATE vehicle_challans SET status="paid" WHERE id=?');
        $upd->execute([$challanId]);
    }

    // Build ledger description
    $vStmt = $pdo->prepare('SELECT brand, model FROM vehicles WHERE id = ?');
    $vStmt->execute([$vehicleId]);
    $vehicle     = $vStmt->fetch();
    $vehicleName = $vehicle ? ($vehicle['brand'] . ' ' . $vehicle['model']) : 'Vehicle #' . $vehicleId;

    $ledgerDesc = 'Challan Paid - ' . $vehicleName . ' - ' . $challan['title'];
    if ($challan['due_date']) {
        $ledgerDesc .= ' (Due: ' . date('d M Y', strtotime($challan['due_date'])) . ')';
    }

    ledger_post(
        $pdo,
        'expense',
        'Traffic Challan',
        (float) $challan['amount'],
        $paymentMode,
        $resolvedBankId,
        'challan_payment',
        $challanId,
        'paid',
        $ledgerDesc,
        (int) ($currentUser['id'] ?? 0),
        'challan_' . $challanId . '_paid_' . time(),
        $postedAt
    );

    app_log('ACTION', "Challan paid (ID: $challanId, Amount: {$challan['amount']}, Mode: $paymentMode) for vehicle ID: $vehicleId");
    flash('success', 'Challan marked as paid. $' . number_format($challan['amount'], 2) . ' posted to ledger as Traffic Challan expense.');

} catch (Throwable $e) {
    app_log('ERROR', 'Failed to pay challan - ' . $e->getMessage(), [
        'file'       => $e->getFile() . ':' . $e->getLine(),
        'challan_id' => $challanId,
        'vehicle_id' => $vehicleId,
    ]);
    flash('error', 'Failed to process payment. Please try again.');
}

redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");