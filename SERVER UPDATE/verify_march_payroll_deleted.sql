-- Verify if March 2026 payroll was deleted
SELECT COUNT(*) AS march_2026_records FROM payroll WHERE month = 3 AND year = 2026;

-- Show all payroll records to see what exists
SELECT id, user_id, month, year, basic_salary, status, created_at 
FROM payroll 
ORDER BY year DESC, month DESC, id DESC
LIMIT 20;
