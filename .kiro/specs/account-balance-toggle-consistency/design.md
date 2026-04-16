# Account Balance Toggle Consistency Bugfix Design

## Overview

The Accounts & Ledger page displays a Monthly/All-time toggle that controls period-based metrics. However, Cash and Credit account balances incorrectly toggle between monthly and all-time values, while Bank accounts correctly maintain their running balance. This fix will ensure Cash and Credit accounts always display their all-time running balance, matching the behavior of Bank accounts.

The fix is minimal: remove the `.acc-monthly` and `.acc-alltime` CSS classes from the Cash and Credit balance display elements in the HTML, preventing the JavaScript toggle from affecting them.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when the Monthly/All-time toggle is clicked
- **Property (P)**: The desired behavior - Cash and Credit balances should remain constant (showing all-time balance)
- **Preservation**: Period-based metrics (Income, Expenses, Net, Overall Total) must continue to toggle correctly
- **switchAccView()**: The JavaScript function in `accounts/index.php` that handles the Monthly/All-time toggle
- **acc-monthly / acc-alltime**: CSS classes used to show/hide elements based on the current view mode
- **cashBalance**: PHP variable containing the all-time Cash account balance
- **creditBalance**: PHP variable containing the all-time Credit account balance
- **mCashBalance**: PHP variable containing the monthly period Cash account balance
- **mCreditBalance**: PHP variable containing the monthly period Credit account balance

## Bug Details

### Bug Condition

The bug manifests when a user clicks the Monthly or All-time toggle button. The `switchAccView()` JavaScript function toggles visibility of all elements with `.acc-monthly` and `.acc-alltime` classes. The Cash and Credit account balance elements incorrectly have these classes, causing them to toggle between monthly and all-time values.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type UserInteraction
  OUTPUT: boolean
  
  RETURN input.action == 'click'
         AND input.target IN ['accViewMonthly', 'accViewAlltime']
         AND (cashBalanceElement.hasClass('acc-monthly') OR cashBalanceElement.hasClass('acc-alltime'))
         AND (creditBalanceElement.hasClass('acc-monthly') OR creditBalanceElement.hasClass('acc-alltime'))
END FUNCTION
```

### Examples

- **Example 1**: User loads page in Monthly view → Cash shows $5,000 (monthly) → User clicks "All-time" → Cash changes to $25,000 (all-time) → **BUG**: Cash balance should always show $25,000
- **Example 2**: User loads page in All-time view → Credit shows $3,000 (all-time) → User clicks "Monthly" → Credit changes to $500 (monthly) → **BUG**: Credit balance should always show $3,000
- **Example 3**: User toggles between views → Bank account "HDFC Bank" shows $10,000 in both views → **CORRECT**: Bank accounts maintain running balance
- **Edge case**: User toggles rapidly between Monthly/All-time → Cash and Credit balances should remain stable at all-time values

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Period-based metrics (Income, Expenses, Net, Overall Total) must continue to toggle between monthly and all-time values
- Bank account balances must continue to display their running balance (unchanged by toggle)
- The period label must continue to show/hide appropriately (visible in Monthly, hidden in All-time)
- The ledger table date filters must continue to update and reload the page with the appropriate date range
- The toggle button active states must continue to update correctly

**Scope:**
All inputs that do NOT involve clicking the Monthly/All-time toggle buttons should be completely unaffected by this fix. This includes:
- Page load behavior
- Form submissions
- Modal interactions
- Other button clicks

## Hypothesized Root Cause

Based on the bug description and code analysis, the root cause is:

1. **Incorrect CSS Class Assignment**: The Cash and Credit account balance `<p>` elements have both `.acc-monthly` and `.acc-alltime` classes with corresponding monthly/all-time values
   - Cash Monthly: `<p id="cashValMonthly" class="acc-monthly ...">$<?= number_format($mCashBalance, 2) ?></p>`
   - Cash All-time: `<p id="cashValAlltime" class="acc-alltime hidden ...">$<?= number_format($cashBalance, 2) ?></p>`
   - Credit Monthly: `<p id="creditValMonthly" class="acc-monthly ...">$<?= number_format($mCreditBalance, 2) ?></p>`
   - Credit All-time: `<p id="creditValAlltime" class="acc-alltime hidden ...">$<?= number_format($creditBalance, 2) ?></p>`

2. **JavaScript Toggle Logic**: The `switchAccView()` function correctly toggles all `.acc-monthly` and `.acc-alltime` elements, but Cash and Credit balances should not be included in this toggle

3. **Inconsistent Design**: Bank accounts correctly display only their running balance without toggle classes, but Cash and Credit accounts were incorrectly implemented with toggle behavior

## Correctness Properties

Property 1: Bug Condition - Cash and Credit Balances Remain Constant

_For any_ user interaction where the Monthly/All-time toggle is clicked, the fixed Cash and Credit account balance displays SHALL always show the all-time running balance (cashBalance and creditBalance) and SHALL NOT change their displayed values.

**Validates: Requirements 2.1, 2.2**

Property 2: Preservation - Period Metrics Continue to Toggle

_For any_ user interaction where the Monthly/All-time toggle is clicked, the fixed code SHALL produce exactly the same toggle behavior for period-based metrics (Income, Expenses, Net, Overall Total) as the original code, preserving the ability to switch between monthly and all-time views for these metrics.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `accounts/index.php`

**Function**: HTML rendering section (lines ~420-450)

**Specific Changes**:
1. **Remove Toggle Classes from Cash Balance**: Remove the two separate `<p>` elements with `.acc-monthly` and `.acc-alltime` classes
   - Delete: `<p id="cashValMonthly" class="acc-monthly ...">$<?= number_format($mCashBalance, 2) ?></p>`
   - Delete: `<p id="cashValAlltime" class="acc-alltime hidden ...">$<?= number_format($cashBalance, 2) ?></p>`
   - Replace with: `<p class="text-lg font-light mt-2 <?= $cashBalance >= 0 ? 'text-green-400' : 'text-red-400' ?>">$<?= number_format($cashBalance, 2) ?></p>`

2. **Remove Toggle Classes from Credit Balance**: Remove the two separate `<p>` elements with `.acc-monthly` and `.acc-alltime` classes
   - Delete: `<p id="creditValMonthly" class="acc-monthly ...">$<?= number_format($mCreditBalance, 2) ?></p>`
   - Delete: `<p id="creditValAlltime" class="acc-alltime hidden ...">$<?= number_format($creditBalance, 2) ?></p>`
   - Replace with: `<p class="text-lg font-light mt-2 text-amber-400">$<?= number_format($creditBalance, 2) ?></p>`

3. **No JavaScript Changes Required**: The `switchAccView()` function will continue to work correctly, it simply won't find Cash/Credit balance elements to toggle

4. **No PHP Variable Changes Required**: Both monthly and all-time variables are still calculated (for potential future use), but only all-time values are displayed

5. **Maintain Color Logic**: Cash balance color should remain dynamic (green for positive, red for negative), Credit balance should remain amber

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write automated browser tests that load the page, observe initial Cash/Credit balance values, click the toggle button, and assert that the balance values change (demonstrating the bug). Run these tests on the UNFIXED code to observe failures and understand the root cause.

**Test Cases**:
1. **Monthly to All-time Toggle Test**: Load page in Monthly view, record Cash balance, click "All-time", assert Cash balance changes (will fail on unfixed code - demonstrates bug)
2. **All-time to Monthly Toggle Test**: Load page with `?date_from=&date_to=` (All-time), record Credit balance, click "Monthly", assert Credit balance changes (will fail on unfixed code - demonstrates bug)
3. **Bank Account Stability Test**: Load page, record Bank account balance, toggle between views, assert Bank balance remains unchanged (will pass on unfixed code - demonstrates correct behavior)
4. **Rapid Toggle Test**: Toggle between Monthly/All-time 5 times rapidly, assert Cash and Credit balances change each time (will fail on unfixed code - demonstrates bug)

**Expected Counterexamples**:
- Cash balance changes from $5,000 (monthly) to $25,000 (all-time) when toggle is clicked
- Credit balance changes from $500 (monthly) to $3,000 (all-time) when toggle is clicked
- Possible causes: `.acc-monthly` and `.acc-alltime` classes on Cash/Credit balance elements

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  cashBalanceBefore := getCashBalanceDisplayed()
  creditBalanceBefore := getCreditBalanceDisplayed()
  
  simulateToggleClick(input)
  
  cashBalanceAfter := getCashBalanceDisplayed()
  creditBalanceAfter := getCreditBalanceDisplayed()
  
  ASSERT cashBalanceBefore == cashBalanceAfter
  ASSERT creditBalanceBefore == creditBalanceAfter
  ASSERT cashBalanceAfter == allTimeCashBalance
  ASSERT creditBalanceAfter == allTimeCreditBalance
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT originalPageBehavior(input) = fixedPageBehavior(input)
END FOR
```

**Testing Approach**: Manual testing and automated browser tests are recommended for preservation checking because:
- The toggle behavior for period metrics is complex and involves multiple UI elements
- We need to verify visual state changes (hidden/visible classes)
- We need to verify form submission behavior (ledger table date filters)
- Property-based testing is less applicable for UI state verification

**Test Plan**: Observe behavior on UNFIXED code first for period metrics toggle, then write tests capturing that behavior.

**Test Cases**:
1. **Income Metric Toggle Preservation**: Load page, observe Income value in Monthly view, click "All-time", assert Income changes to all-time value (same behavior as unfixed)
2. **Expenses Metric Toggle Preservation**: Load page, observe Expenses value in Monthly view, click "All-time", assert Expenses changes to all-time value (same behavior as unfixed)
3. **Net Metric Toggle Preservation**: Load page, observe Net value in Monthly view, click "All-time", assert Net changes to all-time value (same behavior as unfixed)
4. **Overall Total Toggle Preservation**: Load page, observe Overall Total in Monthly view, click "All-time", assert Overall Total changes to all-time value (same behavior as unfixed)
5. **Period Label Visibility Preservation**: Load page in Monthly view, assert period label visible, click "All-time", assert period label hidden (same behavior as unfixed)
6. **Ledger Table Date Filter Preservation**: Load page in Monthly view, click "All-time", assert page reloads with empty date filters and ledger table shows all-time data (same behavior as unfixed)
7. **Button Active State Preservation**: Load page, click toggle buttons, assert active state styling updates correctly (same behavior as unfixed)

### Unit Tests

- Test that Cash balance element does not have `.acc-monthly` or `.acc-alltime` classes after fix
- Test that Credit balance element does not have `.acc-monthly` or `.acc-alltime` classes after fix
- Test that Cash balance displays `$cashBalance` (all-time value) in both Monthly and All-time views
- Test that Credit balance displays `$creditBalance` (all-time value) in both Monthly and All-time views
- Test that Bank account balances continue to display correctly without toggle classes

### Property-Based Tests

Property-based testing is not well-suited for this bug fix because:
- The bug is deterministic and UI-specific (CSS class presence)
- The input domain is small (two toggle buttons)
- The behavior is visual state management, not data transformation
- Manual/automated browser testing provides better coverage

### Integration Tests

- Test full page load in Monthly view, verify Cash and Credit show all-time balances
- Test full page load in All-time view, verify Cash and Credit show all-time balances
- Test toggling between views multiple times, verify Cash and Credit remain stable
- Test that period metrics (Income, Expenses, Net, Overall Total) continue to toggle correctly
- Test that ledger table date filters update correctly when toggling views
- Test that visual feedback (button active states, period label visibility) works correctly
