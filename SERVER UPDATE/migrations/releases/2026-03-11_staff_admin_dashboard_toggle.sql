-- Release: 2026-03-11_staff_admin_dashboard_toggle
-- Author: opencode
-- Safe: idempotent
-- Notes: Adds enable_admin_dashboard column to staff table to allow staff to view admin dashboard

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE staff ADD COLUMN enable_admin_dashboard TINYINT(1) NOT NULL DEFAULT 0;

SET FOREIGN_KEY_CHECKS = 1;
