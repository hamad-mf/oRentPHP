-- Release: 2026-03-22_vehicle_challans_add_client
-- Author: AI Assistant
-- Safe: idempotent (column existence checked at application level via try/catch)
-- Notes: Adds client_id, paid_by, paid_date, payment_mode to vehicle_challans for tracking
--        which client was responsible and how/when the challan was paid.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE vehicle_challans 
ADD COLUMN IF NOT EXISTS client_id INT DEFAULT NULL AFTER vehicle_id,
ADD COLUMN IF NOT EXISTS paid_by ENUM('company','customer') DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS paid_date DATE DEFAULT NULL AFTER paid_by,
ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(20) DEFAULT NULL AFTER paid_date;

SET FOREIGN_KEY_CHECKS = 1;