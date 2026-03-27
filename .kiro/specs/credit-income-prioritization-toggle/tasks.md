# Implementation Plan: Credit Income Prioritization Toggle

## Overview

This implementation adds a toggle switch to the credit transactions page that allows users to prioritize income entries over expense entries. The feature includes preference persistence, URL parameter handling, and conditional SQL sorting. All code will be written in PHP to match the existing codebase.

## Tasks

- [x] 1. Add preference management functions to settings_helpers.php
  - Add `get_credit_prioritize_income()` function that retrieves the preference from system_settings table with default value of true
  - Add `set_credit_prioritize_income()` function that saves the preference to system_settings table
  - _Requirements: 3.1, 3.2, 3.3_

- [ ]* 1.1 Write property test for preference persistence round-trip
  - **Property 5: Preference Persistence Round-Trip**
  - **Validates: Requirements 3.1, 3.2, 3.3**

- [x] 2. Implement URL parameter handling and preference resolution in credit.php
  - [x] 2.1 Add URL parameter parsing logic after existing $includeVoided variable
    - Check for `prioritize_income` GET parameter
    - If parameter exists and equals '1', set $prioritizeIncome to true and save preference
    - If parameter is absent, load preference using `get_credit_prioritize_income()`
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [ ]* 2.2 Write property test for filter independence
    - **Property 4: Filter Independence**
    - **Validates: Requirements 2.5**

- [x] 3. Modify SQL query construction to support conditional sorting
  - [x] 3.1 Replace hardcoded ORDER BY clause with conditional logic
    - When $prioritizeIncome is true, use: `ORDER BY CASE WHEN le.txn_type = 'income' THEN 0 ELSE 1 END, le.posted_at DESC, le.id DESC`
    - When $prioritizeIncome is false, use existing: `ORDER BY le.id DESC, le.posted_at DESC`
    - Apply the conditional ORDER BY clause to $selectSql variable
    - _Requirements: 4.1, 4.2, 4.3_

  - [ ]* 3.2 Write property test for income-first ordering
    - **Property 1: Income-First Ordering**
    - **Validates: Requirements 2.1**

  - [ ]* 3.3 Write property test for within-group chronological sorting
    - **Property 2: Within-Group Chronological Sorting**
    - **Validates: Requirements 2.2, 2.3**

  - [ ]* 3.4 Write property test for pure chronological sorting
    - **Property 3: Pure Chronological Sorting**
    - **Validates: Requirements 2.4**

- [x] 4. Add toggle switch UI to credit.php header
  - [x] 4.1 Add toggle switch form in header section
    - Insert form adjacent to "Show voided" checkbox in the header div
    - Add checkbox input with name="prioritize_income" value="1"
    - Set checked attribute based on $prioritizeIncome variable
    - Add onchange="this.form.submit()" for immediate reload
    - Add hidden input to preserve include_voided parameter if set
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 5. Update pagination to preserve toggle state
  - [x] 5.1 Modify pagination parameter array construction
    - Build $paginationParams array conditionally
    - Include 'prioritize_income' => '1' when $prioritizeIncome is true
    - Include 'include_voided' => '1' when $includeVoided is true
    - Pass $paginationParams to render_pagination() function
    - _Requirements: 3.5, 5.5_

  - [ ]* 5.2 Write property test for pagination parameter preservation
    - **Property 6: Pagination Parameter Preservation**
    - **Validates: Requirements 3.5, 5.5**

- [x] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design document
- The implementation uses PHP to match the existing codebase
- All changes are localized to two files: accounts/credit.php and includes/settings_helpers.php
