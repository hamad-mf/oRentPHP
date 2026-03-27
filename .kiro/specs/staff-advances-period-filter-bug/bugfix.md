# Bugfix Requirements Document

## Introduction

The Staff Advances feature displays a "Due" badge showing the total pending advance amount for a staff member. When a user selects a specific period using the month/year dropdown selectors (e.g., "15 Apr – 14 May 2026"), the "Due" badge incorrectly shows the sum of ALL pending advances across all periods instead of only showing the pending amount for the selected period.

This bug causes confusion when reviewing staff advances by period, as users cannot see the actual amount due for a specific payroll period. For example, if a staff member has $500 pending from January and $1,000 pending from March, selecting the April-May period shows $1,500 instead of $0.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user selects a specific period using the month/year dropdowns (e.g., "15 Apr – 14 May 2026") THEN the system displays the sum of ALL pending advances across all periods in the "Due" badge

1.2 WHEN the selected period has no pending advances THEN the system still displays the total of all other periods' pending advances instead of showing $0 or "No Balance"

1.3 WHEN the page initially loads without period parameters in the URL THEN the system displays the sum of ALL pending advances without indicating which period is being shown

### Expected Behavior (Correct)

2.1 WHEN a user selects a specific period using the month/year dropdowns THEN the system SHALL display only the pending advance amount for that selected period in the "Due" badge

2.2 WHEN the selected period has no pending advances THEN the system SHALL display $0 or "No Balance" in the badge

2.3 WHEN the page initially loads without period parameters THEN the system SHALL default to the current payroll period and display only that period's pending advances

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user changes the period selection using the dropdowns THEN the system SHALL CONTINUE TO reload the page with the correct adv_month and adv_year URL parameters

3.2 WHEN displaying the advance history list THEN the system SHALL CONTINUE TO show all recent advances regardless of the selected period filter

3.3 WHEN giving a new advance through the form THEN the system SHALL CONTINUE TO save it with the correct month and year values

3.4 WHEN the "Due" badge shows a balance greater than $0 THEN the system SHALL CONTINUE TO display it with orange styling

3.5 WHEN the "Due" badge shows no balance THEN the system SHALL CONTINUE TO display "No Balance" with green styling
