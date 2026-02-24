<?php
require_once __DIR__ . '/../config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
$cStmt = $pdo->prepare('SELECT * FROM clients WHERE id=?');
$cStmt->execute([$id]);
$c = $cStmt->fetch();
if (!$c) {
    flash('error', 'Client not found.');
    redirect('index.php');
}

// Guard: cannot delete if active reservations exist
$chk = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE client_id=? AND status IN ('pending','confirmed','active')");
$chk->execute([$id]);
$activeCount = $chk->fetchColumn();
if ($activeCount > 0) {
    flash('error', "Cannot delete {$c['name']} — they have $activeCount active reservation(s).");
    redirect('index.php');
}

$pdo->prepare('DELETE FROM clients WHERE id=?')->execute([$id]);
flash('success', "{$c['name']} has been removed.");
redirect('index.php');
