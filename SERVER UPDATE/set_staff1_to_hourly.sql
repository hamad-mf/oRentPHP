-- Set staff1 (user_id=2) to hourly salary type with $500/hour rate
-- First, find the staff_id for user_id=2
SELECT @staff_id := staff_id FROM users WHERE id = 2;

-- Update the staff record to hourly with $500/hour rate
UPDATE staff 
SET salary_type = 'hourly',
    hourly_rate = 500.00
WHERE id = @staff_id;

-- Verify the update
SELECT 
    u.id AS user_id,
    u.name,
    s.salary_type,
    s.hourly_rate,
    s.salary AS basic_salary
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.id = 2;
