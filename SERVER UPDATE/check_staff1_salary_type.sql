-- Check staff1 (user_id=2) salary configuration
SELECT 
    u.id AS user_id,
    u.name,
    u.username,
    u.is_active,
    s.id AS staff_id,
    s.salary AS basic_salary,
    s.role,
    s.salary_type,
    s.hourly_rate
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.id = 2;

-- Also check if the columns exist
DESCRIBE staff;
