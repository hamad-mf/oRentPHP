-- Release: 2026-03-08_vehicle_insurance_metadata
-- Author: Codex (GPT-5)
-- Safe: idempotent
-- Notes: Adds insurance type and insurance expiry date fields for vehicles.

SET FOREIGN_KEY_CHECKS = 0;

SET @vehicles_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
);

SET @insurance_type_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'insurance_type'
);
SET @insurance_type_sql := IF(
    @vehicles_table_exists > 0 AND @insurance_type_exists = 0,
    'ALTER TABLE vehicles ADD COLUMN insurance_type VARCHAR(30) NULL AFTER maintenance_workshop_name',
    'SELECT 1'
);
PREPARE stmt FROM @insurance_type_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @insurance_expiry_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'insurance_expiry_date'
);
SET @insurance_expiry_sql := IF(
    @vehicles_table_exists > 0 AND @insurance_expiry_exists = 0,
    'ALTER TABLE vehicles ADD COLUMN insurance_expiry_date DATE NULL AFTER insurance_type',
    'SELECT 1'
);
PREPARE stmt FROM @insurance_expiry_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
