# Bug Exploration Test - Counterexamples

## Test Execution Summary

**Test Date**: 2024
**Test Status**: ✗ FAILED (Bug Confirmed)
**Total Tests**: 4
**Passed**: 1
**Failed**: 3

## Bug Confirmation

The bug exploration test has successfully confirmed that **direct vehicle expenses (source_type='vehicle_expense') are completely missing from the Vehicle Financial Report**.

The report only queries reservation-linked expenses, excluding all direct vehicle expenses from both the aggregate totals and the drill-down panel.

## Counterexamples Found

### Counterexample 1: Vehicle with Only Direct Expenses

**Test**: `testVehicleWithOnlyDirectExpenses`

**Scenario**: 
- Vehicle has $500 in direct maintenance expenses
  - $300 for oil change and filter replacement
  - $200 for tire rotation
- No reservation-linked expenses
- Period: 2026-03-15 to 2026-04-14

**Expected Behavior**: Report should show $500 in expenses

**Actual Behavior**: Report shows $0 in expenses

**Evidence**: 
```
Expected: $500
Actual: $0
Message: Bug confirmed: Report shows $0 instead of $500 for vehicle with direct expenses
```

**Root Cause**: The `vfr_calculate_vehicle_expenses()` function uses `INNER JOIN reservations` which excludes all entries where `source_type='vehicle_expense'`.

---

### Counterexample 2: Vehicle with Mixed Expenses

**Test**: `testVehicleWithMixedExpenses`

**Scenario**:
- Vehicle has both reservation-linked and direct expenses:
  - $200 reservation-linked damage charge
  - $500 direct brake pad replacement
  - $300 direct engine tune-up
- Total should be $1000
- Period: 2026-03-15 to 2026-04-14

**Expected Behavior**: Report should show $1000 in total expenses

**Actual Behavior**: Report shows only $200 (reservation expenses only)

**Evidence**:
```
Expected: $1000
Actual: $200
Message: Bug confirmed: Report shows $200 instead of $1000 (missing $800 in direct expenses)
```

**Impact**: This demonstrates that even when reservation expenses are correctly included, direct vehicle expenses are completely invisible, leading to significant underreporting of actual vehicle costs.

---

### Counterexample 3: Drill-Down Panel Missing Direct Expenses

**Test**: `testDrillDownPanelMissingDirectExpenses`

**Scenario**:
- Vehicle has 2 direct expense entries:
  - $400 for battery replacement
  - $150 for AC gas refill
- Total: $550
- Period: 2026-03-15 to 2026-04-14

**Expected Behavior**: Drill-down panel should display 2 entries totaling $550

**Actual Behavior**: Drill-down panel shows 0 entries, $0 total

**Evidence**:
```
Expected: 2 entries, $550
Actual: 0 entries, $0
Message: Bug confirmed: Drill-down panel shows 0 entries instead of 2 (missing direct expense entries)
```

**Root Cause**: The `vfr_get_vehicle_expense_details()` function also uses `INNER JOIN reservations`, excluding all direct vehicle expenses from the detailed view.

**User Impact**: When users click on a vehicle's expense amount to see details, they see an empty panel even though direct expenses exist in the database.

---

### Test Case 4: Date Filtering (Baseline Verification)

**Test**: `testDateFilteringForDirectExpenses`

**Scenario**:
- Vehicle has direct expenses both inside and outside the period:
  - $250 inside period (2026-03-20)
  - $350 outside period (2026-02-10)
- Period: 2026-03-15 to 2026-04-14

**Expected Behavior**: Only the $250 expense within the period should be counted

**Actual Behavior**: ✓ PASS - Date filtering works correctly

**Evidence**:
```
Expected: $250
Actual: $250
Status: PASS
```

**Note**: This test confirms that the date filtering logic itself is correct. The issue is purely that direct vehicle expenses are excluded from the query entirely, not a date filtering problem.

---

## Root Cause Analysis

The bug exists in two functions in `reports/vehicle_financial.php`:

1. **`vfr_calculate_vehicle_expenses()`** (lines ~90-120):
   - Uses `INNER JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id`
   - This JOIN condition explicitly requires `source_type='reservation'`
   - Any ledger entry with `source_type='vehicle_expense'` is excluded

2. **`vfr_get_vehicle_expense_details()`** (lines ~160-190):
   - Uses the same `INNER JOIN reservations` pattern
   - Excludes direct vehicle expenses from the drill-down panel

## Impact Assessment

**Severity**: HIGH

**Financial Impact**: 
- Vehicle expense totals are significantly underreported
- Direct maintenance, service, and repair costs are invisible
- Financial reports show incorrect vehicle profitability

**User Impact**:
- Users cannot see actual vehicle expenses in the report
- Drill-down panel shows empty results for vehicles with only direct expenses
- Decision-making based on incomplete financial data

**Data Integrity**:
- The data exists correctly in the database
- The bug is purely in the query logic
- No data loss or corruption

## Fix Requirements

The fix must:

1. Modify `vfr_calculate_vehicle_expenses()` to include expenses from BOTH:
   - `source_type='reservation'` (existing behavior)
   - `source_type='vehicle_expense'` (missing behavior)

2. Modify `vfr_get_vehicle_expense_details()` to include expense entries from BOTH source types

3. Preserve all existing behavior:
   - KPI exclusion filters
   - Date filtering
   - Security deposit exclusions
   - Income calculations (unchanged)

4. Use UNION query approach to combine both source types while maintaining proper vehicle_id resolution

## Test Validation

After implementing the fix, re-running this test should result in:
- All 4 tests PASSING
- No counterexamples found
- Confirmation message: "✓ ALL TESTS PASSED: Bug is fixed!"
