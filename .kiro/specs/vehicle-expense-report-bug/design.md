# Vehicle Expense Report Bug - Design Document

## Overview

The Vehicle Financial Report currently excludes direct vehicle expenses (maintenance, service, repairs) from its calculations because it only queries ledger entries with `source_type='reservation'`. This causes significant financial reporting inaccuracy, showing $0.00 for vehicles that have substantial direct expenses recorded with `source_type='vehicle_expense'`.

The fix requires modifying two SQL query functions in `reports/vehicle_financial.php`:
1. `vfr_calculate_vehicle_expenses()` - Aggregate expense totals per vehicle
2. `vfr_get_vehicle_expense_details()` - Detailed expense entries for drill-down panel

Both functions must be updated to include expenses from BOTH `source_type='reservation'` AND `source_type='vehicle_expense'`, while preserving all existing KPI exclusion filters and date range logic.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when a vehicle has direct expenses with `source_type='vehicle_expense'`
- **Property (P)**: The desired behavior - Vehicle Financial Report includes all vehicle expenses regardless of source_type
- **Preservation**: Existing reservation-linked expense behavior, income calculations, and KPI exclusion filters must remain unchanged
- **vfr_calculate_vehicle_expenses()**: Function in `reports/vehicle_financial.php` that calculates total expenses per vehicle for a date period
- **vfr_get_vehicle_expense_details()**: Function in `reports/vehicle_financial.php` that retrieves detailed expense entries for the drill-down panel
- **source_type**: Ledger entry field that identifies the origin of the transaction ('reservation' or 'vehicle_expense')
- **source_id**: Foreign key that links to either reservations.id (for reservation expenses) or vehicles.id (for direct vehicle expenses)
- **KPI Exclusion**: Filter logic that excludes certain transaction types (security deposits, transfers) from financial calculations

## Bug Details

### Bug Condition

The bug manifests when a vehicle has direct expenses recorded in the ledger_entries table with `source_type='vehicle_expense'`. The Vehicle Financial Report queries only join ledger_entries to reservations table, which means any expense not linked to a reservation is completely invisible in the report.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type VehicleExpenseQuery
  OUTPUT: boolean
  
  RETURN EXISTS(
           SELECT 1 FROM ledger_entries 
           WHERE source_type = 'vehicle_expense' 
           AND source_id = input.vehicle_id
           AND txn_type = 'expense'
           AND DATE(posted_at) BETWEEN input.period_start AND input.period_end
         )
         AND currentReportQuery.source_type_filter = 'reservation' ONLY
END FUNCTION
```

### Examples

- **Example 1**: Vehicle ID 5 has a $500 maintenance expense recorded on 2026-03-20 with `source_type='vehicle_expense'` and `source_id=5`. The Vehicle Financial Report for March 2026 shows $0.00 expenses for this vehicle instead of $500.00.

- **Example 2**: Vehicle ID 12 has both reservation-linked expenses ($200 from a damage charge) and direct expenses ($800 for tire replacement). The report shows only $200.00 instead of $1,000.00 total.

- **Example 3**: A user clicks on the expense amount for a vehicle with direct expenses. The drill-down panel shows zero entries and $0.00 total, hiding all maintenance costs from view.

- **Edge Case**: Vehicle ID 8 has only reservation-linked expenses ($300). The report correctly shows $300.00 (this behavior must be preserved after the fix).

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Reservation-linked expenses (`source_type='reservation'`) must continue to be included in the Vehicle Financial Report
- Income calculations must continue to query only reservation-linked income entries (no change to income logic)
- KPI exclusion filters must continue to exclude security deposits and transfer transactions from expense calculations
- Date filtering must continue to use the `posted_at` field for both reservation-linked and direct vehicle expenses
- The drill-down panel for income must continue to display reservation-linked income with client names and reservation links
- The report must continue to exclude sold vehicles from the vehicle list
- The period calculation (15th to 14th) must remain unchanged

**Scope:**
All queries and logic that do NOT involve vehicle expense calculations should be completely unaffected by this fix. This includes:
- Income query functions (`vfr_calculate_vehicle_income`, `vfr_get_vehicle_income_details`)
- Vehicle list query (`vfr_get_active_vehicles`)
- Period calculation functions (`period_from_my`, `period_for_today`)
- Frontend JavaScript for drill-down panel rendering
- Summary card calculations (which derive from the expense/income totals)

## Hypothesized Root Cause

Based on the bug description and code analysis, the root cause is clear:

1. **Incorrect JOIN Logic**: Both expense functions use `INNER JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id`, which by definition excludes any ledger entry where `source_type != 'reservation'`.

2. **Missing UNION or OR Condition**: The queries need to retrieve expenses from TWO different source types:
   - `source_type='reservation'` with `source_id` linking to `reservations.id`
   - `source_type='vehicle_expense'` with `source_id` linking to `vehicles.id`

3. **Vehicle ID Resolution**: For reservation expenses, the vehicle_id comes from `reservations.vehicle_id`. For direct vehicle expenses, the vehicle_id comes directly from `source_id` (since `source_id` IS the vehicle_id when `source_type='vehicle_expense'`).

4. **Query Structure**: The fix requires either:
   - A UNION of two separate queries (one for each source_type)
   - A LEFT JOIN approach with conditional logic to resolve vehicle_id from either reservations or source_id

## Correctness Properties

Property 1: Bug Condition - Direct Vehicle Expenses Included

_For any_ vehicle expense query where direct vehicle expenses exist (`source_type='vehicle_expense'`), the fixed expense calculation functions SHALL include these expenses in the total expense amount and display them in the drill-down panel with their amounts, categories, descriptions, and payment methods.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Reservation-Linked Expenses Unchanged

_For any_ vehicle expense query, the fixed functions SHALL continue to include all reservation-linked expenses (`source_type='reservation'`) exactly as before, preserving the existing behavior for vehicles with only reservation expenses or mixed expense types.

**Validates: Requirements 3.1, 3.3, 3.5**

## Fix Implementation

### Changes Required

**File**: `reports/vehicle_financial.php`

**Function 1**: `vfr_calculate_vehicle_expenses()`

**Specific Changes**:
1. **Replace INNER JOIN with UNION Query**: Modify the SQL to use a UNION of two queries:
   - Query 1: Reservation-linked expenses (existing logic)
   - Query 2: Direct vehicle expenses where `source_type='vehicle_expense'` and `source_id` is the vehicle_id

2. **Vehicle ID Resolution**: 
   - For reservation expenses: Use `r.vehicle_id`
   - For direct expenses: Use `le.source_id AS vehicle_id`

3. **Preserve KPI Exclusion**: Apply `ledger_kpi_exclusion_clause('le')` to BOTH queries in the UNION

4. **Preserve Date Filtering**: Apply `DATE(le.posted_at) BETWEEN :period_start AND :period_end` to BOTH queries

5. **Aggregate Results**: Wrap the UNION in a subquery and GROUP BY vehicle_id to sum expenses from both sources

**Function 2**: `vfr_get_vehicle_expense_details()`

**Specific Changes**:
1. **Replace INNER JOIN with UNION Query**: Similar to Function 1, use UNION to retrieve detailed entries from both sources

2. **Handle Missing Reservation Context**: For direct vehicle expenses, there is no reservation_id, client_name, or reservation link. These fields should be NULL or empty in the result set.

3. **Preserve Column Structure**: Ensure the UNION queries return the same columns in the same order:
   - `id`, `amount`, `description`, `category`, `source_event`, `payment_mode`, `posted_at`
   - Additional columns for reservation context (will be NULL for direct expenses)

4. **Preserve Ordering**: ORDER BY `posted_at DESC` should apply to the final UNION result

5. **Preserve KPI Exclusion**: Apply exclusion clause to BOTH queries

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm that direct vehicle expenses are excluded from the report.

**Test Plan**: Create test data with vehicles that have direct expenses (`source_type='vehicle_expense'`), then query the unfixed report functions to observe that these expenses return $0.00. This confirms the root cause.

**Test Cases**:
1. **Direct Expense Only Test**: Create a vehicle with only direct expenses ($500 maintenance). Query `vfr_calculate_vehicle_expenses()` on unfixed code (will return $0.00 instead of $500.00).

2. **Mixed Expense Test**: Create a vehicle with both reservation expenses ($200) and direct expenses ($800). Query unfixed code (will return $200.00 instead of $1,000.00).

3. **Drill-Down Panel Test**: Query `vfr_get_vehicle_expense_details()` for a vehicle with direct expenses on unfixed code (will return empty array instead of expense entries).

4. **Date Filter Test**: Create direct expenses outside the period range. Query unfixed code to confirm date filtering works (will return $0.00, but should still exclude out-of-range expenses after fix).

**Expected Counterexamples**:
- Direct vehicle expenses are completely missing from aggregate totals
- Drill-down panel shows zero entries for vehicles with direct expenses
- Root cause confirmed: INNER JOIN to reservations table excludes `source_type='vehicle_expense'`

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds (vehicles with direct expenses), the fixed functions produce the expected behavior (include those expenses).

**Pseudocode:**
```
FOR ALL vehicle_id WHERE EXISTS(direct_expenses(vehicle_id, period)) DO
  result := vfr_calculate_vehicle_expenses_fixed(pdo, period_start, period_end)
  ASSERT result[vehicle_id] >= sum(direct_expenses(vehicle_id, period))
  
  details := vfr_get_vehicle_expense_details_fixed(pdo, vehicle_id, period_start, period_end)
  ASSERT count(details) >= count(direct_expenses(vehicle_id, period))
  ASSERT sum(details.amount) >= sum(direct_expenses(vehicle_id, period).amount)
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold (vehicles with only reservation expenses), the fixed functions produce the same result as the original functions.

**Pseudocode:**
```
FOR ALL vehicle_id WHERE NOT EXISTS(direct_expenses(vehicle_id, period)) DO
  original_total := vfr_calculate_vehicle_expenses_original(pdo, period_start, period_end)
  fixed_total := vfr_calculate_vehicle_expenses_fixed(pdo, period_start, period_end)
  ASSERT original_total[vehicle_id] = fixed_total[vehicle_id]
  
  original_details := vfr_get_vehicle_expense_details_original(pdo, vehicle_id, period_start, period_end)
  fixed_details := vfr_get_vehicle_expense_details_fixed(pdo, vehicle_id, period_start, period_end)
  ASSERT original_details = fixed_details
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across different vehicle configurations
- It catches edge cases like vehicles with zero expenses, vehicles with only reservation expenses, and vehicles with mixed expense types
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for vehicles with only reservation expenses, then write property-based tests capturing that exact behavior to ensure the fix doesn't alter it.

**Test Cases**:
1. **Reservation Expense Preservation**: Create vehicles with only reservation-linked expenses. Verify that unfixed code returns correct totals, then verify fixed code returns identical totals.

2. **Zero Expense Preservation**: Create vehicles with no expenses at all. Verify both unfixed and fixed code return $0.00.

3. **KPI Exclusion Preservation**: Create expenses with security deposit categories. Verify both unfixed and fixed code exclude these from totals.

4. **Date Filter Preservation**: Create expenses outside the period range. Verify both unfixed and fixed code exclude these from totals.

5. **Income Query Preservation**: Verify that income calculation functions remain completely unchanged (no modifications to income logic).

### Unit Tests

- Test `vfr_calculate_vehicle_expenses()` with vehicles having only direct expenses
- Test `vfr_calculate_vehicle_expenses()` with vehicles having only reservation expenses
- Test `vfr_calculate_vehicle_expenses()` with vehicles having mixed expense types
- Test `vfr_get_vehicle_expense_details()` returns correct entry count and total for direct expenses
- Test that KPI exclusion filters apply to both reservation and direct expenses
- Test that date filtering applies to both expense types

### Property-Based Tests

- Generate random vehicle configurations with varying expense types and verify totals are correct
- Generate random date periods and verify only expenses within the period are included
- Generate random expense categories and verify KPI exclusions work correctly
- Test that for any vehicle with only reservation expenses, the fix produces identical results to the original

### Integration Tests

- Test full report page rendering with vehicles having direct expenses
- Test drill-down panel displays both reservation and direct expenses correctly
- Test that clicking expense amounts opens the panel with correct data
- Test that search/filter functionality in the drill-down panel works for both expense types
- Test that the report summary cards reflect the corrected expense totals
