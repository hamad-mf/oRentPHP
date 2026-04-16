-- Delete March 2026 Payroll for Testing
-- This will allow you to regenerate it with the new hourly salary calculation

-- Delete payroll records for March 2026
DELETE FROM payroll WHERE month = 3 AND year = 2026;

-- Verify deletion
SELECT COUNT(*) AS remaining_records FROM payroll WHERE month = 3 AND year = 2026;

SELECT 'March 2026 payroll deleted successfully' AS status;
