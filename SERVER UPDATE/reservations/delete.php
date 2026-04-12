<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_reservations')) {
    flash('error', 'You do not have permission to delete reservations.');
    redirect('index.php');
}
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$rStmt = $pdo->prepare('SELECT r.*, v.brand, v.model FROM reservations r JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r) {
    flash('error', 'Reservation not found.');
    redirect('index.php');
}

if (in_array($r['status'], ['active', 'completed'])) {
    flash('error', 'Cannot delete an active or completed reservation.');
    redirect('index.php');
}

// Safety: if there's any financial activity, route through cancel.php for proper cleanup
$advancePaid = (float)($r['advance_paid'] ?? 0);
$voucherApplied = (float)($r['voucher_applied'] ?? 0);
$deliveryPrepaid = (float)($r['delivery_charge_prepaid'] ?? 0);
if ($advancePaid > 0 || $voucherApplied > 0 || $deliveryPrepaid > 0) {
    // Has financial activity — must use cancel flow for proper ledger/voucher reversal
    redirect("cancel.php?id=$id");
}

// No financial activity — safe to delete outright
$pdo->prepare('DELETE FROM reservations WHERE id=?')->execute([$id]);

require_once __DIR__ . '/../includes/activity_log.php';
log_activity($pdo, 'delete_reservation', 'reservation', $id, "Removed reservation #{$id} ({$r['brand']} {$r['model']}) — no financial activity.");
app_log('ACTION', "Deleted reservation (ID: $id)");
flash('success', 'Reservation removed.');
redirect('index.php');
