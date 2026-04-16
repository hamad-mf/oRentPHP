-- Test Attendance Data for Hourly Staff
-- This script adds sample attendance records for testing hourly salary calculation
-- Billing Period: 2026-03-16 to 2026-04-15 (current period)

-- First, find the user_id for staff1
SET @user_id = (SELECT u.id FROM users u JOIN staff s ON s.id = u.staff_id WHERE u.username = 'staff1' LIMIT 1);

-- Display the user_id we found
SELECT @user_id AS 'Staff User ID';

-- Add attendance records for the current billing period (March 16 - April 15, 2026)
-- These records will give the staff member some working hours to test with

-- Day 1: March 17, 2026 - 8 hours (9 AM to 6 PM with 1 hour break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-03-17', '2026-03-17 09:00:00', '2026-03-17 18:00:00');

SET @att_id_1 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_1, '2026-03-17 13:00:00', '2026-03-17 14:00:00', NOW());

-- Day 2: March 18, 2026 - 7.5 hours (9 AM to 5:30 PM with 1 hour break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-03-18', '2026-03-18 09:00:00', '2026-03-18 17:30:00');

SET @att_id_2 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_2, '2026-03-18 13:00:00', '2026-03-18 14:00:00', NOW());

-- Day 3: March 19, 2026 - 8 hours (8:30 AM to 5:30 PM with 1 hour break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-03-19', '2026-03-19 08:30:00', '2026-03-19 17:30:00');

SET @att_id_3 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_3, '2026-03-19 12:30:00', '2026-03-19 13:30:00', NOW());

-- Day 4: March 20, 2026 - 6 hours (10 AM to 4:30 PM with 30 min break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-03-20', '2026-03-20 10:00:00', '2026-03-20 16:30:00');

SET @att_id_4 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_4, '2026-03-20 13:00:00', '2026-03-20 13:30:00', NOW());

-- Day 5: March 21, 2026 - 9 hours (8 AM to 6 PM with 1 hour break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-03-21', '2026-03-21 08:00:00', '2026-03-21 18:00:00');

SET @att_id_5 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_5, '2026-03-21 13:00:00', '2026-03-21 14:00:00', NOW());

-- Day 6: April 10, 2026 - 7 hours (9 AM to 5 PM with 1 hour break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-04-10', '2026-04-10 09:00:00', '2026-04-10 17:00:00');

SET @att_id_6 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_6, '2026-04-10 13:00:00', '2026-04-10 14:00:00', NOW());

-- Day 7: April 11, 2026 - 8.5 hours (8:30 AM to 6 PM with 1 hour break)
INSERT INTO staff_attendance (user_id, date, punch_in, punch_out)
VALUES (@user_id, '2026-04-11', '2026-04-11 08:30:00', '2026-04-11 18:00:00');

SET @att_id_7 = LAST_INSERT_ID();

INSERT INTO attendance_breaks (attendance_id, break_start, break_end, created_at)
VALUES (@att_id_7, '2026-04-11 13:00:00', '2026-04-11 14:00:00', NOW());

-- Display summary of added attendance
SELECT 
    'Attendance Summary' AS info,
    COUNT(*) AS total_days,
    SUM(TIMESTAMPDIFF(SECOND, punch_in, punch_out)) / 3600 AS total_hours_with_breaks,
    (SUM(TIMESTAMPDIFF(SECOND, punch_in, punch_out)) - 
     (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)), 0) 
      FROM attendance_breaks ab 
      WHERE ab.attendance_id IN (SELECT id FROM staff_attendance WHERE user_id = @user_id))) / 3600 AS total_hours_worked
FROM staff_attendance
WHERE user_id = @user_id
  AND date BETWEEN '2026-03-16' AND '2026-04-15';

-- Display detailed breakdown
SELECT 
    sa.date,
    sa.punch_in,
    sa.punch_out,
    TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) / 3600 AS hours_with_breaks,
    COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0) / 3600 AS break_hours,
    (TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - 
     COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600 AS actual_hours_worked
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id
WHERE sa.user_id = @user_id
  AND sa.date BETWEEN '2026-03-16' AND '2026-04-15'
GROUP BY sa.id, sa.date, sa.punch_in, sa.punch_out
ORDER BY sa.date;

-- Calculate expected payment
SELECT 
    @user_id AS user_id,
    (SELECT hourly_rate FROM staff s JOIN users u ON u.staff_id = s.id WHERE u.id = @user_id) AS hourly_rate,
    (SUM(TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out)) - 
     (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)), 0) 
      FROM attendance_breaks ab 
      WHERE ab.attendance_id IN (SELECT id FROM staff_attendance WHERE user_id = @user_id AND date BETWEEN '2026-03-16' AND '2026-04-15'))) / 3600 AS total_hours,
    ROUND(((SUM(TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out)) - 
     (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, break_start, break_end)), 0) 
      FROM attendance_breaks ab 
      WHERE ab.attendance_id IN (SELECT id FROM staff_attendance WHERE user_id = @user_id AND date BETWEEN '2026-03-16' AND '2026-04-15'))) / 3600) * 
     (SELECT hourly_rate FROM staff s JOIN users u ON u.staff_id = s.id WHERE u.id = @user_id), 2) AS expected_payment
FROM staff_attendance sa
WHERE sa.user_id = @user_id
  AND sa.date BETWEEN '2026-03-16' AND '2026-04-15';
