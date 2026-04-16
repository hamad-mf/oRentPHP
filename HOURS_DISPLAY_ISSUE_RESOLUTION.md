# Hours Display Issue - Root Cause and Resolution

## Issue Summary

User reported that hours are NOT displaying in the payroll batch preparation screen for staff1, even though the hourly calculation is working correctly (salary shows $32,250.00 = 64.5h × $500).

## Root Cause Identified

**Staff1 is NOT configured as an hourly employee in the database.**

The database migration added the `salary_type` and `hourly_rate` columns to the staff table, but:
- All existing staff defaulted to `salary_type='fixed'` (as designed for backward compatibility)
- Staff1 was never updated to `salary_type='hourly'`
- The display code checks `if ($salaryType === 'hourly')` before showing hours
- Since staff1 has `salary_type='fixed'`, the hours display code never executes

## Why the Calculation Seemed to Work

The debug script (`debug_batch_preparation.php`) manually calculated hours for testing purposes, but the actual payroll page uses the `salary_type` field to determine:
1. Whether to calculate hours (only for hourly staff)
2. Whether to display hours (only for hourly staff)

## The Fix

Run the fix script to update staff1 to hourly:

```bash
php fix_staff1_hourly_display.php
```

This script will:
1. Check staff1's current configuration
2. Update `salary_type='hourly'` and `hourly_rate=500.00`
3. Verify the update was successful

Alternatively, run the SQL directly:

```sql
-- Find staff1's staff_id
SELECT @staff_id := staff_id FROM users WHERE id = 2;

-- Update to hourly with $500/hour rate
UPDATE staff 
SET salary_type = 'hourly',
    hourly_rate = 500.00
WHERE id = @staff_id;

-- Verify
SELECT u.id, u.name, s.salary_type, s.hourly_rate
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.id = 2;
```

## After the Fix

1. Go to Payroll page
2. Select March 2026
3. Click "Generate Payroll"
4. Look for staff1 in the batch table
5. You should now see:
   - **$32,250.00** (the calculated salary)
   - **64.50h × $500.00/h** (the hours breakdown in small gray text below)

## Why This Happened

The implementation is correct and complete. The issue was simply that staff1 was never configured as an hourly employee through the UI or database. The system correctly treats staff1 as a fixed-salary employee because that's what the database says.

## Files Created for Diagnosis

1. `debug_batch_preparation.php` - Confirmed calculation logic works
2. `debug_display_issue.php` - Simulates the display logic
3. `check_staff1_salary_type.sql` - Checks current configuration
4. `set_staff1_to_hourly.sql` - SQL to fix the issue
5. `fix_staff1_hourly_display.php` - Automated fix script (RECOMMENDED)

## Verification Steps

After running the fix:

1. Check database:
   ```sql
   SELECT u.id, u.name, s.salary_type, s.hourly_rate
   FROM users u
   JOIN staff s ON s.id = u.staff_id
   WHERE u.id = 2;
   ```
   Should show: `salary_type='hourly'`, `hourly_rate=500.00`

2. Generate March 2026 payroll batch
3. Verify hours display appears for staff1

## Summary

- ✅ Code implementation is correct
- ✅ Calculation logic works
- ✅ Display logic works
- ❌ Staff1 was not configured as hourly in database
- ✅ Fix: Run `fix_staff1_hourly_display.php`

The hours will display once staff1 is properly configured as an hourly employee.
