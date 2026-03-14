-- Release: 2026-03-14_vehicle_pollution_expiry_date
-- Author: Hamad
-- Safe: idempotent
-- Notes: Adds pollution_expiry_date column on vehicles.

SET FOREIGN_KEY_CHECKS = 0;

SET @vehicles_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
);

SET @pollution_expiry_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'pollution_expiry_date'
);

SET @pollution_expiry_sql := IF(
    @vehicles_table_exists > 0 AND @pollution_expiry_exists = 0,
    'ALTER TABLE vehicles ADD COLUMN pollution_expiry_date DATE NULL AFTER insurance_expiry_date',
    'SELECT 1'
);
PREPARE stmt FROM @pollution_expiry_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
