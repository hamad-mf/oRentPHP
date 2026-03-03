<?php
/**
 * attendance/punch.php
 * POST { action: 'punch_in' | 'punch_out' }
 * Returns JSON { ok: bool, message: string, warning: bool }
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

header('Content-Type: application/json');

auth_check();
$user = current_user();

// Admins do not use punch system
if (($user['role'] ?? '') === 'admin') {
    app_log('ACTION', 'Attendance punch recorded');
echo json_encode(['ok' => false, 'message' => 'Admins do not use the punch system.']);
    exit;
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['punch_in', 'punch_out'], true)) {
    app_log('ACTION', 'Attendance punch recorded');
echo json_encode(['ok' => false, 'message' => 'Invalid action.']);
    exit;
}

$pdo = db();
settings_ensure_table($pdo);

// ── IST helpers ──────────────────────────────────────────────────────────────
$ist = new DateTimeZone('Asia/Kolkata');

function ist_now(DateTimeZone $tz): DateTime
{
    return new DateTime('now', $tz);
}

/**
 * Parse a stored 12hr time string like "08:30 AM" → total minutes since midnight.
 */
function parse_12hr_to_minutes(string $val): int
{
    $val = trim($val);
    if ($val === '')
        return -1;
    $dt = DateTime::createFromFormat('h:i A', strtoupper($val));
    if (!$dt)
        $dt = DateTime::createFromFormat('g:i A', strtoupper($val));
    if (!$dt)
        return -1;
    return (int) $dt->format('H') * 60 + (int) $dt->format('i');
}

$nowIst = ist_now($ist);
$nowMins = (int) $nowIst->format('H') * 60 + (int) $nowIst->format('i');
$todayIst = $nowIst->format('Y-m-d');
$nowDt = $nowIst->format('Y-m-d H:i:s');

// ── Fetch today's attendance row ──────────────────────────────────────────────
$row = $pdo->prepare('SELECT * FROM staff_attendance WHERE user_id = ? AND date = ? LIMIT 1');
$row->execute([$user['id'], $todayIst]);
$rec = $row->fetch();

// ── Check punch window & set warning flag ────────────────────────────────────
$warning = false;
$warnMsg = '';

if ($action === 'punch_in') {
    $start = parse_12hr_to_minutes(settings_get($pdo, 'att_punchin_start', ''));
    $end = parse_12hr_to_minutes(settings_get($pdo, 'att_punchin_end', ''));
    if ($start >= 0 && $end >= 0 && ($nowMins < $start || $nowMins > $end)) {
        $warning = true;
        $warnMsg = 'You are punching in outside the allowed window — recorded with a warning.';
    }
    if ($rec && $rec['punch_in'] !== null) {
        app_log('ACTION', 'Attendance punch recorded');
echo json_encode(['ok' => false, 'message' => 'You have already punched in today.']);
        exit;
    }
} else {
    $start = parse_12hr_to_minutes(settings_get($pdo, 'att_punchout_start', ''));
    $end = parse_12hr_to_minutes(settings_get($pdo, 'att_punchout_end', ''));
    if ($start >= 0 && $end >= 0 && ($nowMins < $start || $nowMins > $end)) {
        $warning = true;
        $warnMsg = 'You are punching out outside the allowed window — recorded with a warning.';
    }
    if (!$rec || $rec['punch_in'] === null) {
        app_log('ACTION', 'Attendance punch recorded');
echo json_encode(['ok' => false, 'message' => 'You have not punched in today yet.']);
        exit;
    }
    if ($rec['punch_out'] !== null) {
        app_log('ACTION', 'Attendance punch recorded');
echo json_encode(['ok' => false, 'message' => 'You have already punched out today.']);
        exit;
    }
}

// ── Save ─────────────────────────────────────────────────────────────────────
try {
    if ($action === 'punch_in') {
        $stmt = $pdo->prepare(
            'INSERT INTO staff_attendance (user_id, date, punch_in, pin_warning)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE punch_in = VALUES(punch_in), pin_warning = VALUES(pin_warning)'
        );
        $stmt->execute([$user['id'], $todayIst, $nowDt, $warning ? 1 : 0]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE staff_attendance SET punch_out = ?, pout_warning = ? WHERE user_id = ? AND date = ?'
        );
        $stmt->execute([$nowDt, $warning ? 1 : 0, $user['id'], $todayIst]);
    }
} catch (Throwable $e) {
    app_log('ACTION', 'Attendance punch recorded');
echo json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$displayTime = $nowIst->format('h:i A');
$baseMsg = $action === 'punch_in'
    ? "Punched in at $displayTime"
    : "Punched out at $displayTime";

echo json_encode([
    'ok' => true,
    'message' => $baseMsg . ($warnMsg ? ' ⚠️ ' . $warnMsg : ''),
    'warning' => $warning,
    'time' => $displayTime,
]);
