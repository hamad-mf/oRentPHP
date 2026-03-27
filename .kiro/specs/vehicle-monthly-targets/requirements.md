# Vehicle Monthly Targets - Requirements

## Feature Overview
A dedicated screen for managing and tracking monthly income targets at the vehicle level. Allows setting individual targets for each vehicle in the fleet, with bulk operations for efficiency, and displays achievement tracking against those targets.

## Business Context
Currently, the system tracks overall monthly targets (Targets page) and daily expected income (Hope Window), but there's no way to break down targets by vehicle. This feature enables fleet performance tracking at the vehicle level, helping identify which vehicles are meeting their income goals and which need attention.

## User Stories

### US-1: Access Vehicle Targets Screen
**As an** admin user  
**I want to** access a dedicated Vehicle Targets screen from Hope Window  
**So that** I can manage and track income targets for each vehicle

**Acceptance Criteria:**
- A new card or button is added to Hope Window page (after "Total Expected Income" card or in a suitable location)
- Card/button is labeled clearly (e.g., "Vehicle Targets" or "Vehicle Breakdown")
- Clicking the card/button navigates to the Vehicle Targets screen
- Navigation preserves the selected period (month/year) from Hope Window
- Only users with appropriate permissions can access this screen

### US-2: View Vehicle List with Targets
**As an** admin user  
**I want to** see all vehicles with their monthly targets and achievements  
**So that** I can track which vehicles are meeting their income goals

**Acceptance Criteria:**
- Screen displays all vehicles in the fleet (excluding sold vehicles)
- For each vehicle, display:
  - Vehicle name/identifier (brand + model + registration)
  - Monthly target amount for the selected period
  - Actual income achieved for the selected period
  - Balance remaining to achieve target (target - achieved)
  - Achievement percentage (achieved / target * 100)
- Visual indicators show performance status:
  - Green/positive indicator when target is met or exceeded
  - Yellow/warning indicator when partially achieved (e.g., 50-99%)
  - Red/negative indicator when significantly behind target (e.g., <50%)
- Total row at bottom shows:
  - Sum of all vehicle targets
  - Sum of all actual income
  - Overall balance
  - Overall achievement percentage
- Empty state message when no vehicles exist

### US-3: Period Selection (15th to 14th)
**As an** admin user  
**I want to** select different monthly periods to view vehicle targets  
**So that** I can track performance across different months

**Acceptance Criteria:**
- Period follows the same 15th-to-14th logic as Hope Window, Targets, and Payroll
- Month/year dropdown selector at top of page
- Dropdown shows period labels like "15 Mar – 14 Apr 2026" (not just "March")
- Default period is the current active period (based on today's date)
- Changing period reloads the page with updated data
- Period range is displayed prominently (e.g., "15 Mar 2026 – 14 Apr 2026")
- Number of days in period is shown (e.g., "31 days")

### US-4: Set Individual Vehicle Target
**As an** admin user  
**I want to** set or edit the monthly target for a specific vehicle  
**So that** I can allocate income goals to individual vehicles

**Acceptance Criteria:**
- Each vehicle row has an "Edit Target" button or inline edit capability
- Clicking edit opens a modal or inline form
- Form shows:
  - Vehicle name (read-only)
  - Period (read-only)
  - Current target amount (if exists)
  - Input field for new target amount (decimal, min 0)
  - Optional notes field
- Save button commits the change
- Cancel button discards changes
- Success message confirms target was saved
- Error message if save fails
- Target is stored per vehicle per period (unique constraint)
- Saving a target of 0 or empty removes the target for that vehicle/period

### US-5: Bulk Set Targets for All Vehicles
**As an** admin user  
**I want to** set targets for all vehicles at once  
**So that** I can quickly allocate targets across the entire fleet

**Acceptance Criteria:**
- "Set All Targets" button at top of page
- Clicking opens a modal with:
  - Option 1: Set same amount for all vehicles
    - Single input field for target amount
    - Applies this amount to every vehicle
  - Option 2: Distribute total target equally
    - Input field for total target amount
    - Calculates per-vehicle amount (total / number of vehicles)
    - Shows calculated per-vehicle amount before saving
  - Option 3: Set targets proportionally based on vehicle rates
    - Input field for total target amount
    - Distributes based on each vehicle's daily rate (higher rate = higher target)
    - Shows calculated per-vehicle amounts before saving
- Preview section shows how targets will be distributed before confirming
- "Apply" button saves all targets
- "Cancel" button discards changes
- Success message confirms how many targets were set
- Existing targets are overwritten by bulk operation

### US-6: Bulk Set Targets for Selected Vehicles
**As an** admin user  
**I want to** set targets for multiple selected vehicles  
**So that** I can manage targets for a subset of the fleet efficiently

**Acceptance Criteria:**
- Checkbox next to each vehicle row for selection
- "Select All" checkbox in table header
- Selected count indicator (e.g., "3 vehicles selected")
- "Set Targets for Selected" button (enabled only when vehicles are selected)
- Clicking opens a modal with same options as US-5 but applies only to selected vehicles
- Preview shows only selected vehicles
- Deselecting all vehicles disables the button
- After saving, selections are cleared

### US-7: Calculate Actual Income per Vehicle
**As an** admin user  
**I want to** see actual income achieved by each vehicle for the period  
**So that** I can compare performance against targets

**Acceptance Criteria:**
- Actual income is calculated from ledger entries linked to reservations for each vehicle
- Income sources included:
  - Advance payments (booking income)
  - Delivery payments
  - Return payments (overdue, damages, km overage, etc.)
  - Extension payments
- Income is attributed to the vehicle via reservation.vehicle_id
- Only income within the selected period date range is counted
- Voided ledger entries are excluded
- Security deposit entries are excluded (not income)
- Calculation matches the logic used in Hope Window and Targets page

### US-8: Target Validation and Business Rules
**As an** admin user  
**I want** the system to validate target inputs  
**So that** data integrity is maintained

**Acceptance Criteria:**
- Target amount must be >= 0
- Target amount must be a valid decimal number (max 2 decimal places)
- Cannot set target for a vehicle that doesn't exist
- Cannot set target for a sold vehicle
- Period dates must be valid (start < end)
- Duplicate targets for same vehicle + period are prevented (unique constraint)
- Total of all vehicle targets is informational only (no validation that it must equal a specific amount)

### US-9: Permission Control
**As a** system administrator  
**I want** to control who can access and modify vehicle targets  
**So that** only authorized users can manage this sensitive data

**Acceptance Criteria:**
- Only users with `view_finances` permission OR admin role can access the Vehicle Targets screen
- Only admin users can edit/set targets
- Non-admin users with `view_finances` can view targets but cannot edit
- Attempting to access without permission redirects to dashboard with error message
- Edit/save buttons are hidden for non-admin users

### US-10: Navigation and Integration
**As an** admin user  
**I want** seamless navigation between related screens  
**So that** I can efficiently manage financial targets

**Acceptance Criteria:**
- Link from Hope Window to Vehicle Targets screen
- Breadcrumb or back button to return to Hope Window
- Period selection is preserved when navigating between screens
- Vehicle Targets screen follows the same UI/UX patterns as Hope Window and Targets pages
- Consistent styling, colors, and layout with existing financial screens

## Functional Requirements

### FR-1: Database Schema
- New table: `vehicle_monthly_targets`
  - `id` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `vehicle_id` (INT, NOT NULL, FOREIGN KEY to vehicles.id)
  - `period_start` (DATE, NOT NULL) - 15th of the month
  - `period_end` (DATE, NOT NULL) - 14th of next month
  - `target_amount` (DECIMAL(12,2), NOT NULL, DEFAULT 0.00)
  - `notes` (TEXT, NULL) - optional notes
  - `created_by` (INT, NULL, FOREIGN KEY to staff.id)
  - `created_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP)
  - `updated_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
  - UNIQUE KEY `uq_vehicle_period` (vehicle_id, period_start)

### FR-2: Period Calculation
- Use same period logic as Hope Window, Targets, and Payroll
- Period runs from 15th of month M to 14th of month M+1
- Function: `period_from_my(int $m, int $y): array` returns ['start' => 'YYYY-MM-15', 'end' => 'YYYY-MM-14']
- Function: `period_for_today(): array` returns current active period based on today's date
- If today's day >= 15, current period starts this month
- If today's day < 15, current period started last month

### FR-3: Income Attribution
- Income is attributed to vehicles via `reservations.vehicle_id`
- Ledger entries link to reservations via `source_type='reservation'` and `source_id`
- Query joins: `ledger_entries` → `reservations` → `vehicles`
- Filter by `ledger_entries.txn_type = 'income'`
- Filter by `DATE(ledger_entries.posted_at) BETWEEN period_start AND period_end`
- Exclude voided entries: `ledger_entries.is_voided = 0` or `ledger_entries.voided_at IS NULL`
- Exclude security deposit entries using `ledger_kpi_exclusion_clause()` helper

### FR-4: Target Management Operations
- **Create/Update Target**: INSERT ... ON DUPLICATE KEY UPDATE
- **Delete Target**: DELETE WHERE vehicle_id = ? AND period_start = ?
- **Bulk Create**: Loop through vehicles and INSERT/UPDATE for each
- **Get Target**: SELECT WHERE vehicle_id = ? AND period_start = ?
- **Get All Targets for Period**: SELECT WHERE period_start = ?

### FR-5: Calculations
- **Achievement Amount**: SUM of actual income for vehicle in period
- **Balance**: target_amount - achievement_amount
- **Achievement Percentage**: (achievement_amount / target_amount) * 100 (handle division by zero)
- **Total Target**: SUM of all vehicle targets for period
- **Total Achievement**: SUM of all vehicle achievements for period
- **Overall Balance**: total_target - total_achievement
- **Overall Percentage**: (total_achievement / total_target) * 100

## Non-Functional Requirements

### NFR-1: Performance
- Page load time < 2 seconds for up to 50 vehicles
- Bulk operations complete within 5 seconds for up to 50 vehicles
- Database queries use proper indexes (vehicle_id, period_start)

### NFR-2: Usability
- UI follows existing design patterns from Hope Window and Targets pages
- Responsive design works on desktop and tablet (mobile optional)
- Clear visual feedback for all actions (loading states, success/error messages)
- Intuitive bulk operations with preview before committing

### NFR-3: Data Integrity
- Foreign key constraints ensure referential integrity
- Unique constraint prevents duplicate targets
- Transactions ensure atomic bulk operations
- Validation prevents invalid data entry

### NFR-4: Security
- Permission checks on every page load and form submission
- SQL injection prevention via prepared statements
- CSRF protection on all forms
- Input sanitization and validation

## Out of Scope
- Daily target breakdown per vehicle (only monthly targets)
- Historical trend analysis or charts
- Automated target suggestions based on past performance
- Email notifications for target achievements
- Export to Excel/PDF (can be added later)
- Vehicle grouping or categories
- Target approval workflow

## Success Criteria
1. Admin can set monthly targets for all vehicles individually or in bulk
2. System accurately calculates and displays actual income per vehicle
3. Achievement tracking shows clear visual indicators of performance
4. Period selection works consistently with other financial screens
5. All operations complete without errors or data loss
6. UI is intuitive and requires minimal training

## Dependencies
- Existing `vehicles` table with active vehicles
- Existing `reservations` table with `vehicle_id` column
- Existing `ledger_entries` table with income tracking
- Existing period calculation functions from Hope Window/Targets
- Existing permission system (`view_finances`, admin role)
- Existing `ledger_kpi_exclusion_clause()` helper function

## Assumptions
- Vehicles table has an `id` column as primary key
- Reservations table has a `vehicle_id` column linking to vehicles
- Ledger entries are properly linked to reservations
- Period logic (15th-14th) is already implemented and tested
- Users understand the 15th-14th period concept from other screens
- Only active vehicles (not sold) should have targets

## Risks and Mitigations
| Risk | Impact | Mitigation |
|------|--------|------------|
| Income attribution logic differs from Hope Window | High | Use exact same calculation logic and helper functions |
| Performance issues with many vehicles | Medium | Add database indexes, optimize queries, implement pagination if needed |
| Users confused by bulk operations | Medium | Provide clear preview before applying, add help text |
| Targets don't sum to expected total | Low | Make it clear that vehicle targets are independent, no validation required |
| Sold vehicles appear in list | Low | Filter out sold vehicles in query |

## Open Questions
1. Should sold vehicles be shown in the list (read-only) or completely hidden? **Decision: Hidden**
2. Should there be a warning if total vehicle targets don't match Hope Window expected income? **Decision: No, they are independent**
3. Should we allow negative targets? **Decision: No, minimum 0**
4. Should we track who last modified each target? **Decision: Yes, via created_by and updated_at**
5. Should we allow setting targets for future periods? **Decision: Yes, no restriction**
6. Should we show vehicles with no reservations in the period? **Decision: Yes, show all active vehicles**

## Correctness Properties

### Property 1: Target Uniqueness
**For all** vehicle V and period P, **there exists at most one** target record in `vehicle_monthly_targets` where `vehicle_id = V.id` AND `period_start = P.start`.

**Test Strategy**: Attempt to insert duplicate targets and verify unique constraint violation.

### Property 2: Income Attribution Accuracy
**For all** vehicles V and period P, **the sum of** actual income displayed **equals** the sum of ledger entries where:
- `txn_type = 'income'`
- `source_type = 'reservation'`
- `reservation.vehicle_id = V.id`
- `DATE(posted_at) BETWEEN P.start AND P.end`
- Entry is not voided
- Entry is not a security deposit

**Test Strategy**: Create test reservations with known income amounts, verify calculated totals match expected values.

### Property 3: Period Consistency
**For all** periods P displayed in Vehicle Targets, **the period dates** (start, end) **must match exactly** the period dates calculated by the same logic used in Hope Window, Targets, and Payroll screens.

**Test Strategy**: Compare period calculations across all screens for the same month/year input.

### Property 4: Bulk Operation Atomicity
**For all** bulk target operations, **either all** vehicle targets are saved successfully **or none** are saved (atomic transaction).

**Test Strategy**: Simulate database errors mid-operation, verify no partial saves occur.

### Property 5: Permission Enforcement
**For all** users U without `view_finances` permission or admin role, **access to** Vehicle Targets screen **is denied** with appropriate error message.

**Test Strategy**: Test access with various user roles and permission combinations.

### Property 6: Non-Negative Targets
**For all** target records T in `vehicle_monthly_targets`, **the value of** `T.target_amount` **is greater than or equal to** 0.

**Test Strategy**: Attempt to save negative targets, verify validation prevents it.

### Property 7: Total Calculation Accuracy
**For all** periods P, **the total target displayed** equals the sum of all individual vehicle targets for period P, **and the total achievement displayed** equals the sum of all individual vehicle achievements for period P.

**Test Strategy**: Set known targets, generate known income, verify totals match manual calculations.
