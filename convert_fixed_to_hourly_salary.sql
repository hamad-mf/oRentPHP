-- ============================================================================
-- Convert Fixed Salary Staff to Hourly Rate
-- ============================================================================
-- Purpose: Check all staff salary configurations and convert any staff
--          still using 'fixed' salary type to 'hourly' with a minimal rate
-- Date: 2026-04-17
-- Safe: Review results before running UPDATE query
-- ============================================================================

-- STEP 1: Check current salary configuration for all staff
-- Run this first to see who needs to be updated
SELECT 
    id,
    name,
    role,
    salary_type,
    salary AS fixed_salary,
    hourly_rate,
    CASE 
        WHEN salary_type = 'fixed' THEN '⚠️ NEEDS UPDATE'
        WHEN salary_type = 'hourly' THEN '✓ Already hourly'
        ELSE '? Unknown'
    END AS status
FROM staff
ORDER BY salary_type DESC, id;

-- ============================================================================
-- STEP 2: Preview what will be updated
-- This shows which staff will be converted and what their new hourly rate will be
-- ============================================================================
SELECT 
    id,
    name,
    role,
    salary_type AS current_type,
    salary AS current_fixed_salary,
    hourly_rate AS current_hourly_rate,
    'hourly' AS new_type,
    600.00 AS new_hourly_rate,
    'Will be converted to hourly' AS action
FROM staff
WHERE salary_type = 'fixed';

-- ============================================================================
-- STEP 3: UPDATE QUERY - Convert fixed salary staff to hourly
-- ⚠️ IMPORTANT: Review the preview results above before running this!
-- This will update all staff with salary_type='fixed' to hourly with 600/hour
-- ============================================================================
UPDATE staff
SET 
    salary_type = 'hourly',
    hourly_rate = 600.00,
    updated_at = NOW()
WHERE salary_type = 'fixed';

-- ============================================================================
-- STEP 4: Verify the changes
-- Run this after the UPDATE to confirm all staff are now on hourly
-- ============================================================================
SELECT 
    id,
    name,
    role,
    salary_type,
    hourly_rate,
    updated_at,
    CASE 
        WHEN salary_type = 'hourly' AND hourly_rate IS NOT NULL THEN '✓ Configured correctly'
        WHEN salary_type = 'hourly' AND hourly_rate IS NULL THEN '⚠️ Missing hourly rate'
        ELSE '⚠️ Still on fixed'
    END AS verification_status
FROM staff
ORDER BY id;

-- ============================================================================
-- OPTIONAL: If you want different rates for different staff
-- Uncomment and modify the queries below
-- ============================================================================

-- Example: Set specific staff to 500/hour
-- UPDATE staff
-- SET hourly_rate = 500.00, updated_at = NOW()
-- WHERE id IN (1, 3, 5);

-- Example: Set specific staff to 700/hour
-- UPDATE staff
-- SET hourly_rate = 700.00, updated_at = NOW()
-- WHERE id IN (2, 4);

-- ============================================================================
-- ROLLBACK (if needed)
-- If you need to revert changes, you can set them back to fixed
-- ⚠️ Only use if you need to undo the changes
-- ============================================================================
-- UPDATE staff
-- SET 
--     salary_type = 'fixed',
--     hourly_rate = NULL,
--     updated_at = NOW()
-- WHERE id IN (/* specify staff IDs to revert */);
