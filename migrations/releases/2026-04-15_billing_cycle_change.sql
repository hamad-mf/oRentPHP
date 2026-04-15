-- Billing Cycle Change Migration
-- Date: 2026-04-15
-- Changes period from 15th-to-14th to 16th-to-15th

-- Update monthly_targets for current period
-- Old: 2026-04-15 to 2026-05-14
-- New: 2026-04-16 to 2026-05-15
UPDATE monthly_targets 
SET 
    period_start = '2026-04-16',
    period_end = '2026-05-15',
    updated_at = NOW()
WHERE period_start = '2026-04-15';

-- Update vehicle_monthly_targets for current period
UPDATE vehicle_monthly_targets 
SET 
    period_start = '2026-04-16',
    period_end = '2026-05-15',
    updated_at = NOW()
WHERE period_start = '2026-04-15';

-- Verify changes
SELECT 'monthly_targets' AS table_name, period_start, period_end, target_amount 
FROM monthly_targets 
WHERE period_start >= '2026-04-01'
UNION ALL
SELECT 'vehicle_monthly_targets' AS table_name, period_start, period_end, target_amount 
FROM vehicle_monthly_targets 
WHERE period_start >= '2026-04-01'
ORDER BY period_start;
