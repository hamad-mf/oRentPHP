# Hourly Salary - Production Deployment (Simplified)

**Date:** April 16, 2026  
**Status:** Ready to deploy

---

## What You Need to Do

1. Run 2 SQL migrations
2. Upload 2 PHP files  
3. Configure staff profiles

---

## Step 1: Run SQL Migrations

### Migration A: Add Hourly Salary Columns to Staff Table

Copy and paste this into phpMyAdmin SQL tab:

```sql
-- Add salary_type and hourly_rate columns to staff table
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS salary_type ENUM('fixed', 'hourly') NOT NULL DEFAULT 'fixed' AFTER salary;

ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) DEFAULT NULL AFTER salary_type;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify it worked
SELECT id, name, salary, salary_type, hourly_rate FROM staff ORDER BY id;
```

**Expected result:** You should see all staff with `salary_type='fixed'` and `hourly_rate=NULL`

---

### Migration B: Add Hours Worked Column to Payroll Table

Copy and paste this into phpMyAdmin SQL tab:

```sql
-- Add hours_worked column to payroll table
ALTER TABLE payroll 
ADD COLUMN IF NOT EXISTS hours_worked DECIMAL(10,4) DEFAULT NULL COMMENT 'Hours worked for hourly staff (NULL for fixed salary staff)' 
AFTER basic_salary;

-- Verify it worked
DESCRIBE payroll;
```

**Expected result:** You should see `hours_worked` column in the payroll table structure

---

## Step 2: Upload PHP Files

Upload these 2 files to your production server:

1. **`payroll/index.php`** - Main payroll file with hourly calculation
2. **`includes/payroll_helpers.php`** - Helper functions for calculations

---

## Step 3: Configure Staff for Hourly Rates

For each staff member you want to pay hourly, run this SQL:

```sql
-- Example: Set staff member to hourly with $500/hour rate
UPDATE staff 
SET 
    salary_type = 'hourly',
    hourly_rate = 500.00
WHERE id = 1;  -- Replace with actual staff ID
```

**To find staff IDs:**
```sql
SELECT id, name, role, salary FROM staff ORDER BY name;
```

---

## Step 4: Verify Everything Works

### Check Staff Configuration

```sql
SELECT 
    id,
    name,
    role,
    salary AS fixed_monthly_salary,
    salary_type,
    hourly_rate
FROM staff
ORDER BY name;
```

**What to look for:**
- Fixed salary staff: `salary_type='fixed'`, `hourly_rate=NULL`
- Hourly staff: `salary_type='hourly'`, `hourly_rate` has a value

### Check Attendance Data

```sql
SELECT 
    u.name,
    COUNT(*) as days_worked,
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

**What to look for:** Hourly staff should have attendance records

---

## Step 5: Test Payroll Generation

1. Go to Payroll page in your system
2. Click "Prepare Payroll"
3. Select current month/year
4. Click "Generate Batch"

### What You Should See

**For Hourly Staff:**
- Basic Salary shows calculated amount (e.g., $32,250.00)
- Below it in bright cyan text: `64.50h × $500.00/h`

**For Fixed Salary Staff:**
- Basic Salary shows their fixed monthly amount
- No hours display (this is correct)

---

## Troubleshooting

### Problem: Hours show 0.00h

**Cause:** No attendance records for the billing period

**Fix:** Add attendance records or verify dates are correct

### Problem: Salary shows $0.00 for hourly staff

**Cause:** `hourly_rate` is NULL

**Fix:**
```sql
UPDATE staff SET hourly_rate = 500.00 WHERE id = X;
```

### Problem: Can't see hours text

**Cause:** Browser cache

**Fix:** Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)

---

## Billing Period

The system uses **16th-to-15th billing period**:

- March 2026 payroll = March 16 to April 15
- April 2026 payroll = April 16 to May 15
- May 2026 payroll = May 16 to June 15

---

## Summary Checklist

- [ ] Run Migration A (add salary_type and hourly_rate columns)
- [ ] Run Migration B (add hours_worked column)
- [ ] Upload `payroll/index.php`
- [ ] Upload `includes/payroll_helpers.php`
- [ ] Configure hourly staff (set salary_type and hourly_rate)
- [ ] Verify staff configuration
- [ ] Test payroll generation
- [ ] Verify hours display in cyan text

---

## Need Help?

If migrations fail or something doesn't work:
1. Check the error message
2. Verify table names match your database
3. Make sure you have ALTER TABLE permissions
4. Contact support with the specific error

---

**Status:** ✅ Ready for Production
