-- Release: 2026-03-10_reservation_advance_payment
-- Author: Hamad
-- Safe: idempotent (ALTER TABLE with IF NOT EXISTS)
-- Notes: Adds advance payment fields to reservations table.
--        advance_paid            - amount collected at reservation creation as advance
--        advance_payment_method  - how the advance was paid (cash/account/credit)
--        advance_bank_account_id - bank account used when method = account

SET FOREIGN_KEY_CHECKS = 0;

SET @reservations_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
);

SET @advance_paid_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'advance_paid'
);
SET @advance_method_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'advance_payment_method'
);
SET @advance_bank_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'advance_bank_account_id'
);

SET @advance_paid_sql := IF(
    @reservations_table_exists > 0 AND @advance_paid_exists = 0,
    'ALTER TABLE reservations ADD COLUMN advance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @advance_paid_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @advance_method_sql := IF(
    @reservations_table_exists > 0 AND @advance_method_exists = 0,
    'ALTER TABLE reservations ADD COLUMN advance_payment_method ENUM(''cash'',''account'',''credit'') DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @advance_method_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @advance_bank_sql := IF(
    @reservations_table_exists > 0 AND @advance_bank_exists = 0,
    'ALTER TABLE reservations ADD COLUMN advance_bank_account_id INT DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @advance_bank_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
