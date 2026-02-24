<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

// Auto-create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('due_today','due_soon','overdue','info') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    reservation_id INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// Auto-generate notifications for due-soon reservations (idempotent – won't duplicate today)
$dueSoon = $pdo->query("
    SELECT r.id, c.name AS client, v.brand, v.model, r.end_date,
           DATEDIFF(DATE(r.end_date), CURDATE()) AS days_left
    FROM reservations r
    JOIN clients c ON r.client_id = c.id
    JOIN vehicles v ON r.vehicle_id = v.id
    WHERE r.status = 'active'
      AND DATE(r.end_date) <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
")->fetchAll();

foreach ($dueSoon as $row) {
    $daysLeft = (int) $row['days_left'];
    $type = $daysLeft <= 0 ? 'overdue' : ($daysLeft === 0 ? 'due_today' : 'due_soon');
    if ($daysLeft < 0) {
        $type = 'overdue';
        $msg = "⚠️ Overdue! {$row['client']}'s {$row['brand']} {$row['model']} was due " . abs($daysLeft) . " day(s) ago.";
    } elseif ($daysLeft === 0) {
        $type = 'due_today';
        $msg = "🔴 Due Today: {$row['client']}'s {$row['brand']} {$row['model']} must be returned today.";
    } else {
        $type = 'due_soon';
        $msg = "🟡 Due Soon: {$row['client']}'s {$row['brand']} {$row['model']} is due in {$daysLeft} day(s).";
    }
    // Only insert if no notification exists for this reservation created today
    $exists = $pdo->prepare("SELECT id FROM notifications WHERE reservation_id = ? AND DATE(created_at) = CURDATE()");
    $exists->execute([$row['id']]);
    if (!$exists->fetch()) {
        $pdo->prepare("INSERT INTO notifications (type, message, reservation_id) VALUES (?, ?, ?)")
            ->execute([$type, $msg, $row['id']]);
    }
}

/**
 * Returns unread notification count (for badge)
 */
function notif_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
}

/**
 * Returns all notifications ordered newest first
 */
function notif_all(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM notifications ORDER BY is_read ASC, created_at DESC LIMIT 50")->fetchAll();
}
