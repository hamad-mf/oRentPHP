# Bugfix Requirements Document

## Introduction

The Vehicle Financial Report currently shows $0.00 for vehicle expenses even when direct vehicle expenses exist in the system. This occurs because the report only queries expenses linked to reservations (`source_type='reservation'`), completely ignoring direct vehicle expenses stored with `source_type='vehicle_expense'`. This causes significant financial reporting inaccuracy, as maintenance costs, service expenses, and other direct vehicle costs are invisible in the report.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a vehicle has direct expenses recorded with `source_type='vehicle_expense'` THEN the Vehicle Financial Report displays $0.00 for that vehicle's expenses

1.2 WHEN the Vehicle Financial Report queries vehicle expenses THEN the system only retrieves expenses where `source_type='reservation'`, excluding all direct vehicle expenses

1.3 WHEN viewing the expense drill-down panel for a vehicle with direct expenses THEN the system shows zero entries and $0.00 total, hiding all direct vehicle expenses from the user

### Expected Behavior (Correct)

2.1 WHEN a vehicle has direct expenses recorded with `source_type='vehicle_expense'` THEN the Vehicle Financial Report SHALL include these expenses in the total expense calculation

2.2 WHEN the Vehicle Financial Report queries vehicle expenses THEN the system SHALL retrieve expenses from BOTH `source_type='reservation'` AND `source_type='vehicle_expense'`

2.3 WHEN viewing the expense drill-down panel for a vehicle with direct expenses THEN the system SHALL display all direct vehicle expenses with their amounts, categories, descriptions, and payment methods

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a vehicle has reservation-linked expenses (`source_type='reservation'`) THEN the system SHALL CONTINUE TO include these expenses in the Vehicle Financial Report

3.2 WHEN the Vehicle Financial Report calculates vehicle income THEN the system SHALL CONTINUE TO query only reservation-linked income entries

3.3 WHEN the Vehicle Financial Report applies KPI exclusion filters THEN the system SHALL CONTINUE TO exclude security deposits and transfer transactions from expense calculations

3.4 WHEN viewing the income drill-down panel THEN the system SHALL CONTINUE TO display reservation-linked income entries with client names and reservation links

3.5 WHEN the Vehicle Financial Report filters by date period THEN the system SHALL CONTINUE TO use the `posted_at` date for both reservation-linked and direct vehicle expenses
