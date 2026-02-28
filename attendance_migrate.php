<?php
/**
 * Staff Attendance Migration — run once to create the table.
 * Access via browser or CLI, then delete this file.
 */
require_once __DIR__ . '/config/db.php';
$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    date        DATE NOT NULL,
    punch_in    DATETIME DEFAULT NULL,
    punch_out   DATETIME DEFAULT NULL,
    pin_warning TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = punched in outside allowed window',
    pout_warning TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = punched out outside allowed window',
    UNIQUE KEY uq_user_date (user_id, date),
    KEY idx_date (date),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo '<p style=\"font-family:sans-serif;color:green\">✅ staff_attendance table created (or already exists).</p>';
echo '<p style=\"font-family:sans-serif\">You may delete this file now.</p>';
