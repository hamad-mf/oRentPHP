# Implementation Plan

- [ ] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Inspection Save with Empty Fields
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: For deterministic bugs, scope the property to the concrete failing case(s) to ensure reproducibility
  - Test that form submissions with valid vehicle_id and all 37 item keys (1-37) present but with empty check_value/note fields should save successfully
  - Test cases: all fields empty, 10 fields filled with 27 empty, first half filled (1-18), random mix of filled/empty
  - The test assertions should verify: result.success = true, result.jobCardId > 0, result.itemsCount = 37
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: which field combinations cause "Invalid inspection data" error
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1, 2.2, 2.3_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Vehicle Selection and Error Handling
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs
  - Write property-based tests capturing observed behavior patterns from Preservation Requirements
  - Property-based testing generates many test cases for stronger guarantees
  - Test cases:
    - No vehicle selected (vehicle_id = 0) → should display "Please select a vehicle." error
    - Invalid vehicle ID (non-existent) → should display "Selected vehicle does not exist." error
    - Database error simulation → should rollback transaction and display error message
    - Success flow with all fields filled → should save, log action, display success message, and redirect
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [ ] 3. Fix for vehicle inspection save error

  - [ ] 3.1 Implement the fix in vehicles/job_card.php
    - Replace count-based validation `count($items) !== 37` with key-based validation
    - Check that all keys 1 through 37 exist in the items array using isset()
    - Add validation logging when validation fails (log missing keys, items count, vehicle_id)
    - Ensure empty check_value and note fields are handled as NULL (already implemented with ternary operators)
    - _Bug_Condition: isBugCondition(input) where input.vehicle_id IS valid AND count(input.items) < 37 AND some optional fields are empty_
    - _Expected_Behavior: For any form submission where a valid vehicle is selected and the items array contains keys for all 37 item numbers (1-37), the validation SHALL allow the save operation to proceed, even if check_value and note fields are empty or NULL_
    - _Preservation: Vehicle selection validation, database error handling, success flow, and audit logging must remain unchanged_
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

  - [ ] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Inspection Save with Empty Fields
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Vehicle Selection and Error Handling
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
