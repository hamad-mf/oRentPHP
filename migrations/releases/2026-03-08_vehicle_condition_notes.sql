-- Release: 2026-03-08_vehicle_condition_notes
-- Author: Codex (GPT-5)
-- Safe: idempotent
-- Notes: Adds optional condition_notes column on vehicles for condition tracking from vehicle details page.

SET FOREIGN_KEY_CHECKS = 0;

SET @vehicles_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
);
SET @vehicles_condition_notes_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'condition_notes'
);
SET @vehicles_condition_notes_sql := IF(
    @vehicles_table_exists > 0 AND @vehicles_condition_notes_exists = 0,
    'ALTER TABLE vehicles ADD COLUMN condition_notes TEXT NULL AFTER maintenance_workshop_name',
    'SELECT 1'
);
PREPARE stmt FROM @vehicles_condition_notes_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
