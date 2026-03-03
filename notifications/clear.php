<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$pdo = db();
$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$notifId = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
$go = trim((string) ($_POST['go'] ?? ($_GET['go'] ?? '')));

if ($action === 'clear_all') {
    $pdo->exec("DELETE FROM notifications");
} elseif ($action === 'mark_read' && $notifId > 0) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([$notifId]);
} elseif ($action === 'mark_all_read') {
    $pdo->exec("UPDATE notifications SET is_read=1");
}

// Redirect back (or to explicit internal target on click-through).
$redirectTo = $_SERVER['HTTP_REFERER'] ?? '../index.php';
if ($go !== '') {
    $parts = parse_url($go);
    $isLocal = $parts !== false && !isset($parts['scheme']) && !isset($parts['host']) && !preg_match('/[\r\n]/', $go);
    if ($isLocal) {
        $redirectTo = $go;
    }
}

app_log('ACTION', "Notification action: {$action}" . ($notifId > 0 ? " (id: {$notifId})" : ''));
header("Location: {$redirectTo}");
exit;
