# Vehicle Monthly Targets - Design Document

## Overview

The Vehicle Monthly Targets feature provides a dedicated interface for managing and tracking monthly income targets at the vehicle level. This enables fleet performance monitoring by comparing actual income against set targets for each vehicle within a billing period.

The feature integrates with the existing financial tracking system, using the same 15th-to-14th period logic as Hope Window, Targets, and Payroll screens. It calculates actual income from ledger entries linked to reservations, providing visibility into which vehicles are meeting their income goals.

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Hope Window Page                          │
│                 (accounts/hope_window.php)                   │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │  [Vehicle Targets] Card/Button                      │    │
│  │  → Links to Vehicle Targets screen                  │    │
│  │  → Preserves period (m, y) parameters               │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│              Vehicle Targets Screen                          │
│            (accounts/vehicle_targets.php)                    │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Period Selector (Month/Year Dropdown)               │  │
│  │  → Uses period_from_my() and period_for_today()     │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Vehicle List Table                                   │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │ Vehicle | Target | Actual | Balance | % | Edit │  │  │
│  │  ├────────────────────────────────────────────────┤  │  │
│  │  │ Data rows...                                    │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  │  Total Row: Sum of all targets and actuals          │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Bulk Operations                                      │  │
│  │  • Set All Targets                                    │  │
│  │  • Set Selected Targets                               │  │
│  │  • Distribute Equally / Proportionally                │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer                            │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  vehicle_monthly_targets                              │  │
│  │  • Stores target amounts per vehicle per period      │  │
│  │  • UNIQUE(vehicle_id, period_start)                  │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Income Calculation Query                             │  │
│  │  ledger_entries → reservations → vehicles            │  │
│  │  • Filter by txn_type='income'                       │  │
│  │  • Apply ledger_kpi_exclusion_clause()               │  │
│  │  • Group by vehicle_id                                │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Page Load**:
   - User navigates from Hope Window or directly to vehicle_targets.php
   - System determines current period (or uses m/y from URL)
   - Loads all active vehicles (status != 'sold')
   - Fetches targets from vehicle_monthly_targets table
   - Calculates actual income from ledger_entries
   - Computes balance and achievement percentage
   - Renders table with visual indicators

2. **Set Individual Target**:
   - User clicks "Edit Target" button on vehicle row
   - Modal opens with vehicle info and current target
   - User enters new target amount
   - Form submits via POST with action='save_target'
   - Server validates input and saves to database
   - Page reloads with success message

3. **Bulk Operations**:
   - User selects vehicles (checkboxes) or clicks "Set All"
   - Modal opens with distribution options
   - User chooses method and enters total/amount
   - Preview shows calculated per-vehicle targets
   - User confirms, form submits via POST
   - Server loops through vehicles and saves targets
   - Page reloads with success message

4. **Income Calculation**:
   ```
   SELECT r.vehicle_id, SUM(le.amount) AS total_income
   FROM ledger_entries le
   INNER JOIN reservations r ON le.source_type='reservation' AND le.source_id=r.id
   WHERE le.txn_type='income'
     AND [ledger_kpi_exclusion_clause()]
     AND DATE(le.posted_at) BETWEEN period_start AND period_end
     AND r.vehicle_id IS NOT NULL
   GROUP BY r.vehicle_id
   ```

## Components and Interfaces

### 1. Hope Window Integration

**File**: `accounts/hope_window.php`

**Changes**: Add a new card/button after the existing summary cards to link to Vehicle Targets screen.

```php
<!-- Vehicle Targets Card -->
<a href="vehicle_targets.php?m=<?= $selM ?>&y=<?= $selY ?>" 
   class="block bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 hover:border-mb-accent/50 transition-colors">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Vehicle Breakdown</p>
            <p class="text-white text-sm">View targets by vehicle</p>
        </div>
        <svg class="w-5 h-5 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
    </div>
</a>
```

### 2. Vehicle Targets Screen

**File**: `accounts/vehicle_targets.php` (new file)

**URL Parameters**:
- `m` (int): Month (1-12), defaults to current period month
- `y` (int): Year (2020-2099), defaults to current period year
- `page` (int): Pagination page number, defaults to 1

**POST Actions**:
- `action=save_target`: Save individual vehicle target
- `action=save_bulk`: Save bulk targets for multiple vehicles

**Required Permissions**:
- View: `view_finances` permission OR admin role
- Edit: admin role only

### 3. Database Schema

**New Table**: `vehicle_monthly_targets`

```sql
CREATE TABLE IF NOT EXISTS vehicle_monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    period_start DATE NOT NULL COMMENT '15th of the month',
    period_end DATE NOT NULL COMMENT '14th of next month',
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_vehicle_period (vehicle_id, period_start),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL,
    
    INDEX idx_period (period_start, period_end),
    INDEX idx_vehicle (vehicle_id)
) ENGINE=InnoDB COMMENT='Monthly income targets per vehicle';
```

**Migration File**: `migrations/releases/2026-03-27_vehicle_monthly_targets.sql`

```sql
-- Release: 2026-03-27_vehicle_monthly_targets
-- Author: system
-- Safe: idempotent
-- Notes: Creates vehicle_monthly_targets table for tracking monthly income targets per vehicle.
--        Run manually via phpMyAdmin before deploying vehicle-monthly-targets feature.

SET FOREIGN_KEY_CHECKS = 0;

-- Create vehicle_monthly_targets table (idempotent via IF NOT EXISTS)
CREATE TABLE IF NOT EXISTS vehicle_monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    period_start DATE NOT NULL COMMENT '15th of the month',
    period_end DATE NOT NULL COMMENT '14th of next month',
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Monthly income targets per vehicle';

-- Add unique constraint (idempotent - will fail silently if exists)
ALTER TABLE vehicle_monthly_targets 
    ADD UNIQUE KEY uq_vehicle_period (vehicle_id, period_start);

-- Add indexes (idempotent - will fail silently if exists)
ALTER TABLE vehicle_monthly_targets 
    ADD INDEX idx_period (period_start, period_end);

ALTER TABLE vehicle_monthly_targets 
    ADD INDEX idx_vehicle (vehicle_id);

SET FOREIGN_KEY_CHECKS = 1;
```

## Data Models

### Vehicle Target Record

```php
[
    'id' => int,
    'vehicle_id' => int,
    'period_start' => string, // 'YYYY-MM-15'
    'period_end' => string,   // 'YYYY-MM-14'
    'target_amount' => float,
    'notes' => string|null,
    'created_by' => int|null,
    'created_at' => string,   // 'YYYY-MM-DD HH:MM:SS'
    'updated_at' => string
]
```

### Vehicle Display Row

```php
[
    'vehicle_id' => int,
    'vehicle_name' => string,        // "{brand} {model} ({license_plate})"
    'daily_rate' => float,
    'target_amount' => float,        // From vehicle_monthly_targets or 0
    'actual_income' => float,        // Calculated from ledger
    'balance' => float,              // target - actual
    'achievement_pct' => float,      // (actual / target) * 100
    'status' => string,              // 'success', 'warning', 'danger', 'none'
    'notes' => string|null
]
```

### Period Structure

```php
[
    'start' => string,  // 'YYYY-MM-15'
    'end' => string,    // 'YYYY-MM-14'
    'label' => string,  // '15 Mar - 14 Apr 2026'
    'days' => int       // Number of days in period
]
```

## API/Endpoint Design

### GET Parameters

**vehicle_targets.php**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `m` | int | No | Current period month | Month (1-12) |
| `y` | int | No | Current period year | Year (2020-2099) |
| `page` | int | No | 1 | Pagination page |

### POST Actions

#### 1. Save Individual Target

**Action**: `save_target`

**Parameters**:
```php
[
    'action' => 'save_target',
    'vehicle_id' => int,
    'period_start' => string,  // 'YYYY-MM-15'
    'period_end' => string,    // 'YYYY-MM-14'
    'target_amount' => float,  // >= 0
    'notes' => string|null
]
```

**Validation**:
- vehicle_id must exist and not be sold
- period_start and period_end must be valid dates
- target_amount must be >= 0
- Only admin can save

**Response**:
- Success: Flash message + redirect to same period
- Error: Flash error message + stay on page

#### 2. Save Bulk Targets

**Action**: `save_bulk`

**Parameters**:
```php
[
    'action' => 'save_bulk',
    'vehicle_ids' => array,    // Array of vehicle IDs
    'period_start' => string,
    'period_end' => string,
    'method' => string,        // 'same', 'equal', 'proportional'
    'amount' => float,         // Total or per-vehicle amount
    'notes' => string|null
]
```

**Methods**:
- `same`: Set same amount for all selected vehicles
- `equal`: Distribute total equally (total / count)
- `proportional`: Distribute based on daily_rate

**Validation**:
- vehicle_ids must be array of valid vehicle IDs
- method must be one of: 'same', 'equal', 'proportional'
- amount must be > 0
- Only admin can save

**Response**:
- Success: Flash message with count + redirect
- Error: Flash error message + stay on page

## Correctness Properties

Before writing the correctness properties, I need to analyze the acceptance criteria from the requirements document to determine which are testable.


### Property Reflection

After analyzing all acceptance criteria, I've identified the following redundancies:

**Redundant Properties to Remove:**
- US-9.1 (duplicate of US-1.5): Permission check for access
- US-9.4 (duplicate of US-1.5): Redirect without permission
- US-9.5 (duplicate of US-9.3): Edit buttons hidden for non-admin
- US-10.1 (duplicate of US-1.3): Link from Hope Window
- US-10.3 (duplicate of US-1.4): Period preservation

**Properties to Combine:**
- US-7.5 and US-7.6 can be combined into one property about KPI exclusion (voided + security deposits)
- US-8.1 and US-8.2 can be combined into one property about valid target input
- US-9.2 and US-9.3 can be combined into one property about admin-only editing

**Edge Cases to Handle in Generators:**
- US-2.5: Empty vehicle list
- US-8.3: Non-existent vehicle ID
- US-8.4: Sold vehicle

After reflection, the unique testable properties are:
- Period preservation in navigation (US-1.4)
- Permission-based access control (US-1.5)
- Active vehicles only displayed (US-2.1)
- Vehicle row contains all required fields (US-2.2)
- Visual status indicators match achievement (US-2.3)
- Total row accuracy (US-2.4)
- Period calculation consistency (US-3.1)
- Period label format (US-3.3)
- Days in period calculation (US-3.7)
- Edit button presence (US-4.1)
- Target persistence (US-4.4)
- Target uniqueness constraint (US-4.8)
- Zero/empty target deletion (US-4.9)
- Bulk same amount distribution (US-5.3)
- Bulk equal distribution (US-5.4)
- Bulk proportional distribution (US-5.5)
- Bulk operation atomicity (US-5.7)
- Bulk overwrite existing (US-5.10)
- Selected count accuracy (US-6.3)
- Bulk applies only to selected (US-6.5)
- Income calculation accuracy (US-7.1)
- Income attribution to vehicle (US-7.3)
- Income period filtering (US-7.4)
- KPI exclusion (US-7.5 + US-7.6 combined)
- Income calculation consistency with Hope Window (US-7.7)
- Valid target input (US-8.1 + US-8.2 combined)
- Period date validation (US-8.5)
- Duplicate prevention (US-8.6)
- Admin-only editing (US-9.2 + US-9.3 combined)

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Period Preservation in Navigation

*For any* period (month M, year Y) selected in Hope Window, the link to Vehicle Targets screen should include parameters `m=M` and `y=Y`, preserving the selected period across navigation.

**Validates: Requirements US-1.4**

### Property 2: Permission-Based Access Control

*For any* user without `view_finances` permission AND without admin role, attempting to access the Vehicle Targets screen should result in access denial with redirect to dashboard and error message.

**Validates: Requirements US-1.5, US-9.1, US-9.4**

### Property 3: Active Vehicles Only

*For any* set of vehicles in the database, the Vehicle Targets screen should display exactly those vehicles where `status != 'sold'`, excluding all sold vehicles from the list.

**Validates: Requirements US-2.1**

### Property 4: Vehicle Row Completeness

*For any* vehicle row rendered on the screen, it should contain all required fields: vehicle name (brand + model + license plate), target amount, actual income, balance (target - actual), and achievement percentage.

**Validates: Requirements US-2.2**

### Property 5: Visual Status Indicators

*For any* vehicle with a target amount > 0, the visual status indicator should be:
- Green/success when achievement >= 100%
- Yellow/warning when 50% <= achievement < 100%
- Red/danger when achievement < 50%
- Gray/none when target = 0

**Validates: Requirements US-2.3**

### Property 6: Total Row Accuracy

*For any* set of vehicles with targets and actual income, the total row should display:
- Total target = SUM of all individual vehicle targets
- Total actual = SUM of all individual vehicle actual income
- Total balance = total target - total actual
- Overall percentage = (total actual / total target) * 100

**Validates: Requirements US-2.4**

### Property 7: Period Calculation Consistency

*For any* month M and year Y input, the period calculated by `period_from_my(M, Y)` in Vehicle Targets should return exactly the same start and end dates as the same function call in Hope Window, Targets, and Payroll screens.

**Validates: Requirements US-3.1**

### Property 8: Period Label Format

*For any* period with start date S and end date E, the displayed label should match the format "DD MMM - DD MMM YYYY" (e.g., "15 Mar - 14 Apr 2026").

**Validates: Requirements US-3.3**

### Property 9: Days in Period Calculation

*For any* period with start date S and end date E, the displayed number of days should equal `(strtotime(E) - strtotime(S)) / 86400 + 1`.

**Validates: Requirements US-3.7**

### Property 10: Edit Button Presence

*For any* vehicle row displayed to an admin user, there should be an "Edit Target" button or equivalent edit control present.

**Validates: Requirements US-4.1**

### Property 11: Target Persistence

*For any* valid target input (vehicle V, period P, amount A >= 0), saving the target should result in a record in `vehicle_monthly_targets` where `vehicle_id = V.id`, `period_start = P.start`, and `target_amount = A`.

**Validates: Requirements US-4.4**

### Property 12: Target Uniqueness

*For any* vehicle V and period P, there should exist at most one record in `vehicle_monthly_targets` where `vehicle_id = V.id` AND `period_start = P.start`.

**Validates: Requirements US-4.8, US-8.6**

### Property 13: Zero Target Deletion

*For any* vehicle V and period P with an existing target, saving a target amount of 0 or empty string should delete the target record from `vehicle_monthly_targets`.

**Validates: Requirements US-4.9**

### Property 14: Bulk Same Amount Distribution

*For any* set of vehicles V and amount A, using the "set same amount" bulk operation should result in each vehicle in V having a target of exactly A for the selected period.

**Validates: Requirements US-5.3**

### Property 15: Bulk Equal Distribution

*For any* set of vehicles V with count N and total amount T, using the "distribute equally" bulk operation should result in each vehicle having a target of T/N (rounded to 2 decimal places).

**Validates: Requirements US-5.4**

### Property 16: Bulk Proportional Distribution

*For any* set of vehicles V with daily rates R and total amount T, using the "distribute proportionally" bulk operation should result in each vehicle i having a target of `(R[i] / SUM(R)) * T`.

**Validates: Requirements US-5.5**

### Property 17: Bulk Operation Atomicity

*For any* bulk target operation affecting N vehicles, either all N target records are saved successfully, or none are saved (atomic transaction).

**Validates: Requirements US-5.7**

### Property 18: Bulk Overwrite Existing

*For any* vehicle V with an existing target for period P, a bulk operation that includes V should overwrite the existing target with the new calculated value.

**Validates: Requirements US-5.10**

### Property 19: Selected Count Accuracy

*For any* number of vehicles selected via checkboxes, the displayed selected count should equal the actual number of checked checkboxes.

**Validates: Requirements US-6.3**

### Property 20: Bulk Applies Only to Selected

*For any* subset S of vehicles selected for bulk operation, only vehicles in S should have their targets modified, and all vehicles not in S should remain unchanged.

**Validates: Requirements US-6.5**

### Property 21: Income Calculation Accuracy

*For any* vehicle V and period P, the actual income displayed should equal the sum of all ledger entries where:
- `txn_type = 'income'`
- `source_type = 'reservation'`
- `reservation.vehicle_id = V.id`
- `DATE(posted_at) BETWEEN P.start AND P.end`
- Entry passes `ledger_kpi_exclusion_clause()` (not voided, not security deposit, not transfer)

**Validates: Requirements US-7.1, US-7.2, US-7.5, US-7.6**

### Property 22: Income Attribution to Vehicle

*For any* ledger entry with `source_type = 'reservation'` and `source_id = R`, the income should be attributed to the vehicle where `reservations.vehicle_id = V.id` for reservation R.

**Validates: Requirements US-7.3**

### Property 23: Income Period Filtering

*For any* ledger entry with `posted_at` date D, the entry should only be included in period P's income calculation if `P.start <= D <= P.end`.

**Validates: Requirements US-7.4**

### Property 24: Income Calculation Consistency

*For any* period P, the sum of all vehicle actual income should equal the total actual income displayed in Hope Window for the same period (both using `ledger_kpi_exclusion_clause()`).

**Validates: Requirements US-7.7**

### Property 25: Valid Target Input

*For any* target input value V, the system should accept V if and only if V is a valid decimal number >= 0 with at most 2 decimal places.

**Validates: Requirements US-8.1, US-8.2**

### Property 26: Period Date Validation

*For any* period input with start date S and end date E, the system should reject the input if S or E are not valid dates, or if S >= E.

**Validates: Requirements US-8.5**

### Property 27: Admin-Only Editing

*For any* user U without admin role, all target edit operations (individual or bulk) should be rejected with an error message, regardless of whether U has `view_finances` permission.

**Validates: Requirements US-9.2, US-9.3, US-9.5**

## Error Handling

### Input Validation Errors

**Invalid Period Selection**:
- If month < 1 or month > 12, default to current period month
- If year < 2020 or year > 2099, default to current period year
- Display error message: "Invalid period selected. Showing current period."

**Invalid Target Amount**:
- If amount < 0, reject with error: "Target amount cannot be negative."
- If amount is not numeric, reject with error: "Please enter a valid number."
- If amount has more than 2 decimal places, round to 2 places

**Invalid Vehicle Selection**:
- If vehicle_id doesn't exist, reject with error: "Vehicle not found."
- If vehicle status is 'sold', reject with error: "Cannot set target for sold vehicle."

### Database Errors

**Unique Constraint Violation**:
- Catch duplicate key error on (vehicle_id, period_start)
- This should not happen in normal flow (use INSERT ... ON DUPLICATE KEY UPDATE)
- If it does occur, log error and show: "Failed to save target. Please try again."

**Foreign Key Violation**:
- If vehicle_id references non-existent vehicle, reject with error: "Invalid vehicle."
- If created_by references non-existent staff, set to NULL (ON DELETE SET NULL)

**Connection Errors**:
- Catch PDOException on all database operations
- Log error details for debugging
- Show user-friendly message: "Database error. Please try again later."

### Permission Errors

**Access Denied**:
- Check permissions on page load
- If user lacks view_finances AND is not admin, redirect to dashboard
- Flash error message: "Access denied. You don't have permission to view this page."

**Edit Denied**:
- Check admin role on all POST operations
- If user is not admin, reject with error: "Only administrators can modify targets."
- Return 403 Forbidden status

### Bulk Operation Errors

**No Vehicles Selected**:
- If vehicle_ids array is empty, reject with error: "Please select at least one vehicle."

**Invalid Distribution Method**:
- If method not in ['same', 'equal', 'proportional'], reject with error: "Invalid distribution method."

**Transaction Failure**:
- Wrap bulk operations in database transaction
- If any save fails, rollback all changes
- Show error: "Failed to save targets. No changes were made."

### Edge Cases

**No Vehicles in Fleet**:
- Display empty state message: "No vehicles found. Add vehicles to start tracking targets."
- Hide bulk operation buttons

**No Ledger Data**:
- If ledger_entries table doesn't exist, show actual income as 0
- Display warning: "Ledger system not initialized. Actual income unavailable."

**Period in Future**:
- Allow setting targets for future periods
- Show actual income as 0 or "—" for future dates
- Display info message: "This period hasn't started yet."

**Period in Past**:
- Allow viewing and editing past period targets
- Show actual income from historical data
- No special handling needed

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests for comprehensive coverage:

**Unit Tests** focus on:
- Specific examples of target calculations
- Edge cases (empty vehicle list, sold vehicles, zero targets)
- Error conditions (invalid inputs, permission denials)
- Integration points (database operations, form submissions)
- UI rendering (modals, buttons, status indicators)

**Property-Based Tests** focus on:
- Universal properties across all inputs
- Income calculation accuracy with randomized data
- Bulk distribution algorithms with various vehicle counts and amounts
- Period calculation consistency across all month/year combinations
- Permission enforcement across all user role combinations

Together, unit tests catch concrete bugs in specific scenarios, while property tests verify general correctness across the input space.

### Property-Based Testing Configuration

**Library**: Use `fast-check` for JavaScript or `PHPUnit` with custom generators for PHP

**Test Configuration**:
- Minimum 100 iterations per property test
- Each test tagged with: `Feature: vehicle-monthly-targets, Property {number}: {property_text}`
- Generators for: vehicles, periods, targets, ledger entries, users

**Example Property Test Structure**:

```php
/**
 * Feature: vehicle-monthly-targets, Property 21: Income Calculation Accuracy
 * 
 * For any vehicle V and period P, the actual income displayed should equal 
 * the sum of all qualifying ledger entries.
 */
public function testIncomeCalculationAccuracy(): void
{
    $this->runPropertyTest(100, function() {
        // Generate random vehicle
        $vehicle = $this->generateVehicle();
        
        // Generate random period
        $period = $this->generatePeriod();
        
        // Generate random ledger entries for this vehicle
        $entries = $this->generateLedgerEntries($vehicle, $period);
        
        // Calculate expected income (sum of qualifying entries)
        $expected = $this->sumQualifyingEntries($entries, $period);
        
        // Get actual income from system
        $actual = $this->getVehicleIncome($vehicle->id, $period);
        
        // Assert they match
        $this->assertEquals($expected, $actual, 
            "Income calculation mismatch for vehicle {$vehicle->id}");
    });
}
```

### Unit Test Coverage

**Target Management**:
- Test saving individual target
- Test updating existing target
- Test deleting target (zero amount)
- Test unique constraint enforcement
- Test foreign key constraints

**Bulk Operations**:
- Test "set same amount" for 3 vehicles
- Test "distribute equally" for 5 vehicles with total 10000
- Test "distribute proportionally" for vehicles with different rates
- Test bulk operation with no vehicles selected (error)
- Test bulk operation transaction rollback on error

**Income Calculation**:
- Test income for vehicle with 1 reservation
- Test income for vehicle with multiple reservations
- Test income excludes voided entries
- Test income excludes security deposits
- Test income filtered by period dates
- Test income for vehicle with no reservations (0)

**Permission Checks**:
- Test admin can access and edit
- Test user with view_finances can access but not edit
- Test user without permissions cannot access
- Test non-admin cannot save targets

**Period Handling**:
- Test period calculation for January (year boundary)
- Test period calculation for December (year boundary)
- Test period calculation for current month
- Test invalid month/year defaults to current period
- Test period label formatting

**UI Rendering**:
- Test vehicle list shows active vehicles only
- Test sold vehicles are excluded
- Test empty state when no vehicles
- Test status indicators (green/yellow/red)
- Test total row calculations

### Integration Tests

**End-to-End Flows**:
1. Admin logs in → navigates to Hope Window → clicks Vehicle Targets → sees vehicle list
2. Admin selects period → sets individual target → saves → sees success message
3. Admin selects multiple vehicles → uses bulk equal distribution → confirms → all targets saved
4. User with view_finances logs in → accesses Vehicle Targets → cannot see edit buttons
5. User without permissions → attempts access → redirected with error

**Database Integration**:
- Test migration creates table correctly
- Test unique constraint prevents duplicates
- Test foreign key cascade on vehicle deletion
- Test transaction rollback on bulk operation failure

### Manual Testing Checklist

- [ ] Navigate from Hope Window with different periods
- [ ] Set target for single vehicle
- [ ] Edit existing target
- [ ] Delete target by setting to 0
- [ ] Use "Set All" with same amount
- [ ] Use "Set All" with equal distribution
- [ ] Use "Set All" with proportional distribution
- [ ] Select subset of vehicles and use bulk operation
- [ ] Verify actual income matches Hope Window total
- [ ] Check visual indicators (green/yellow/red)
- [ ] Test with sold vehicles (should not appear)
- [ ] Test with no vehicles (empty state)
- [ ] Test permission checks (admin vs non-admin)
- [ ] Test period selection dropdown
- [ ] Test pagination with many vehicles
- [ ] Verify total row calculations
- [ ] Test with future period (income should be 0)
- [ ] Test with past period (historical data)

## Security Considerations

### Authentication and Authorization

**Access Control**:
- Check `auth_check()` on page load to ensure user is logged in
- Verify user has `view_finances` permission OR admin role
- Redirect unauthorized users to dashboard with error message
- Check admin role on all POST operations (save target, bulk operations)

**Permission Levels**:
- **Admin**: Full access (view, create, edit, delete targets)
- **User with view_finances**: Read-only access (view targets, cannot edit)
- **Other users**: No access (redirected)

### Input Validation

**SQL Injection Prevention**:
- Use prepared statements for all database queries
- Never concatenate user input into SQL strings
- Parameterize all WHERE clauses and VALUES

**XSS Prevention**:
- Use `e()` function (htmlspecialchars) on all user-generated content
- Escape vehicle names, notes, and any displayed text
- Sanitize all output in HTML attributes

**CSRF Protection**:
- Include CSRF token in all forms
- Verify token on POST requests
- Reject requests with missing or invalid tokens

### Data Validation

**Type Checking**:
- Cast all numeric inputs to appropriate types (int, float)
- Validate date formats before using in queries
- Check array types for bulk operations

**Range Validation**:
- Ensure target_amount >= 0
- Ensure month in range 1-12
- Ensure year in range 2020-2099
- Ensure vehicle_id exists and is not sold

**Business Logic Validation**:
- Verify vehicle exists before saving target
- Verify period dates are valid (start < end)
- Verify user has permission before allowing edits

### Database Security

**Foreign Key Constraints**:
- Enforce referential integrity with FK constraints
- Use ON DELETE CASCADE for vehicle deletion
- Use ON DELETE SET NULL for staff deletion

**Unique Constraints**:
- Enforce (vehicle_id, period_start) uniqueness
- Prevent duplicate targets at database level

**Transaction Safety**:
- Use transactions for bulk operations
- Rollback on any error to maintain consistency
- Use appropriate isolation level (READ COMMITTED)

### Logging and Auditing

**Action Logging**:
- Log all target modifications with `app_log('ACTION', ...)`
- Include user ID, vehicle ID, period, and amount
- Log bulk operations with count of affected vehicles

**Error Logging**:
- Log all database errors with full exception details
- Log permission violations
- Log validation failures for security monitoring

**Audit Trail**:
- Store `created_by` and `updated_at` in database
- Track who last modified each target
- Enable forensic analysis if needed

## Implementation Notes

### File Structure

```
accounts/
  vehicle_targets.php          # Main screen (new file)
  hope_window.php              # Add link to vehicle targets
  
migrations/releases/
  2026-03-27_vehicle_monthly_targets.sql  # Database migration
  
includes/
  ledger_helpers.php           # Existing helper functions (no changes)
  
PRODUCTION_DB_STEPS.md         # Add migration to pending list
```

### Code Reuse

**Existing Functions to Use**:
- `period_from_my(int $m, int $y): array` - Period calculation
- `period_for_today(): array` - Current period detection
- `ledger_kpi_exclusion_clause(string $alias = ''): string` - Income filtering
- `auth_check()` - Authentication check
- `auth_has_perm(string $perm): bool` - Permission check
- `current_user(): array` - Get current user info
- `flash(string $type, string $message)` - Flash messages
- `redirect(string $url)` - Page redirection
- `e(string $str): string` - HTML escaping
- `app_log(string $type, string $message)` - Action logging
- `app_now_sql(): string` - Current timestamp for SQL

**New Functions to Create**:
```php
function vehicle_targets_get_income(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd): float
function vehicle_targets_get_all_income(PDO $pdo, string $periodStart, string $periodEnd): array
function vehicle_targets_save(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd, float $amount, ?string $notes, int $userId): bool
function vehicle_targets_save_bulk(PDO $pdo, array $vehicleIds, string $periodStart, string $periodEnd, array $amounts, int $userId): bool
function vehicle_targets_get_target(PDO $pdo, int $vehicleId, string $periodStart): ?array
function vehicle_targets_get_all_targets(PDO $pdo, string $periodStart): array
function vehicle_targets_delete(PDO $pdo, int $vehicleId, string $periodStart): bool
```

### Database Query Optimization

**Indexes**:
- Primary key on `id` (auto-created)
- Unique index on `(vehicle_id, period_start)` (enforces constraint)
- Index on `period_start` (for period-based queries)
- Index on `vehicle_id` (for vehicle-based queries)

**Query Patterns**:
- Use JOIN instead of subqueries for income calculation
- Use GROUP BY for aggregating income per vehicle
- Use LEFT JOIN to include vehicles with no targets
- Use COALESCE for default values (0 for missing targets)

**Example Optimized Query**:
```sql
SELECT 
    v.id AS vehicle_id,
    CONCAT(v.brand, ' ', v.model, ' (', v.license_plate, ')') AS vehicle_name,
    v.daily_rate,
    COALESCE(vmt.target_amount, 0) AS target_amount,
    COALESCE(income.total, 0) AS actual_income,
    COALESCE(vmt.notes, '') AS notes
FROM vehicles v
LEFT JOIN vehicle_monthly_targets vmt 
    ON v.id = vmt.vehicle_id 
    AND vmt.period_start = ?
LEFT JOIN (
    SELECT r.vehicle_id, SUM(le.amount) AS total
    FROM ledger_entries le
    INNER JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id
    WHERE le.txn_type = 'income'
      AND [ledger_kpi_exclusion_clause('le')]
      AND DATE(le.posted_at) BETWEEN ? AND ?
      AND r.vehicle_id IS NOT NULL
    GROUP BY r.vehicle_id
) income ON v.id = income.vehicle_id
WHERE v.status != 'sold'
ORDER BY v.brand, v.model
```

### UI/UX Design

**Layout**:
- Follow Hope Window and Targets page patterns
- Use same color scheme (mb-surface, mb-accent, mb-subtle)
- Use same card/table styling
- Use same modal design for edit forms

**Visual Indicators**:
- Green: `bg-green-500/10 text-green-400 border-green-500/30`
- Yellow: `bg-yellow-500/10 text-yellow-400 border-yellow-500/30`
- Red: `bg-red-500/10 text-red-400 border-red-500/30`
- Gray: `bg-mb-subtle/10 text-mb-subtle border-mb-subtle/20`

**Responsive Design**:
- Table scrolls horizontally on mobile
- Modals are full-width on mobile
- Buttons stack vertically on small screens
- Period selector wraps on mobile

**Loading States**:
- Show spinner during form submission
- Disable buttons during save operations
- Show "Saving..." text on submit buttons

**Empty States**:
- Show friendly message when no vehicles
- Provide link to add vehicles
- Show "—" for missing data instead of 0

### Migration Strategy

**Pre-Deployment**:
1. Review migration SQL for idempotency
2. Test migration on development database
3. Verify unique constraint works correctly
4. Verify foreign key constraints work correctly
5. Add migration to PRODUCTION_DB_STEPS.md under "Pending"

**Deployment**:
1. Run migration manually via phpMyAdmin
2. Verify table created successfully
3. Verify indexes created
4. Test inserting sample data
5. Deploy code changes
6. Test feature in production
7. Move migration from "Pending" to "Applied" in PRODUCTION_DB_STEPS.md

**Rollback Plan**:
- If issues occur, drop table: `DROP TABLE IF EXISTS vehicle_monthly_targets;`
- Remove link from Hope Window
- Revert code changes
- No data loss (new feature, no existing data)

### Performance Considerations

**Query Optimization**:
- Use indexes on frequently queried columns
- Limit result set with pagination (50 vehicles per page)
- Cache period calculations (don't recalculate per row)
- Use single query for all vehicles instead of N+1 queries

**Page Load Time**:
- Target < 2 seconds for 50 vehicles
- Use EXPLAIN to analyze query performance
- Consider adding LIMIT/OFFSET for pagination
- Lazy load modals (don't render until opened)

**Bulk Operations**:
- Use single transaction for all saves
- Batch INSERT statements where possible
- Show progress indicator for large operations
- Limit bulk operations to reasonable size (< 100 vehicles)

## Dependencies

### Required Tables

- `vehicles` - Must exist with columns: id, brand, model, license_plate, status, daily_rate
- `reservations` - Must exist with column: vehicle_id
- `ledger_entries` - Must exist with columns: txn_type, source_type, source_id, amount, posted_at
- `staff` - Must exist with column: id (for created_by foreign key)

### Required Functions

- `period_from_my()` - From Hope Window/Targets
- `period_for_today()` - From Hope Window/Targets
- `ledger_kpi_exclusion_clause()` - From includes/ledger_helpers.php
- `auth_check()` - From config/db.php
- `auth_has_perm()` - From config/db.php
- `current_user()` - From config/db.php

### Required Permissions

- `view_finances` - Permission to view financial data
- Admin role - Permission to edit targets

### Migration Dependencies

- `2026-03-25_vehicle_sold_status.sql` - Must be applied first (adds 'sold' status)
- Vehicles table must exist
- Staff table must exist

## Future Enhancements

**Out of Scope for Initial Release**:
- Daily target breakdown per vehicle (only monthly targets in v1)
- Historical trend charts and graphs
- Automated target suggestions based on past performance
- Email notifications for target achievements
- Export to Excel/PDF
- Vehicle grouping or categories
- Target approval workflow
- Comparison with previous periods
- Forecasting and predictions
- Mobile app integration

**Potential Future Features**:
- Dashboard widget showing top/bottom performing vehicles
- Alerts when vehicle falls below target threshold
- Integration with maintenance schedule (adjust targets for maintenance periods)
- Seasonal target adjustments
- Multi-period target planning
- Target templates (copy from previous period)
- Vehicle performance reports
- Commission calculations based on vehicle targets

