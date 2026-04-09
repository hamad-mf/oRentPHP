# Implementation Plan: Vehicle Financial Report

## Overview

This implementation creates a new Vehicle Financial Report screen that displays income, expense, and balance for each vehicle in the fleet. The report follows the existing 15th-to-14th monthly period convention and reuses the existing ledger infrastructure. The implementation will be done incrementally, building from core data queries to UI components to interactive features.

## Tasks

- [x] 1. Set up report page structure and navigation
  - Create reports/vehicle_financial.php with basic page structure
  - Add permission check using auth_has_perm('view_finances')
  - Add navigation link to reports/index.php
  - Include existing header, footer, and dark theme styling
  - _Requirements: 1.1, 1.2, 1.3, 13.1, 13.2, 13.3_

- [-] 2. Implement period selection and calculation
  - [x] 2.1 Add period selector UI matching reports/index.php design
    - Create month dropdown with "15 MMM – 14 MMM" format
    - Create year dropdown from 2024 to current year
    - Add auto-submit on change functionality
    - _Requirements: 2.1, 2.2, 2.5, 2.6_

  - [x] 2.2 Implement period calculation functions
    - Reuse period_from_my() and period_for_today() functions
    - Handle GET parameters m and y for period selection
    - Default to current period when no parameters provided
    - _Requirements: 2.3, 2.4, 15.3_

  - [ ]* 2.3 Write property test for period calculation
    - **Property 1: Period Calculation Consistency**
    - **Validates: Requirements 2.2**

- [x] 3. Implement vehicle income calculation
  - [x] 3.1 Create vfr_calculate_vehicle_income() function
    - Query ledger_entries with txn_type='income' and source_type='reservation'
    - Join with reservations table on source_id
    - Filter by posted_at date range using period boundaries
    - Use ledger_kpi_exclusion_clause() to exclude voided, security deposits, and transfers
    - Group by vehicle_id and sum amounts
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 15.1, 15.2_

  - [ ]* 3.2 Write property test for income aggregation
    - **Property 2: KPI-Compliant Income Aggregation**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7**

- [x] 4. Implement vehicle expense calculation
  - [x] 4.1 Create vfr_calculate_vehicle_expenses() function
    - Query ledger_entries with txn_type='expense' and source_type='reservation'
    - Join with reservations table on source_id
    - Filter by posted_at date range using period boundaries
    - Use ledger_kpi_exclusion_clause() to exclude voided, security deposits, and transfers
    - Group by vehicle_id and sum amounts
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 15.1, 15.2_

  - [ ]* 4.2 Write property test for expense aggregation
    - **Property 3: KPI-Compliant Expense Aggregation**
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8**

- [x] 5. Implement vehicle list retrieval
  - [x] 5.1 Create vfr_get_active_vehicles() function
    - Query vehicles table where is_sold=0 or is_sold IS NULL
    - Select id, brand, model, license_plate
    - Order by brand, model
    - _Requirements: 4.2, 4.3, 15.4_

  - [ ]* 5.2 Write property test for sold vehicle exclusion
    - **Property 7: Sold Vehicle Exclusion**
    - **Validates: Requirements 4.2, 4.3**

- [x] 6. Implement summary cards display
  - [x] 6.1 Calculate and render summary cards
    - Calculate total_income by summing all vehicle income
    - Calculate total_expense by summing all vehicle expenses
    - Calculate net_balance as total_income - total_expense
    - Render three cards: Total Income (green), Total Expense (red), Net Balance (conditional color)
    - Apply "+" prefix for positive balance, "-" prefix for negative balance
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_

  - [ ]* 6.2 Write property tests for summary calculations
    - **Property 4: Total Income Aggregation**
    - **Property 5: Total Expense Aggregation**
    - **Property 6: Balance Calculation**
    - **Validates: Requirements 3.2, 3.3, 3.4**

  - [ ]* 6.3 Write property tests for balance formatting
    - **Property 9: Positive Balance Formatting**
    - **Property 10: Negative Balance Formatting**
    - **Validates: Requirements 3.7, 3.8**

- [ ] 7. Checkpoint - Ensure core data loading works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Implement vehicle breakdown table
  - [x] 8.1 Render vehicle table with income, expense, and balance
    - Create table with columns: Vehicle Name, Income, Expense, Balance
    - Format vehicle name as "{brand} {model} · {license_plate}"
    - Display income in green, expense in red
    - Display balance with "+" or "-" prefix and conditional color
    - Handle zero amounts with "$0.00" in gray
    - _Requirements: 4.1, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.11, 4.12_

  - [ ]* 8.2 Write property test for vehicle name formatting
    - **Property 8: Vehicle Name Formatting**
    - **Validates: Requirements 4.4**

  - [ ]* 8.3 Write unit tests for table rendering
    - Test zero amounts display correctly
    - Test empty vehicle list shows empty state
    - _Requirements: 4.8, 14.1, 14.2_

- [x] 9. Implement drill-down data retrieval
  - [x] 9.1 Create vfr_get_vehicle_income_details() function
    - Query ledger_entries for income with vehicle_id filter
    - Join with reservations and clients tables
    - Select id, amount, description, category, source_event, source_id, payment_mode, posted_at, client_name, reservation_id
    - Filter by period and use ledger_kpi_exclusion_clause()
    - Order by posted_at DESC
    - _Requirements: 9.2, 9.3, 9.10, 15.1, 15.2_

  - [x] 9.2 Create vfr_get_vehicle_expense_details() function
    - Query ledger_entries for expense with vehicle_id filter
    - Join with reservations table
    - Select id, amount, description, category, source_event, payment_mode, posted_at
    - Filter by period and use ledger_kpi_exclusion_clause()
    - Order by posted_at DESC
    - _Requirements: 10.2, 10.3, 10.9, 15.1, 15.2_

  - [ ]* 9.3 Write property test for drill-down filtering
    - **Property 11: Drill-Down Filtering**
    - **Validates: Requirements 7.3, 8.3, 9.2, 10.2**

  - [ ]* 9.4 Write property test for chronological sorting
    - **Property 19: Chronological Sorting**
    - **Validates: Requirements 9.10, 10.9**

- [-] 10. Implement drill-down panel UI
  - [x] 10.1 Create slide-out panel HTML structure
    - Add panel container with backdrop
    - Add panel header with vehicle name and title
    - Add search input field
    - Add scrollable entry list container
    - Add footer with total amount
    - Add close button
    - Match styling from reports/index.php drill-down panel
    - _Requirements: 9.1, 9.12, 10.1, 10.11, 11.1, 12.4, 12.5_

  - [x] 10.2 Render income entries in panel
    - Format date as DD/MM/YYYY
    - Format time as HH:MM
    - Humanize event labels (e.g., 'delivery' → 'Delivery')
    - Display client name from joined data
    - Create clickable reservation link
    - Display payment mode badge
    - _Requirements: 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9_

  - [x] 10.3 Render expense entries in panel
    - Format date as DD/MM/YYYY
    - Format time as HH:MM
    - Display category and description
    - Display payment mode badge
    - _Requirements: 10.3, 10.4, 10.5, 10.6, 10.7, 10.8_

  - [ ]* 10.4 Write property tests for entry formatting
    - **Property 12: Date Formatting Consistency**
    - **Property 13: Time Formatting Consistency**
    - **Property 14: Entry Field Completeness**
    - **Property 15: Expense Field Completeness**
    - **Validates: Requirements 9.3, 9.4, 9.5, 10.3, 10.4, 10.5**

  - [ ]* 10.5 Write property tests for label formatting
    - **Property 16: Event Label Humanization**
    - **Property 17: Reservation Link Generation**
    - **Property 18: Payment Mode Badge Display**
    - **Validates: Requirements 9.6, 9.8, 9.9, 10.8**

- [-] 11. Implement JavaScript interactivity
  - [x] 11.1 Create openVehiclePanel() function
    - Accept vehicleId, vehicleName, and mode (income/expense) parameters
    - Fetch drill-down data via AJAX or embed in page data
    - Populate panel with entries
    - Show panel with slide-in animation
    - _Requirements: 7.2, 8.2_

  - [x] 11.2 Create closePanel() function
    - Hide panel with slide-out animation
    - Clear search input
    - Reset panel content
    - _Requirements: 9.12, 10.11_

  - [x] 11.3 Implement clickable amount cells
    - Add click handlers to income/expense cells where amount > 0
    - Call openVehiclePanel() with appropriate parameters
    - Style clickable cells with hover effect
    - _Requirements: 7.1, 7.4, 8.1, 8.4_

  - [x] 11.4 Implement search filtering
    - Add input event listener to search field
    - Filter entries by matching client name, vehicle details, category, description, date
    - Use case-insensitive matching
    - Update displayed entries in real-time
    - Update footer total to reflect filtered entries
    - Show "No entries found" when no matches
    - _Requirements: 11.2, 11.3, 11.4, 11.5, 11.6_

  - [x] 11.5 Add keyboard and backdrop interactions
    - Close panel on Escape key press
    - Close panel on backdrop click
    - Prevent panel close when clicking inside panel content
    - _Requirements: 9.12, 10.11_

  - [ ]* 11.6 Write property tests for search filtering
    - **Property 21: Search Filtering**
    - **Property 22: Filtered Total Update**
    - **Validates: Requirements 11.2, 11.3, 11.4, 11.6**

  - [ ]* 11.7 Write property test for clickable amounts
    - **Property 24: Clickable Amount Condition**
    - **Validates: Requirements 7.1, 7.4, 8.1, 8.4**

- [ ] 12. Checkpoint - Ensure drill-down functionality works
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 13. Implement responsive design
  - [x] 13.1 Add responsive Tailwind classes
    - Make summary cards stack vertically on mobile (< 768px)
    - Make vehicle table horizontally scrollable on small screens
    - Make drill-down panel full-width on mobile
    - Test on different screen sizes
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [ ]* 13.2 Write unit tests for responsive behavior
    - Test summary card layout on mobile
    - Test table scrolling on small screens
    - _Requirements: 12.2, 12.3_

- [ ] 14. Implement error handling and edge cases
  - [x] 14.1 Add empty state handling
    - Display "No vehicles found" when vehicle list is empty
    - Display "No entries found" in drill-down when filtered results are empty
    - Handle zero amounts gracefully
    - _Requirements: 14.1, 14.2, 14.3, 14.4_

  - [x] 14.2 Add error handling for database queries
    - Wrap queries in try-catch blocks
    - Log errors using app_log()
    - Display user-friendly error messages
    - Gracefully degrade on failures
    - _Requirements: 15.4, 15.5_

  - [ ]* 14.3 Write unit tests for error conditions
    - Test permission denial redirects correctly
    - Test invalid period parameters default to current period
    - Test empty states display correctly
    - _Requirements: 13.1, 13.2, 14.1, 14.2, 14.3_

  - [ ]* 14.4 Write property test for permission control
    - **Property 23: Permission-Based Access Control**
    - **Validates: Requirements 13.1**

- [ ] 15. Final integration and testing
  - [ ] 15.1 Verify drill-down total calculations
    - Ensure footer totals match sum of displayed entries
    - Ensure totals update correctly when filtering
    - _Requirements: 9.11, 10.10_

  - [ ] 15.2 Verify period selector format
    - Ensure month labels display as "15 MMM – 14 MMM"
    - Ensure year range is correct
    - _Requirements: 2.5, 2.6_

  - [ ]* 15.3 Write property tests for total calculations
    - **Property 20: Drill-Down Total Calculation**
    - **Validates: Requirements 9.11, 10.10**

  - [ ]* 15.4 Write property test for period selector format
    - **Property 25: Period Selector Format**
    - **Validates: Requirements 2.5**

- [ ] 16. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- The implementation reuses existing ledger helper functions for consistency
- The UI design matches the existing Reports screen for familiarity
