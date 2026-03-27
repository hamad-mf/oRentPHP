<?php
/**
 * reservations/resolve_held_deposit.php
 *
 * Handles two POST actions for a completed reservation's held deposit:
 *   action=release   → Returns held amount to client (deposit_returned += deposit_held)
 *   action=convert   → Converts held amount to income  (deposit_deducted += deposit_held)
 *
 * Both clear deposit_held and set deposit_held_action / deposit_held_resolved_at.
 */
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('do_return')) {
    flash('error', 'You do not have permission to resolve held deposits.');
    redirect('index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

$id     = (int) ($_POST['reservation_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$pdo    = db();

if (!in_array($action, ['release', 'convert'], true) || $id <= 0) {
    flash('error', 'Invalid request.');
    redirect('index.php');
}

// ── Runtime schema guard (safe if migration already ran) ──────────────────────
try {
    $pdo->exec("ALTER TABLE reservations
        ADD COLUMN IF NOT EXISTS deposit_held_resolved_at DATETIME    DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS deposit_held_action      VARCHAR(20) DEFAULT NULL");
} catch (Throwable $e) {
    // Columns already exist — ignore
}

// ── Load reservation ──────────────────────────────────────────────────────────
$rStmt = $pdo->prepare('SELECT r.*, v.brand, v.model, c.name AS client_name FROM reservations r
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN clients  c ON r.client_id  = c.id
    WHERE r.id = ?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();

if (!$r) {
    flash('error', 'Reservation not found.');
    redirect('index.php');
}
if ($r['status'] !== 'completed') {
    flash('error', 'Held deposit can only be resolved on completed reservations.');
    redirect("show.php?id=$id");
}

$depHeld   = max(0, (float) ($r['deposit_held'] ?? 0));
$heldAction = $r['deposit_held_action'] ?? null;

if ($depHeld <= 0) {
    flash('error', 'No held deposit amount to resolve for this reservation.');
    redirect("show.php?id=$id");
}
if ($heldAction !== null) {
    flash('error', 'This held deposit has already been resolved.');
    redirect("show.php?id=$id");
}

// ── Resolve ───────────────────────────────────────────────────────────────────
$ledgerUserId = (int) ($_SESSION['user']['id'] ?? 0);
$now          = (new DateTime())->format('Y-m-d H:i:s');

ledger_ensure_schema($pdo);
settings_ensure_table($pdo);

$depositBankAccountId = ledger_get_security_deposit_account_id($pdo, $id)
    ?? ledger_get_active_bank_account_id($pdo, (int) settings_get($pdo, 'security_deposit_bank_account_id', '0'));

try {
    if ($action === 'release') {
        // ── Release: add to deposit_returned, post ledger OUT ────────────────
        $newReturned = (float) ($r['deposit_returned'] ?? 0) + $depHeld;

        $pdo->prepare("UPDATE reservations
            SET deposit_returned          = ?,
                deposit_held              = 0,
                deposit_hold_reason       = NULL,
                deposit_held_at           = NULL,
                deposit_held_action       = 'released',
                deposit_held_resolved_at  = ?
            WHERE id = ?")
            ->execute([$newReturned, $now, $id]);

        if ($depositBankAccountId !== null) {
            ledger_post_security_deposit($pdo, $id, 'out', $depHeld, $depositBankAccountId, $ledgerUserId);
        } else {
            app_log('WARNING', "Held deposit released for reservation #$id but no deposit bank account configured — ledger skipped.");
        }

        $msg = "Held deposit of \${$depHeld} released to client for reservation #{$id} ({$r['client_name']}).";
        app_log('ACTION', $msg);

    } else {
        // ── Convert: add to deposit_deducted, post ledger EXPENSE then INCOME ─
        $newDeducted = (float) ($r['deposit_deducted'] ?? 0) + $depHeld;

        $pdo->prepare("UPDATE reservations
            SET deposit_deducted          = ?,
                deposit_held              = 0,
                deposit_hold_reason       = NULL,
                deposit_held_at           = NULL,
                deposit_held_action       = 'converted',
                deposit_held_resolved_at  = ?
            WHERE id = ?")
            ->execute([$newDeducted, $now, $id]);

        if ($depositBankAccountId !== null) {
            // Move out of deposit tracking
            ledger_post($pdo, 'expense', 'Security Deposit', $depHeld, 'account', $depositBankAccountId,
                'reservation', $id, 'security_deposit_deducted',
                "Reservation #$id — Held deposit converted to income",
                $ledgerUserId, "reservation:held_converted_deducted:$id");

            // Post as real income
            ledger_post($pdo, 'income', 'Damage Charges', $depHeld, 'account', $depositBankAccountId,
                'reservation', $id, 'damage_from_deposit',
                "Reservation #$id — Held deposit converted to income",
                $ledgerUserId, "reservation:held_converted_income:$id");
        } else {
            app_log('WARNING', "Held deposit converted for reservation #$id but no deposit bank account configured — ledger skipped.");
        }

        $msg = "Held deposit of \${$depHeld} converted to income for reservation #{$id} ({$r['client_name']}).";
        app_log('ACTION', $msg);
    }

    // Log staff activity
    require_once __DIR__ . '/../includes/activity_log.php';
    log_activity($pdo, $action === 'release' ? 'deposit_released' : 'deposit_converted',
        'reservation', $id, $msg);

    flash('success', $action === 'release'
        ? 'Held deposit released to client successfully.'
        : 'Held deposit converted to income successfully.');

} catch (Throwable $e) {
    app_log('ERROR', "resolve_held_deposit failed for reservation #$id: " . $e->getMessage());
    flash('error', 'Could not resolve held deposit. Please try again.');
}

redirect("show.php?id=$id");