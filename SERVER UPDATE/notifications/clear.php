<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'clear_all') {
    $pdo->exec("DELETE FROM notifications");
} elseif ($action === 'mark_read' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([(int) $_POST['id']]);
} elseif ($action === 'mark_all_read') {
    $pdo->exec("UPDATE notifications SET is_read=1");
}

// Redirect back to dashboard or referrer
$ref = $_SERVER['HTTP_REFERER'] ?? '../index.php';
header("Location: $ref");
exit;
