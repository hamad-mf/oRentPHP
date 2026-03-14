<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
settings_ensure_table($pdo);

$notifyDueSoon = settings_get($pdo, 'notify_due_soon', '1') !== '0';
$notifyDueToday = settings_get($pdo, 'notify_due_today', '1') !== '0';
$notifyOverdue = settings_get($pdo, 'notify_overdue', '1') !== '0';
$notifyResCreated = settings_get($pdo, 'notify_res_created', '1') !== '0';
$notifyResDelivered = settings_get($pdo, 'notify_res_delivered', '1') !== '0';
$notifyResReturned = settings_get($pdo, 'notify_res_returned', '1') !== '0';
$notifyResCancelled = settings_get($pdo, 'notify_res_cancelled', '1') !== '0';

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

$dueAlertsEnabled = $notifyDueSoon || $notifyDueToday || $notifyOverdue;
// Auto-generate notifications for due-soon reservations (idempotent – won't duplicate today)
if ($dueAlertsEnabled) {
    $todayDate = app_today_sql();
    $cutoffDate = date('Y-m-d', strtotime($todayDate . ' +2 days'));
    $dueSoonStmt = $pdo->prepare("
        SELECT r.id, c.name AS client, v.brand, v.model, r.end_date
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN vehicles v ON v.id = r.vehicle_id
        WHERE r.status = 'active'
          AND DATE(r.end_date) <= ?
    ");
    $dueSoonStmt->execute([$cutoffDate]);
    $dueSoon = $dueSoonStmt->fetchAll();

    foreach ($dueSoon as $row) {
        $endDateTs = strtotime((string) ($row['end_date'] ?? ''));
        $todayTs = strtotime($todayDate . ' 00:00:00');
        if ($endDateTs === false || $todayTs === false) {
            continue;
        }
        $daysLeft = (int) floor((strtotime(date('Y-m-d', $endDateTs) . ' 00:00:00') - $todayTs) / 86400);
        $type = $daysLeft <= 0 ? 'overdue' : ($daysLeft === 0 ? 'due_today' : 'due_soon');
        if ($daysLeft < 0) {
            $type = 'overdue';
            if (!$notifyOverdue) {
                continue;
            }
            $msg = "⚠️ Overdue! {$row['client']}'s {$row['brand']} {$row['model']} was due " . abs($daysLeft) . " day(s) ago.";
        } elseif ($daysLeft === 0) {
            $type = 'due_today';
            if (!$notifyDueToday) {
                continue;
            }
            $msg = "🔴 Due Today: {$row['client']}'s {$row['brand']} {$row['model']} must be returned today.";
        } else {
            $type = 'due_soon';
            if (!$notifyDueSoon) {
                continue;
            }
            $msg = "🟡 Due Soon: {$row['client']}'s {$row['brand']} {$row['model']} is due in {$daysLeft} day(s).";
        }
        // Only insert if no notification exists for this reservation created today
        $exists = $pdo->prepare("SELECT id FROM notifications WHERE reservation_id = ? AND DATE(created_at) = ?");
        $exists->execute([$row['id'], $todayDate]);
        if (!$exists->fetch()) {
            $pdo->prepare("INSERT INTO notifications (type, message, reservation_id) VALUES (?, ?, ?)")
                ->execute([$type, $msg, $row['id']]);
        }
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

/**
 * Create a notification for a reservation event
 * @param PDO $pdo Database connection
 * @param int $reservationId Reservation ID
 * @param string $event Event type: 'created', 'delivered', 'returned', 'cancelled'
 * @param string $clientName Client name
 * @param string $vehicleName Vehicle brand and model
 * @return void
 */
function notif_create_reservation_event(PDO $pdo, int $reservationId, string $event, string $clientName, string $vehicleName): void
{
    $settingMap = [
        'created' => 'notify_res_created',
        'delivered' => 'notify_res_delivered',
        'returned' => 'notify_res_returned',
        'cancelled' => 'notify_res_cancelled',
    ];
    $settingKey = $settingMap[$event] ?? null;
    if ($settingKey !== null && settings_get($pdo, $settingKey, '1') === '0') {
        return;
    }
    $messages = [
        'created' => "📋 New reservation: {$clientName} - {$vehicleName}",
        'delivered' => "🚗 Vehicle delivered: {$clientName} - {$vehicleName}",
        'returned' => "✅ Vehicle returned: {$clientName} - {$vehicleName}",
        'cancelled' => "❌ Reservation cancelled: {$clientName} - {$vehicleName}",
    ];
    $message = $messages[$event] ?? "Reservation update: {$clientName} - {$vehicleName}";
    
    try {
        $pdo->prepare("INSERT INTO notifications (type, message, reservation_id) VALUES ('info', ?, ?)")
            ->execute([$message, $reservationId]);
    } catch (Throwable $e) {
        // Silent fail - don't break reservation flow
    }
}
