-- Release: 2026-03-23_deposit_tracking
-- Author: AI Assistant
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Adds columns to track deposit deductions, holds, and real income conversion.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations
ADD COLUMN IF NOT EXISTS deposit_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER deposit_returned,
ADD COLUMN IF NOT EXISTS deposit_held DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER deposit_deducted,
ADD COLUMN IF NOT EXISTS deposit_hold_reason TEXT DEFAULT NULL AFTER deposit_held;

SET FOREIGN_KEY_CHECKS = 1;
