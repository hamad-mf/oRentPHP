-- Release: 2026-03-06_delivery_location
-- Author: AI Assistant
-- Safe: idempotent (uses column existence check pattern)
-- Notes: Adds delivery_location column to reservations table for GPS tracking feature

SET @db = DATABASE();
SET @tbl = 'reservations';
SET @col = 'delivery_location';

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tbl AND COLUMN_NAME = @col
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reservations ADD COLUMN delivery_location VARCHAR(255) DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
