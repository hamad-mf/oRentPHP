-- Release: 2026-03-12_ledger_void_entries
-- Author: Hamad
-- Safe: idempotent (ALTER TABLE with IF NOT EXISTS)
-- Notes: Adds void metadata to ledger entries (soft-void with reason).

SET FOREIGN_KEY_CHECKS = 0;

SET @ledger_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_entries'
);

SET @voided_at_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_entries'
      AND COLUMN_NAME = 'voided_at'
);
SET @voided_by_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_entries'
      AND COLUMN_NAME = 'voided_by'
);
SET @void_reason_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_entries'
      AND COLUMN_NAME = 'void_reason'
);

SET @voided_at_sql := IF(
    @ledger_table_exists > 0 AND @voided_at_exists = 0,
    'ALTER TABLE ledger_entries ADD COLUMN voided_at DATETIME DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @voided_at_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @voided_by_sql := IF(
    @ledger_table_exists > 0 AND @voided_by_exists = 0,
    'ALTER TABLE ledger_entries ADD COLUMN voided_by INT DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @voided_by_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @void_reason_sql := IF(
    @ledger_table_exists > 0 AND @void_reason_exists = 0,
    'ALTER TABLE ledger_entries ADD COLUMN void_reason VARCHAR(255) DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @void_reason_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
