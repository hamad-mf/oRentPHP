-- Release: 2026-03-18_payroll_overtime_pay
-- Author: AI
-- Safe: idempotent
-- Notes: Adds overtime_pay column to payroll table for overtime wage tracking.

SET FOREIGN_KEY_CHECKS = 0;

-- Add overtime_pay column to payroll table (idempotent)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll' AND COLUMN_NAME = 'overtime_pay');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE payroll ADD COLUMN overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER incentive',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
