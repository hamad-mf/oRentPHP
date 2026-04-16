# Implementation Plan: Hourly Salary Calculation

## Overview

This implementation adds hourly-based salary calculation to the existing payroll system. The feature maintains backward compatibility with fixed monthly salaries while introducing a new hourly payment model based on attendance tracking. Staff can be configured as either fixed-salary or hourly-rate employees, with hourly staff paid based on actual hours worked during the 15th-to-15th billing period, subject to a minimum 1.0 hour monthly threshold.

## Tasks

- [x] 1. Create database migration for salary type and hourly rate
  - Create migration file in migrations/releases/ directory
  - Add salary_type ENUM column with values 'fixed' and 'hourly', default 'fixed'
  - Add hourly_rate DECIMAL(10,2) column, nullable
  - Test migration on development database
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 7.1_

- [x] 2. Implement hours worked calculation function
  - [x] 2.1 Create calculate_hours_worked() function in payroll/index.php or helper file
    - Query staff_attendance records with complete punch_in and punch_out for date range
    - Calculate work duration as (punch_out - punch_in) for each record
    - Query and subtract break durations from attendance_breaks table
    - Handle records with no breaks correctly
    - Convert total seconds to hours with 4 decimal precision
    - Handle negative durations by logging error and treating as 0 hours
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 8.2, 8.3, 8.4_

  - [ ]* 2.2 Write property test for complete attendance records inclusion
    - **Property 3: Complete attendance records are included in hours calculation**
    - **Validates: Requirements 3.1, 8.2**

  - [ ]* 2.3 Write property test for work duration calculation with breaks
    - **Property 4: Work duration calculation accounts for breaks**
    - **Validates: Requirements 3.2, 8.3**

  - [ ]* 2.4 Write property test for hours conversion precision
    - **Property 5: Hours conversion maintains precision**
    - **Validates: Requirements 3.3, 3.5**

  - [ ]* 2.5 Write property test for total hours aggregation
    - **Property 6: Total hours aggregation is accurate**
    - **Validates: Requirements 3.4, 4.1**

- [x] 3. Implement hourly payment calculation with threshold enforcement
  - [x] 3.1 Create calculate_hourly_payment() function
    - Accept total_hours, hourly_rate, and minimum_threshold (default 1.0) as parameters
    - Return 0.00 if total_hours < minimum_threshold
    - Calculate basic_salary as (total_hours × hourly_rate) if >= threshold
    - Round result to 2 decimal places
    - _Requirements: 4.2, 4.3, 4.5_

  - [ ]* 3.2 Write property test for hourly payment above threshold
    - **Property 7: Hourly payment calculation above threshold**
    - **Validates: Requirements 4.2, 4.5**

  - [ ]* 3.3 Write property test for hourly payment below threshold
    - **Property 8: Hourly payment calculation below threshold**
    - **Validates: Requirements 4.3**

- [x] 4. Modify payroll generation logic to support both salary types
  - [x] 4.1 Update payroll batch preparation in payroll/index.php
    - Query staff.salary_type and staff.hourly_rate alongside existing fields
    - For salary_type='hourly', call calculate_hours_worked() for billing period
    - For salary_type='hourly', call calculate_hourly_payment() with hours and rate
    - For salary_type='fixed', use existing staff.salary logic
    - Handle NULL hourly_rate by logging error and setting basic_salary to 0.00
    - Handle NULL salary_type by defaulting to 'fixed' behavior
    - Store hours_worked and hourly_rate in batch data for display
    - _Requirements: 4.1, 4.4, 4.6, 8.1, 8.5_

  - [ ]* 4.2 Write property test for fixed salary staff using existing logic
    - **Property 9: Fixed salary staff use existing logic**
    - **Validates: Requirements 4.4, 7.2, 7.3**

  - [ ]* 4.3 Write unit tests for payroll generation with mixed staff types
    - Test billing period with only fixed staff
    - Test billing period with only hourly staff
    - Test billing period with mixed fixed and hourly staff
    - Test hourly staff with zero attendance records
    - _Requirements: 4.6_

- [x] 5. Checkpoint - Ensure core calculation logic works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Update staff creation form (staff/create.php)
  - [x] 6.1 Add salary type selector (radio buttons or dropdown)
    - Add options for 'Fixed Monthly Salary' and 'Hourly Rate'
    - Add JavaScript to show/hide salary vs hourly_rate input based on selection
    - Default to 'Fixed Monthly Salary' for new staff
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 6.2 Add hourly_rate input field
    - Display when salary type 'Hourly Rate' is selected
    - Hide salary field when hourly rate is selected
    - Add validation for positive number when salary_type='hourly'
    - _Requirements: 2.3, 2.5_

  - [x] 6.3 Update salary field validation
    - Validate positive number when salary_type='fixed'
    - _Requirements: 2.6_

  - [ ]* 6.4 Write property test for hourly rate validation
    - **Property 1: Hourly rate validation rejects invalid inputs**
    - **Validates: Requirements 2.5**

  - [ ]* 6.5 Write property test for fixed salary validation
    - **Property 2: Fixed salary validation rejects invalid inputs**
    - **Validates: Requirements 2.6**

- [x] 7. Update staff edit form (staff/edit.php)
  - [x] 7.1 Display current salary_type and allow modification
    - Show current salary type selection
    - Allow switching between fixed and hourly
    - Show/hide appropriate input fields based on selection
    - Preserve existing validation and permission checks
    - _Requirements: 2.4_

  - [x] 7.2 Add validation for salary type changes
    - Validate hourly_rate when changing to hourly
    - Validate salary when changing to fixed
    - _Requirements: 2.5, 2.6_

- [x] 8. Update staff profile display (staff/show.php)
  - [x] 8.1 Display salary type and appropriate amount
    - Show "Salary Type: Fixed Monthly" with monthly salary for fixed staff
    - Show "Salary Type: Hourly Rate" with hourly rate for hourly staff
    - _Requirements: 9.1, 9.2_

  - [x] 8.2 Display payroll history with hours for hourly staff
    - Show hours worked for each pay period alongside payment amount
    - Only display hours column for hourly staff
    - _Requirements: 9.4_

- [x] 9. Update payroll batch display screen (payroll/index.php)
  - [x] 9.1 Add columns for hourly staff information
    - Add Salary Type column showing 'Fixed' or 'Hourly'
    - Add Hours Worked column (display only for hourly staff)
    - Add Hourly Rate column (display only for hourly staff)
    - _Requirements: 5.1, 5.2, 5.3_

  - [x] 9.2 Display calculated basic salary for hourly staff
    - Show calculated amount (hours × rate) when hours >= 1.0
    - Show 0.00 with threshold indicator when hours < 1.0
    - Add orange/yellow badge with "Below 1hr monthly threshold" message
    - _Requirements: 5.4, 5.5_

  - [x] 9.3 Display fixed salary for fixed staff
    - Show monthly salary amount unchanged
    - _Requirements: 5.6_

- [x] 10. Verify overtime compatibility for hourly staff
  - [x] 10.1 Test overtime calculation integration
    - Verify overtime_pay is calculated using existing att_overtime_rate_per_hour
    - Verify overtime_pay is added to hourly-calculated basic_salary
    - Verify hourly staff working beyond overtime threshold receive overtime_pay
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [ ]* 10.2 Write property test for overtime calculation with hourly staff
    - **Property 10: Overtime calculation applies to hourly staff**
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.4**

- [x] 11. Verify backward compatibility
  - [x] 11.1 Test existing fixed-salary staff behavior
    - Run payroll generation for existing staff after migration
    - Verify salary_type='fixed' for all existing records
    - Verify payroll results identical to pre-migration behavior
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [ ]* 11.2 Write property test for backward compatibility
    - **Property 11: Backward compatibility for existing staff**
    - **Validates: Requirements 7.1, 7.3**

- [x] 12. Final checkpoint - Integration testing and verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- The implementation uses PHP and integrates with existing payroll infrastructure
- Property tests should use a PHP property-based testing library (e.g., Eris)
- Minimum 100 iterations recommended for each property test
- Database migration should be tested on development environment before production
- Existing overtime, advance deduction, and ledger systems remain unchanged
