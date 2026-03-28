-- Release: 2026-03-29_add_deposit_returned
-- Author: System
-- Safe: idempotent
-- Notes: Adds missing deposit_returned column that was referenced but never created

SET FOREIGN_KEY_CHECKS = 0;

-- Add deposit_returned column to track amount returned to client
ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS deposit_returned DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER deposit_amount;

SET FOREIGN_KEY_CHECKS = 1;
