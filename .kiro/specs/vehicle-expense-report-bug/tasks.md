# Implementation Plan

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Direct Vehicle Expenses Excluded from Report
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate direct vehicle expenses are missing from the report
  - **Scoped PBT Approach**: Scope the property to concrete failing cases - vehicles with `source_type='vehicle_expense'` entries
  - Test that vehicles with direct expenses (`source_type='vehicle_expense'`) are included in expense calculations
  - Test that `vfr_calculate_vehicle_expenses()` returns non-zero totals for vehicles with direct expenses
  - Test that `vfr_get_vehicle_expense_details()` returns expense entries for vehicles with direct expenses
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found:
    - Vehicle with only direct expenses shows $0.00 instead of actual expense total
    - Vehicle with mixed expenses shows only reservation expenses, missing direct expenses
    - Drill-down panel shows zero entries for vehicles with direct expenses
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Reservation-Linked Expenses and Income Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for vehicles with only reservation-linked expenses
  - Observe that `vfr_calculate_vehicle_expenses()` correctly calculates totals for reservation expenses
  - Observe that `vfr_get_vehicle_expense_details()` correctly retrieves reservation expense entries
  - Observe that income calculation functions remain unchanged
  - Write property-based tests capturing observed behavior patterns:
    - For vehicles with only reservation expenses, expense totals match sum of reservation-linked ledger entries
    - For vehicles with zero expenses, functions return $0.00 or empty arrays
    - KPI exclusion filters exclude security deposits and transfers from calculations
    - Date filtering applies correctly to `posted_at` field
    - Income queries remain unchanged (only query `source_type='reservation'`)
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x] 3. Fix for Vehicle Expense Report Bug

  - [x] 3.1 Modify `vfr_calculate_vehicle_expenses()` function
    - Replace INNER JOIN with UNION query to include both source types
    - Query 1: Reservation-linked expenses (existing logic with `r.vehicle_id`)
    - Query 2: Direct vehicle expenses where `source_type='vehicle_expense'` and `source_id` is the vehicle_id
    - Apply KPI exclusion clause to BOTH queries in the UNION
    - Apply date filtering to BOTH queries
    - Wrap UNION in subquery and GROUP BY vehicle_id to aggregate totals
    - _Bug_Condition: isBugCondition(input) where EXISTS(direct vehicle expenses with source_type='vehicle_expense')_
    - _Expected_Behavior: Functions include expenses from BOTH source_type='reservation' AND source_type='vehicle_expense'_
    - _Preservation: Reservation-linked expenses, income calculations, KPI exclusions, and date filtering remain unchanged_
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1, 3.3, 3.5_

  - [x] 3.2 Modify `vfr_get_vehicle_expense_details()` function
    - Replace INNER JOIN with UNION query to retrieve detailed entries from both sources
    - Query 1: Reservation-linked expense details (existing logic)
    - Query 2: Direct vehicle expense details where `source_type='vehicle_expense'`
    - Handle missing reservation context (NULL for reservation_id, client_name for direct expenses)
    - Ensure UNION queries return same columns in same order
    - Apply ORDER BY posted_at DESC to final UNION result
    - Apply KPI exclusion clause to BOTH queries
    - _Bug_Condition: isBugCondition(input) where EXISTS(direct vehicle expenses)_
    - _Expected_Behavior: Drill-down panel displays all direct vehicle expenses with amounts, categories, descriptions, payment methods_
    - _Preservation: Reservation expense details, column structure, ordering, and KPI exclusions remain unchanged_
    - _Requirements: 1.3, 2.3, 3.1, 3.3, 3.5_

  - [x] 3.3 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Direct Vehicle Expenses Included in Report
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms direct vehicle expenses are now included
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - Verify vehicles with direct expenses show correct totals
    - Verify drill-down panel displays direct expense entries
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.4 Verify preservation tests still pass
    - **Property 2: Preservation** - Reservation-Linked Expenses and Income Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm vehicles with only reservation expenses show identical totals
    - Confirm income calculations remain unchanged
    - Confirm KPI exclusions and date filtering work correctly
    - Confirm all tests still pass after fix (no regressions)

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
