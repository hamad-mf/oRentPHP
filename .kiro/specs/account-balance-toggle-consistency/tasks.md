# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Cash and Credit Balances Toggle Incorrectly
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: For this deterministic UI bug, scope the property to concrete failing cases: toggle button clicks
  - Test that clicking Monthly/All-time toggle causes Cash and Credit balances to change (from Bug Condition in design)
  - The test assertions should match the Expected Behavior Properties from design: balances should remain constant at all-time values
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found to understand root cause (e.g., "Cash balance changes from $5,000 to $25,000 when clicking All-time toggle")
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Period Metrics Continue to Toggle
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for period-based metrics (Income, Expenses, Net, Overall Total)
  - Write tests capturing observed toggle behavior patterns from Preservation Requirements
  - Test that Income, Expenses, Net, and Overall Total toggle between monthly and all-time values
  - Test that Bank account balances remain unchanged when toggling
  - Test that period label visibility changes appropriately
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

- [ ] 3. Fix for Cash and Credit balance toggle inconsistency

  - [x] 3.1 Remove toggle classes from Cash balance element
    - In `accounts/index.php` (lines ~470-480), locate the Cash Account Card section
    - Remove the two separate `<p>` elements with `.acc-monthly` and `.acc-alltime` classes
    - Replace with a single `<p>` element displaying only `$cashBalance` (all-time value)
    - Maintain the dynamic color logic (green for positive, red for negative)
    - _Bug_Condition: isBugCondition(input) where input.action == 'click' AND input.target IN ['accViewMonthly', 'accViewAlltime'] AND cashBalanceElement.hasClass('acc-monthly' OR 'acc-alltime')_
    - _Expected_Behavior: Cash balance SHALL always display $cashBalance and SHALL NOT change when toggle is clicked_
    - _Preservation: Period metrics (Income, Expenses, Net, Overall Total) must continue to toggle correctly_
    - _Requirements: 2.1, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

  - [x] 3.2 Remove toggle classes from Credit balance element
    - In `accounts/index.php` (lines ~490-500), locate the Credit Account Card section
    - Remove the two separate `<p>` elements with `.acc-monthly` and `.acc-alltime` classes
    - Replace with a single `<p>` element displaying only `$creditBalance` (all-time value)
    - Maintain the amber color styling
    - _Bug_Condition: isBugCondition(input) where input.action == 'click' AND input.target IN ['accViewMonthly', 'accViewAlltime'] AND creditBalanceElement.hasClass('acc-monthly' OR 'acc-alltime')_
    - _Expected_Behavior: Credit balance SHALL always display $creditBalance and SHALL NOT change when toggle is clicked_
    - _Preservation: Period metrics (Income, Expenses, Net, Overall Total) must continue to toggle correctly_
    - _Requirements: 2.2, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

  - [x] 3.3 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Cash and Credit Balances Remain Constant
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: Expected Behavior Properties from design (2.1, 2.2)_

  - [x] 3.4 Verify preservation tests still pass
    - **Property 2: Preservation** - Period Metrics Continue to Toggle
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
