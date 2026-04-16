# Testing Hours Display - Step by Step Guide

## Current Status

✅ Staff1 is configured as hourly with $500/hour rate  
✅ Attendance data exists for March 16-22, 2026 (64.5 hours)  
✅ Display code is present in payroll/index.php  
✅ Calculation logic is working correctly  

## Issue

User reports not seeing the hours display in the payroll batch preparation screen.

## Root Cause Analysis

The hours display code checks `if ($salaryType === 'hourly')` before showing the hours breakdown. Since staff1 IS configured as hourly, the code SHOULD be executing.

Possible reasons for not seeing the display:
1. **Browser cache** - Old version of the page is cached
2. **Wrong screen** - Looking at saved payroll list instead of batch preparation
3. **Old batch** - Viewing a batch generated before staff1 was set to hourly
4. **CSS issue** - The text is there but hidden by CSS
5. **JavaScript issue** - JS is interfering with display

## Testing Steps

### Step 1: Clear Browser Cache
1. Open the payroll page
2. Press **Ctrl+Shift+R** (Windows) or **Cmd+Shift+R** (Mac) to hard refresh
3. This clears the cached version and loads fresh HTML/CSS/JS

### Step 2: Generate a NEW Batch
**IMPORTANT**: You must generate a NEW batch, not view an old one.

1. Go to Payroll page: `payroll/index.php`
2. Select **March 2026** from the dropdown
3. Click **"Generate Payroll"** button
4. This will show the **Batch Preparation Screen**

### Step 3: Verify You're on the Batch Preparation Screen
The batch preparation screen has:
- A form with editable incentive fields
- A "Save Payroll" button at the bottom
- Staff listed in a table with columns: Name, Role, Leads, Deliveries, Basic Salary, Incentive, Overtime, Net, Notes

If you see:
- "Pay" buttons next to each staff member
- "Status: Pending" or "Status: Paid"
- No editable fields

Then you're on the **Saved Payroll List** screen (wrong screen).

### Step 4: Look for Hours Display
In the **Basic Salary** column for staff1, you should see:

```
$32,250.00
64.50h × $500.00/h
```

The hours breakdown (`64.50h × $500.00/h`) appears in small gray text below the salary amount.

### Step 5: If Still Not Visible - Check Browser Inspector
1. Right-click on the salary amount for staff1
2. Select "Inspect" or "Inspect Element"
3. Look for a `<p>` tag with class `text-[10px] text-mb-subtle/70`
4. Check if:
   - The element exists in the HTML
   - The element has `display: none` or `visibility: hidden` in CSS
   - The element has content (should show "64.50h × $500.00/h")

### Step 6: Check Console for Errors
1. Open browser console (F12)
2. Look for any JavaScript errors (red text)
3. Errors might be preventing the page from rendering correctly

## Expected Results

### Batch Preparation Screen (NEW batch)
For staff1, the Basic Salary column should show:
```
$32,250.00
64.50h × $500.00/h
```

### Saved Payroll List Screen (after saving)
After you save the batch and view the payroll list, staff1 should also show:
```
$32,250.00
64.50h × $500.00/h
```

## Troubleshooting

### Problem: "Payroll for March 2026 has already been generated"
**Solution**: The batch already exists. You need to either:
1. Delete the existing March 2026 payroll records:
   ```sql
   DELETE FROM payroll WHERE month = 3 AND year = 2026;
   ```
2. Or test with a different month (e.g., April 2026) after adding attendance data for that month

### Problem: Hours show as "0.00h × $500.00/h"
**Solution**: No attendance records found for the billing period. Check:
1. Attendance records exist for March 16-22, 2026
2. Records have both `punch_in` and `punch_out` (not NULL)
3. Records are for user_id=2 (staff1)

### Problem: Salary shows $0.00
**Solution**: Hours worked is below 1.0 hour threshold. You should see an orange badge saying "Below 1hr threshold".

### Problem: Still shows fixed salary format (just "$X,XXX.XX" with no hours)
**Solution**: Staff1 is not configured as hourly. Run:
```bash
php check_and_fix_staff1.php
```

## Verification Queries

### Check staff1 configuration:
```sql
SELECT u.id, u.name, s.salary_type, s.hourly_rate
FROM users u
JOIN staff s ON s.id = u.staff_id
WHERE u.id = 2;
```
Should show: `salary_type='hourly'`, `hourly_rate=500.00`

### Check attendance data:
```sql
SELECT date, punch_in, punch_out,
       TIMESTAMPDIFF(SECOND, punch_in, punch_out) / 3600 AS hours
FROM staff_attendance
WHERE user_id = 2
  AND date BETWEEN '2026-03-16' AND '2026-03-22'
  AND punch_in IS NOT NULL
  AND punch_out IS NOT NULL;
```
Should show 7 records totaling ~64.5 hours

### Check if March 2026 payroll exists:
```sql
SELECT * FROM payroll WHERE month = 3 AND year = 2026;
```
If records exist, you're viewing saved payroll, not generating a new batch.

## Summary

The most likely issue is that you're viewing an OLD batch or the SAVED payroll list, not generating a NEW batch. To see the hours display:

1. **Delete existing March 2026 payroll** (if it exists)
2. **Hard refresh the browser** (Ctrl+Shift+R)
3. **Generate a NEW batch** for March 2026
4. **Look in the Basic Salary column** for the hours breakdown

The hours will appear in small gray text below the salary amount.
