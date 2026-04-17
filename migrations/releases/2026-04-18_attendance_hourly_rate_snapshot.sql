-- Migration: Store hourly rate at time of attendance for historical accuracy
-- Date: 2026-04-18
-- Purpose: When viewing old attendance records, we need to show the rate that was active at that time,
--          not the current rate. This prevents historical salary calculations from changing when rates are updated.

-- Add hourly_rate_snapshot column to staff_attendance
ALTER TABLE staff_attendance 
ADD COLUMN hourly_rate_snapshot DECIMAL(10,2) DEFAULT NULL 
COMMENT 'Hourly rate at time of punch-in for historical accuracy';

-- Backfill existing records with current hourly rates
UPDATE staff_attendance sa
JOIN users u ON u.id = sa.user_id
JOIN staff s ON s.id = u.staff_id
SET sa.hourly_rate_snapshot = s.hourly_rate
WHERE sa.hourly_rate_snapshot IS NULL 
  AND s.salary_type = 'hourly'
  AND s.hourly_rate IS NOT NULL;
