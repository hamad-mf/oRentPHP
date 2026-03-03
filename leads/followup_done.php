<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_log.php';
auth_check();
if (!auth_has_perm('add_leads')) {
    flash('error', 'You are not allowed to update follow-ups.');
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../leads/index.php');
}
$pdo = db();
$fid = (int) ($_POST['followup_id'] ?? 0);
$leadId = (int) ($_POST['lead_id'] ?? 0);
if (!$leadId || !$fid) {
    flash('error', 'Invalid follow-up request.');
    redirect('index.php');
}

$followupStmt = $pdo->prepare('SELECT is_done FROM lead_followups WHERE id=? AND lead_id=? LIMIT 1');
$followupStmt->execute([$fid, $leadId]);
$followup = $followupStmt->fetch();
if (!$followup) {
    flash('error', 'Follow-up not found for this lead.');
    redirect("show.php?id=$leadId");
}

if (!(int) $followup['is_done']) {
    $pdo->prepare('UPDATE lead_followups SET is_done=1 WHERE id=? AND lead_id=?')->execute([$fid, $leadId]);
    $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$leadId, 'Follow-up marked as done.']);

    log_activity($pdo, 'completed_followup', 'lead', $leadId, "Marked follow-up #$fid as done for lead #$leadId.");

    app_log('ACTION', "Marked followup done for lead (ID: $leadId)");
    flash('success', 'Follow-up marked as done.');
} else {
    flash('success', 'Follow-up is already marked as done.');
}
redirect("show.php?id=$leadId");
