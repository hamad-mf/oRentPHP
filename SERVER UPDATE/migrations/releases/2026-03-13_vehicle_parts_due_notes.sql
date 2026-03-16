-- Release: 2026-03-13_vehicle_parts_due_notes
-- Author: Hamad
-- Safe: idempotent
-- Notes: Adds parts_due_notes column on vehicles to track upcoming parts replacements.

SET FOREIGN_KEY_CHECKS = 0;

SET @vehicles_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
);

SET @parts_due_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'parts_due_notes'
);

SET @parts_due_sql := IF(
    @vehicles_table_exists > 0 AND @parts_due_exists = 0,
    'ALTER TABLE vehicles ADD COLUMN parts_due_notes TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @parts_due_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
