# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Default Period Filtering
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: Scope the property to page loads without adv_month/adv_year URL parameters
  - Test that when page loads without URL parameters, the "Due" badge displays only pending advances for the default period shown in dropdowns (not sum of all periods)
  - The test assertions should match the Expected Behavior Properties from design: badge amount equals sum of advances for default period only
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found (e.g., "Badge shows $1,500 for all periods instead of $0 for default period")
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Explicit Period Selection
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code when adv_month and adv_year URL parameters ARE present
  - Write property-based tests capturing that the badge correctly filters by the specified period
  - Property-based testing generates many test cases (different month/year combinations) for stronger guarantees
  - Test that dropdown selection and page reload mechanism continues to work
  - Test that badge styling (orange for balance > 0, green for no balance) continues to work
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3. Fix for staff advances period filter bug

  - [x] 3.1 Implement the fix
    - Move default period calculation logic (currently at lines 358-363) to occur BEFORE the database query (before line 38)
    - Modify lines 38-39 to use calculated defaults when $_GET parameters are not present
    - Calculate $defAdv_m and $defAdv_y considering day-of-month logic (day >= 20 advances to next month)
    - Assign calculated defaults to $selAdvMonth and $selAdvYear
    - Simplify query logic (lines 40-46) to always filter by period since variables will always have values
    - Remove duplicate default calculation at lines 358-363
    - Ensure dropdowns still use $defAdv_m and $defAdv_y for their selected values
    - _Bug_Condition: isBugCondition(input) where NOT isset(input.GET['adv_month']) AND NOT isset(input.GET['adv_year'])_
    - _Expected_Behavior: Badge displays only pending advances for default period shown in dropdowns_
    - _Preservation: When URL parameters ARE present, filtering must work exactly as before_
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Default Period Filtering
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Explicit Period Selection
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
