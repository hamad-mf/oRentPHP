# Design Document: Hourly Salary Calculation

## Overview

This feature extends the existing payroll system to support hourly-based salary calculation alongside the current fixed monthly salary model. The system will maintain a hybrid approach where staff members can be configured as either fixed-salary or hourly-rate employees. For hourly staff, the system calculates payment based on actual hours worked during the 15th-to-15th billing period, derived from precise attendance tracking data including punch times and break durations.

The design integrates seamlessly with existing payroll infrastructure including overtime calculation, advance deduction, and ledger entry creation. A minimum monthly work threshold of 1.0 hour ensures that staff who work minimal hours receive zero payment for that period, while staff meeting or exceeding the threshold receive payment proportional to their hours worked.

## Architecture

### System Components

The feature integrates with three primary system components:

1. **Staff Management UI** (staff/create.php, staff/edit.php, staff/show.php)
   - Provides interface for configuring salary type and hourly rate
   - Displays salary configuration in staff profiles
   - Validates input based on selected salary type

2. **Payroll Generator** (payroll/index.php)
   - Calculates hours worked from attendance data
   - Computes payment for hourly staff with threshold enforcement
   - Maintains backward compatibility for fixed-salary staff
   - Integrates with existing overtime and advance systems

3. **Database Layer** (staff table, staff_attendance table, attendance_breaks table)
   - Stores salary configuration (salary_type, hourly_rate)
   - Provides attendance and break data for hours calculation
   - Maintains referential integrity across tables

### Data Flow

1. **Configuration Phase**: Administrator configures staff member as hourly or fixed via Staff UI
2. **Attendance Tracking**: Staff punch in/out and take breaks throughout billing period
3. **Payroll Preparation**: Payroll Generator queries attendance data and calculates hours
4. **Payment Calculation**: System applies hourly rate with minimum threshold enforcement
5. **Payroll Display**: Batch screen shows calculated values for review
6. **Payment Processing**: Final payment includes hourly salary plus overtime minus advances

### Integration Points

- **Existing Overtime System**: Hourly staff remain eligible for overtime bonuses calculated using att_overtime_rate_per_hour setting
- **Advance Deduction System**: Hourly-calculated salaries integrate with existing advance recovery logic
- **Ledger System**: Payments create ledger entries using existing infrastructure
- **Bank Account System**: Payments deduct from selected bank accounts as with fixed salaries

## Components and Interfaces

### Database Schema Changes

**staff table modifications:**
```sql
ALTER TABLE staff 
ADD COLUMN salary_type ENUM('fixed', 'hourly') NOT NULL DEFAULT 'fixed',
ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT NULL;
```

**Migration strategy:**
- Add columns with default values to ensure existing records default to 'fixed'
- No data migration required as existing staff automatically become fixed-salary
- hourly_rate remains NULL for fixed-salary staff

### Staff UI Components

**staff/create.php modifications:**
- Add salary type radio buttons or select dropdown
- Show/hide salary vs hourly_rate input based on selection
- Add JavaScript for dynamic field visibility
- Validate appropriate field based on salary_type

**staff/edit.php modifications:**
- Display current salary_type and allow modification
- Preserve existing validation and permission checks
- Support changing between fixed and hourly types

**staff/show.php modifications:**
- Display salary type label ("Fixed Monthly" or "Hourly Rate")
- Show appropriate amount (monthly salary or hourly rate)
- Display payroll history with hours for hourly staff

### Payroll Generator Components

**Hours Calculation Function:**
```php
function calculate_hours_worked($pdo, $userId, $startDate, $endDate) {
    // Query attendance records with complete punch times
    $stmt = $pdo->prepare("
        SELECT id, punch_in, punch_out 
        FROM staff_attendance 
        WHERE user_id = ? 
          AND date BETWEEN ? AND ? 
          AND punch_in IS NOT NULL 
          AND punch_out IS NOT NULL
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $attendanceRecords = $stmt->fetchAll();
    
    $totalSeconds = 0;
    
    foreach ($attendanceRecords as $record) {
        // Calculate work duration in seconds
        $workSeconds = strtotime($record['punch_out']) - strtotime($record['punch_in']);
        
        // Subtract break durations
        $breakStmt = $pdo->prepare("
            SELECT break_start, break_end 
            FROM attendance_breaks 
            WHERE attendance_id = ? 
              AND break_start IS NOT NULL 
              AND break_end IS NOT NULL
        ");
        $breakStmt->execute([$record['id']]);
        $breaks = $breakStmt->fetchAll();
        
        foreach ($breaks as $break) {
            $breakSeconds = strtotime($break['break_end']) - strtotime($break['break_start']);
            $workSeconds -= $breakSeconds;
        }
        
        // Handle negative durations (data error)
        if ($workSeconds < 0) {
            app_log('ERROR', "Negative work duration for attendance#{$record['id']}");
            $workSeconds = 0;
        }
        
        $totalSeconds += $workSeconds;
    }
    
    // Convert to hours with 4 decimal precision
    $totalHours = round($totalSeconds / 3600, 4);
    
    return $totalHours;
}
```

**Payment Calculation Function:**
```php
function calculate_hourly_payment($totalHours, $hourlyRate, $minimumThreshold = 1.0) {
    // Enforce minimum monthly work threshold
    if ($totalHours < $minimumThreshold) {
        return 0.00;
    }
    
    // Calculate payment
    $basicSalary = $totalHours * $hourlyRate;
    
    // Round to 2 decimal places
    return round($basicSalary, 2);
}
```

**Payroll Batch Preparation Modifications:**
- Query staff.salary_type and staff.hourly_rate alongside existing fields
- For hourly staff, call calculate_hours_worked() for billing period
- Apply calculate_hourly_payment() with threshold enforcement
- For fixed staff, use existing staff.salary logic
- Attach hours_worked and hourly_rate to batch data for display

### Payroll Display Components

**Batch Screen Table Columns:**
- Staff Name
- Salary Type (Fixed/Hourly)
- Hours Worked (for hourly staff only)
- Hourly Rate (for hourly staff only)
- Basic Salary (calculated or fixed)
- Threshold Indicator (for hourly staff < 1.0 hours)
- Overtime Pay
- Net Salary
- Advance Deducted
- Payable Salary

**Visual Indicators:**
- Orange/yellow badge for hourly staff below 1.0 hour threshold
- Display "Below 1hr monthly threshold" message
- Show 0.00 basic salary for below-threshold staff

## Data Models

### staff Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| name | VARCHAR(255) | NOT NULL | Staff member name |
| role | VARCHAR(100) | NULL | Job title |
| phone | VARCHAR(20) | NULL | Contact phone |
| email | VARCHAR(255) | NULL | Contact email |
| salary | DECIMAL(10,2) | NULL | Fixed monthly salary |
| salary_type | ENUM('fixed','hourly') | NOT NULL, DEFAULT 'fixed' | Payment model |
| hourly_rate | DECIMAL(10,2) | NULL | Hourly payment rate |
| joined_date | DATE | NULL | Employment start date |
| notes | TEXT | NULL | Internal notes |
| id_proof_path | VARCHAR(500) | NULL | Document path |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Update timestamp |

**Constraints:**
- salary_type defaults to 'fixed' for backward compatibility
- hourly_rate can be NULL when salary_type is 'fixed'
- hourly_rate should be positive when salary_type is 'hourly' (enforced by application)

### staff_attendance Table (existing)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| user_id | INT | NOT NULL, FOREIGN KEY | References users.id |
| date | DATE | NOT NULL | Attendance date |
| punch_in | DATETIME | NULL | Clock-in timestamp |
| punch_out | DATETIME | NULL | Clock-out timestamp |
| ... | ... | ... | Additional columns |

**Usage:**
- Records with both punch_in and punch_out NOT NULL are used for hours calculation
- Records with NULL punch_in or punch_out are excluded from calculation

### attendance_breaks Table (existing)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| attendance_id | INT | NOT NULL, FOREIGN KEY | References staff_attendance.id |
| break_start | DATETIME | NOT NULL | Break start timestamp |
| break_end | DATETIME | NULL | Break end timestamp |
| ... | ... | ... | Additional columns |

**Usage:**
- Break durations are subtracted from work duration
- Records with NULL break_end are excluded from calculation

### payroll Table (existing, no changes)

The payroll table structure remains unchanged. The basic_salary column stores either:
- Fixed monthly salary (for salary_type='fixed')
- Calculated hourly payment (for salary_type='hourly')

The existing overtime_pay, advance_deducted, and payable_salary columns work identically for both salary types.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Hourly rate validation rejects invalid inputs

*For any* input value submitted as hourly_rate when salary_type is 'hourly', if the value is not a positive number (negative, zero, or non-numeric), the system should reject the input and display a validation error.

**Validates: Requirements 2.5**

### Property 2: Fixed salary validation rejects invalid inputs

*For any* input value submitted as salary when salary_type is 'fixed', if the value is not a positive number (negative, zero, or non-numeric), the system should reject the input and display a validation error.

**Validates: Requirements 2.6**

### Property 3: Complete attendance records are included in hours calculation

*For any* billing period and staff member, only attendance records where both punch_in and punch_out are NOT NULL should be included in the hours worked calculation.

**Validates: Requirements 3.1, 8.2**

### Property 4: Work duration calculation accounts for breaks

*For any* attendance record with associated breaks, the calculated work duration should equal (punch_out - punch_in) minus the sum of all break durations where both break_start and break_end are NOT NULL.

**Validates: Requirements 3.2, 8.3**

### Property 5: Hours conversion maintains precision

*For any* work duration in seconds, when converted to hours by dividing by 3600, the result should maintain at least 4 decimal places of precision.

**Validates: Requirements 3.3, 3.5**

### Property 6: Total hours aggregation is accurate

*For any* set of daily hours worked across a billing period, the total hours worked should equal the sum of all daily hours.

**Validates: Requirements 3.4, 4.1**

### Property 7: Hourly payment calculation above threshold

*For any* hourly staff member with total_hours_worked >= 1.0 hour in the billing period, the basic_salary should equal (total_hours_worked × hourly_rate) rounded to 2 decimal places.

**Validates: Requirements 4.2, 4.5**

### Property 8: Hourly payment calculation below threshold

*For any* hourly staff member with total_hours_worked < 1.0 hour in the billing period, the basic_salary should be set to 0.00.

**Validates: Requirements 4.3**

### Property 9: Fixed salary staff use existing logic

*For any* staff member with salary_type='fixed', the payroll generation should use the staff.salary field as basic_salary, maintaining identical behavior to the pre-feature implementation.

**Validates: Requirements 4.4, 7.2, 7.3**

### Property 10: Overtime calculation applies to hourly staff

*For any* hourly staff member who works beyond the overtime threshold, the overtime_pay should be calculated using the existing att_overtime_rate_per_hour setting and added to the basic_salary.

**Validates: Requirements 6.1, 6.2, 6.3, 6.4**

### Property 11: Backward compatibility for existing staff

*For any* existing staff record after migration, the salary_type should be 'fixed' and payroll generation should produce identical results to pre-migration behavior.

**Validates: Requirements 7.1, 7.3**

## Error Handling

### Input Validation Errors

**Invalid hourly_rate:**
- Validation: Reject negative, zero, or non-numeric values when salary_type='hourly'
- User feedback: Display error message "Hourly rate must be a positive number"
- System behavior: Prevent form submission

**Invalid salary:**
- Validation: Reject negative, zero, or non-numeric values when salary_type='fixed'
- User feedback: Display error message "Salary must be a positive number"
- System behavior: Prevent form submission

### Data Integrity Errors

**NULL hourly_rate for hourly staff:**
- Detection: Check hourly_rate during payroll generation
- Logging: app_log('ERROR', "Hourly staff user_id={$userId} has NULL hourly_rate")
- Recovery: Set basic_salary to 0.00 and continue processing
- User notification: Display warning in payroll batch screen

**Incomplete attendance records:**
- Detection: Check for NULL punch_in or punch_out during hours calculation
- Logging: app_log('WARNING', "Incomplete attendance record id={$recordId} excluded from calculation")
- Recovery: Exclude record from hours calculation
- Impact: Staff member receives payment only for complete attendance records

**Incomplete break records:**
- Detection: Check for NULL break_start or break_end during duration calculation
- Logging: app_log('WARNING', "Incomplete break record id={$breakId} excluded from calculation")
- Recovery: Exclude break from duration calculation
- Impact: Work duration may be slightly overstated if break was actually taken

**Negative work duration:**
- Detection: Check if (punch_out - punch_in - breaks) < 0
- Logging: app_log('ERROR', "Negative work duration for attendance id={$recordId}, data integrity issue")
- Recovery: Treat attendance record as 0 hours
- Impact: Staff member receives no payment for that day
- Root cause: Data corruption or clock manipulation

**NULL salary_type:**
- Detection: Check salary_type during payroll generation
- Logging: app_log('WARNING', "Staff user_id={$userId} has NULL salary_type, defaulting to fixed")
- Recovery: Use fixed salary behavior with staff.salary field
- Impact: Staff member processed as fixed-salary employee

### System Errors

**Database connection failures:**
- Handled by existing error handling infrastructure
- Transaction rollback ensures data consistency
- User sees generic error message

**Calculation overflow:**
- Unlikely given DECIMAL(10,2) precision
- PHP handles large numbers gracefully
- Rounding to 2 decimal places prevents precision issues

## Testing Strategy

### Unit Testing

Unit tests should focus on specific examples, edge cases, and error conditions:

**Hours Calculation Tests:**
- Test attendance record with no breaks
- Test attendance record with multiple breaks
- Test attendance record with incomplete breaks (NULL break_end)
- Test attendance record with negative duration (data error)
- Test billing period with no attendance records
- Test billing period with mix of complete and incomplete records

**Payment Calculation Tests:**
- Test hourly payment exactly at 1.0 hour threshold
- Test hourly payment just below threshold (0.9999 hours)
- Test hourly payment just above threshold (1.0001 hours)
- Test hourly payment with zero hours
- Test hourly payment with NULL hourly_rate
- Test fixed salary payment (unchanged behavior)

**Validation Tests:**
- Test hourly_rate validation with negative value
- Test hourly_rate validation with zero
- Test hourly_rate validation with non-numeric string
- Test salary validation with negative value
- Test salary validation with zero
- Test salary validation with non-numeric string

**Integration Tests:**
- Test complete payroll generation for mixed staff (fixed and hourly)
- Test overtime calculation for hourly staff
- Test advance deduction for hourly staff
- Test ledger entry creation for hourly staff payment
- Test bank account balance update for hourly staff payment

### Property-Based Testing

Property tests should verify universal properties across all inputs using a property-based testing library (e.g., PHPUnit with Eris or similar). Each test should run minimum 100 iterations.

**Property Test 1: Hourly rate validation rejects invalid inputs**
- Generate: Random invalid inputs (negative, zero, non-numeric)
- Test: Validation rejects all invalid inputs
- Tag: **Feature: hourly-salary-calculation, Property 1: Hourly rate validation rejects invalid inputs**

**Property Test 2: Fixed salary validation rejects invalid inputs**
- Generate: Random invalid inputs (negative, zero, non-numeric)
- Test: Validation rejects all invalid inputs
- Tag: **Feature: hourly-salary-calculation, Property 2: Fixed salary validation rejects invalid inputs**

**Property Test 3: Complete attendance records are included**
- Generate: Random mix of complete and incomplete attendance records
- Test: Only complete records (both punch_in and punch_out NOT NULL) are included
- Tag: **Feature: hourly-salary-calculation, Property 3: Complete attendance records are included in hours calculation**

**Property Test 4: Work duration calculation accounts for breaks**
- Generate: Random attendance records with random break configurations
- Test: Work duration = (punch_out - punch_in) - sum(break durations)
- Tag: **Feature: hourly-salary-calculation, Property 4: Work duration calculation accounts for breaks**

**Property Test 5: Hours conversion maintains precision**
- Generate: Random work durations in seconds
- Test: Conversion to hours maintains at least 4 decimal places
- Tag: **Feature: hourly-salary-calculation, Property 5: Hours conversion maintains precision**

**Property Test 6: Total hours aggregation is accurate**
- Generate: Random sets of daily hours
- Test: Total hours = sum of daily hours
- Tag: **Feature: hourly-salary-calculation, Property 6: Total hours aggregation is accurate**

**Property Test 7: Hourly payment calculation above threshold**
- Generate: Random hours >= 1.0 and random hourly rates
- Test: basic_salary = round(hours × rate, 2)
- Tag: **Feature: hourly-salary-calculation, Property 7: Hourly payment calculation above threshold**

**Property Test 8: Hourly payment calculation below threshold**
- Generate: Random hours < 1.0
- Test: basic_salary = 0.00
- Tag: **Feature: hourly-salary-calculation, Property 8: Hourly payment calculation below threshold**

**Property Test 9: Fixed salary staff use existing logic**
- Generate: Random fixed-salary staff configurations
- Test: basic_salary = staff.salary (unchanged from pre-feature)
- Tag: **Feature: hourly-salary-calculation, Property 9: Fixed salary staff use existing logic**

**Property Test 10: Overtime calculation applies to hourly staff**
- Generate: Random hourly staff with overtime hours
- Test: overtime_pay calculated correctly and added to basic_salary
- Tag: **Feature: hourly-salary-calculation, Property 10: Overtime calculation applies to hourly staff**

**Property Test 11: Backward compatibility for existing staff**
- Generate: Random existing staff records
- Test: After migration, salary_type='fixed' and payroll results identical
- Tag: **Feature: hourly-salary-calculation, Property 11: Backward compatibility for existing staff**

### Manual Testing Checklist

- [ ] Create new hourly staff member via UI
- [ ] Create new fixed staff member via UI
- [ ] Edit existing staff to change from fixed to hourly
- [ ] Edit existing staff to change from hourly to fixed
- [ ] View staff profile for hourly staff
- [ ] View staff profile for fixed staff
- [ ] Generate payroll for billing period with hourly staff
- [ ] Generate payroll for billing period with fixed staff
- [ ] Generate payroll for billing period with mixed staff
- [ ] Verify hourly staff with < 1.0 hours shows threshold indicator
- [ ] Verify hourly staff with >= 1.0 hours shows calculated salary
- [ ] Verify overtime calculation for hourly staff
- [ ] Verify advance deduction for hourly staff
- [ ] Pay hourly staff salary and verify ledger entry
- [ ] Pay fixed staff salary and verify unchanged behavior
- [ ] View payroll history for hourly staff (shows hours)
- [ ] View payroll history for fixed staff (no hours)
- [ ] Run migration on database with existing staff
- [ ] Verify all existing staff have salary_type='fixed' after migration
- [ ] Verify payroll generation for existing staff unchanged after migration
