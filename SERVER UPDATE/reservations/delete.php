<?php
require_once __DIR__ . '/../config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$rStmt = $pdo->prepare('SELECT r.*, v.brand, v.model FROM reservations r JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r) {
    flash('error', 'Reservation not found.');
    redirect('index.php');
}

if (!in_array($r['status'], ['active', 'completed'])) {
    // Free vehicle if rented
    if ($r['status'] === 'confirmed') {
        $vChk = $pdo->prepare('SELECT status FROM vehicles WHERE id=?');
        $vChk->execute([$r['vehicle_id']]);
        $vStatus = $vChk->fetchColumn();
        if ($vStatus === 'rented') {
            $pdo->prepare("UPDATE vehicles SET status='available' WHERE id=?")->execute([$r['vehicle_id']]);
        }
    }
    $pdo->prepare('DELETE FROM reservations WHERE id=?')->execute([$id]);
    flash('success', 'Reservation cancelled and removed.');
    redirect('index.php');
} else {
    flash('error', 'Cannot delete an active or completed reservation.');
    redirect('index.php');
}
