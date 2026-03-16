-- Release: 2026-03-11_staff_admin_dashboard_toggle
-- Author: opencode
-- Safe: idempotent
-- Notes: Adds enable_admin_dashboard column to staff table to allow staff to view admin dashboard

SET FOREIGN_KEY_CHECKS = 0;

SET @staff_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff'
);

SET @enable_admin_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff'
      AND COLUMN_NAME = 'enable_admin_dashboard'
);

SET @enable_admin_sql := IF(
    @staff_table_exists > 0 AND @enable_admin_exists = 0,
    'ALTER TABLE staff ADD COLUMN enable_admin_dashboard TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @enable_admin_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
