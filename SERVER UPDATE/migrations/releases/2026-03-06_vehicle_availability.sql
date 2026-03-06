-- Release: 2026-03-06_vehicle_availability
-- Author: opencode
-- Safe: idempotent
-- Notes: Adds delivered_at column to track when vehicle was actually delivered. Used by Vehicle Availability page.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations ADD COLUMN delivered_at DATETIME DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
