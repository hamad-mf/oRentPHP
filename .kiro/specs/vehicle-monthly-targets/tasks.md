# Implementation Plan: Vehicle Monthly Targets

## Overview

This implementation plan breaks down the Vehicle Monthly Targets feature into discrete, testable coding tasks. The feature provides a dedicated screen for setting and tracking monthly income targets per vehicle, using the same 15th-to-14th period system as other financial screens.

The implementation follows a bottom-up approach: database schema first, then core business logic, then UI components, and finally integration with existing screens.

## Tasks

- [x] 1. Create database migration and update documentation
  - Create migration file `migrations/releases/2026-03-27_vehicle_monthly_targets.sql`
  - Define `vehicle_monthly_targets` table with columns: id, vehicle_id, period_start, period_end, target_amount, notes, created_by, created_at, updated_at
  - Add UNIQUE constraint on (vehicle_id, period_start)
  - Add foreign key constraints for vehicle_id (CASCADE) and created_by (SET NULL)
  - Add indexes: idx_period (period_start, period_end), idx_vehicle (vehicle_id)
  - Use IF NOT EXISTS and information_schema guards for idempotency
  - Add entry to `PRODUCTION_DB_STEPS.md` under "Pending" section
  - Add release log entry to `UPDATE_SESSION_RULES.md` with release ID `2026-03-27_vehicle_monthly_targets`
  - _Requirements: FR-1, US-8.6_

- [x] 2. Implement core income calculation logic
  - [x] 2.1 Create income calculation function in vehicle_targets.php
    - Implement `calculate_vehicle_income($pdo, $periodStart, $periodEnd)` function
    - Query: JOIN ledger_entries → reservations → vehicles
    - Filter by txn_type='income', source_type='reservation'
    - Apply ledger_kpi_exclusion_clause() to exclude voided and security deposits
    - Filter by DATE(posted_at) BETWEEN period_start AND period_end
    - Exclude reservations with NULL vehicle_id
    - GROUP BY vehicle_id and SUM(amount)
    - Return associative array: [vehicle_id => total_income]
    - _Requirements: FR-3, US-7.1, US-7.2, US-7.3, US-7.4, US-7.5, US-7.6_

  - [ ]* 2.2 Write property test for income calculation accuracy
    - **Property 21: Income Calculation Accuracy**
    - **Validates: Requirements US-7.1, US-7.2, US-7.5, US-7.6**
    - Generate random vehicle, period, and ledger entries
    - Verify calculated income matches sum of qualifying entries

  - [ ]* 2.3 Write unit tests for income calculation
    - Test income includes advance payments (booking)
    - Test income includes delivery payments
    - Test income includes return payments
    - Test income includes extension payments
    - Test income excludes voided entries
    - Test income excludes security deposit transactions
    - Test income filters by date range correctly
    - Test reservations with null vehicle_id are excluded
    - _Requirements: US-7.1, US-7.2, US-7.4, US-7.5, US-7.6_

- [x] 3. Implement target management functions
  - [x] 3.1 Create individual target save function
    - Implement `save_vehicle_target($pdo, $vehicleId, $periodStart, $periodEnd, $amount, $notes, $userId)` function
    - Use INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior
    - Handle zero/empty amount as deletion (DELETE WHERE vehicle_id AND period_start)
    - Set created_by to current user ID
    - Set updated_at to current timestamp
    - Return boolean success status
    - Log action with app_log('ACTION', ...)
    - _Requirements: FR-4, US-4.4, US-4.9_

  - [x] 3.2 Create target retrieval functions
    - Implement `get_vehicle_target($pdo, $vehicleId, $periodStart)` to get single target
    - Implement `get_all_targets($pdo, $periodStart)` to get all targets for period
    - Return associative arrays with target data
    - Return NULL or empty array if no targets found
    - _Requirements: FR-4_

  - [ ]* 3.3 Write property test for target uniqueness
    - **Property 12: Target Uniqueness**
    - **Validates: Requirements US-4.8, US-8.6**
    - Attempt to insert duplicate targets for same vehicle + period
    - Verify unique constraint prevents duplicates

  - [ ]* 3.4 Write unit tests for target management
    - Test saving new target creates record
    - Test updating existing target modifies record
    - Test saving zero amount deletes target
    - Test saving empty string deletes target
    - Test unique constraint on (vehicle_id, period_start)
    - Test foreign key constraint on vehicle_id
    - _Requirements: US-4.4, US-4.8, US-4.9, US-8.6_

- [x] 4. Implement bulk target operations
  - [x] 4.1 Create bulk "set same amount" function
    - Implement `bulk_set_same_amount($pdo, $vehicleIds, $periodStart, $periodEnd, $amount, $userId)` function
    - Validate vehicleIds is non-empty array
    - Wrap in database transaction (BEGIN/COMMIT/ROLLBACK)
    - Loop through vehicle IDs and call save_vehicle_target for each
    - Return count of successfully updated vehicles
    - Log bulk action with count
    - _Requirements: FR-4, US-5.3_

  - [x] 4.2 Create bulk "distribute equally" function
    - Implement `bulk_distribute_equally($pdo, $vehicleIds, $periodStart, $periodEnd, $totalAmount, $userId)` function
    - Calculate per-vehicle amount: totalAmount / count(vehicleIds)
    - Round to 2 decimal places
    - Wrap in transaction
    - Loop and save each target
    - Return count of updated vehicles
    - _Requirements: FR-4, US-5.4_

  - [x] 4.3 Create bulk "distribute proportionally" function
    - Implement `bulk_distribute_proportionally($pdo, $vehicleIds, $periodStart, $periodEnd, $totalAmount, $userId)` function
    - Query daily_rate for each vehicle in vehicleIds
    - Calculate sum of all daily rates
    - For each vehicle: target = (vehicle_rate / sum_rates) * totalAmount
    - Round to 2 decimal places
    - Wrap in transaction
    - Loop and save each target
    - Return count of updated vehicles
    - _Requirements: FR-4, US-5.5_

  - [ ]* 4.4 Write property test for bulk operation atomicity
    - **Property 17: Bulk Operation Atomicity**
    - **Validates: Requirements US-5.7**
    - Simulate database error mid-operation
    - Verify either all targets saved or none saved (no partial saves)

  - [ ]* 4.5 Write unit tests for bulk operations
    - Test "set same amount" for 3 vehicles
    - Test "distribute equally" for 5 vehicles with total 10000
    - Test "distribute proportionally" for vehicles with different rates
    - Test bulk operation with empty vehicle array (error)
    - Test bulk operation overwrites existing targets
    - Test transaction rollback on error
    - _Requirements: US-5.3, US-5.4, US-5.5, US-5.7, US-5.10_

- [ ] 5. Checkpoint - Verify core business logic
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Create main vehicle targets screen
  - [x] 6.1 Create accounts/vehicle_targets.php file structure
    - Add auth_check() and permission check (view_finances OR admin)
    - Redirect unauthorized users to dashboard with error message
    - Get period from URL params (m, y) or default to current period
    - Validate month (1-12) and year (2020-2099)
    - Calculate period dates using period_from_my($m, $y)
    - Query all active vehicles (status != 'sold')
    - Fetch targets for period using get_all_targets()
    - Calculate income for period using calculate_vehicle_income()
    - _Requirements: US-1.5, US-2.1, US-3.1, US-3.2, US-9.1_

  - [x] 6.2 Implement vehicle list table rendering
    - For each vehicle, create display row with:
      - Vehicle name: "{brand} {model} ({license_plate})"
      - Target amount (from targets or 0)
      - Actual income (from income calculation)
      - Balance: target - actual
      - Achievement percentage: (actual / target) * 100
    - Apply visual status indicators based on achievement:
      - Green (success): achievement >= 100%
      - Yellow (warning): 50% <= achievement < 100%
      - Red (danger): achievement < 50%
      - Gray (none): target = 0
    - Show "Edit Target" button for each vehicle (admin only)
    - Display empty state message if no vehicles
    - _Requirements: US-2.2, US-2.3, US-2.5, US-4.1, US-9.3_

  - [x] 6.3 Implement total row calculations
    - Calculate total target: SUM of all vehicle targets
    - Calculate total actual: SUM of all vehicle actual income
    - Calculate total balance: total_target - total_actual
    - Calculate overall percentage: (total_actual / total_target) * 100
    - Handle division by zero (show "—" or 0%)
    - Display totals in footer row
    - _Requirements: FR-5, US-2.4_

  - [x] 6.4 Implement period selector dropdown
    - Create month/year dropdown with periods from current -12 to +6 months
    - Format labels as "15 MMM – 14 MMM YYYY" (e.g., "15 Mar – 14 Apr 2026")
    - Display selected period range prominently
    - Calculate and display number of days in period
    - On change, reload page with new m/y parameters
    - _Requirements: US-3.1, US-3.3, US-3.4, US-3.7_

  - [ ]* 6.5 Write unit tests for screen rendering
    - Test active vehicles only displayed (sold excluded)
    - Test empty state when no vehicles
    - Test status indicators (green/yellow/red/gray)
    - Test total row calculations
    - Test period label formatting
    - Test days in period calculation
    - _Requirements: US-2.1, US-2.3, US-2.4, US-2.5, US-3.3, US-3.7_

- [x] 7. Implement individual target edit modal
  - [x] 7.1 Create edit target modal HTML/JavaScript
    - Modal shows vehicle name (read-only)
    - Modal shows period (read-only)
    - Input field for target amount (decimal, min 0)
    - Optional notes textarea
    - Save button submits form via POST
    - Cancel button closes modal
    - Show loading state during save
    - _Requirements: US-4.1, US-4.2, US-4.3_

  - [x] 7.2 Implement POST handler for save_target action
    - Validate user is admin (reject if not)
    - Validate vehicle_id exists and not sold
    - Validate target_amount >= 0 and valid decimal
    - Validate period dates are valid
    - Call save_vehicle_target() function
    - Flash success message on save
    - Flash error message on failure
    - Redirect back to same period
    - _Requirements: US-4.4, US-4.5, US-4.6, US-8.1, US-8.2, US-8.3, US-8.4, US-8.5, US-9.2_

  - [ ]* 7.3 Write unit tests for edit modal
    - Test modal opens with correct vehicle data
    - Test save creates/updates target
    - Test validation errors displayed
    - Test admin-only access enforced
    - Test zero amount deletes target
    - _Requirements: US-4.1, US-4.4, US-4.9, US-8.1, US-8.2, US-9.2_

- [ ] 8. Implement bulk operations UI
  - [ ] 8.1 Create vehicle selection checkboxes
    - Add checkbox to each vehicle row
    - Add "Select All" checkbox in table header
    - Track selected count with JavaScript
    - Display selected count indicator (e.g., "3 vehicles selected")
    - Enable/disable bulk button based on selection
    - _Requirements: US-6.1, US-6.2, US-6.3, US-6.4_

  - [ ] 8.2 Create "Set All Targets" button and modal
    - Button at top of page (always enabled)
    - Modal with three distribution options:
      - Option 1: Set same amount for all vehicles
      - Option 2: Distribute total equally
      - Option 3: Distribute proportionally by daily rate
    - Input field for amount/total
    - Preview section showing calculated per-vehicle amounts
    - Apply button submits form
    - Cancel button closes modal
    - _Requirements: US-5.1, US-5.2, US-5.3, US-5.4, US-5.5, US-5.6_

  - [ ] 8.3 Create "Set Targets for Selected" button and modal
    - Button enabled only when vehicles selected
    - Same modal structure as "Set All" but applies to selected only
    - Preview shows only selected vehicles
    - After save, clear selections
    - _Requirements: US-6.4, US-6.5, US-6.6, US-6.7_

  - [ ] 8.4 Implement POST handler for save_bulk action
    - Validate user is admin
    - Validate vehicle_ids array is not empty
    - Validate method is one of: 'same', 'equal', 'proportional'
    - Validate amount > 0
    - Call appropriate bulk function based on method
    - Flash success message with count
    - Flash error message on failure
    - Redirect back to same period
    - _Requirements: US-5.7, US-5.8, US-5.9, US-6.5, US-9.2_

  - [ ]* 8.5 Write unit tests for bulk operations UI
    - Test "Select All" selects all vehicles
    - Test selected count updates correctly
    - Test bulk button enabled/disabled based on selection
    - Test preview calculations for each method
    - Test bulk save applies only to selected vehicles
    - Test selections cleared after save
    - _Requirements: US-6.2, US-6.3, US-6.4, US-6.5, US-6.8_

- [ ] 9. Checkpoint - Verify UI and interactions
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Integrate with Hope Window
  - [x] 10.1 Add Vehicle Targets card to Hope Window
    - Open accounts/hope_window.php
    - Add new card/button after existing summary cards
    - Card labeled "Vehicle Targets" or "Vehicle Breakdown"
    - Link to vehicle_targets.php with m and y parameters
    - Preserve selected period from Hope Window
    - Use consistent styling with existing cards
    - _Requirements: US-1.1, US-1.2, US-1.3, US-1.4, US-10.1, US-10.3_

  - [ ]* 10.2 Write integration test for navigation
    - Test link from Hope Window to Vehicle Targets
    - Test period parameters preserved in URL
    - Test back navigation returns to Hope Window
    - _Requirements: US-1.3, US-1.4, US-10.1, US-10.3_

- [ ] 11. Implement permission controls
  - [ ] 11.1 Add permission checks to vehicle_targets.php
    - Check view_finances permission OR admin role for page access
    - Check admin role for all POST operations (save_target, save_bulk)
    - Hide edit buttons for non-admin users
    - Show read-only view for users with view_finances but not admin
    - _Requirements: US-1.5, US-9.1, US-9.2, US-9.3, US-9.4, US-9.5_

  - [ ]* 11.2 Write property test for permission enforcement
    - **Property 2: Permission-Based Access Control**
    - **Validates: Requirements US-1.5, US-9.1, US-9.4**
    - Test users without view_finances AND without admin cannot access
    - **Property 27: Admin-Only Editing**
    - **Validates: Requirements US-9.2, US-9.3, US-9.5**
    - Test non-admin users cannot save targets

  - [ ]* 11.3 Write unit tests for permission controls
    - Test admin can access and edit
    - Test user with view_finances can access but not edit
    - Test user without permissions redirected with error
    - Test non-admin POST requests rejected
    - Test edit buttons hidden for non-admin
    - _Requirements: US-1.5, US-9.1, US-9.2, US-9.3, US-9.4, US-9.5_

- [ ] 12. Add error handling and validation
  - [ ] 12.1 Implement input validation
    - Validate target_amount >= 0
    - Validate target_amount is valid decimal (max 2 decimal places)
    - Validate vehicle_id exists and not sold
    - Validate period dates are valid (start < end)
    - Validate month in range 1-12
    - Validate year in range 2020-2099
    - Display user-friendly error messages
    - _Requirements: US-8.1, US-8.2, US-8.3, US-8.4, US-8.5_

  - [ ] 12.2 Implement database error handling
    - Wrap all database operations in try-catch
    - Log errors with full exception details
    - Show user-friendly error messages
    - Handle unique constraint violations gracefully
    - Handle foreign key violations gracefully
    - _Requirements: NFR-3_

  - [ ]* 12.3 Write unit tests for error handling
    - Test negative target amount rejected
    - Test non-numeric target rejected
    - Test invalid vehicle_id rejected
    - Test sold vehicle rejected
    - Test invalid period dates rejected
    - Test database errors handled gracefully
    - _Requirements: US-8.1, US-8.2, US-8.3, US-8.4, US-8.5_

- [ ] 13. Implement security measures
  - [ ] 13.1 Add SQL injection prevention
    - Use prepared statements for all queries
    - Parameterize all WHERE clauses and VALUES
    - Never concatenate user input into SQL
    - _Requirements: NFR-4_

  - [ ] 13.2 Add XSS prevention
    - Use htmlspecialchars() on all user-generated content
    - Escape vehicle names, notes, and displayed text
    - Sanitize all output in HTML attributes
    - _Requirements: NFR-4_

  - [ ] 13.3 Add CSRF protection
    - Include CSRF token in all forms
    - Verify token on POST requests
    - Reject requests with missing/invalid tokens
    - _Requirements: NFR-4_

  - [ ]* 13.4 Write security tests
    - Test SQL injection attempts blocked
    - Test XSS attempts sanitized
    - Test CSRF token validation
    - _Requirements: NFR-4_

- [ ] 14. Add logging and audit trail
  - [ ] 14.1 Implement action logging
    - Log all target modifications with app_log('ACTION', ...)
    - Include user ID, vehicle ID, period, and amount
    - Log bulk operations with count of affected vehicles
    - _Requirements: NFR-4_

  - [ ] 14.2 Implement error logging
    - Log all database errors with full exception details
    - Log permission violations
    - Log validation failures
    - _Requirements: NFR-4_

- [ ] 15. Optimize performance
  - [ ] 15.1 Add database indexes
    - Verify indexes created by migration: idx_period, idx_vehicle
    - Test query performance with EXPLAIN
    - Ensure queries use indexes efficiently
    - _Requirements: NFR-1_

  - [ ] 15.2 Optimize income calculation query
    - Use single query with JOINs instead of N+1 queries
    - Use LEFT JOIN to include vehicles with no income
    - Use COALESCE for default values
    - Test with 50+ vehicles to ensure < 2 second load time
    - _Requirements: NFR-1_

  - [ ]* 15.3 Write performance tests
    - Test page load time with 50 vehicles < 2 seconds
    - Test bulk operations complete within 5 seconds for 50 vehicles
    - _Requirements: NFR-1_

- [ ] 16. Final integration and testing
  - [ ] 16.1 Test complete user flows
    - Admin logs in → Hope Window → Vehicle Targets → sees vehicle list
    - Admin selects period → sets individual target → saves → sees success
    - Admin selects multiple vehicles → bulk equal distribution → confirms → all saved
    - User with view_finances → accesses page → sees read-only view
    - User without permissions → redirected with error
    - _Requirements: All US stories_

  - [ ] 16.2 Test edge cases
    - No vehicles in fleet (empty state)
    - All vehicles sold (empty state)
    - Vehicle with no reservations (income = 0)
    - Future period (income = 0)
    - Past period (historical data)
    - Period in January (year boundary)
    - Period in December (year boundary)
    - _Requirements: US-2.5, US-3.1_

  - [ ] 16.3 Verify income calculation consistency
    - Compare total vehicle income with Hope Window total for same period
    - Verify both use ledger_kpi_exclusion_clause()
    - Verify both filter by same date range
    - _Requirements: US-7.7_

  - [ ]* 16.4 Write property test for income consistency
    - **Property 24: Income Calculation Consistency**
    - **Validates: Requirements US-7.7**
    - For any period P, sum of vehicle income equals Hope Window total

- [ ] 17. Checkpoint - Final verification
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 18. Documentation and deployment preparation
  - [ ] 18.1 Verify migration is ready
    - Review migration SQL for idempotency
    - Test migration on development database
    - Verify unique constraint works
    - Verify foreign key constraints work
    - Confirm entry in PRODUCTION_DB_STEPS.md under "Pending"
    - Confirm release log entry in UPDATE_SESSION_RULES.md
    - _Requirements: FR-1_

  - [ ] 18.2 Create deployment checklist
    - Document pre-deployment steps (backup database)
    - Document migration steps (run SQL via phpMyAdmin)
    - Document post-deployment verification (test feature)
    - Document rollback plan (drop table if needed)
    - _Requirements: NFR-3_

  - [ ] 18.3 Update documentation
    - Add feature description to user documentation (if exists)
    - Document permission requirements
    - Document period calculation logic
    - Document bulk operation methods
    - _Requirements: All_

## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties across input space
- Unit tests validate specific examples and edge cases
- All database operations use prepared statements for security
- All user input is validated and sanitized
- Permission checks enforce access control at every level
- Migration is idempotent and safe to re-run
- Feature integrates seamlessly with existing Hope Window and financial screens
