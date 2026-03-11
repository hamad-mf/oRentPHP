-- Release: 2026-03-11_reservation_notes
-- Author: opencode
-- Safe: idempotent
-- Notes: Adds optional note column to reservations table

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations ADD COLUMN IF NOT EXISTS note VARCHAR(1000) DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
