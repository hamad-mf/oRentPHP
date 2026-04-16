# Hours Display Fix - Summary

## Problem Identified

The hourly salary calculation was working correctly (staff1 showed $32,250.00 salary), but the hours worked breakdown was NOT displaying in the payroll list.

### Root Cause

The hours display logic existed in TWO different places in `payroll/index.php`:

1. **Batch Preparation View** (lines 800-820) - Shows hours when CREATING a new payroll batch
2. **Saved Payroll List View** (lines 980-982) - Shows saved payroll records

The issue was:
- Hours display logic existed in the batch preparation view ✓
- Hours display logic was MISSING in the saved payroll list view ✗
- The `payroll` table had NO `hours_worked` column to store the data ✗

When you generated the March 2026 payroll and saved it, the hours_worked data was calculated but NEVER SAVED to the database. When viewing the saved payroll list, there was no hours data to display.

## Solution Implemented

### 1. Database Migration
**File**: `migrations/releases/2026-04-16_payroll_hours_worked.sql`

Added `hours_worked` column to the payroll table:
```sql
ALTER TABLE payroll 
ADD COLUMN IF NOT EXISTS hours_worked DECIMAL(10,4) DEFAULT NULL 
COMMENT 'Hours worked for hourly staff (NULL for fixed salary staff)' 
AFTER basic_salary;
```

### 2. Save Logic Update
**File**: `payroll/index.php` (save_payroll action, ~line 300)

Updated to:
- Calculate and store `$hoursWorked` variable (NULL for fixed staff, calculated value for hourly staff)
- Include `hours_worked` in INSERT statements for both advance-enabled and non-advance payroll

**Before**:
```php
INSERT INTO payroll (user_id, month, year, basic_salary, incentive, ...)
VALUES (?, ?, ?, ?, ?, ...)
```

**After**:
```php
INSERT INTO payroll (user_id, month, year, basic_salary, hours_worked, incentive, ...)
VALUES (?, ?, ?, ?, ?, ?, ...)
```

### 3. Query Update
**File**: `payroll/index.php` (line ~614)

Updated payroll list query to include `salary_type` and `hourly_rate`:

**Before**:
```php
SELECT p.*, u.name AS staff_name, s.role AS staff_role, ba.name AS paid_from_name
FROM payroll p ...
```

**After**:
```php
SELECT p.*, u.name AS staff_name, s.role AS staff_role, s.salary_type, s.hourly_rate, ba.name AS paid_from_name
FROM payroll p ...
```

### 4. Display Logic Update
**File**: `payroll/index.php` (line ~980)

Added hours display logic to the saved payroll list (same logic as batch preparation view):

**Before**:
```php
<td class="px-6 py-4 text-right text-mb-silver">$
    <?= number_format($row['basic_salary'], 2) ?>
</td>
```

**After**:
```php
<td class="px-6 py-4 text-right text-mb-silver">
    <?php 
    $salaryType = $row['salary_type'] ?? 'fixed';
    if ($salaryType === 'hourly' && isset($row['hours_worked'])): 
        $hoursWorked = (float) $row['hours_worked'];
        $hourlyRate = (float) ($row['hourly_rate'] ?? 0);
        $belowThreshold = $hoursWorked < 1.0;
    ?>
        <div class="text-right">
            <span class="text-mb-silver">$<?= number_format($row['basic_salary'], 2) ?></span>
            <p class="text-[10px] text-mb-subtle/70 mt-0.5">
                <?= number_format($hoursWorked, 2) ?>h × $<?= number_format($hourlyRate, 2) ?>/h
            </p>
            <?php if ($belowThreshold): ?>
                <span class="inline-flex items-center gap-1 bg-orange-500/10 text-orange-400 border border-orange-500/20 rounded-full px-2 py-0.5 text-[10px] font-medium mt-1">
                    Below 1hr threshold
                </span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        $<?= number_format($row['basic_salary'], 2) ?>
    <?php endif; ?>
</td>
```

## How to Apply the Fix

### Option 1: Automated Script (Recommended)
1. Run: `php apply_hours_worked_fix.php` in your browser
2. The script will:
   - Add the hours_worked column
   - Delete existing March 2026 payroll
   - Show instructions for regenerating

### Option 2: Manual Steps
1. Run the migration:
   ```bash
   mysql -u your_user -p your_database < migrations/releases/2026-04-16_payroll_hours_worked.sql
   ```

2. Delete existing March 2026 payroll:
   ```sql
   DELETE FROM payroll WHERE month = 3 AND year = 2026;
   ```

3. Regenerate March 2026 payroll:
   - Go to Payroll page
   - Click "Generate Payroll"
   - Select March 2026
   - Click "Prepare Batch"
   - Review and click "Save Payroll"

## Expected Result

After applying the fix and regenerating payroll, you will see:

**For hourly staff (like staff1 with 64.50 hours @ $500/hour):**
```
$32,250.00
64.50h × $500.00/h
```

**For fixed salary staff:**
```
$5,000.00
```

## Files Modified

1. `migrations/releases/2026-04-16_payroll_hours_worked.sql` - NEW
2. `payroll/index.php` - MODIFIED (3 sections)
3. `apply_hours_worked_fix.php` - NEW (helper script)
4. `HOURS_DISPLAY_FIX_SUMMARY.md` - NEW (this file)

## Testing Checklist

- [ ] Run migration to add hours_worked column
- [ ] Delete existing March 2026 payroll
- [ ] Regenerate March 2026 payroll
- [ ] Verify hours display in batch preparation view
- [ ] Save payroll
- [ ] Verify hours display in saved payroll list ← THIS WAS THE MISSING PIECE
- [ ] Verify fixed salary staff show no hours
- [ ] Verify below-threshold badge shows for <1 hour

## Why This Happened

The original implementation focused on the batch preparation view (where you create payroll) but didn't consider that the saved payroll list is a DIFFERENT view that queries the database. Without storing hours_worked in the database, there was no way to display it later.

This is a common oversight when implementing features that have both "create" and "view" interfaces - you need to ensure data persistence for both views.

## Status

✅ Fix implemented and ready to test
✅ Migration created
✅ Helper script created
✅ Documentation complete

---

**Date**: April 15, 2026
**Issue**: Hours worked not displaying in saved payroll list
**Resolution**: Added hours_worked column and display logic to saved payroll list view
