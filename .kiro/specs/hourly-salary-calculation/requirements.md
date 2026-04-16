# Requirements Document

## Introduction

This feature adds support for hourly-based salary calculation alongside the existing fixed monthly salary system. The system will maintain a hybrid approach where some staff members receive fixed monthly salaries while others are paid based on actual hours worked, calculated from precise attendance data tracked in the staff_attendance and attendance_breaks tables.

## Glossary

- **Staff_Table**: The database table storing staff member information including salary configuration
- **Payroll_Generator**: The system component that generates payroll batches for 15th-to-15th billing periods (payroll/index.php)
- **Attendance_System**: The system that tracks punch_in, punch_out, and break times in staff_attendance and attendance_breaks tables
- **Hourly_Staff**: Staff members configured with salary_type='hourly' who are paid based on hours worked
- **Fixed_Staff**: Staff members configured with salary_type='fixed' who receive a predetermined monthly salary
- **Billing_Period**: The 15th-to-15th date range used for payroll calculation (approximately one month)
- **Work_Duration**: Total time between punch_in and punch_out minus all break durations, measured in seconds
- **Hourly_Rate**: The amount paid per hour of work for hourly staff (stored in staff.hourly_rate)
- **Minimum_Work_Threshold**: The minimum total hours (1.0 hour) that must be worked across the entire billing period to qualify for payment
- **Staff_UI**: The user interface forms for creating and editing staff members (staff/create.php, staff/edit.php)
- **Payroll_Batch_Screen**: The interface displaying payroll calculations for all staff (payroll/index.php)

## Requirements

### Requirement 1: Database Schema Extension

**User Story:** As a system administrator, I want to store salary type and hourly rate information for each staff member, so that the system can support both fixed and hourly payment models.

#### Acceptance Criteria

1. THE Staff_Table SHALL include a salary_type column of type ENUM with values 'fixed' and 'hourly'
2. THE Staff_Table SHALL include an hourly_rate column of type DECIMAL(10,2) for storing hourly payment rates
3. THE Staff_Table SHALL default salary_type to 'fixed' for backward compatibility with existing records
4. THE Staff_Table SHALL allow hourly_rate to be NULL when salary_type is 'fixed'

### Requirement 2: Staff Configuration Interface

**User Story:** As an administrator, I want to configure whether a staff member is paid hourly or with a fixed salary, so that I can set up appropriate payment terms for each employee.

#### Acceptance Criteria

1. WHEN creating a new staff member, THE Staff_UI SHALL display a salary type selector with options 'Fixed Monthly Salary' and 'Hourly Rate'
2. WHEN salary type 'Fixed Monthly Salary' is selected, THE Staff_UI SHALL display the existing salary input field
3. WHEN salary type 'Hourly Rate' is selected, THE Staff_UI SHALL display an hourly_rate input field and hide the salary field
4. WHEN editing an existing staff member, THE Staff_UI SHALL display the current salary_type and allow modification
5. THE Staff_UI SHALL validate that hourly_rate is a positive number when salary_type is 'hourly'
6. THE Staff_UI SHALL validate that salary is a positive number when salary_type is 'fixed'

### Requirement 3: Hours Worked Calculation

**User Story:** As the payroll system, I want to calculate total hours worked from attendance records, so that I can accurately compute payment for hourly staff.

#### Acceptance Criteria

1. WHEN calculating hours for a billing period, THE Payroll_Generator SHALL query all attendance records where punch_in and punch_out are not NULL for the date range
2. FOR EACH attendance record, THE Payroll_Generator SHALL calculate Work_Duration as (punch_out - punch_in) minus the sum of all break durations from attendance_breaks table
3. THE Payroll_Generator SHALL convert Work_Duration from seconds to hours by dividing by 3600
4. THE Payroll_Generator SHALL sum all daily hours to produce total hours worked for the billing period
5. THE Payroll_Generator SHALL maintain precision to at least 4 decimal places during calculation (e.g., 7 hours 23 minutes = 7.3833 hours)
6. WHEN an attendance record has no associated breaks, THE Payroll_Generator SHALL calculate Work_Duration as (punch_out - punch_in)

### Requirement 4: Hourly Payment Calculation with Minimum Threshold

**User Story:** As the payroll system, I want to calculate payment for hourly staff based on hours worked and hourly rate with a minimum monthly work threshold, so that staff are paid fairly and consistently.

#### Acceptance Criteria

1. WHEN generating payroll for a staff member with salary_type='hourly', THE Payroll_Generator SHALL sum all hours worked across all days in the billing period to calculate total_hours_worked
2. WHEN total_hours_worked >= 1.0 hour for the billing period, THE Payroll_Generator SHALL calculate basic_salary as (total_hours_worked × hourly_rate)
3. WHEN total_hours_worked < 1.0 hour for the billing period, THE Payroll_Generator SHALL set basic_salary to 0.00
4. WHEN generating payroll for a staff member with salary_type='fixed', THE Payroll_Generator SHALL use the existing logic with staff.salary as basic_salary
5. THE Payroll_Generator SHALL round the final basic_salary to 2 decimal places for payment
6. WHEN an hourly staff member has zero attendance records in the billing period, THE Payroll_Generator SHALL set basic_salary to 0.00 and total_hours_worked to 0.00

### Requirement 5: Payroll Batch Display

**User Story:** As an administrator reviewing payroll, I want to see how hourly staff payments are calculated including minimum threshold status, so that I can verify accuracy before finalizing payroll.

#### Acceptance Criteria

1. WHEN displaying the payroll batch preparation screen, THE Payroll_Batch_Screen SHALL show salary_type for each staff member
2. WHEN a staff member has salary_type='hourly', THE Payroll_Batch_Screen SHALL display total hours worked across the entire billing period
3. WHEN a staff member has salary_type='hourly', THE Payroll_Batch_Screen SHALL display the hourly_rate
4. WHEN a staff member has salary_type='hourly' AND total hours >= 1.0 for the billing period, THE Payroll_Batch_Screen SHALL display the calculated basic_salary as (hours × rate)
5. WHEN a staff member has salary_type='hourly' AND total hours < 1.0 for the billing period, THE Payroll_Batch_Screen SHALL display basic_salary as 0.00 with a visual indicator (e.g., orange/yellow badge) showing "Below 1hr monthly threshold"
6. WHEN a staff member has salary_type='fixed', THE Payroll_Batch_Screen SHALL display the monthly salary amount

### Requirement 6: Overtime Compatibility

**User Story:** As the payroll system, I want hourly staff to remain eligible for overtime bonuses, so that they receive additional compensation for extra hours beyond the threshold.

#### Acceptance Criteria

1. WHEN calculating overtime for hourly staff, THE Payroll_Generator SHALL apply the existing overtime calculation logic
2. THE Payroll_Generator SHALL add overtime_pay to the basic_salary calculated from hourly rate
3. WHEN an hourly staff member works beyond the overtime threshold, THE Payroll_Generator SHALL calculate overtime_pay using the existing att_overtime_rate_per_hour setting
4. THE Payroll_Generator SHALL treat overtime_pay as separate from and additional to the hourly-based basic_salary

### Requirement 7: Backward Compatibility

**User Story:** As a system administrator, I want existing fixed-salary staff to continue working without changes, so that the new feature does not disrupt current operations.

#### Acceptance Criteria

1. WHEN the database migration is applied, THE Staff_Table SHALL set salary_type='fixed' for all existing staff records
2. WHEN generating payroll for existing staff with salary_type='fixed', THE Payroll_Generator SHALL use the existing staff.salary field
3. THE Payroll_Generator SHALL produce identical results for fixed-salary staff before and after the feature implementation
4. WHEN viewing existing staff profiles, THE Staff_UI SHALL display salary_type='fixed' and the current salary value

### Requirement 8: Data Validation and Error Handling

**User Story:** As the payroll system, I want to handle missing or invalid data gracefully, so that payroll generation does not fail due to data issues.

#### Acceptance Criteria

1. WHEN an hourly staff member has NULL hourly_rate, THE Payroll_Generator SHALL log an error and set basic_salary to 0.00
2. WHEN an hourly staff member has incomplete attendance records (punch_in without punch_out), THE Payroll_Generator SHALL exclude that record from hours calculation
3. WHEN a break record has NULL break_start or break_end, THE Payroll_Generator SHALL exclude that break from duration calculation
4. IF a calculated Work_Duration is negative, THEN THE Payroll_Generator SHALL log an error and treat that attendance record as 0 hours
5. WHEN salary_type is NULL for a staff member, THE Payroll_Generator SHALL default to 'fixed' behavior

### Requirement 9: Staff Profile Display

**User Story:** As an administrator viewing a staff profile, I want to see the salary configuration type and rate, so that I understand how the staff member is compensated.

#### Acceptance Criteria

1. WHEN viewing a staff profile with salary_type='fixed', THE Staff_UI SHALL display "Salary Type: Fixed Monthly" and the monthly salary amount
2. WHEN viewing a staff profile with salary_type='hourly', THE Staff_UI SHALL display "Salary Type: Hourly Rate" and the hourly rate amount
3. THE Staff_UI SHALL display the total amount paid to the staff member including both regular salary and advances
4. WHEN viewing payroll history for hourly staff, THE Staff_UI SHALL show hours worked for each pay period alongside the payment amount
