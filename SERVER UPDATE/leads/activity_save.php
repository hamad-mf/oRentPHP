<?php
require_once __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../leads/index.php');
}
$pdo = db();
$leadId = (int) ($_POST['lead_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
if ($leadId && $note) {
    $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$leadId, $note]);
}
redirect("show.php?id=$leadId");
