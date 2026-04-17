-- Check for in-progress attendance (punch_out is NULL)
-- This might be showing a projected daily salary based on current time

SELECT 
    sa.id,
    sa.date,
    sa.punch_in,
    sa.punch_out,
    NOW() AS current_time,
    CASE 
        WHEN sa.punch_out IS NULL THEN TIMESTAMPDIFF(SECOND, sa.punch_in, NOW())
        ELSE TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out)
    END AS elapsed_seconds,
    CASE 
        WHEN sa.punch_out IS NULL THEN ROUND(TIMESTAMPDIFF(SECOND, sa.punch_in, NOW()) / 3600, 4)
        ELSE ROUND(TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) / 3600, 4)
    END AS elapsed_hours,
    CASE 
        WHEN sa.punch_out IS NULL THEN ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, NOW()) / 3600) * 500.00, 2)
        ELSE ROUND((TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out) / 3600) * 500.00, 2)
    END AS projected_daily_salary,
    CASE 
        WHEN sa.punch_out IS NULL THEN 'IN PROGRESS - Salary shown is projected based on current time'
        ELSE 'COMPLETED'
    END AS status
FROM staff_attendance sa
WHERE sa.user_id = 2
  AND sa.date >= '2026-04-01'
  AND sa.punch_in IS NOT NULL
ORDER BY sa.date DESC;

-- Also check what date the attendance screen is currently filtering by
-- The screen might be showing a different date than today
