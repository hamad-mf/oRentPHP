-- Release: 2026-03-11_reservation_notes
-- Author: opencode
-- Safe: idempotent
-- Notes: Adds optional note column to reservations table

SET FOREIGN_KEY_CHECKS = 0;

SET @reservations_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
);

SET @note_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'note'
);

SET @note_sql := IF(
    @reservations_table_exists > 0 AND @note_exists = 0,
    'ALTER TABLE reservations ADD COLUMN note VARCHAR(1000) DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @note_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
