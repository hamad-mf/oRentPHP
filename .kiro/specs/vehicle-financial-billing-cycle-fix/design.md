# Vehicle Financial Billing Cycle Fix - Bugfix Design

## Overview

The Vehicle Financial Report uses an inconsistent billing cycle (15th to 14th) compared to the system standard (16th to 15th). This causes transactions to appear in different billing periods depending on which report is viewed, leading to data discrepancies. The fix involves updating the `period_from_my()` and `period_for_today()` functions in `reports/vehicle_financial.php` to use the correct date thresholds (16th as start, 15th as end) matching the implementation in `reports/index.php` and `accounts/targets.php`.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when the billing period calculation uses 15th as the start date instead of 16th
- **Property (P)**: The desired behavior - billing periods should use 16th to 15th cycle matching system standard
- **Preservation**: Existing function structure, return format, and month/year rollover logic that must remain unchanged
- **period_from_my()**: The function in `reports/vehicle_financial.php` (lines 17-21) that calculates billing period start and end dates from month and year parameters
- **period_for_today()**: The function in `reports/vehicle_financial.php` (lines 23-28) that determines the current billing period based on today's date
- **Billing Cycle**: The standardized period from 16th of one month to 15th of the next month used across the system

## Bug Details

### Bug Condition

The bug manifests when the `period_from_my()` function calculates a billing period using incorrect date thresholds. The function uses 15th as the start date and 14th as the end date, while the system standard (used in `reports/index.php` and `accounts/targets.php`) uses 16th as the start date and 15th as the end date.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type { month: int, year: int, calculatedPeriod: { start: string, end: string } }
  OUTPUT: boolean
  
  RETURN calculatedPeriod.start ENDS_WITH '-15'
         AND calculatedPeriod.end ENDS_WITH '-14'
         AND input.month IN [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
         AND input.year >= 2020
END FUNCTION
```

### Examples

- **Example 1**: For March 2025 (month=3, year=2025)
  - Current (buggy): Returns `{ start: '2025-03-15', end: '2025-04-14' }`
  - Expected (correct): Should return `{ start: '2025-03-16', end: '2025-04-15' }`

- **Example 2**: For December 2024 (month=12, year=2024)
  - Current (buggy): Returns `{ start: '2024-12-15', end: '2025-01-14' }`
  - Expected (correct): Should return `{ start: '2024-12-16', end: '2025-01-15' }`

- **Example 3**: Transaction on 15th of any month
  - Current (buggy): Appears in current period in Vehicle Financial Report, previous period in other reports
  - Expected (correct): Should appear in previous period across all reports

- **Example 4**: Transaction on 16th of any month
  - Current (buggy): Appears in next period in Vehicle Financial Report, current period in other reports
  - Expected (correct): Should appear in current period across all reports

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Function return format must remain `['start' => string, 'end' => string]` with date strings in 'YYYY-MM-DD' format
- Month rollover logic (December → January with year increment) must continue to work correctly
- Non-December month logic (month + 1 without year change) must continue to work correctly
- The `period_for_today()` function structure must remain the same (checking if current day >= threshold to determine which period applies)
- All other reports (`reports/index.php`, `accounts/targets.php`) must continue using 16th to 15th cycle without any changes

**Scope:**
All inputs that do NOT involve the billing period calculation should be completely unaffected by this fix. This includes:
- Database queries that use the calculated period dates
- UI rendering of period information
- Month/year selection dropdowns
- Vehicle income and expense calculations

## Hypothesized Root Cause

Based on the bug description and code analysis, the root cause is:

1. **Incorrect Date Literals**: The `period_from_my()` function uses hardcoded date values '15' and '14' instead of the system standard '16' and '15'
   - Line 18: `$start = sprintf('%04d-%02d-15', $y, $m);` should use '16'
   - Line 21: `return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];` should use '15'

2. **Incorrect Threshold in period_for_today()**: The function checks `if ($d >= 15)` instead of `if ($d >= 16)`
   - Line 25: `if ($d >= 15) return period_from_my($m, $y);` should check `>= 16`

3. **Copy-Paste Error**: The comment on line 16 says "15th to 14th next month" which matches the implementation but contradicts the system standard, suggesting this was intentionally implemented incorrectly or copied from an older version before the billing cycle was standardized

## Correctness Properties

Property 1: Bug Condition - Correct Billing Period Calculation

_For any_ valid month (1-12) and year (>= 2020) input, the fixed period_from_my function SHALL return a period starting on the 16th of the specified month and ending on the 15th of the next month, matching the system standard used in reports/index.php and accounts/targets.php.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

Property 2: Preservation - Function Return Format

_For any_ valid month and year input, the fixed period_from_my function SHALL continue to return an array with 'start' and 'end' keys containing properly formatted date strings in 'YYYY-MM-DD' format, preserving the existing return structure.

**Validates: Requirements 3.1**

Property 3: Preservation - Month Rollover Logic

_For any_ input where month equals 12 (December), the fixed period_from_my function SHALL correctly calculate the next month as 1 (January) and increment the year, preserving the existing rollover behavior.

**Validates: Requirements 3.2**

Property 4: Preservation - Non-December Logic

_For any_ input where month is not 12, the fixed period_from_my function SHALL correctly calculate the next month without changing the year, preserving the existing logic.

**Validates: Requirements 3.3**

Property 5: Preservation - Current Period Detection

_For any_ date input, the fixed period_for_today function SHALL use the same logic structure (checking if current day >= threshold) to determine which billing period applies, only changing the threshold value from 15 to 16.

**Validates: Requirements 3.4**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `reports/vehicle_financial.php`

**Function**: `period_from_my()` (lines 17-21)

**Specific Changes**:
1. **Update Start Date Literal**: Change line 18 from `sprintf('%04d-%02d-15', $y, $m)` to `sprintf('%04d-%02d-16', $y, $m)`
   - This changes the billing period start from 15th to 16th

2. **Update End Date Literal**: Change line 21 from `sprintf('%04d-%02d-14', $nY, $nM)` to `sprintf('%04d-%02d-15', $nY, $nM)`
   - This changes the billing period end from 14th to 15th

3. **Update Comment**: Change line 16 comment from "15th to 14th next month" to "16th to 15th next month"
   - This ensures documentation matches implementation

**Function**: `period_for_today()` (lines 23-28)

**Specific Changes**:
4. **Update Threshold Check**: Change line 25 from `if ($d >= 15)` to `if ($d >= 16)`
   - This ensures the current period is selected when the date is 16th or later

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that call `period_from_my()` with various month/year combinations and assert the returned dates match the system standard (16th to 15th). Run these tests on the UNFIXED code to observe failures and confirm the root cause.

**Test Cases**:
1. **March 2025 Test**: Call `period_from_my(3, 2025)` and assert start is '2025-03-16' and end is '2025-04-15' (will fail on unfixed code, returning '2025-03-15' and '2025-04-14')
2. **December 2024 Test**: Call `period_from_my(12, 2024)` and assert start is '2024-12-16' and end is '2025-01-15' (will fail on unfixed code, returning '2024-12-15' and '2025-01-14')
3. **January 2025 Test**: Call `period_from_my(1, 2025)` and assert start is '2025-01-16' and end is '2025-02-15' (will fail on unfixed code)
4. **Today Function Test**: Mock today's date as 15th and assert `period_for_today()` returns previous month's period; mock as 16th and assert it returns current month's period (will fail on unfixed code)

**Expected Counterexamples**:
- All period start dates will be one day earlier (15th instead of 16th)
- All period end dates will be one day earlier (14th instead of 15th)
- The threshold check will trigger one day earlier (15th instead of 16th)

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := period_from_my_fixed(input.month, input.year)
  ASSERT result.start ENDS_WITH '-16'
  ASSERT result.end ENDS_WITH '-15'
  ASSERT result matches system standard from reports/index.php
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result structure and logic as the original function.

**Pseudocode:**
```
FOR ALL input WHERE valid month and year DO
  result := period_from_my_fixed(input.month, input.year)
  ASSERT result has keys 'start' and 'end'
  ASSERT result.start matches format 'YYYY-MM-DD'
  ASSERT result.end matches format 'YYYY-MM-DD'
  IF input.month == 12 THEN
    ASSERT result.end starts with (input.year + 1)
    ASSERT result.end contains '-01-'
  ELSE
    ASSERT result.end starts with input.year
    ASSERT result.end contains sprintf('-%02d-', input.month + 1)
  END IF
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain (all months 1-12, various years)
- It catches edge cases that manual unit tests might miss (e.g., February, leap years)
- It provides strong guarantees that the function structure and logic remain unchanged

**Test Plan**: Observe behavior on UNFIXED code first for return format and month rollover logic, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Return Format Preservation**: Verify the function continues to return an array with 'start' and 'end' keys containing date strings
2. **December Rollover Preservation**: Verify December correctly rolls over to January of next year
3. **Non-December Preservation**: Verify non-December months correctly increment without year change
4. **Date Format Preservation**: Verify dates are formatted as 'YYYY-MM-DD'

### Unit Tests

- Test `period_from_my()` with each month (1-12) and verify correct start/end dates
- Test December rollover (month=12) and verify year increment
- Test non-December months and verify no year change
- Test `period_for_today()` with mocked dates on 15th, 16th, and other days
- Test edge cases (month=1, month=12, year boundaries)

### Property-Based Tests

- Generate random month/year combinations and verify period dates match system standard
- Generate random dates and verify `period_for_today()` returns correct period
- Test that all periods are exactly 30 or 31 days long (depending on month)
- Test that consecutive periods connect correctly (end of period N + 1 day = start of period N+1)

### Integration Tests

- Compare output of Vehicle Financial Report with Monthly Report for same period
- Verify transactions on 15th appear in same period across all reports
- Verify transactions on 16th appear in same period across all reports
- Test full report generation with corrected billing cycle
