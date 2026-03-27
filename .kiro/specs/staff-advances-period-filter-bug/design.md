# Staff Advances Period Filter Bug - Bugfix Design

## Overview

The Staff Advances "Due" badge displays the total pending advance amount for a staff member. When a user selects a specific period using the month/year dropdown selectors, the badge correctly filters by that period. However, when the page initially loads without URL parameters (`adv_month` and `adv_year`), the dropdowns display a default period but the "Due" badge query ignores this default and shows the sum of ALL pending advances across all periods instead of filtering by the displayed default period.

This creates a mismatch between what the user sees selected in the dropdowns and what the "Due" badge actually displays. The fix ensures that the default period shown in the dropdowns is also used to filter the database query for the "Due" badge calculation.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when the page loads without `adv_month` and `adv_year` URL parameters
- **Property (P)**: The desired behavior - the "Due" badge should display only pending advances for the period shown in the dropdowns
- **Preservation**: Existing behavior when URL parameters ARE present must remain unchanged
- **advanceBalance**: The variable in `staff/show.php` that stores the total pending advance amount displayed in the "Due" badge
- **$selAdvMonth / $selAdvYear**: Variables that store the selected period from URL parameters (currently only from `$_GET`, not from calculated defaults)
- **$defAdv_m / $defAdv_y**: Variables that calculate the default period to display in the dropdowns (lines 358-363)

## Bug Details

### Bug Condition

The bug manifests when the page loads without `adv_month` and `adv_year` URL parameters. The dropdown selectors calculate and display a default period (lines 358-363), but the database query for `$advanceBalance` (lines 38-46) does not use these calculated defaults. Instead, it queries ALL pending advances because `$selAdvMonth` and `$selAdvYear` remain null.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type PageRequest
  OUTPUT: boolean
  
  RETURN NOT isset(input.GET['adv_month'])
         AND NOT isset(input.GET['adv_year'])
         AND dropdownsShowDefaultPeriod()
         AND dueBadgeShowsAllPeriods()
END FUNCTION
```

### Examples

- **Example 1**: User navigates to `staff/show.php?id=5` (no period parameters)
  - Dropdowns show: "15 Apr – 14 May 2026" (calculated default)
  - Due badge shows: $1,500 (sum of ALL pending advances from all periods)
  - Expected: Due badge should show only advances for April-May 2026 period

- **Example 2**: Staff member has $500 pending from January 2026 and $1,000 pending from March 2026
  - User loads page without parameters in April 2026
  - Dropdowns show: "15 Apr – 14 May 2026"
  - Due badge shows: $1,500 (incorrect - includes January and March)
  - Expected: $0 (no advances for April-May period)

- **Example 3**: User manually selects "15 Mar – 14 Apr 2026" from dropdowns
  - Page reloads with `?id=5&adv_month=3&adv_year=2026`
  - Due badge shows: $1,000 (correct - only March advances)
  - This works correctly (not affected by bug)

- **Edge Case**: User loads page on day 25 of current month
  - Dropdowns auto-advance to next month's period (lines 360-363)
  - Due badge should filter by that next month's period
  - Currently shows all periods instead

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- When URL parameters `adv_month` and `adv_year` ARE present, the filtering must continue to work exactly as it does now
- The dropdown period selection and page reload mechanism must continue to work unchanged
- The advance history list display (showing all recent advances) must remain unchanged
- The "Give Advance" form submission must continue to save advances with correct month/year values
- The badge styling (orange for balance > 0, green for no balance) must remain unchanged

**Scope:**
All page loads that INCLUDE `adv_month` and `adv_year` URL parameters should be completely unaffected by this fix. This includes:
- Manual period selection via dropdowns (triggers page reload with parameters)
- Direct URL navigation with period parameters
- Form submissions that redirect back with period parameters

## Hypothesized Root Cause

Based on the bug description and code analysis, the root cause is:

1. **Disconnected Default Calculation**: The default period is calculated for the dropdowns (lines 358-363 in `$defAdv_m` and `$defAdv_y`) but these values are never used for the database query

2. **Null Variable Issue**: The variables `$selAdvMonth` and `$selAdvYear` (lines 38-39) are only populated from `$_GET` parameters, so they remain null on initial page load

3. **Conditional Query Logic**: The database query (lines 40-46) correctly checks if `$selAdvMonth` and `$selAdvYear` exist, but when they're null, it falls back to querying ALL pending advances without any period filter

4. **Missing Default Assignment**: The fix requires assigning the calculated default values (`$defAdv_m` and `$defAdv_y`) to `$selAdvMonth` and `$selAdvYear` when URL parameters are not present

## Correctness Properties

Property 1: Bug Condition - Default Period Filtering

_For any_ page load where `adv_month` and `adv_year` URL parameters are NOT present, the "Due" badge SHALL display only the pending advance amount for the default period shown in the dropdown selectors, not the sum of all periods.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Explicit Period Selection

_For any_ page load where `adv_month` and `adv_year` URL parameters ARE present, the "Due" badge SHALL produce exactly the same filtered result as the original code, preserving the existing period filtering behavior.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `staff/show.php`

**Location**: Lines 36-46 (the advance balance calculation section)

**Specific Changes**:

1. **Move Default Period Calculation Earlier**: Move the default period calculation logic (currently at lines 358-363) to occur BEFORE the database query (before line 38)

2. **Use Defaults When URL Parameters Missing**: Modify lines 38-39 to use the calculated defaults when `$_GET` parameters are not present:
   ```php
   // Calculate default period first
   $defAdv_m = isset($_GET['adv_month']) ? (int)$_GET['adv_month'] : (int)date('n');
   $defAdv_y = isset($_GET['adv_year'])  ? (int)$_GET['adv_year']  : (int)date('Y');
   if (!isset($_GET['adv_month']) && (int)date('j') >= 20) {
       $defAdv_m = $defAdv_m === 12 ? 1 : $defAdv_m + 1;
       if ($defAdv_m === 1) $defAdv_y++;
   }
   
   // Use defaults for query
   $selAdvMonth = $defAdv_m;
   $selAdvYear  = $defAdv_y;
   ```

3. **Simplify Query Logic**: Since `$selAdvMonth` and `$selAdvYear` will always have values now, simplify the conditional query (lines 40-46) to always filter by period:
   ```php
   $balStmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM payroll_advances WHERE user_id = ? AND remaining_amount > 0 AND status IN ('pending','partially_recovered') AND month = ? AND year = ?");
   $balStmt->execute([$userId, $selAdvMonth, $selAdvYear]);
   ```

4. **Remove Duplicate Default Calculation**: Remove the duplicate default calculation at lines 358-363 since it will now be calculated earlier and can be reused

5. **Maintain Dropdown Display**: Ensure the dropdowns still use `$defAdv_m` and `$defAdv_y` for their selected values (lines 358-378)

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Create test scenarios that load the staff profile page without URL parameters and verify that the "Due" badge displays ALL pending advances instead of only the default period's advances. Run these tests on the UNFIXED code to observe failures and confirm the root cause.

**Test Cases**:
1. **Initial Load Test**: Load `staff/show.php?id=X` without period parameters, verify badge shows sum of all periods (will fail on unfixed code - shows wrong total)
2. **Multiple Period Advances Test**: Create advances for 3 different periods, load page without parameters, verify badge shows sum of all 3 instead of just default period (will fail on unfixed code)
3. **Empty Default Period Test**: Create advances for past periods only, load page without parameters in current month, verify badge shows past advances instead of $0 (will fail on unfixed code)
4. **Day 25+ Test**: Load page on day 25 or later of month, verify badge uses next month's period as default (will fail on unfixed code - shows all periods)

**Expected Counterexamples**:
- Badge displays $1,500 when only $0 should be shown for the default period
- Possible causes: `$selAdvMonth` and `$selAdvYear` are null, query falls back to unfiltered sum

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds (no URL parameters), the fixed function produces the expected behavior (filters by default period).

**Pseudocode:**
```
FOR ALL pageLoad WHERE NOT isset(pageLoad.GET['adv_month']) AND NOT isset(pageLoad.GET['adv_year']) DO
  result := calculateAdvanceBalance_fixed(pageLoad)
  defaultPeriod := calculateDefaultPeriod(pageLoad.currentDate)
  ASSERT result = sumOfAdvancesForPeriod(defaultPeriod)
  ASSERT result != sumOfAllAdvances()
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold (URL parameters present), the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL pageLoad WHERE isset(pageLoad.GET['adv_month']) AND isset(pageLoad.GET['adv_year']) DO
  ASSERT calculateAdvanceBalance_original(pageLoad) = calculateAdvanceBalance_fixed(pageLoad)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across different period combinations
- It catches edge cases like year boundaries (December to January transitions)
- It provides strong guarantees that behavior is unchanged for all explicit period selections

**Test Plan**: Observe behavior on UNFIXED code first for explicit period selections (with URL parameters), then write property-based tests capturing that behavior.

**Test Cases**:
1. **Explicit Period Selection Preservation**: Load page with `?adv_month=3&adv_year=2026`, verify badge shows only March advances (should work on both unfixed and fixed code)
2. **Dropdown Change Preservation**: Select different period from dropdown, verify page reloads with correct parameters and badge updates (should work on both versions)
3. **Year Boundary Preservation**: Test December to January period transitions with URL parameters (should work on both versions)
4. **Badge Styling Preservation**: Verify orange badge for balance > 0 and green badge for $0 balance continues to work

### Unit Tests

- Test default period calculation logic for various dates (day 1-19 vs day 20-31)
- Test database query with explicit month/year parameters
- Test database query with calculated default period
- Test edge cases: year boundaries, leap years, invalid month values

### Property-Based Tests

- Generate random dates and verify default period is calculated correctly
- Generate random sets of advances across multiple periods and verify filtering works correctly
- Generate random month/year combinations and verify preservation of explicit period filtering
- Test that badge amount always equals sum of advances for the selected/default period

### Integration Tests

- Test full page load without parameters, verify badge matches default period
- Test full page load with parameters, verify badge matches specified period
- Test dropdown selection flow: initial load → select period → verify badge updates
- Test that "Give Advance" form submission doesn't break period filtering
- Test visual feedback: verify badge styling changes based on balance amount
