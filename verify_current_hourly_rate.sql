-- Verify the CURRENT hourly rate for staff1 (user_id = 2)
-- Check if it was recently updated

SELECT 
    u.id AS user_id,
    u.name,
    s.salary_type,
    s.hourly_rate,
    s.updated_at AS staff_updated_at
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.id = 2;

-- Also check if there's an audit log or history table
SHOW TABLES LIKE '%audit%';
SHOW TABLES LIKE '%history%';
SHOW TABLES LIKE '%log%';
