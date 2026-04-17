-- Find which date shows $4,800.00 for staff1
-- This will show all recent attendance records and their calculated daily salary

SELECT 
    sa.date,
    sa.punch_in,
    sa.punch_out,
    TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) AS gross_seconds,
    COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0) AS break_seconds,
    (TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) AS net_work_seconds,
    ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) AS hours_worked,
    500.00 AS hourly_rate,
    ROUND(
        ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) 
        * 500.00, 
        2
    ) AS daily_salary,
    CASE 
        WHEN ROUND(
            ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600, 4) 
            * 500.00, 
            2
        ) = 4800.00 THEN '✓ THIS IS THE $4,800 DAY'
        ELSE ''
    END AS match_indicator
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id 
    AND ab.break_start IS NOT NULL 
    AND ab.break_end IS NOT NULL
WHERE sa.user_id = 2
  AND sa.date >= '2026-04-01'  -- Check April 2026
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY sa.id, sa.date, sa.punch_in, sa.punch_out
ORDER BY sa.date DESC;

-- If no results, check if there's an attendance record with NULL punch_out (still in progress)
SELECT 
    sa.id,
    sa.date,
    sa.punch_in,
    sa.punch_out,
    'Attendance record exists but punch_out is NULL (still in progress)' AS status
FROM staff_attendance sa
WHERE sa.user_id = 2
  AND sa.date >= '2026-04-01'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NULL
ORDER BY sa.date DESC;
