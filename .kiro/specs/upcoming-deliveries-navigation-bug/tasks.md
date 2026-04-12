# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Deliveries Navigation 404 Errors
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: Scope the property to concrete failing cases - navigation from `/deliveries/upcoming.php` to various side menu targets
  - Test that when on `/deliveries/upcoming.php`, clicking side menu links (Accounts, Reservations, Clients, etc.) navigates to correct absolute paths (e.g., `/accounts/index.php`, not `/deliveries/accounts/index.php`)
  - Test that `$root` variable is calculated correctly as `/` when current page is in `/deliveries/` directory
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found: which navigation links produce 404 errors, what the incorrect URLs are, what the `$root` value is
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Existing Module Navigation Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-deliveries pages (e.g., `/accounts/index.php`, `/reservations/index.php`, `/vehicles/index.php`)
  - Test that navigation from other module directories (vehicles, clients, reservations, accounts, staff, etc.) continues to work correctly
  - Test that `$root` calculation for existing module pages produces the same results as before
  - Test that active state highlighting in side menu works correctly for all existing pages
  - Test that navigation from root dashboard (`/index.php`) works correctly
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [x] 3. Fix for deliveries navigation bug

  - [x] 3.1 Implement the fix
    - Open `includes/header.php`
    - Locate the `$moduleDirs` array (around line 420)
    - Add `'deliveries',` to the array alongside other module directory names
    - Ensure proper array syntax (comma after the entry)
    - _Bug_Condition: isBugCondition(input) where input.currentPage IN ['deliveries/upcoming.php', 'deliveries/index.php', 'deliveries/*'] AND input.action == 'click_side_menu_link' AND 'deliveries' NOT IN $moduleDirs_
    - _Expected_Behavior: For any navigation event from deliveries pages, $root is calculated correctly as '/' (without 'deliveries/' prefix), causing navigation to succeed to intended target pages_
    - _Preservation: Navigation from all other module directories (vehicles, clients, reservations, accounts, staff, etc.) must produce exactly the same behavior as before the fix_
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 3.4_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Deliveries Navigation Works Correctly
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify that navigation from `/deliveries/upcoming.php` to all side menu targets works correctly
    - Verify that `$root` variable is calculated as `/` when on deliveries pages
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Module Navigation Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)
    - Verify navigation from other module directories continues to work correctly
    - Verify active state highlighting continues to work correctly
    - Verify root dashboard navigation continues to work correctly

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
  - Manually test navigation from `/deliveries/upcoming.php` to various sections
  - Manually test navigation from other module pages to ensure no regressions
  - Verify side menu active state highlighting works correctly on all pages
  - Verify mobile bottom navigation menu works correctly (if applicable)
