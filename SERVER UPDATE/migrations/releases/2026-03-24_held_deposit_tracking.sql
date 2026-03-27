-- Release: 2026-03-24_held_deposit_tracking
-- Author: System
-- Safe: idempotent
-- Notes: Adds timestamp tracking for held deposits and configurable alert threshold

SET FOREIGN_KEY_CHECKS = 0;

-- Add timestamp to track when deposit was held
ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS deposit_held_at DATETIME DEFAULT NULL AFTER deposit_held;

-- Add system setting for alert threshold (days)
INSERT IGNORE INTO system_settings (`key`, `value`) 
VALUES ('held_deposit_alert_days', '7');

-- Add test mode setting (hours treated as days for testing)
INSERT IGNORE INTO system_settings (`key`, `value`) 
VALUES ('held_deposit_test_mode', '0');

SET FOREIGN_KEY_CHECKS = 1;
