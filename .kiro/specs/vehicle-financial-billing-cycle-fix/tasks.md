# Implementation Plan

- [ ] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Correct Billing Period Calculation
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: Test concrete failing cases across all months (1-12) with specific years to ensure reproducibility
  - Test that `period_from_my()` returns periods starting on 16th and ending on 15th (from Bug Condition in design)
  - Test cases: March 2025 (should return start='2025-03-16', end='2025-04-15'), December 2024 (should return start='2024-12-16', end='2025-01-15'), January 2025 (should return start='2025-01-16', end='2025-02-15')
  - Test that `period_for_today()` uses 16th as threshold (mock date as 15th should return previous period, 16th should return current period)
  - The test assertions should match the Expected Behavior Properties from design
  - Run test on UNFIXED code in `reports/vehicle_financial.php`
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: start dates will be '15th' instead of '16th', end dates will be '14th' instead of '15th'
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Function Return Format and Logic
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for function structure and return format
  - Observe: `period_from_my()` returns array with 'start' and 'end' keys containing 'YYYY-MM-DD' formatted strings
  - Observe: December (month=12) correctly rolls over to January with year increment
  - Observe: Non-December months increment month without year change
  - Observe: `period_for_today()` uses threshold check structure to determine current period
  - Write property-based tests capturing observed behavior patterns from Preservation Requirements
  - Property-based testing generates many test cases for stronger guarantees
  - Test return format preservation: verify array structure with 'start' and 'end' keys
  - Test December rollover preservation: verify month=12 produces January of next year
  - Test non-December preservation: verify months 1-11 increment without year change
  - Test date format preservation: verify 'YYYY-MM-DD' format maintained
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 3. Fix billing cycle date thresholds in vehicle_financial.php

  - [ ] 3.1 Implement the fix
    - Update `period_from_my()` function (lines 17-21) in `reports/vehicle_financial.php`
    - Change line 18: Update start date from `sprintf('%04d-%02d-15', $y, $m)` to `sprintf('%04d-%02d-16', $y, $m)`
    - Change line 21: Update end date from `sprintf('%04d-%02d-14', $nY, $nM)` to `sprintf('%04d-%02d-15', $nY, $nM)`
    - Change line 16: Update comment from "15th to 14th next month" to "16th to 15th next month"
    - Update `period_for_today()` function (lines 23-28) in `reports/vehicle_financial.php`
    - Change line 25: Update threshold check from `if ($d >= 15)` to `if ($d >= 16)`
    - _Bug_Condition: isBugCondition(input) where calculatedPeriod.start ENDS_WITH '-15' AND calculatedPeriod.end ENDS_WITH '-14'_
    - _Expected_Behavior: period_from_my returns periods with start on 16th and end on 15th matching system standard_
    - _Preservation: Function return format ['start' => string, 'end' => string], month rollover logic, and period detection structure_
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

  - [ ] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Correct Billing Period Calculation
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify all test cases pass: March 2025 returns correct dates, December 2024 handles rollover correctly, threshold check works at 16th
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [ ] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Function Return Format and Logic
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix: return format unchanged, December rollover works, non-December logic works, date format preserved
    - Confirm no regressions in function structure or logic

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
