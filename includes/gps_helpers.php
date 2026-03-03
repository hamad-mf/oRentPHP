<?php

function gps_tracking_ensure_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS gps_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reservation_id INT DEFAULT NULL,
                vehicle_id INT NOT NULL,
                tracker_id VARCHAR(100) DEFAULT NULL,
                last_location VARCHAR(255) DEFAULT NULL,
                tracking_active TINYINT(1) NOT NULL DEFAULT 1,
                last_seen TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                notes TEXT DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_gps_reservation_id (reservation_id),
                INDEX idx_gps_vehicle_id (vehicle_id),
                INDEX idx_gps_tracking_active (tracking_active)
            ) ENGINE=InnoDB
        ");

        $columnSql = [
            'reservation_id' => "ALTER TABLE gps_tracking ADD COLUMN reservation_id INT DEFAULT NULL AFTER id",
            'tracking_active' => "ALTER TABLE gps_tracking ADD COLUMN tracking_active TINYINT(1) NOT NULL DEFAULT 1 AFTER last_location",
            'updated_by' => "ALTER TABLE gps_tracking ADD COLUMN updated_by INT DEFAULT NULL AFTER notes",
            'updated_at' => "ALTER TABLE gps_tracking ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by",
        ];

        $colChk = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'gps_tracking'
              AND COLUMN_NAME = ?
        ");

        foreach ($columnSql as $column => $sql) {
            $colChk->execute([$column]);
            $exists = (int) $colChk->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($sql);
            }
        }

        $indexSql = [
            'idx_gps_reservation_id' => "ALTER TABLE gps_tracking ADD INDEX idx_gps_reservation_id (reservation_id)",
            'idx_gps_vehicle_id' => "ALTER TABLE gps_tracking ADD INDEX idx_gps_vehicle_id (vehicle_id)",
            'idx_gps_tracking_active' => "ALTER TABLE gps_tracking ADD INDEX idx_gps_tracking_active (tracking_active)",
        ];

        $idxChk = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'gps_tracking'
              AND INDEX_NAME = ?
        ");

        foreach ($indexSql as $index => $sql) {
            $idxChk->execute([$index]);
            $exists = (int) $idxChk->fetchColumn() > 0;
            if (!$exists) {
                $pdo->exec($sql);
            }
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'GPS schema ensure failed: ' . $e->getMessage());
    }
}

function gps_latest_tracking_join_sql(): string
{
    return "
        SELECT gt1.*
        FROM gps_tracking gt1
        INNER JOIN (
            SELECT reservation_id, MAX(id) AS max_id
            FROM gps_tracking
            WHERE reservation_id IS NOT NULL
            GROUP BY reservation_id
        ) latest ON latest.max_id = gt1.id
    ";
}

function gps_active_warning_count(PDO $pdo): int
{
    gps_tracking_ensure_schema($pdo);

    try {
        $sql = "
            SELECT COUNT(*)
            FROM reservations r
            LEFT JOIN (" . gps_latest_tracking_join_sql() . ") g ON g.reservation_id = r.id
            WHERE r.status = 'active'
              AND COALESCE(g.tracking_active, 1) = 0
        ";
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        app_log('ERROR', 'GPS warning count failed: ' . $e->getMessage());
        return 0;
    }
}
