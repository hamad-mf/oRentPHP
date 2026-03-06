<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_log.php';
auth_check();
if (!auth_has_perm('add_leads')) {
    flash('error', 'You are not allowed to schedule follow-ups.');
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../leads/index.php');
}
$pdo = db();
$leadId = (int) ($_POST['lead_id'] ?? 0);
$type = in_array($_POST['type'] ?? '', ['call', 'meeting', 'email', 'whatsapp']) ? $_POST['type'] : 'call';
$date = $_POST['date'] ?? '';
$timeHour = (int) ($_POST['time_hour'] ?? 10);
$timeMinute = (int) ($_POST['time_minute'] ?? 0);
$timeAmpm = strtoupper($_POST['time_ampm'] ?? 'AM');
$notes = trim($_POST['notes'] ?? '');
if (!$leadId) {
    flash('error', 'Invalid lead selected.');
    redirect('index.php');
}
if (!$date) {
    flash('error', 'Please select a date for the follow-up.');
    redirect("show.php?id=$leadId");
}

$hour24 = $timeHour;
if ($timeAmpm === 'PM' && $hour24 !== 12) {
    $hour24 += 12;
} elseif ($timeAmpm === 'AM' && $hour24 === 12) {
    $hour24 = 0;
}
$time = sprintf('%02d:%02d', $hour24, $timeMinute);

$scheduledDt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
$dtErrors = DateTime::getLastErrors();
if (
    !$scheduledDt ||
    ($dtErrors && ($dtErrors['warning_count'] > 0 || $dtErrors['error_count'] > 0)) ||
    $scheduledDt->format('Y-m-d H:i') !== ($date . ' ' . $time)
) {
    flash('error', 'Invalid follow-up date/time format.');
    redirect("show.php?id=$leadId");
}

$now = new DateTime();
if ($scheduledDt <= $now) {
    flash('error', 'Follow-up must be scheduled in the future.');
    redirect("show.php?id=$leadId");
}

$scheduledAt = $scheduledDt->format('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO lead_followups (lead_id, type, scheduled_at, notes) VALUES (?,?,?,?)')
    ->execute([$leadId, $type, $scheduledAt, $notes ?: null]);
$pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')
    ->execute([$leadId, "Follow-up scheduled: " . ucfirst($type) . " on " . $scheduledDt->format('d M Y, h:i A') . "."]);

$staffLogDescription = "Scheduled " . ucfirst($type) . " follow-up for lead #$leadId on " . $scheduledDt->format('d M Y, h:i A') . ".";
log_activity($pdo, 'scheduled_followup', 'lead', $leadId, $staffLogDescription);

app_log('ACTION', "Scheduled followup for lead (ID: $leadId)");
flash('success', 'Follow-up scheduled.');
redirect("show.php?id=$leadId");
