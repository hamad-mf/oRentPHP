-- Release: 2026-04-16_hourly_salary_calculation
-- Author: system
-- Safe: idempotent
-- Notes: Adds salary_type ENUM and hourly_rate columns to staff table.
--        Supports both fixed monthly salaries and hourly rate payments.
--        Run manually via phpMyAdmin before deploying hourly-salary-calculation feature.

SET FOREIGN_KEY_CHECKS = 0;

-- Step 1: Add salary_type ENUM column with default 'fixed' for backward compatibility
-- MODIFY COLUMN is idempotent — re-running with the same definition is safe.
ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS salary_type ENUM('fixed', 'hourly') NOT NULL DEFAULT 'fixed' AFTER salary;

-- Step 2: Add hourly_rate DECIMAL column (nullable for fixed-salary staff)
ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) DEFAULT NULL AFTER salary_type;

SET FOREIGN_KEY_CHECKS = 1;

-- Verification query: Show staff table structure
DESCRIBE staff;

-- Verification query: Show all staff with their salary configuration
SELECT id, name, salary, salary_type, hourly_rate 
FROM staff 
ORDER BY id;
