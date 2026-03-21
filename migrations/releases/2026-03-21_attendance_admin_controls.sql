-- Release: 2026-03-21_attendance_admin_controls
-- Author: Admin
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds admin_note and is_manual_punch columns to staff_attendance
--        to support admin force punch-out and manual attendance editing.

SET FOREIGN_KEY_CHECKS = 0;

-- Add admin_note column to track why an admin edited the record
ALTER TABLE staff_attendance
    ADD COLUMN IF NOT EXISTS admin_note VARCHAR(500) DEFAULT NULL;

-- Add is_manual_punch flag to identify admin-edited records in reporting
ALTER TABLE staff_attendance
    ADD COLUMN IF NOT EXISTS is_manual_punch TINYINT(1) NOT NULL DEFAULT 0;

SET FOREIGN_KEY_CHECKS = 1;
