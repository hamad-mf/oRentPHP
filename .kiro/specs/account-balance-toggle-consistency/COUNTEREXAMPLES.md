# Bug Condition Counterexamples

**Test Date:** 2026-04-16 10:32:38

## Summary

The bug exploration test found 2 counterexample(s) that demonstrate the bug exists in the unfixed code.

## Root Cause

The Cash and Credit account balance elements have `.acc-monthly` and `.acc-alltime` CSS classes in the HTML. The JavaScript `switchAccView()` function toggles the visibility of all elements with these classes when the Monthly/All-time button is clicked. This causes the Cash and Credit balances to appear to change between monthly and all-time values, when they should always display the all-time running balance.

Bank accounts correctly do NOT have these classes, so they maintain their running balance regardless of the toggle state.

## Counterexamples

### Counterexample 1: Cash Account Balance

- **Issue:** Has .acc-monthly or .acc-alltime classes
- **Impact:** Cash balance will toggle between monthly and all-time values when user clicks toggle button
- **Expected Behavior:** Cash balance should always display all-time value without toggle classes

### Counterexample 2: Credit Account Balance

- **Issue:** Has .acc-monthly or .acc-alltime classes
- **Impact:** Credit balance will toggle between monthly and all-time values when user clicks toggle button
- **Expected Behavior:** Credit balance should always display all-time value without toggle classes

## Fix Required

Remove the `.acc-monthly` and `.acc-alltime` classes from the Cash and Credit balance display elements in `accounts/index.php`. Replace the two separate `<p>` elements (one for monthly, one for all-time) with a single `<p>` element that displays only the all-time balance value.

**Files to modify:**
- `accounts/index.php` (lines ~470-480 for Cash, lines ~490-500 for Credit)

