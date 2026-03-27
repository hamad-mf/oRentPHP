# Bug Condition Exploration - Counterexamples

## Test Execution Summary

**Date**: 2026-03-25  
**Status**: ✗ BUG CONFIRMED  
**Tests Run**: 4  
**Tests Failed**: 3  
**Tests Passed**: 1  

## Bug Description

When the staff profile page (`staff/show.php`) loads without `adv_month` and `adv_year` URL parameters, the "Due" badge displays the sum of ALL pending advances across all periods instead of filtering by the default period shown in the dropdown selectors.

## Root Cause Analysis

The bug occurs because:
1. The default period is calculated for the dropdowns (lines 358-363) but NOT used for the database query
2. Variables `$selAdvMonth` and `$selAdvYear` (lines 38-39) are only populated from `$_GET` parameters
3. When these variables are null, the query falls back to selecting ALL pending advances without period filtering (lines 44-46)

## Counterexamples

### Counterexample 1: Multiple Periods with Advances, Default Period Empty

**Scenario**: Staff has advances in January 2026 ($500) and March 2026 ($1000). Page loads in April 2026 without URL parameters.

**Expected Behavior**: Badge should show $0 (no advances for April 2026 default period)

**Actual Behavior**: Badge shows $1500 (sum of all periods: $500 + $1000)

**Evidence**: 
- Default period calculated: Month 4, Year 2026
- Advances in database: Jan 2026 ($500), Mar 2026 ($1000)
- Unfixed code query returns: $1500
- Expected for default period only: $0

### Counterexample 2: Default Period Has Advances, Other Periods Also Have Advances

**Scenario**: Staff has $300 in current default period (March 2026), $400 in previous month, and $300 two months ago. Page loads on day 5 of March 2026.

**Expected Behavior**: Badge should show $300 (only March 2026 default period)

**Actual Behavior**: Badge shows $1000 (sum of all periods: $300 + $400 + $300)

**Evidence**:
- Default period calculated: Month 3, Year 2026
- Advances in database: $300 (March), $400 (February), $300 (January)
- Unfixed code query returns: $1000
- Expected for default period only: $300

### Counterexample 3: Day >= 20 Advances to Next Month

**Scenario**: Page loads on day 25 of March 2026. Staff has $500 in current month (March) and $200 in next month (April). Default period should advance to April when day >= 20.

**Expected Behavior**: Badge should show $200 (only April 2026, the advanced default period)

**Actual Behavior**: Badge shows $700 (sum of all periods: $500 + $200)

**Evidence**:
- Current date: Day 25, Month 3, Year 2026
- Default period calculated: Month 4, Year 2026 (advanced due to day >= 20)
- Advances in database: $500 (March), $200 (April)
- Unfixed code query returns: $700
- Expected for default period only: $200

### Test Case 4: No Advances (Edge Case - Passed)

**Scenario**: Staff has no advances in any period.

**Expected Behavior**: Badge should show $0

**Actual Behavior**: Badge shows $0 ✓

**Note**: This edge case passes even on unfixed code because the sum of zero advances is still zero.

## Conclusion

The bug is definitively confirmed. The "Due" badge query in `staff/show.php` (lines 38-46) does not filter by the default period when URL parameters are absent. This creates a mismatch between what the user sees selected in the dropdowns and what the badge actually displays.

## Next Steps

1. Implement the fix as described in design.md
2. Re-run this test to verify the fix works (test should PASS after fix)
3. Run preservation tests to ensure existing behavior with URL parameters is unchanged
