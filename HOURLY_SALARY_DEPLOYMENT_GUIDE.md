# Hourly Salary Feature - Production Deployment Guide

**Date:** April 16, 2026  
**Feature:** Hourly-based salary calculation for staff  
**Billing Period:** 16th-to-15th (changed from 15th-to-14th)

---

## Overview

This feature allows you to configure staff members with hourly rates instead of fixed monthly salaries. The system will:
- Calculate salary based on actual hours worked from attendance records
- Display hours worked alongside the calculated salary
- Support both fixed and hourly salary types simultaneously

---

## Pre-Deployment Checklist

- [ ] Backup production database
- [ ] Verify all staff attendance data is accurate
- [ ] Identify which staff members should be hourly vs fixed salary
- [ ] Determine hourly rates for each hourly staff member

---

## Step 1: Run Database Migrations

You need to run **3 SQL migration files** on your production server in this order:

### Migration 1: Billing Cycle Change (Optional - only if you want to change billing period)

**File:** `migrations/releases/2026-04-15_billing_cycle_change.sql`

**What it does:** Updates monthly targets from 15th-to-14th period to 16th-to-15th period

**Run this if:** You want to change the billing period system-wide

```sql
-- This updates existing monthly_targets and vehicle_monthly_targets
-- Review the file before running to ensure it matches your needs
```

### Migration 2: Add Hourly Salary Columns to Staff Table

**File:** `migrations/releases/2026-04-16_hourly_salary_calculation.sql`

**What it does:** 
- Adds `salary_type` column (ENUM: 'fixed' or 'hourly') - defaults to 'fixed'
- Adds `hourly_rate` column (DECIMAL) - stores per-hour rate for hourly staff

**IMPORTANT:** This is **idempotent** - safe to run multiple times

```sql
-- Run this migration via phpMyAdmin or MySQL command line
-- All existing staff will default to salary_type='fixed'
```

### Migration 3: Add Hours Worked Column to Payroll Table

**File:** `migrations/releases/2026-04-16_payroll_hours_worked.sql`

**What it does:**
- Adds `hours_worked` column to payroll table
- Stores calculated hours for hourly staff (NULL for fixed salary staff)

```sql
-- Run this migration via phpMyAdmin or MySQL command line
-- Existing payroll records will have NULL hours_worked (which is correct)
```

---

## Step 2: Deploy Updated PHP Files

Upload these files to your production server:

### Core Files
1. **`payroll/index.php`** - Updated with hourly calculation logic and display
2. **`includes/payroll_helpers.php`** - Contains calculation functions

### Helper Functions Added
- `calculate_hours_worked($pdo, $userId, $startDate, $endDate)` - Calculates total hours from attendance
- `calculate_hourly_payment($hours, $hourlyRate)` - Calculates payment from hours × rate

---

## Step 3: Configure Staff Profiles for Hourly Rates

After migrations are complete, you need to configure each staff member's salary type.

### For Fixed Salary Staff (Default)
No action needed - they will continue working as before with their monthly salary.

### For Hourly Rate Staff

You need to update each hourly staff member's profile:

```sql
-- Example: Set staff1 to hourly with $500/hour rate
UPDATE staff 
SET 
    salary_type = 'hourly',
    hourly_rate = 500.00
WHERE id = 1;  -- Replace with actual staff_id
```

**Or via Staff Management UI:**
1. Go to Staff Management
2. Edit the staff member's profile
3. Change "Salary Type" to "Hourly"
4. Enter their "Hourly Rate" (e.g., 500.00)
5. Save

---

## Step 4: Verify Configuration

### Check Staff Configuration

Run this query to verify all staff are configured correctly:

```sql
SELECT 
    s.id,
    s.name,
    s.role,
    s.salary AS fixed_monthly_salary,
    s.salary_type,
    s.hourly_rate,
    u.is_active
FROM staff s
LEFT JOIN users u ON u.staff_id = s.id
ORDER BY s.name;
```

**Expected Results:**
- Fixed salary staff: `salary_type='fixed'`, `hourly_rate=NULL`
- Hourly staff: `salary_type='hourly'`, `hourly_rate` has a value (e.g., 500.00)

### Check Attendance Data

Verify attendance records exist for hourly staff:

```sql
SELECT 
    u.name,
    COUNT(*) as attendance_days,
    MIN(sa.date) as first_date,
    MAX(sa.date) as last_date
FROM staff_attendance sa
JOIN users u ON u.id = sa.user_id
JOIN staff s ON s.id = u.staff_id
WHERE s.salary_type = 'hourly'
  AND sa.punch_in IS NOT NULL
  AND sa.punch_out IS NOT NULL
GROUP BY u.id, u.name;
```

---

## Step 5: Test Payroll Generation

### Test with Current Month

1. Go to Payroll page
2. Click "Prepare Payroll"
3. Select current month/year
4. Click "Generate Batch"

### What to Look For

**For Hourly Staff:**
- Basic Salary column shows calculated amount (e.g., $32,250.00)
- Below the amount, you should see in **bright cyan text**: `64.50h × $500.00/h`
- This shows: hours worked × hourly rate

**For Fixed Salary Staff:**
- Basic Salary column shows their fixed monthly salary
- No hours display (this is correct)

### If Hours Don't Display

1. Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)
2. Check browser console (F12) for JavaScript errors
3. Verify the staff member has `salary_type='hourly'` in database
4. Verify attendance records exist for the billing period

---

## Billing Period Information

The system now uses **16th-to-15th billing period**:

- **March 2026 payroll** = March 16, 2026 to April 15, 2026
- **April 2026 payroll** = April 16, 2026 to May 15, 2026
- **May 2026 payroll** = May 16, 2026 to June 15, 2026

This applies to:
- Payroll calculations
- Incentive calculations (closed leads, deliveries)
- Overtime calculations
- Hours worked calculations

---

## Configuration Examples

### Example 1: Driver with $500/hour rate

```sql
UPDATE staff 
SET 
    salary_type = 'hourly',
    hourly_rate = 500.00
WHERE name = 'Driver Name';
```

If they work 64.5 hours in the billing period:
- Salary = 64.5h × $500/h = **$32,250.00**
- Display: `$32,250.00` with `64.50h × $500.00/h` below

### Example 2: Manager with fixed $50,000/month

```sql
UPDATE staff 
SET 
    salary_type = 'fixed',
    hourly_rate = NULL
WHERE name = 'Manager Name';
```

- Salary = **$50,000.00** (from staff.salary column)
- Display: `$50,000.00` (no hours shown)

### Example 3: Mixed Team

You can have both types in the same payroll batch:
- 3 drivers on hourly rates
- 2 managers on fixed salaries
- 1 admin on fixed salary

Each will calculate correctly based on their `salary_type`.

---

## Troubleshooting

### Hours show as 0.00h

**Cause:** No attendance records for the billing period

**Solution:** 
1. Check attendance records exist
2. Verify punch_in and punch_out are not NULL
3. Verify dates fall within billing period (16th-to-15th)

### Hours display is invisible

**Cause:** Browser cache showing old CSS

**Solution:**
1. Hard refresh (Ctrl+Shift+R)
2. Clear browser cache
3. Check if text is cyan colored (should be visible)

### Salary shows $0.00 for hourly staff

**Cause:** `hourly_rate` is NULL in database

**Solution:**
```sql
-- Check the staff record
SELECT id, name, salary_type, hourly_rate FROM staff WHERE name = 'Staff Name';

-- Set the hourly rate
UPDATE staff SET hourly_rate = 500.00 WHERE id = X;
```

### "NULL hourly_rate" error in logs

**Cause:** Staff has `salary_type='hourly'` but `hourly_rate=NULL`

**Solution:** Set a valid hourly_rate for that staff member

---

## Rollback Plan

If you need to rollback this feature:

### 1. Revert PHP Files
Replace with previous versions of:
- `payroll/index.php`
- `includes/payroll_helpers.php`

### 2. Keep Database Changes
The database columns are safe to keep:
- `staff.salary_type` defaults to 'fixed' (backward compatible)
- `staff.hourly_rate` is NULL for fixed staff (ignored)
- `payroll.hours_worked` is NULL for old records (ignored)

### 3. Or Remove Columns (Optional)
```sql
-- Only if you want to completely remove the feature
ALTER TABLE staff DROP COLUMN hourly_rate;
ALTER TABLE staff DROP COLUMN salary_type;
ALTER TABLE payroll DROP COLUMN hours_worked;
```

---

## Support

If you encounter issues:

1. Check application logs for errors
2. Verify all 3 migrations ran successfully
3. Verify staff configuration is correct
4. Test with a single hourly staff member first
5. Review attendance data for the billing period

---

## Summary Checklist

- [ ] Backup database
- [ ] Run migration: `2026-04-15_billing_cycle_change.sql` (optional)
- [ ] Run migration: `2026-04-16_hourly_salary_calculation.sql` (required)
- [ ] Run migration: `2026-04-16_payroll_hours_worked.sql` (required)
- [ ] Upload `payroll/index.php`
- [ ] Upload `includes/payroll_helpers.php`
- [ ] Configure hourly staff profiles (set salary_type and hourly_rate)
- [ ] Verify staff configuration with SQL query
- [ ] Test payroll generation for current month
- [ ] Verify hours display in bright cyan text
- [ ] Generate actual payroll when ready

---

**Feature Status:** ✅ Ready for Production Deployment
