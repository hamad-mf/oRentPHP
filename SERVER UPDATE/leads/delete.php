<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_log.php';
auth_check();
if (!auth_has_perm('add_leads')) {
    flash('error', 'You are not allowed to delete leads.');
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
$pdo = db();
$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    $leadStmt = $pdo->prepare('SELECT name FROM leads WHERE id=? LIMIT 1');
    $leadStmt->execute([$id]);
    $leadName = (string) ($leadStmt->fetchColumn() ?: '');

    $pdo->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
    $description = $leadName !== '' ? "Deleted lead #$id ($leadName)." : "Deleted lead #$id.";
    log_activity($pdo, 'deleted_lead', 'lead', $id, $description);
}
app_log('ACTION', "Deleted lead (ID: $id)");
flash('success', 'Lead deleted.');
redirect('index.php');
