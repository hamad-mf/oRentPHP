-- Check staff1 user details
SELECT u.id AS user_id, u.name, u.username, s.salary_type, s.hourly_rate, s.salary
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.username = 'staff1';

-- Check attendance records for staff1
SELECT sa.*, u.username
FROM staff_attendance sa
JOIN users u ON u.id = sa.user_id
WHERE u.username = 'staff1'
ORDER BY sa.date DESC;

-- Count hours for staff1 in March 2026 billing period (16 Mar - 15 Apr)
SELECT 
    u.username,
    COUNT(*) AS attendance_days,
    SUM(TIMESTAMPDIFF(SECOND, sa.punch_in, sa.punch_out)) / 3600 AS total_hours
FROM staff_attendance sa
JOIN users u ON u.id = sa.user_id
WHERE u.username = 'staff1'
  AND sa.date BETWEEN '2026-03-16' AND '2026-04-15'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY u.username;
