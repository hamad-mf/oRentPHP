# Billing Cycle Change Implementation Plan
## Change from 15th-to-14th → 16th-to-15th

**Date Created**: April 15, 2026  
**Current System**: Month runs from 15th to 14th (e.g., April 15 - May 14)  
**New System**: Month runs from 16th to 15th (e.g., April 16 - May 15)  
**Implementation Date**: April 15, 2026 (Today)

---

## Executive Summary

This document outlines the complete plan to change the billing cycle from **15th-to-14th** to **16th-to-15th** across all financial modules in the system.

### Key Points
- ✅ **Safe to implement today** - April 15 becomes last day of current period
- ✅ **No data loss** - All historical data preserved
- ✅ **Minimal code changes** - Only 4 files need modification
- ⚠️ **User communication required** - Staff need to know about the change
- ⚠️ **Optional database migration** - Update existing period targets

---

## Current vs New System Comparison

### Current System (15th-to-14th)
```
Period: April 15, 2026 → May 14, 2026 (30 days)
├─ Start: 15th of month (00:00:00)
└─ End: 14th of next month (23:59:59)

Today (April 15): DAY 1 of new period
```

### New System (16th-to-15th)
```
Period: April 16, 2026 → May 15, 2026 (30 days)
├─ Start: 16th of month (00:00:00)
└─ End: 15th of next month (23:59:59)

Today (April 15): LAST DAY of current period (March 16 - April 15)
Tomorrow (April 16): DAY 1 of new period
```

---

## Impact Analysis

### 1. Affected Modules (6 Total)

| Module | File | Function to Change |
|--------|------|-------------------|
| Hope Window | `accounts/hope_window.php` | `hope_period_from_my()` |
| Monthly Targets | `accounts/targets.php` | `period_from_my()` |
| Vehicle Targets | `accounts/vehicle_targets.php` | `vt_period_from_my()` |
| Monthly Reports | `reports/index.php` | `period_from_my()` |
| Staff Advances | `staff/show.php` | `period_from_my()` |
| Staff Incentives | `staff/show.php` | `period_from_my()` |

### 2. Database Tables Affected

| Table | Column | Impact | Action Required |
|-------|--------|--------|-----------------|
| `monthly_targets` | `period_start`, `period_end` | Old records keep old dates | Optional: Migrate current period |
| `vehicle_monthly_targets` | `period_start`, `period_end` | Old records keep old dates | Optional: Migrate current period |
| `hope_daily_targets` | `target_date` | Individual dates, no conflict | None |
| `hope_daily_predictions` | `target_date` | Individual dates, no conflict | None |

### 3. Data Behavior Timeline

#### Today (April 15, 2026)
- ✅ All transactions entered today belong to: **March 16 - April 15 period**
- ✅ Hope Window shows April 15 in current period
- ✅ Monthly Targets count April 15 towards current period
- ✅ Reports include April 15 in current period

#### Tonight (April 15, 2026 at 23:59:59)
- 🔚 Current period ends: March 16 - April 15

#### Tomorrow (April 16, 2026 at 00:00:00)
- 🆕 New period starts: April 16 - May 15
- 🆕 All transactions go into new period automatically

---

## Implementation Steps

### Phase 1: Pre-Implementation (Before Code Changes)

#### Step 1.1: Backup Database ⚠️ CRITICAL
```bash
# Create full database backup
mysqldump -u [username] -p [database_name] > backup_before_billing_cycle_change_2026-04-15.sql

# Verify backup file exists and has content
ls -lh backup_before_billing_cycle_change_2026-04-15.sql
```

**Verification**: Backup file should be > 1MB and contain SQL statements

#### Step 1.2: Document Current State
```sql
-- Record current period targets for reference
SELECT * FROM monthly_targets WHERE period_start >= '2026-03-15' ORDER BY period_start;
SELECT * FROM vehicle_monthly_targets WHERE period_start >= '2026-03-15' ORDER BY period_start;
```

**Save output** to `current_targets_snapshot.txt` for reference

#### Step 1.3: Notify Users
**Send notification to all staff:**
```
Subject: Important: Billing Cycle Change - April 15, 2026

Dear Team,

Starting tomorrow (April 16), our monthly billing cycle will change:

OLD: 15th to 14th (e.g., April 15 - May 14)
NEW: 16th to 15th (e.g., April 16 - May 15)

What this means:
- Today (April 15) is the LAST day of the current period
- Tomorrow (April 16) starts the new period
- From now on, the 16th marks the start of each new month

All historical data remains intact. If you have questions, please contact IT.

Thank you,
IT Team
```

---

### Phase 2: Code Changes

#### Step 2.1: Update Hope Window (`accounts/hope_window.php`)

**Location**: Line ~33-39

**BEFORE:**
```php
function hope_period_from_my(int $m, int $y): array
{
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}
```

**AFTER:**
```php
function hope_period_from_my(int $m, int $y): array
{
    $start = sprintf('%04d-%02d-16', $y, $m);  // Changed: 15 → 16
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-15', $nY, $nM)];  // Changed: 14 → 15
}
```

**Also update `hope_period_for_today()` function:**

**BEFORE:**
```php
function hope_period_for_today(): array
{
    $d = (int) date('d');
    $m = (int) date('m');
    $y = (int) date('Y');
    if ($d >= 15) {  // Changed: 15 → 16
        return hope_period_from_my($m, $y);
    }
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return hope_period_from_my($pm, $py);
}
```

**AFTER:**
```php
function hope_period_for_today(): array
{
    $d = (int) date('d');
    $m = (int) date('m');
    $y = (int) date('Y');
    if ($d >= 16) {  // Changed: 15 → 16
        return hope_period_from_my($m, $y);
    }
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return hope_period_from_my($pm, $py);
}
```

#### Step 2.2: Update Monthly Targets (`accounts/targets.php`)

**Location**: Line ~27-34

**BEFORE:**
```php
function period_from_my(int $m, int $y): array {
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}
```

**AFTER:**
```php
function period_from_my(int $m, int $y): array {
    $start = sprintf('%04d-%02d-16', $y, $m);  // Changed: 15 → 16
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-15', $nY, $nM)];  // Changed: 14 → 15
}
```

**Also update `period_for_today()` function:**

**BEFORE:**
```php
function period_for_today(): array {
    $d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y');
    if ($d >= 15) return period_from_my($m, $y);
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return period_from_my($pm, $py);
}
```

**AFTER:**
```php
function period_for_today(): array {
    $d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y');
    if ($d >= 16) return period_from_my($m, $y);  // Changed: 15 → 16
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return period_from_my($pm, $py);
}
```

#### Step 2.3: Update Vehicle Targets (`accounts/vehicle_targets.php`)

**Location**: Line ~20-27

**BEFORE:**
```php
function vt_period_from_my(int $m, int $y): array
{
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}
```

**AFTER:**
```php
function vt_period_from_my(int $m, int $y): array
{
    $start = sprintf('%04d-%02d-16', $y, $m);  // Changed: 15 → 16
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-15', $nY, $nM)];  // Changed: 14 → 15
}
```

**Also update `vt_period_for_today()` function:**

**BEFORE:**
```php
function vt_period_for_today(): array
{
    $d = (int) date('d');
    $m = (int) date('m');
    $y = (int) date('Y');
    if ($d >= 15) {
        return vt_period_from_my($m, $y);
    }
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return vt_period_from_my($pm, $py);
}
```

**AFTER:**
```php
function vt_period_for_today(): array
{
    $d = (int) date('d');
    $m = (int) date('m');
    $y = (int) date('Y');
    if ($d >= 16) {  // Changed: 15 → 16
        return vt_period_from_my($m, $y);
    }
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return vt_period_from_my($pm, $py);
}
```

#### Step 2.4: Update Monthly Reports (`reports/index.php`)

**Location**: Line ~17-24

**BEFORE:**
```php
function period_from_my(int $m, int $y): array {
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}
```

**AFTER:**
```php
function period_from_my(int $m, int $y): array {
    $start = sprintf('%04d-%02d-16', $y, $m);  // Changed: 15 → 16
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-15', $nY, $nM)];  // Changed: 14 → 15
}
```

**Also update `period_for_today()` function:**

**BEFORE:**
```php
function period_for_today(): array {
    $d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y');
    if ($d >= 15) return period_from_my($m, $y);
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return period_from_my($pm, $py);
}
```

**AFTER:**
```php
function period_for_today(): array {
    $d = (int)date('d'); $m = (int)date('m'); $y = (int)date('Y');
    if ($d >= 16) return period_from_my($m, $y);  // Changed: 15 → 16
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return period_from_my($pm, $py);
}
```

#### Step 2.5: Update Staff Modules (if applicable)

**Files**: `staff/show.php`, `staff/advance_history.php`, `staff/incentive_history.php`

**Same changes as above** - Update `period_from_my()` and `period_for_today()` functions

---

### Phase 3: Database Migration (Optional but Recommended)

#### Step 3.1: Migrate Current Period Targets

**Purpose**: Update existing targets for current period to use new dates

**SQL Script**: `migrations/releases/2026-04-15_billing_cycle_change.sql`

```sql
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
```

**Execution**:
```bash
mysql -u [username] -p [database_name] < migrations/releases/2026-04-15_billing_cycle_change.sql
```

#### Step 3.2: Alternative - Manual Update via UI

If you prefer not to run SQL directly:

1. After code deployment, go to **Monthly Targets** page
2. Delete existing target for "April 15 - May 14"
3. Create new target for "April 16 - May 15" with same amount
4. Repeat for **Vehicle Targets** if applicable

---

### Phase 4: Testing & Verification

#### Step 4.1: Immediate Testing (April 15, 2026)

**Test 1: Current Period Display**
- Navigate to Hope Window
- **Expected**: Shows "March 16 - April 15" as current period
- **Expected**: Today (April 15) is included in current period

**Test 2: Period Selector**
- Check month dropdown in all modules
- **Expected**: Shows "16 Mar – 15 Apr" format for each month

**Test 3: Data Inclusion**
- Enter a test transaction today (April 15)
- **Expected**: Transaction appears in current period (March 16 - April 15)

**Test 4: Historical Data**
- View previous periods
- **Expected**: All historical data visible, dates shifted by 1 day

#### Step 4.2: Next Day Testing (April 16, 2026)

**Test 5: New Period Start**
- Navigate to Hope Window at 00:01 on April 16
- **Expected**: Shows "April 16 - May 15" as current period
- **Expected**: Yesterday (April 15) NOT in current period

**Test 6: New Transactions**
- Enter a test transaction on April 16
- **Expected**: Transaction appears in new period (April 16 - May 15)

**Test 7: Period Boundary**
- Check that April 15 data is in previous period
- Check that April 16 data is in new period
- **Expected**: Clean separation at midnight

#### Step 4.3: Module-Specific Tests

| Module | Test | Expected Result |
|--------|------|-----------------|
| Hope Window | View current period | March 16 - April 15 (today) |
| Monthly Targets | Check daily breakdown | April 15 included in current |
| Vehicle Targets | View period selector | Shows 16th-to-15th format |
| Monthly Reports | Check daily rows | April 15 in current period |
| Staff Advances | Filter by period | Uses new date boundaries |

---

### Phase 5: Rollback Plan (If Needed)

#### Step 5.1: Code Rollback

**If issues occur, revert code changes:**

1. Restore backup of modified files
2. Change all `16` back to `15` and `15` back to `14`
3. Change all `>= 16` back to `>= 15`
4. Redeploy

#### Step 5.2: Database Rollback

**If database was migrated:**

```sql
-- Revert monthly_targets
UPDATE monthly_targets 
SET 
    period_start = '2026-04-15',
    period_end = '2026-05-14',
    updated_at = NOW()
WHERE period_start = '2026-04-16';

-- Revert vehicle_monthly_targets
UPDATE vehicle_monthly_targets 
SET 
    period_start = '2026-04-15',
    period_end = '2026-05-14',
    updated_at = NOW()
WHERE period_start = '2026-04-16';
```

#### Step 5.3: Full Database Restore (Last Resort)

```bash
# Restore from backup
mysql -u [username] -p [database_name] < backup_before_billing_cycle_change_2026-04-15.sql
```

---

## Risk Assessment

### Low Risk ✅
- Code changes are simple and isolated
- No database schema changes required
- All data preserved
- Easy to rollback

### Medium Risk ⚠️
- User confusion during transition
- Existing targets may need manual update
- Historical reports show different date ranges

### High Risk ❌
- None identified

---

## Post-Implementation

### Step 1: Monitor for 7 Days
- Check daily that periods are calculating correctly
- Verify transactions go into correct periods
- Monitor user feedback

### Step 2: Update Documentation
- Update user manual with new billing cycle
- Update training materials
- Update any external documentation

### Step 3: Archive This Plan
- Keep this document for future reference
- Document any issues encountered
- Note any additional changes made

---

#### Step 2.5: Update UI Dropdown Labels

**Purpose**: Update all month dropdown labels to show new 16th-to-15th format

**Files with Dropdown Labels** (9 files total):

1. **`accounts/hope_window.php`** - Line 830
   ```php
   // OLD
   $mLabel = '15 ' . date('M', mktime(0,0,0,$mVal,1)) . ' – 14 ' . date('M', mktime(0,0,0,$mNext,1));
   
   // NEW
   $mLabel = '16 ' . date('M', mktime(0,0,0,$mVal,1)) . ' – 15 ' . date('M', mktime(0,0,0,$mNext,1));
   ```

2. **`accounts/targets.php`** - Line 170
   ```php
   // OLD
   15 <?= date('M', mktime(0,0,0,$i,1)) ?> – 14 <?= date('M', mktime(0,0,0,$iN,1)) ?>
   
   // NEW
   16 <?= date('M', mktime(0,0,0,$i,1)) ?> – 15 <?= date('M', mktime(0,0,0,$iN,1)) ?>
   ```

3. **`accounts/vehicle_targets.php`** - Line 632
   ```php
   // OLD
   $mLabel = '15 ' . date('M', mktime(0,0,0,$mVal,1)) . ' – 14 ' . date('M', mktime(0,0,0,$mNext,1));
   
   // NEW
   $mLabel = '16 ' . date('M', mktime(0,0,0,$mVal,1)) . ' – 15 ' . date('M', mktime(0,0,0,$mNext,1));
   ```

4. **`reports/index.php`** - Line 181
   ```php
   // OLD
   $iLabel = '15 ' . date('M', mktime(0,0,0,$i,1)) . ' – 14 ' . date('M', mktime(0,0,0,$iN,1));
   
   // NEW
   $iLabel = '16 ' . date('M', mktime(0,0,0,$i,1)) . ' – 15 ' . date('M', mktime(0,0,0,$iN,1));
   ```

5. **`reports/vehicle_financial.php`** - Lines 391, 588 (2 locations)
   ```php
   // OLD (Line 391)
   $iLabel = '15 ' . date('M', mktime(0,0,0,$i,1)) . ' – 14 ' . date('M', mktime(0,0,0,$iN,1));
   
   // NEW
   $iLabel = '16 ' . date('M', mktime(0,0,0,$i,1)) . ' – 15 ' . date('M', mktime(0,0,0,$iN,1));
   
   // OLD (Line 588)
   $iLabel = '15 ' . date('M', mktime(0,0,0,$i,1)) . ' – 14 ' . date('M', mktime(0,0,0,$iN,1));
   
   // NEW
   $iLabel = '16 ' . date('M', mktime(0,0,0,$i,1)) . ' – 15 ' . date('M', mktime(0,0,0,$iN,1));
   ```

6. **`staff/show.php`** - Lines 357, 424, 462, 519 (4 locations)
   ```php
   // OLD (Line 357 - Advance dropdown)
   $amLabel = '15 ' . date('M', mktime(0,0,0,$am,1)) . ' – 14 ' . date('M', mktime(0,0,0,$amNext,1));
   
   // NEW
   $amLabel = '16 ' . date('M', mktime(0,0,0,$am,1)) . ' – 15 ' . date('M', mktime(0,0,0,$amNext,1));
   
   // OLD (Line 424 - Advance history display)
   $advPeriod = '15 ' . date('M', mktime(0,0,0,$advM,1)) . ' – 14 ' . date('M', mktime(0,0,0,$advMNext,1)) . ' ' . $advY;
   
   // NEW
   $advPeriod = '16 ' . date('M', mktime(0,0,0,$advM,1)) . ' – 15 ' . date('M', mktime(0,0,0,$advMNext,1)) . ' ' . $advY;
   
   // OLD (Line 462 - Incentive dropdown)
   $imLabel = '15 ' . date('M', mktime(0,0,0,$im,1)) . ' – 14 ' . date('M', mktime(0,0,0,$imNext,1));
   
   // NEW
   $imLabel = '16 ' . date('M', mktime(0,0,0,$im,1)) . ' – 15 ' . date('M', mktime(0,0,0,$imNext,1));
   
   // OLD (Line 519 - Incentive history display)
   $incPeriod = '15 ' . date('M', mktime(0,0,0,$incM,1)) . ' – 14 ' . date('M', mktime(0,0,0,$incMNext,1)) . ' ' . $incY;
   
   // NEW
   $incPeriod = '16 ' . date('M', mktime(0,0,0,$incM,1)) . ' – 15 ' . date('M', mktime(0,0,0,$incMNext,1)) . ' ' . $incY;
   ```

7. **`staff/advance_history.php`** - Line 119
   ```php
   // OLD
   $periodLabel = '15 ' . date('M', mktime(0,0,0,$monthNo,1)) . ' – 14 ' . date('M', mktime(0,0,0,$monthNext,1)) . ' ' . $yearNo;
   
   // NEW
   $periodLabel = '16 ' . date('M', mktime(0,0,0,$monthNo,1)) . ' – 15 ' . date('M', mktime(0,0,0,$monthNext,1)) . ' ' . $yearNo;
   ```

8. **`staff/incentive_history.php`** - Line 111
   ```php
   // OLD
   $periodLabel = '15 ' . date('M', mktime(0,0,0,$monthNo,1)) . ' – 14 ' . date('M', mktime(0,0,0,$monthNext,1)) . ' ' . $yearNo;
   
   // NEW
   $periodLabel = '16 ' . date('M', mktime(0,0,0,$monthNo,1)) . ' – 15 ' . date('M', mktime(0,0,0,$monthNext,1)) . ' ' . $yearNo;
   ```

9. **`payroll/index.php`** - Lines 806, 987 (2 locations)
   ```php
   // OLD (Line 806)
   15 <?= date('M', mktime(0,0,0,$m,1)) ?> – 14 <?= date('M', mktime(0,0,0,$mn,1)) ?>
   
   // NEW
   16 <?= date('M', mktime(0,0,0,$m,1)) ?> – 15 <?= date('M', mktime(0,0,0,$mn,1)) ?>
   
   // OLD (Line 987)
   15 <?= date('M', mktime(0,0,0,$m,1)) ?> – 14 <?= date('M', mktime(0,0,0,$mn,1)) ?>
   
   // NEW
   16 <?= date('M', mktime(0,0,0,$m,1)) ?> – 15 <?= date('M', mktime(0,0,0,$mn,1)) ?>
   ```

**Total UI Label Changes**: 15 label changes across 9 files

---

### Phase 3: Database Migration (Optional but Recommended)

#### Step 3.1: Migrate Current Period Targets

**Purpose**: Update existing targets for current period to use new dates

**SQL Script**: `migrations/releases/2026-04-15_billing_cycle_change.sql`

```sql
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
```

**Execution**:
```bash
mysql -u [username] -p [database_name] < migrations/releases/2026-04-15_billing_cycle_change.sql
```

#### Step 3.2: Alternative - Manual Update via UI

If you prefer not to run SQL directly:

1. After code deployment, go to **Monthly Targets** page
2. Delete existing target for "April 15 - May 14"
3. Create new target for "April 16 - May 15" with same amount
4. Repeat for **Vehicle Targets** if applicable

---

### Phase 4: Testing & Verification

#### Step 4.1: Immediate Testing (April 15, 2026)

**Test 1: Current Period Display**
- Navigate to Hope Window
- **Expected**: Shows "March 16 - April 15" as current period
- **Expected**: Today (April 15) is included in current period

**Test 2: Period Selector**
- Check month dropdown in all modules
- **Expected**: Shows "16 Mar – 15 Apr" format for each month

**Test 3: Data Inclusion**
- Enter a test transaction today (April 15)
- **Expected**: Transaction appears in current period (March 16 - April 15)

**Test 4: Historical Data**
- View previous periods
- **Expected**: All historical data visible, dates shifted by 1 day

**Test 5: UI Label Verification**
- Check all 9 files with dropdown labels
- **Expected**: All dropdowns show "16 Month – 15 Month" format
- **Files to check**:
  - Hope Window dropdown
  - Monthly Targets dropdown
  - Vehicle Targets dropdown
  - Monthly Reports dropdown
  - Vehicle Financial Report dropdown (2 locations)
  - Staff profile advance dropdown
  - Staff profile incentive dropdown
  - Staff advance history display
  - Staff incentive history display
  - Payroll month selector (2 locations)

#### Step 4.2: Next Day Testing (April 16, 2026)

**Test 6: New Period Start**
- Navigate to Hope Window at 00:01 on April 16
- **Expected**: Shows "April 16 - May 15" as current period
- **Expected**: Yesterday (April 15) NOT in current period

**Test 7: New Transactions**
- Enter a test transaction on April 16
- **Expected**: Transaction appears in new period (April 16 - May 15)

**Test 8: Period Boundary**
- Check that April 15 data is in previous period
- Check that April 16 data is in new period
- **Expected**: Clean separation at midnight

#### Step 4.3: Module-Specific Tests

| Module | Test | Expected Result |
|--------|------|-----------------|
| Hope Window | View current period | March 16 - April 15 (today) |
| Hope Window | Check dropdown label | Shows "16 Apr – 15 May" |
| Monthly Targets | Check daily breakdown | April 15 included in current |
| Monthly Targets | Check dropdown label | Shows "16 Apr – 15 May" |
| Vehicle Targets | View period selector | Shows 16th-to-15th format |
| Vehicle Targets | Check dropdown label | Shows "16 Apr – 15 May" |
| Monthly Reports | Check daily rows | April 15 in current period |
| Monthly Reports | Check dropdown label | Shows "16 Apr – 15 May" |
| Vehicle Financial | Check dropdown labels | Both locations show "16 Apr – 15 May" |
| Staff Advances | Filter by period | Uses new date boundaries |
| Staff Advances | Check dropdown label | Shows "16 Apr – 15 May" |
| Staff Advances | Check history display | Shows "16 Apr – 15 May" format |
| Staff Incentives | Check dropdown label | Shows "16 Apr – 15 May" |
| Staff Incentives | Check history display | Shows "16 Apr – 15 May" format |
| Payroll | Check month selector | Both locations show "16 Apr – 15 May" |

---

### Phase 5: Rollback Plan (If Needed)

#### Step 5.1: Code Rollback

**If issues occur, revert code changes:**

1. Restore backup of modified files
2. Change all `16` back to `15` and `15` back to `14`
3. Change all `>= 16` back to `>= 15`
4. Revert all UI label changes (15 label changes across 9 files)
5. Redeploy

#### Step 5.2: Database Rollback

**If database was migrated:**

```sql
-- Revert monthly_targets
UPDATE monthly_targets 
SET 
    period_start = '2026-04-15',
    period_end = '2026-05-14',
    updated_at = NOW()
WHERE period_start = '2026-04-16';

-- Revert vehicle_monthly_targets
UPDATE vehicle_monthly_targets 
SET 
    period_start = '2026-04-15',
    period_end = '2026-05-14',
    updated_at = NOW()
WHERE period_start = '2026-04-16';
```

#### Step 5.3: Full Database Restore (Last Resort)

```bash
# Restore from backup
mysql -u [username] -p [database_name] < backup_before_billing_cycle_change_2026-04-15.sql
```

---

## Risk Assessment

### Low Risk ✅
- Code changes are simple and isolated
- No database schema changes required
- All data preserved
- Easy to rollback

### Medium Risk ⚠️
- User confusion during transition
- Existing targets may need manual update
- Historical reports show different date ranges
- UI labels in 9 files need updating

### High Risk ❌
- None identified

---

## Post-Implementation

### Step 1: Monitor for 7 Days
- Check daily that periods are calculating correctly
- Verify transactions go into correct periods
- Monitor user feedback
- Verify all dropdown labels display correctly

### Step 2: Update Documentation
- Update user manual with new billing cycle
- Update training materials
- Update any external documentation

### Step 3: Archive This Plan
- Keep this document for future reference
- Document any issues encountered
- Note any additional changes made

---

## Summary Checklist

### Pre-Implementation
- [ ] Database backup created and verified
- [ ] Current targets documented
- [ ] Users notified of change
- [ ] Rollback plan reviewed

### Implementation
- [ ] `accounts/hope_window.php` updated (functions + dropdown label)
- [ ] `accounts/targets.php` updated (functions + dropdown label)
- [ ] `accounts/vehicle_targets.php` updated (functions + dropdown label)
- [ ] `reports/index.php` updated (functions + dropdown label)
- [ ] `reports/vehicle_financial.php` updated (2 dropdown labels)
- [ ] `staff/show.php` updated (4 dropdown labels)
- [ ] `staff/advance_history.php` updated (1 dropdown label)
- [ ] `staff/incentive_history.php` updated (1 dropdown label)
- [ ] `payroll/index.php` updated (2 dropdown labels)
- [ ] Database migration executed (optional)

### Testing
- [ ] Current period displays correctly (March 16 - April 15)
- [ ] Today (April 15) included in current period
- [ ] Period selectors show new format
- [ ] All 15 dropdown labels verified (9 files)
- [ ] Test transaction on April 15 in correct period
- [ ] Test transaction on April 16 in new period (next day)
- [ ] Historical data accessible

### Post-Implementation
- [ ] Users confirmed understanding
- [ ] No critical issues reported
- [ ] Documentation updated
- [ ] This plan archived

---

## Contact & Support

**Implementation Lead**: IT Team  
**Date**: April 15, 2026  
**Questions**: Contact IT Support

---

## Appendix A: Code Change Summary

**Total Files to Modify**: 9 files  
**Total Functions to Change**: 8 functions  
**Total UI Labels to Change**: 15 labels  
**Lines of Code Changed**: ~39 lines (24 function lines + 15 label lines)  
**Estimated Time**: 45-75 minutes

**Changes Pattern**:
```php
// BACKEND FUNCTIONS
// OLD
sprintf('%04d-%02d-15', $y, $m)  →  sprintf('%04d-%02d-16', $y, $m)
sprintf('%04d-%02d-14', $nY, $nM)  →  sprintf('%04d-%02d-15', $nY, $nM)
if ($d >= 15)  →  if ($d >= 16)

// UI DROPDOWN LABELS
// OLD
'15 ' . date('M', mktime(0,0,0,$m,1)) . ' – 14 ' . date('M', mktime(0,0,0,$mNext,1))
// NEW
'16 ' . date('M', mktime(0,0,0,$m,1)) . ' – 15 ' . date('M', mktime(0,0,0,$mNext,1))
```

---

## Appendix B: Database Impact

**Tables with Period Dates**:
- `monthly_targets` (period_start, period_end)
- `vehicle_monthly_targets` (period_start, period_end)

**Tables with Individual Dates** (No Impact):
- `hope_daily_targets` (target_date)
- `hope_daily_predictions` (target_date)
- `ledger_entries` (posted_at)
- `reservations` (created_at, start_date, end_date)

---

**END OF PLAN**
