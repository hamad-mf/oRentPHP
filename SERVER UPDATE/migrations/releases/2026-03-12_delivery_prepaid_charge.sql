-- Release: 2026-03-12_delivery_prepaid_charge
-- Author: Hamad
-- Safe: idempotent (ALTER TABLE with IF NOT EXISTS)
-- Notes: Adds prepaid delivery charge fields to reservations table.
--        delivery_charge_prepaid         - delivery charge collected at reservation creation
--        delivery_prepaid_payment_method - how the prepaid delivery charge was paid (cash/account/credit)
--        delivery_prepaid_bank_account_id- bank account used when method = account

SET FOREIGN_KEY_CHECKS = 0;

SET @reservations_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
);

SET @delivery_charge_prepaid_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'delivery_charge_prepaid'
);
SET @delivery_prepaid_method_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'delivery_prepaid_payment_method'
);
SET @delivery_prepaid_bank_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'delivery_prepaid_bank_account_id'
);

SET @delivery_charge_prepaid_sql := IF(
    @reservations_table_exists > 0 AND @delivery_charge_prepaid_exists = 0,
    'ALTER TABLE reservations ADD COLUMN delivery_charge_prepaid DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @delivery_charge_prepaid_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @delivery_prepaid_method_sql := IF(
    @reservations_table_exists > 0 AND @delivery_prepaid_method_exists = 0,
    'ALTER TABLE reservations ADD COLUMN delivery_prepaid_payment_method ENUM(''cash'',''account'',''credit'') DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @delivery_prepaid_method_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @delivery_prepaid_bank_sql := IF(
    @reservations_table_exists > 0 AND @delivery_prepaid_bank_exists = 0,
    'ALTER TABLE reservations ADD COLUMN delivery_prepaid_bank_account_id INT DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @delivery_prepaid_bank_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
