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

if (!in_array($r['status'], ['active', 'completed'])) {
    $pdo->prepare('DELETE FROM reservations WHERE id=?')->execute([$id]);
    app_log('ACTION', "Deleted reservation (ID: $id)");
    flash('success', 'Reservation cancelled and removed.');
    redirect('index.php');
} else {
    flash('error', 'Cannot delete an active or completed reservation.');
    redirect('index.php');
}
