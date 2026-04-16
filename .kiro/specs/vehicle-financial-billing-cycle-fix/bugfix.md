# Bugfix Requirements Document

## Introduction

The Vehicle Financial Report (`reports/vehicle_financial.php`) uses an inconsistent billing cycle (15th to 14th) compared to the rest of the system which uses a standardized billing cycle (16th to 15th). This inconsistency causes transactions to appear in different billing periods depending on which report is viewed, leading to data discrepancies and confusion when comparing financial data across reports.

The bug is located in the `period_from_my()` function (lines 17-21) which calculates the billing period start and end dates.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN the `period_from_my()` function in `reports/vehicle_financial.php` calculates a billing period THEN the system uses 15th as the start date instead of 16th

1.2 WHEN the `period_from_my()` function in `reports/vehicle_financial.php` calculates a billing period THEN the system uses 14th of the next month as the end date instead of 15th

1.3 WHEN a transaction occurs on the 15th of a month THEN the transaction appears in different billing periods in the Vehicle Financial Report versus other system reports (e.g., Monthly Reports in `reports/index.php`)

1.4 WHEN a transaction occurs on the 16th of a month THEN the transaction is excluded from the current period in the Vehicle Financial Report but included in other system reports

### Expected Behavior (Correct)

2.1 WHEN the `period_from_my()` function in `reports/vehicle_financial.php` calculates a billing period THEN the system SHALL use 16th as the start date (matching the system standard)

2.2 WHEN the `period_from_my()` function in `reports/vehicle_financial.php` calculates a billing period THEN the system SHALL use 15th of the next month as the end date (matching the system standard)

2.3 WHEN a transaction occurs on the 15th of a month THEN the transaction SHALL appear in the same billing period across all system reports

2.4 WHEN a transaction occurs on the 16th of a month THEN the transaction SHALL be included in the current period in both the Vehicle Financial Report and other system reports

### Unchanged Behavior (Regression Prevention)

3.1 WHEN the `period_from_my()` function receives valid month and year parameters THEN the system SHALL CONTINUE TO return an array with 'start' and 'end' keys containing properly formatted date strings

3.2 WHEN the month parameter is 12 (December) THEN the system SHALL CONTINUE TO correctly calculate the next month as 1 (January) and increment the year

3.3 WHEN the month parameter is not 12 THEN the system SHALL CONTINUE TO correctly calculate the next month without changing the year

3.4 WHEN the `period_for_today()` function determines the current billing period THEN the system SHALL CONTINUE TO use the same logic structure (checking if current day is >= threshold to determine which period applies)

3.5 WHEN other reports (e.g., `reports/index.php`) calculate billing periods THEN the system SHALL CONTINUE TO use the 16th to 15th billing cycle without any changes
