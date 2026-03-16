-- Migration: Add 'emi_due' to notifications type enum
-- Date: 2026-03-16
-- Purpose: Support EMI due notifications
-- Idempotent: Yes - checks if type exists before modifying

-- First check if 'emi_due' is already in the enum
-- If not, modify the enum to include it
-- Note: In MySQL 8.0+, we can use ALTER TABLE...MODIFY COLUMN with the new enum values

-- Check if notifications table exists
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'notifications');

-- If table exists, check if emi_due is already in enum
SET @enum_has_emi_due = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'notifications' 
                          AND COLUMN_NAME = 'type'
                          AND COLUMN_TYPE LIKE "%'emi_due'%");

-- Only modify if table exists and emi_due is NOT already in enum
SET @sql = IF(@table_exists > 0 AND @enum_has_emi_due = 0,
    'ALTER TABLE notifications MODIFY COLUMN type ENUM(\'due_today\',\'due_soon\',\'overdue\',\'info\',\'emi_due\') NOT NULL DEFAULT \'info\'',
    'SELECT "emi_due already exists or table does not exist, skipping" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
