# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - NULL Photo Uniqueness Validation
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: Scope the property to concrete failing cases: clients with NULL photos attempting to add a photo
  - Test that when a client has photo=NULL and attempts to add a new photo, the validation does NOT incorrectly show "photo already in use" error
  - The test should verify that isBugCondition(input) where input.photoValue IS NULL AND input.validationQuery CONTAINS "WHERE photo=?" AND NOT (input.validationQuery CONTAINS "photo IS NOT NULL") AND existsOtherClientWithNullPhoto(input.clientId)
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found to understand root cause (e.g., "Client ID 5 with photo=NULL cannot add photo because validation matches Client ID 3 who also has photo=NULL")
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Actual Photo Duplicate Detection and Email/Phone Validation
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs (cases where photo is NOT NULL)
  - Write property-based tests capturing observed behavior patterns:
    - When a client has an existing photo and attempts to change it to a photo used by a different client, validation shows "photo already in use"
    - When a client has an existing photo and changes it to a different unused photo, the update succeeds
    - When a client has an existing photo and leaves it unchanged, no validation errors occur
    - Email uniqueness validation continues to work correctly
    - Phone uniqueness validation continues to work correctly
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 2.3, 3.1, 3.2, 3.3_

- [x] 3. Fix for NULL photo validation bug

  - [x] 3.1 Implement the fix in clients/edit.php
    - Add NULL check before photo uniqueness validation: only run validation if the new photo value is not NULL
    - Modify validation query to exclude NULL values: add "photo IS NOT NULL" to WHERE clause
    - Ensure validation checks the new photo file path, not the existing NULL value
    - Apply fix in the POST request handling section where photo validation occurs
    - _Bug_Condition: isBugCondition(input) where input.photoValue IS NULL AND input.validationQuery CONTAINS "WHERE photo=?" AND NOT (input.validationQuery CONTAINS "photo IS NOT NULL") AND existsOtherClientWithNullPhoto(input.clientId)_
    - _Expected_Behavior: NULL photo values are excluded from uniqueness validation, allowing photo upload to proceed without false positive errors_
    - _Preservation: Actual photo duplicate detection for non-NULL photos, email/phone validation, and all non-photo-related client operations remain unchanged_
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 2.3, 3.1, 3.2, 3.3_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - NULL Photo Uniqueness Validation
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2_

  - [x] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Actual Photo Duplicate Detection and Email/Phone Validation
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
