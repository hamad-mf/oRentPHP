-- Diagnose staff1 hours calculation
-- Check all attendance records for staff1 in March billing period

SELECT 
    sa.id,
    sa.date,
    sa.punch_in,
    sa.punch_out,
    TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) AS work_seconds,
    TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) / 3600 AS work_hours,
    (SELECT COUNT(*) FROM attendance_breaks WHERE attendance_id = sa.id) AS break_count
FROM staff_attendance sa
WHERE sa.user_id = 2
  AND sa.date BETWEEN '2026-03-16' AND '2026-04-15'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
ORDER BY sa.date;

-- Check breaks
SELECT 
    ab.*,
    sa.date,
    TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end) AS break_seconds,
    TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end) / 3600 AS break_hours
FROM attendance_breaks ab
JOIN staff_attendance sa ON sa.id = ab.attendance_id
WHERE sa.user_id = 2
  AND sa.date BETWEEN '2026-03-16' AND '2026-04-15'
ORDER BY sa.date;

-- Calculate total
SELECT 
    COUNT(DISTINCT sa.date) AS days_worked,
    SUM(TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out)) / 3600 AS gross_hours,
    COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0) / 3600 AS break_hours,
    (SUM(TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out)) - COALESCE(SUM(TIMESTAMPDIFF(SECOND, ab.break_start, ab.break_end)), 0)) / 3600 AS net_hours
FROM staff_attendance sa
LEFT JOIN attendance_breaks ab ON ab.attendance_id = sa.id AND ab.break_end IS NOT NULL
WHERE sa.user_id = 2
  AND sa.date BETWEEN '2026-03-16' AND '2026-04-15'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL;
