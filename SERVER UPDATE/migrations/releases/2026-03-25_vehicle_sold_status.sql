-- Release: 2026-03-25_vehicle_sold_status
-- Author: system
-- Safe: idempotent
-- Notes: Adds 'sold' to vehicles.status ENUM and adds sold_at DATETIME column.
--        Run manually via phpMyAdmin before deploying vehicle-sold-status feature.

SET FOREIGN_KEY_CHECKS = 0;

-- Step 1: Extend vehicles.status ENUM to include 'sold'
-- MODIFY COLUMN is idempotent — re-running with the same definition is safe.
ALTER TABLE vehicles
    MODIFY COLUMN status ENUM('available','rented','maintenance','sold') NOT NULL DEFAULT 'available';

-- Step 2: Add sold_at column (idempotent via IF NOT EXISTS guard)
ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS sold_at DATETIME NULL DEFAULT NULL AFTER status;

SET FOREIGN_KEY_CHECKS = 1;
