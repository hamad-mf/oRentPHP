-- Release: 2026-03-14_vehicle_storage_locations
-- Author: Hamad
-- Safe: idempotent
-- Notes: Adds optional storage locations for second key and original documents on vehicles.

SET FOREIGN_KEY_CHECKS = 0;

SET @vehicles_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
);

SET @second_key_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'second_key_location'
);

SET @parts_due_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'parts_due_notes'
);

SET @original_docs_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'original_documents_location'
);

SET @second_key_sql := IF(
    @vehicles_table_exists > 0 AND @second_key_exists = 0,
    IF(
        @parts_due_exists > 0,
        'ALTER TABLE vehicles ADD COLUMN second_key_location VARCHAR(255) NULL AFTER parts_due_notes',
        'ALTER TABLE vehicles ADD COLUMN second_key_location VARCHAR(255) NULL'
    ),
    'SELECT 1'
);
PREPARE stmt FROM @second_key_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @second_key_exists_after := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'second_key_location'
);

SET @original_docs_sql := IF(
    @vehicles_table_exists > 0 AND @original_docs_exists = 0,
    IF(
        @second_key_exists_after > 0,
        'ALTER TABLE vehicles ADD COLUMN original_documents_location VARCHAR(255) NULL AFTER second_key_location',
        'ALTER TABLE vehicles ADD COLUMN original_documents_location VARCHAR(255) NULL'
    ),
    'SELECT 1'
);
PREPARE stmt FROM @original_docs_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
