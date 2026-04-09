# Requirements Document

## Introduction

The Vehicle Financial Report feature provides a comprehensive view of income and expenses for each vehicle in the fleet. This report enables management to track vehicle-level profitability, identify high-performing and underperforming vehicles, and drill down into specific transactions. The report follows the existing 15th-to-14th monthly period convention used throughout the system.

## Glossary

- **Vehicle_Financial_Report**: The new screen displaying income, expense, and balance for each vehicle
- **Reports_Screen**: The existing reports/index.php screen with monthly period selection
- **Ledger_Entry**: A record in the ledger_entries table representing income or expense
- **Monthly_Period**: A time range from the 15th of one month to the 14th of the next month
- **Drill_Down_Panel**: A slide-out panel showing detailed ledger entries for a specific vehicle and transaction type
- **Vehicle**: A rental vehicle in the fleet (excluding sold vehicles)
- **Reservation**: A booking linking a client to a vehicle for a rental period
- **Income_Entry**: A ledger entry with txn_type='income' linked to a reservation
- **Expense_Entry**: A ledger entry with txn_type='expense' that may be vehicle-related
- **Balance**: The difference between income and expense for a vehicle or period
- **IST**: Indian Standard Time timezone used for all date/time operations

## Requirements

### Requirement 1: Navigation from Reports Screen

**User Story:** As a manager, I want to access the Vehicle Financial Report from the Reports screen, so that I can analyze vehicle-level financial performance.

#### Acceptance Criteria

1. THE Reports_Screen SHALL display a navigation link labeled "Vehicle Financial Report"
2. WHEN the user clicks the Vehicle Financial Report link, THE System SHALL navigate to the Vehicle Financial Report screen
3. THE Vehicle Financial Report link SHALL be visible to users with view_finances permission or admin role

### Requirement 2: Monthly Period Selection

**User Story:** As a manager, I want to select different monthly periods, so that I can compare vehicle performance across time.

#### Acceptance Criteria

1. THE Vehicle_Financial_Report SHALL display a period selector matching the Reports_Screen design
2. THE period selector SHALL use the 15th-to-14th monthly period convention
3. WHEN no period is selected, THE Vehicle_Financial_Report SHALL default to the current period
4. WHEN the user changes the period, THE Vehicle_Financial_Report SHALL reload with data for the selected period
5. THE period selector SHALL display the date range in format "15 MMM – 14 MMM" for month selection
6. THE period selector SHALL allow year selection from 2024 to current year

### Requirement 3: Summary Cards Display

**User Story:** As a manager, I want to see total income, expense, and balance across all vehicles, so that I can understand overall fleet financial performance.

#### Acceptance Criteria

1. THE Vehicle_Financial_Report SHALL display three summary cards: Total Income, Total Expense, and Net Balance
2. THE Total Income card SHALL sum all vehicle income for the selected period
3. THE Total Expense card SHALL sum all vehicle expenses for the selected period
4. THE Net Balance card SHALL calculate Total Income minus Total Expense
5. THE Total Income card SHALL display amounts in green color
6. THE Total Expense card SHALL display amounts in red color
7. WHEN Net Balance is positive, THE Net Balance card SHALL display in green with a "+" prefix
8. WHEN Net Balance is negative, THE Net Balance card SHALL display in red with a "-" prefix
9. THE summary cards SHALL exclude voided ledger entries from calculations
10. THE summary cards SHALL exclude security deposit and transfer entries from calculations

### Requirement 4: Vehicle List Display

**User Story:** As a manager, I want to see a list of all vehicles with their income and expenses, so that I can identify which vehicles are most profitable.

#### Acceptance Criteria

1. THE Vehicle_Financial_Report SHALL display a table with columns: Vehicle Name, Income, Expense, Balance
2. THE vehicle list SHALL include all vehicles where is_sold=0 or is_sold IS NULL
3. THE vehicle list SHALL exclude vehicles marked as sold
4. THE Vehicle Name column SHALL display the vehicle's brand, model, and license plate
5. THE Income column SHALL display the total income for each vehicle in the selected period
6. THE Expense column SHALL display the total expense for each vehicle in the selected period
7. THE Balance column SHALL calculate Income minus Expense for each vehicle
8. WHEN a vehicle has no transactions in the period, THE table SHALL display "$0.00" for income and expense
9. THE Income amounts SHALL be displayed in green color
10. THE Expense amounts SHALL be displayed in red color
11. WHEN Balance is positive, THE Balance SHALL be displayed in green with a "+" prefix
12. WHEN Balance is negative, THE Balance SHALL be displayed in red with a "-" prefix

### Requirement 5: Income Calculation per Vehicle

**User Story:** As a manager, I want accurate income calculations per vehicle, so that I can trust the financial data.

#### Acceptance Criteria

1. THE System SHALL calculate vehicle income from ledger_entries joined with reservations
2. THE income calculation SHALL use the query: ledger_entries WHERE txn_type='income' AND source_type='reservation' JOIN reservations ON source_id=reservation.id
3. THE income calculation SHALL filter by DATE(posted_at) BETWEEN period_start AND period_end
4. THE income calculation SHALL exclude voided entries using ledger_non_voided_clause
5. THE income calculation SHALL exclude security deposit entries (source_event NOT IN 'security_deposit_in', 'security_deposit_out')
6. THE income calculation SHALL exclude transfer entries (source_event NOT IN 'transfer_in', 'transfer_out')
7. THE income calculation SHALL group results by reservations.vehicle_id
8. THE income calculation SHALL use IST timezone for date comparisons

### Requirement 6: Expense Calculation per Vehicle

**User Story:** As a manager, I want to see vehicle-related expenses, so that I can understand the cost of operating each vehicle.

#### Acceptance Criteria

1. THE System SHALL calculate vehicle expenses from ledger_entries linked to vehicles
2. THE expense calculation SHALL identify vehicle-related expenses through reservation linkage
3. THE expense calculation SHALL use the query: ledger_entries WHERE txn_type='expense' AND source_type='reservation' JOIN reservations ON source_id=reservation.id
4. THE expense calculation SHALL filter by DATE(posted_at) BETWEEN period_start AND period_end
5. THE expense calculation SHALL exclude voided entries using ledger_non_voided_clause
6. THE expense calculation SHALL exclude security deposit entries
7. THE expense calculation SHALL exclude transfer entries
8. THE expense calculation SHALL group results by reservations.vehicle_id

### Requirement 7: Clickable Income Amounts

**User Story:** As a manager, I want to click on a vehicle's income amount, so that I can see the detailed breakdown of income transactions.

#### Acceptance Criteria

1. WHEN a vehicle's income amount is greater than zero, THE Income cell SHALL be clickable
2. WHEN the user clicks an income amount, THE System SHALL open the Drill_Down_Panel
3. THE Drill_Down_Panel SHALL display all income entries for the selected vehicle and period
4. WHEN a vehicle has zero income, THE Income cell SHALL display "$0.00" in gray and not be clickable

### Requirement 8: Clickable Expense Amounts

**User Story:** As a manager, I want to click on a vehicle's expense amount, so that I can see the detailed breakdown of expense transactions.

#### Acceptance Criteria

1. WHEN a vehicle's expense amount is greater than zero, THE Expense cell SHALL be clickable
2. WHEN the user clicks an expense amount, THE System SHALL open the Drill_Down_Panel
3. THE Drill_Down_Panel SHALL display all expense entries for the selected vehicle and period
4. WHEN a vehicle has zero expenses, THE Expense cell SHALL display "$0.00" in gray and not be clickable

### Requirement 9: Drill-Down Panel for Income

**User Story:** As a manager, I want to see detailed income entries for a vehicle, so that I can understand where the income came from.

#### Acceptance Criteria

1. THE Drill_Down_Panel SHALL display a header with vehicle name and "Income Breakdown"
2. THE Drill_Down_Panel SHALL list all income entries for the vehicle in the selected period
3. FOR EACH income entry, THE panel SHALL display: date, time, amount, event type, client name, reservation ID, payment mode
4. THE date SHALL be formatted as DD/MM/YYYY
5. THE time SHALL be formatted as HH:MM in 24-hour format
6. THE event type SHALL use human-readable labels (e.g., "Delivery", "Return", "Advance Payment")
7. THE client name SHALL be displayed from the linked reservation
8. THE reservation ID SHALL be a clickable link to reservations/show.php
9. THE payment mode SHALL display as a badge (cash, account, credit)
10. THE panel SHALL display entries in reverse chronological order (newest first)
11. THE panel SHALL display a footer showing the total income amount
12. THE panel SHALL include a close button to dismiss the panel

### Requirement 10: Drill-Down Panel for Expenses

**User Story:** As a manager, I want to see detailed expense entries for a vehicle, so that I can understand what costs were incurred.

#### Acceptance Criteria

1. THE Drill_Down_Panel SHALL display a header with vehicle name and "Expense Breakdown"
2. THE Drill_Down_Panel SHALL list all expense entries for the vehicle in the selected period
3. FOR EACH expense entry, THE panel SHALL display: date, time, amount, category, description, payment mode
4. THE date SHALL be formatted as DD/MM/YYYY
5. THE time SHALL be formatted as HH:MM in 24-hour format
6. THE category SHALL display the ledger entry category field
7. THE description SHALL display the ledger entry description field
8. THE payment mode SHALL display as a badge (cash, account, credit)
9. THE panel SHALL display entries in reverse chronological order (newest first)
10. THE panel SHALL display a footer showing the total expense amount
11. THE panel SHALL include a close button to dismiss the panel

### Requirement 11: Search and Filter in Drill-Down Panel

**User Story:** As a manager, I want to search within the drill-down panel, so that I can quickly find specific transactions.

#### Acceptance Criteria

1. THE Drill_Down_Panel SHALL display a search input field at the top
2. WHEN the user types in the search field, THE panel SHALL filter entries in real-time
3. THE search SHALL match against: client name, vehicle details, category, description, date
4. THE search SHALL be case-insensitive
5. WHEN no entries match the search, THE panel SHALL display "No entries found"
6. THE panel footer total SHALL update to reflect only the filtered entries

### Requirement 12: Responsive Design

**User Story:** As a manager, I want the Vehicle Financial Report to work on different screen sizes, so that I can access it from any device.

#### Acceptance Criteria

1. THE Vehicle_Financial_Report SHALL use responsive Tailwind CSS classes
2. THE summary cards SHALL stack vertically on mobile devices (< 768px width)
3. THE vehicle table SHALL be horizontally scrollable on small screens
4. THE Drill_Down_Panel SHALL occupy full width on mobile devices
5. THE design SHALL match the existing dark theme used in Reports_Screen

### Requirement 13: Permission Control

**User Story:** As a system administrator, I want to restrict access to financial reports, so that only authorized users can view sensitive financial data.

#### Acceptance Criteria

1. WHEN a user without view_finances permission and non-admin role attempts to access the Vehicle_Financial_Report, THE System SHALL redirect to index.php
2. WHEN access is denied, THE System SHALL display an error message "Access denied. You do not have permission to view this page."
3. THE permission check SHALL occur before any data is loaded or displayed
4. THE System SHALL use the existing auth_has_perm function for permission validation

### Requirement 14: Empty State Handling

**User Story:** As a manager, I want clear messaging when there's no data, so that I understand the report is working correctly.

#### Acceptance Criteria

1. WHEN no vehicles exist in the system, THE Vehicle_Financial_Report SHALL display "No vehicles found"
2. WHEN a vehicle has no transactions in the selected period, THE table SHALL display "$0.00" for all amounts
3. WHEN the Drill_Down_Panel has no entries after filtering, THE panel SHALL display "No entries found"
4. THE empty states SHALL use consistent styling with other screens in the system

### Requirement 15: Data Integrity

**User Story:** As a manager, I want the report to use existing ledger functions, so that calculations are consistent across the system.

#### Acceptance Criteria

1. THE System SHALL use ledger_non_voided_clause() for excluding voided entries
2. THE System SHALL use ledger_kpi_exclusion_clause() for excluding non-KPI entries
3. THE System SHALL use existing period calculation functions: period_from_my() and period_for_today()
4. THE System SHALL query the ledger_entries table directly (no new tables required)
5. THE System SHALL use the existing PDO database connection from db()
