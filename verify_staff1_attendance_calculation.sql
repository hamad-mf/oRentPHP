-- ============================================================================
-- STAFF1 ATTENDANCE CALCULATION VERIFICATION
-- Date: 2026-04-17
-- Purpose: Verify how staff1 got $4,800.00 shown in attendance screen today
-- ============================================================================

-- Step 1: Check staff1 configuration (user_id = 2)
-- ============================================================================
SELECT 
    u.id AS user_id,
    u.name,
    s.salary_type,
    s.hourly_rate,
    s.salary AS fixed_salary
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.id = 2;

-- Expected: salary_type='hourly', hourly_rate=600.00


-- Step 2: Check today's attendance record for staff1
-- ============================================================================
SELECT 
    id,
    user_id,
    date,
    punch_in,
    punch_out,
    TIMESTAMPDIFF(SECOND, punch_in, punch_out) AS total_seconds,
    TIMESTAMPDIFF(SECOND, punch_in, punch_out) / 3600 AS total_hours_raw
FROM staff_attendance
WHERE user_id = 2
  AND date = '2026-04-17'
  AND punch_in IS NOT NULL
  AND punch_out IS NOT NULL;

-- This shows the raw work duration before breaks


-- Step 3: Check breaks for today's attendance
-- ============================================================================
SELECT 
    ab.id,
    ab.attendance_id,
    ab.break_start,
    ab.break_end,
    TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end) AS break_seconds,
    TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end) / 3600 AS break_hours
FROM attendance_breaks ab
JOIN staff_attendance sa ON sa.id = ab.attendance_id
WHERE sa.user_id = 2
  AND sa.date = '2026-04-17'
  AND ab.break_start IS NOT NULL
  AND ab.break_end IS NOT NULL;

-- This shows all breaks taken today


-- Step 4: Calculate net work hours (work time - breaks)
-- ============================================================================
SELECT 
    sa.id AS attendance_id,
    sa.date,
    sa.punch_in,
    sa.punch_out,
    TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) AS gross_seconds,
    COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0) AS break_seconds,
    (TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) AS net_work_seconds,
    ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) AS net_work_hours
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id 
    AND ab.break_start IS NOT NULL 
    AND ab.break_end IS NOT NULL
WHERE sa.user_id = 2
  AND sa.date = '2026-04-17'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY sa.id, sa.date, sa.punch_in, sa.punch_out;

-- This calculates: (punch_out - punch_in) - (sum of all breaks)


-- Step 5: Calculate expected payment for today
-- ============================================================================
SELECT 
    sa.date,
    ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) AS hours_worked,
    s.hourly_rate,
    ROUND(
        ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) 
        * s.hourly_rate, 
        2
    ) AS calculated_payment,
    CASE 
        WHEN ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) < 1.0 
        THEN 'Below 1hr threshold - would be $0.00 in payroll'
        ELSE 'Above threshold - payment applies'
    END AS threshold_status
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id 
    AND ab.break_start IS NOT NULL 
    AND ab.break_end IS NOT NULL
JOIN users u ON u.id = sa.user_id
JOIN staff s ON s.id = u.staff_id
WHERE sa.user_id = 2
  AND sa.date = '2026-04-17'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY sa.id, sa.date, sa.punch_in, sa.punch_out, s.hourly_rate;

-- Formula: hours_worked × hourly_rate = payment
-- If hours_worked = 8.0 and hourly_rate = 600.00, then payment = 4,800.00


-- Step 6: Verify the calculation matches $4,800.00
-- ============================================================================
-- If the result shows 8.0 hours × $600/hr = $4,800.00, then:
-- - Staff worked 8 hours today (09:00 AM to 06:00 PM with 1 hour lunch break)
-- - Calculation: 8 hours × $600/hour = $4,800.00

-- IMPORTANT NOTE: The attendance screen shows DAILY earnings
-- This is different from monthly payroll which:
-- 1. Sums ALL hours in the billing period (16th to 15th)
-- 2. Applies the 1.0 hour minimum threshold
-- 3. Adds overtime pay if applicable


-- Step 7: Check if there are any other attendance records this month
-- ============================================================================
SELECT 
    sa.date,
    sa.punch_in,
    sa.punch_out,
    ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) AS hours_worked,
    ROUND(
        ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) 
        * 600.00, 
        2
    ) AS daily_payment
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id 
    AND ab.break_start IS NOT NULL 
    AND ab.break_end IS NOT NULL
WHERE sa.user_id = 2
  AND sa.date >= '2026-04-16'  -- Current billing period start
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY sa.id, sa.date, sa.punch_in, sa.punch_out
ORDER BY sa.date;

-- This shows all attendance records in the current billing period


-- Step 8: Calculate total for current billing period (for payroll)
-- ============================================================================
SELECT 
    COUNT(*) AS days_worked,
    SUM(ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4)) AS total_hours,
    ROUND(
        SUM(ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4)) 
        * 600.00, 
        2
    ) AS total_payment_if_above_threshold,
    CASE 
        WHEN SUM(ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4)) < 1.0
        THEN 0.00
        ELSE ROUND(
            SUM(ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4)) 
            * 600.00, 
            2
        )
    END AS actual_payroll_payment
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id 
    AND ab.break_start IS NOT NULL 
    AND ab.break_end IS NOT NULL
WHERE sa.user_id = 2
  AND sa.date BETWEEN '2026-04-16' AND '2026-05-15'  -- Current billing period
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY sa.user_id;

-- This shows what would be paid in the monthly payroll


-- ============================================================================
-- SUMMARY OF CALCULATION
-- ============================================================================
-- The $4,800.00 shown in attendance screen is calculated as:
-- 
-- 1. Today's work hours = (punch_out - punch_in) - (total break time)
-- 2. Today's payment = work_hours × hourly_rate
-- 3. If work_hours = 8.0 and hourly_rate = $600.00
--    Then payment = 8.0 × 600.00 = $4,800.00
--
-- This is a DAILY calculation shown in the attendance screen.
-- The actual monthly payroll will sum all days and apply the 1hr threshold.
-- ============================================================================
