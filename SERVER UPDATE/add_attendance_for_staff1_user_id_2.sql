-- Add Test Attendance Data for staff1 (user_id = 2)
-- Billing Period: March 16 - April 15, 2026
-- This will add 7 days of attendance records for testing hourly salary calculation

-- Delete any existing test data for staff1 first (to avoid duplicates)
DELETE FROM staff_attendance WHERE user_id = 2 AND date BETWEEN '2026-03-16' AND '2026-03-22';

-- Add 7 days of attendance (March 16-22, 2026)
-- Each day: 9:00 AM to 5:00 PM (8 hours) with 1 hour lunch break = 7 hours per day
-- Total: 7 days × 7 hours = 49 hours

INSERT INTO staff_attendance (user_id, date, punch_in, punch_out) VALUES
(2, '2026-03-16', '2026-03-16 09:00:00', '2026-03-16 17:00:00'),
(2, '2026-03-17', '2026-03-17 09:00:00', '2026-03-17 17:00:00'),
(2, '2026-03-18', '2026-03-18 09:00:00', '2026-03-18 17:00:00'),
(2, '2026-03-19', '2026-03-19 09:00:00', '2026-03-19 17:00:00'),
(2, '2026-03-20', '2026-03-20 09:00:00', '2026-03-20 17:00:00'),
(2, '2026-03-21', '2026-03-21 09:00:00', '2026-03-21 17:00:00'),
(2, '2026-03-22', '2026-03-22 09:00:00', '2026-03-22 17:00:00');

-- Get the attendance IDs we just created
SET @att1 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-16' LIMIT 1);
SET @att2 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-17' LIMIT 1);
SET @att3 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-18' LIMIT 1);
SET @att4 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-19' LIMIT 1);
SET @att5 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-20' LIMIT 1);
SET @att6 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-21' LIMIT 1);
SET @att7 = (SELECT id FROM staff_attendance WHERE user_id = 2 AND date = '2026-03-22' LIMIT 1);

-- Add 1-hour lunch breaks for each day (12:00 PM - 1:00 PM)
INSERT INTO attendance_breaks (attendance_id, break_start, break_end) VALUES
(@att1, '2026-03-16 12:00:00', '2026-03-16 13:00:00'),
(@att2, '2026-03-17 12:00:00', '2026-03-17 13:00:00'),
(@att3, '2026-03-18 12:00:00', '2026-03-18 13:00:00'),
(@att4, '2026-03-19 12:00:00', '2026-03-19 13:00:00'),
(@att5, '2026-03-20 12:00:00', '2026-03-20 13:00:00'),
(@att6, '2026-03-21 12:00:00', '2026-03-21 13:00:00'),
(@att7, '2026-03-22 12:00:00', '2026-03-22 13:00:00');

-- Show summary
SELECT 'Test attendance data added successfully for staff1 (user_id=2)' AS status;

-- Show breakdown
SELECT 
    date,
    punch_in,
    punch_out,
    TIMESTAMPDIFF(HOUR, punch_in, punch_out) AS gross_hours,
    '1 hour lunch break' AS break_info,
    (TIMESTAMPDIFF(HOUR, punch_in, punch_out) - 1) AS net_hours
FROM staff_attendance
WHERE user_id = 2 AND date BETWEEN '2026-03-16' AND '2026-03-22'
ORDER BY date;

-- Calculate expected payment
SELECT 
    COUNT(*) AS days_worked,
    SUM(TIMESTAMPDIFF(SECOND, punch_in, punch_out) - 3600) / 3600 AS total_hours,
    (SUM(TIMESTAMPDIFF(SECOND, punch_in, punch_out) - 3600) / 3600) * 500 AS expected_payment
FROM staff_attendance
WHERE user_id = 2 AND date BETWEEN '2026-03-16' AND '2026-03-22';
