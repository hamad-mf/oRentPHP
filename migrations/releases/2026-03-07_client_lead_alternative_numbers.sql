-- Release: 2026-03-07_client_lead_alternative_numbers
-- Author: Codex (GPT-5)
-- Safe: idempotent
-- Notes: Adds optional alternative_number fields to clients and leads.

SET FOREIGN_KEY_CHECKS = 0;

SET @clients_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
);
SET @clients_alt_col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'alternative_number'
);
SET @clients_sql := IF(
    @clients_table_exists > 0 AND @clients_alt_col_exists = 0,
    'ALTER TABLE clients ADD COLUMN alternative_number VARCHAR(30) NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @clients_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @leads_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leads'
);
SET @leads_alt_col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'leads'
      AND COLUMN_NAME = 'alternative_number'
);
SET @leads_sql := IF(
    @leads_table_exists > 0 AND @leads_alt_col_exists = 0,
    'ALTER TABLE leads ADD COLUMN alternative_number VARCHAR(30) NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @leads_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
