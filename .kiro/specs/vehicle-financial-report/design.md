# Design Document: Vehicle Financial Report

## Overview

The Vehicle Financial Report provides a comprehensive view of income and expenses for each vehicle in the fleet. This feature enables management to track vehicle-level profitability, identify high-performing and underperforming vehicles, and drill down into specific transactions.

The report follows the existing 15th-to-14th monthly period convention used throughout the system and reuses the existing ledger infrastructure. The design emphasizes consistency with the existing Reports screen (reports/index.php) in terms of UI patterns, period selection, and drill-down functionality.

### Key Features

- Monthly period selection (15th to 14th) with year selector
- Summary cards showing total income, expense, and net balance across all vehicles
- Vehicle-level breakdown table with income, expense, and balance per vehicle
- Clickable amounts that open drill-down panels with detailed transaction lists
- Search and filter capabilities within drill-down panels
- Permission-based access control (view_finances or admin role)
- Responsive design matching the existing dark theme

## Architecture

### System Components

The Vehicle Financial Report is implemented as a standalone PHP page that integrates with the existing ledger system. The architecture follows the established patterns in the codebase:

```
┌─────────────────────────────────────────────────────────────┐
│                    reports/index.php                        │
│                  (Existing Reports Screen)                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ Navigation Link
                         ▼
┌─────────────────────────────────────────────────────────────┐
│              reports/vehicle_financial.php                  │
│                (New Vehicle Financial Report)               │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Period Selection (15th-14th)                       │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Summary Cards                                       │  │
│  │  • Total Income  • Total Expense  • Net Balance     │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │  Vehicle Breakdown Table                            │  │
│  │  Vehicle | Income | Expense | Balance               │  │
│  │  (Clickable amounts open drill-down panel)          │  │
│  └─────────────────────────────────────────────────────┘  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ Queries
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   Ledger System                             │
│  • ledger_entries table                                     │
│  • reservations table (for vehicle linkage)                 │
│  • vehicles table                                           │
│  • clients table (for drill-down context)                   │
│  • ledger_helpers.php (helper functions)                    │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. User selects period (month/year) via dropdown selectors
2. System calculates period start (15th) and end (14th next month)
3. System queries ledger_entries joined with reservations to get vehicle-linked transactions
4. System aggregates income and expense by vehicle_id
5. System renders summary cards and vehicle table
6. User clicks on income/expense amount
7. System opens drill-down panel with filtered transaction details
8. User can search/filter within the panel

### Technology Stack

- PHP 7.4+ (server-side logic)
- MySQL/MariaDB (data storage)
- Tailwind CSS (styling, matching existing dark theme)
- Vanilla JavaScript (client-side interactivity)
- PDO (database access)

## Components and Interfaces

### 1. Main Report Page (reports/vehicle_financial.php)

**Purpose**: Display vehicle-level financial data for a selected period

**Inputs**:
- GET parameter `m` (month, 1-12, optional)
- GET parameter `y` (year, 2024-current, optional)

**Outputs**:
- HTML page with summary cards, vehicle table, and drill-down panel

**Key Functions**:

```php
// Period calculation (reuse from reports/index.php)
function period_from_my(int $m, int $y): array
function period_for_today(): array

// Vehicle income calculation
function vfr_calculate_vehicle_income(PDO $pdo, string $periodStart, string $periodEnd): array

// Vehicle expense calculation
function vfr_calculate_vehicle_expenses(PDO $pdo, string $periodStart, string $periodEnd): array

// Get vehicle list (excluding sold vehicles)
function vfr_get_active_vehicles(PDO $pdo): array

// Get detailed entries for drill-down
function vfr_get_vehicle_income_details(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd): array
function vfr_get_vehicle_expense_details(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd): array
```

### 2. Period Selection Component

**Purpose**: Allow users to select month and year for the report

**Implementation**: Reuse the exact pattern from reports/index.php
- Month dropdown: Shows "15 MMM – 14 MMM" format
- Year dropdown: Shows years from 2024 to current year
- Auto-submit on change

### 3. Summary Cards Component

**Purpose**: Display aggregated totals across all vehicles

**Data Structure**:
```php
[
    'total_income' => float,    // Sum of all vehicle income
    'total_expense' => float,   // Sum of all vehicle expenses
    'net_balance' => float      // total_income - total_expense
]
```

**Styling**:
- Total Income: Green border, green text
- Total Expense: Red border, red text
- Net Balance: Green (positive) or red (negative) border and text

### 4. Vehicle Breakdown Table

**Purpose**: Display income, expense, and balance for each vehicle

**Data Structure**:
```php
[
    [
        'vehicle_id' => int,
        'brand' => string,
        'model' => string,
        'license_plate' => string,
        'income' => float,
        'expense' => float,
        'balance' => float  // income - expense
    ],
    // ... more vehicles
]
```

**Table Columns**:
- Vehicle Name: "{brand} {model} · {license_plate}"
- Income: Clickable if > 0, green text
- Expense: Clickable if > 0, red text
- Balance: Green (positive) or red (negative) with +/- prefix

### 5. Drill-Down Panel Component

**Purpose**: Show detailed transaction list for a specific vehicle and transaction type

**Implementation**: Reuse the slide-out panel pattern from reports/index.php

**Data Structure for Income Entries**:
```php
[
    'date' => string,           // DD/MM/YYYY
    'time' => string,           // HH:MM
    'amount' => float,
    'event' => string,          // Human-readable event type
    'client' => string,         // Client name
    'reservation_id' => int,    // Link to reservation
    'payment_mode' => string    // cash, account, credit
]
```

**Data Structure for Expense Entries**:
```php
[
    'date' => string,           // DD/MM/YYYY
    'time' => string,           // HH:MM
    'amount' => float,
    'category' => string,       // Expense category
    'description' => string,    // Expense description
    'payment_mode' => string    // cash, account, credit
]
```

**Features**:
- Search input (filters entries in real-time)
- Entry list (scrollable)
- Footer with total amount
- Close button

### 6. JavaScript Interactivity

**Functions**:

```javascript
// Open drill-down panel for a specific vehicle and type
function openVehiclePanel(vehicleId, vehicleName, mode)

// Close drill-down panel
function closePanel()

// Filter panel entries based on search input
function filterPanel()

// Render panel entries
function renderPanel(entries)

// Escape HTML for safe rendering
function esc(s)
```

**Event Handlers**:
- Click on income/expense amount: Opens panel
- Click on backdrop: Closes panel
- Escape key: Closes panel
- Input in search field: Filters entries

## Data Models

### Ledger Entry (ledger_entries table)

```sql
CREATE TABLE ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    txn_type ENUM('income','expense','adjustment') NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode VARCHAR(20) DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    source_id INT DEFAULT NULL,
    source_event VARCHAR(50) DEFAULT NULL,
    voided_at DATETIME DEFAULT NULL,
    posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_txn_type (txn_type),
    INDEX idx_posted_at (posted_at),
    INDEX idx_source (source_type, source_id)
)
```

### Reservation (reservations table)

```sql
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    status ENUM('pending','confirmed','active','completed') NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    -- ... other fields
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
)
```

### Vehicle (vehicles table)

```sql
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(255) NOT NULL,
    model VARCHAR(255) NOT NULL,
    license_plate VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('available','rented','maintenance') NOT NULL,
    is_sold TINYINT(1) DEFAULT 0,
    -- ... other fields
)
```

### Query Patterns

**Income Calculation per Vehicle**:
```sql
SELECT 
    r.vehicle_id,
    SUM(le.amount) AS total_income
FROM ledger_entries le
INNER JOIN reservations r 
    ON le.source_type = 'reservation' 
    AND le.source_id = r.id
WHERE le.txn_type = 'income'
    AND {ledger_kpi_exclusion_clause}
    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
    AND r.vehicle_id IS NOT NULL
GROUP BY r.vehicle_id
```

**Expense Calculation per Vehicle**:
```sql
SELECT 
    r.vehicle_id,
    SUM(le.amount) AS total_expense
FROM ledger_entries le
INNER JOIN reservations r 
    ON le.source_type = 'reservation' 
    AND le.source_id = r.id
WHERE le.txn_type = 'expense'
    AND {ledger_kpi_exclusion_clause}
    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
    AND r.vehicle_id IS NOT NULL
GROUP BY r.vehicle_id
```

**Income Details for Drill-Down**:
```sql
SELECT 
    le.id, le.amount, le.description, le.category, 
    le.source_event, le.source_id, le.payment_mode, le.posted_at,
    c.name AS client_name,
    r.id AS reservation_id
FROM ledger_entries le
INNER JOIN reservations r 
    ON le.source_type = 'reservation' 
    AND le.source_id = r.id
LEFT JOIN clients c ON r.client_id = c.id
WHERE le.txn_type = 'income'
    AND {ledger_kpi_exclusion_clause}
    AND r.vehicle_id = :vehicle_id
    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
ORDER BY le.posted_at DESC
```

**Expense Details for Drill-Down**:
```sql
SELECT 
    le.id, le.amount, le.description, le.category,
    le.source_event, le.payment_mode, le.posted_at
FROM ledger_entries le
INNER JOIN reservations r 
    ON le.source_type = 'reservation' 
    AND le.source_id = r.id
WHERE le.txn_type = 'expense'
    AND {ledger_kpi_exclusion_clause}
    AND r.vehicle_id = :vehicle_id
    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
ORDER BY le.posted_at DESC
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Period Calculation Consistency

*For any* month (1-12) and year (2024-current), the period calculation should return a start date of the 15th of that month and an end date of the 14th of the next month, with proper year rollover when month is December.

**Validates: Requirements 2.2**

### Property 2: KPI-Compliant Income Aggregation

*For any* vehicle and period, the income calculation should sum only ledger entries where txn_type='income', source_type='reservation', the reservation links to that vehicle, the posted_at date falls within the period, and the entry is not voided and not a security deposit or transfer event.

**Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7**

### Property 3: KPI-Compliant Expense Aggregation

*For any* vehicle and period, the expense calculation should sum only ledger entries where txn_type='expense', source_type='reservation', the reservation links to that vehicle, the posted_at date falls within the period, and the entry is not voided and not a security deposit or transfer event.

**Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8**

### Property 4: Total Income Aggregation

*For any* period, the total income across all vehicles should equal the sum of individual vehicle income amounts for that period.

**Validates: Requirements 3.2**

### Property 5: Total Expense Aggregation

*For any* period, the total expense across all vehicles should equal the sum of individual vehicle expense amounts for that period.

**Validates: Requirements 3.3**

### Property 6: Balance Calculation

*For any* vehicle or aggregate totals, the balance should always equal income minus expense.

**Validates: Requirements 3.4, 4.7**

### Property 7: Sold Vehicle Exclusion

*For any* vehicle where is_sold=1, that vehicle should not appear in the vehicle list on the report.

**Validates: Requirements 4.2, 4.3**

### Property 8: Vehicle Name Formatting

*For any* vehicle, the displayed name should be formatted as "{brand} {model} · {license_plate}" with all three components present.

**Validates: Requirements 4.4**

### Property 9: Positive Balance Formatting

*For any* balance amount greater than zero, the displayed value should include a "+" prefix and green color styling.

**Validates: Requirements 3.7, 4.11**

### Property 10: Negative Balance Formatting

*For any* balance amount less than zero, the displayed value should include a "-" prefix and red color styling, with the absolute value shown.

**Validates: Requirements 3.8, 4.12**

### Property 11: Drill-Down Filtering

*For any* vehicle and period, when opening the drill-down panel for income or expense, only entries linked to that specific vehicle and falling within the period should be displayed.

**Validates: Requirements 7.3, 8.3, 9.2, 10.2**

### Property 12: Date Formatting Consistency

*For any* ledger entry timestamp, the date should be formatted as DD/MM/YYYY in the drill-down panel.

**Validates: Requirements 9.4, 10.4**

### Property 13: Time Formatting Consistency

*For any* ledger entry timestamp, the time should be formatted as HH:MM in 24-hour format in the drill-down panel.

**Validates: Requirements 9.5, 10.5**

### Property 14: Entry Field Completeness

*For any* income entry in the drill-down panel, all required fields (date, time, amount, event type, client name, reservation ID, payment mode) should be present in the rendered output.

**Validates: Requirements 9.3**

### Property 15: Expense Field Completeness

*For any* expense entry in the drill-down panel, all required fields (date, time, amount, category, description, payment mode) should be present in the rendered output.

**Validates: Requirements 10.3**

### Property 16: Event Label Humanization

*For any* source_event code (e.g., 'delivery', 'return', 'advance'), the displayed label should be a human-readable version (e.g., 'Delivery', 'Return', 'Advance Payment').

**Validates: Requirements 9.6**

### Property 17: Reservation Link Generation

*For any* income entry with a reservation ID, the rendered output should include a clickable link to reservations/show.php?id={reservation_id}.

**Validates: Requirements 9.8**

### Property 18: Payment Mode Badge Display

*For any* ledger entry with a payment_mode value, the rendered output should display the mode as a badge element.

**Validates: Requirements 9.9, 10.8**

### Property 19: Chronological Sorting

*For any* list of entries in the drill-down panel, the entries should be sorted by posted_at timestamp in descending order (newest first).

**Validates: Requirements 9.10, 10.9**

### Property 20: Drill-Down Total Calculation

*For any* filtered set of entries in the drill-down panel, the footer total should equal the sum of all displayed entry amounts.

**Validates: Requirements 9.11, 10.10**

### Property 21: Search Filtering

*For any* search query in the drill-down panel, only entries where the query (case-insensitive) matches any of the searchable fields (client name, vehicle details, category, description, date) should be displayed.

**Validates: Requirements 11.2, 11.3, 11.4**

### Property 22: Filtered Total Update

*For any* search filter applied in the drill-down panel, the footer total should update to reflect only the sum of the filtered entries.

**Validates: Requirements 11.6**

### Property 23: Permission-Based Access Control

*For any* user without view_finances permission and non-admin role, attempting to access the Vehicle Financial Report should result in a redirect to index.php with an error message.

**Validates: Requirements 13.1**

### Property 24: Clickable Amount Condition

*For any* vehicle with income or expense greater than zero, the corresponding table cell should be clickable; for amounts equal to zero, the cell should display "$0.00" in gray and not be clickable.

**Validates: Requirements 7.1, 7.4, 8.1, 8.4**

### Property 25: Period Selector Format

*For any* month value (1-12), the period selector dropdown should display the label in format "15 {MonthAbbr} – 14 {NextMonthAbbr}".

**Validates: Requirements 2.5**

## Error Handling

### Permission Errors

**Scenario**: User without proper permissions attempts to access the report

**Handling**:
- Check permissions immediately after auth_check()
- Use auth_has_perm('view_finances') and check for admin role
- If denied, call flash('error', 'Access denied...') and redirect('../index.php')
- Exit script to prevent any data loading

### Database Errors

**Scenario**: Database query fails or connection is lost

**Handling**:
- Wrap database queries in try-catch blocks
- Log errors using app_log('ERROR', ...)
- Display user-friendly error message: "Unable to load financial data. Please try again."
- Gracefully degrade: Show empty state rather than breaking the page

### Invalid Period Parameters

**Scenario**: User provides invalid month or year in URL parameters

**Handling**:
- Validate month is between 1-12
- Validate year is between 2024 and current year
- If invalid, default to current period using period_for_today()
- No error message needed (silent correction)

### No Vehicles Found

**Scenario**: System has no vehicles or all vehicles are sold

**Handling**:
- Display empty state message: "No vehicles found"
- Show summary cards with $0.00 values
- Maintain consistent layout and styling

### No Transactions in Period

**Scenario**: Selected period has no ledger entries

**Handling**:
- Display all vehicles with $0.00 for income, expense, and balance
- Summary cards show $0.00 for all totals
- No error message (valid state)

### Empty Drill-Down Results

**Scenario**: Vehicle has no entries or search returns no results

**Handling**:
- Display "No entries found" message in panel
- Show $0.00 in footer total
- Keep search input and close button functional

### JavaScript Errors

**Scenario**: Client-side JavaScript fails to load or execute

**Handling**:
- Ensure core functionality (viewing data) works without JavaScript
- Drill-down panels won't open, but data is still visible in table
- Use progressive enhancement approach

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests to ensure comprehensive coverage:

- **Unit tests**: Verify specific examples, edge cases, and error conditions
- **Property tests**: Verify universal properties across all inputs

Together, these approaches provide comprehensive coverage where unit tests catch concrete bugs and property tests verify general correctness.

### Unit Testing

**Focus Areas**:
- Specific example cases (e.g., accessing the page with specific month/year parameters)
- Edge cases (e.g., no vehicles, no transactions, zero amounts)
- Integration points (e.g., permission checks, database queries)
- Error conditions (e.g., invalid parameters, database failures)

**Example Unit Tests**:

```php
// Test: Default period selection
test_default_period_is_current_period()

// Test: Navigation link exists
test_reports_screen_has_vehicle_financial_link()

// Test: Permission denial redirects
test_unauthorized_user_redirected()

// Test: Empty vehicle list shows message
test_no_vehicles_shows_empty_state()

// Test: Zero amounts display correctly
test_zero_amounts_display_correctly()

// Test: Drill-down panel opens for non-zero amounts
test_drill_down_opens_for_income()

// Test: Search input exists in panel
test_drill_down_has_search_input()

// Test: Close button dismisses panel
test_close_button_dismisses_panel()

// Test: Uses ledger helper functions
test_uses_ledger_kpi_exclusion_clause()
```

### Property-Based Testing

**Library**: Use PHPUnit with a property-based testing extension (e.g., Eris or php-quickcheck)

**Configuration**: Each property test should run a minimum of 100 iterations to ensure comprehensive input coverage.

**Test Tagging**: Each property test must include a comment referencing the design document property:

```php
/**
 * Feature: vehicle-financial-report, Property 1: Period Calculation Consistency
 */
public function test_period_calculation_consistency()
{
    // Generate random month (1-12) and year (2024-current)
    // Verify period boundaries are correct
    // Verify year rollover for December
}
```

**Property Test Examples**:

```php
/**
 * Feature: vehicle-financial-report, Property 2: KPI-Compliant Income Aggregation
 * Iterations: 100
 */
public function test_kpi_compliant_income_aggregation()
{
    // Generate random vehicle, period, and ledger entries
    // Include voided entries, security deposits, transfers
    // Verify only KPI-compliant entries are summed
}

/**
 * Feature: vehicle-financial-report, Property 6: Balance Calculation
 * Iterations: 100
 */
public function test_balance_equals_income_minus_expense()
{
    // Generate random income and expense amounts
    // Verify balance = income - expense for all cases
}

/**
 * Feature: vehicle-financial-report, Property 7: Sold Vehicle Exclusion
 * Iterations: 100
 */
public function test_sold_vehicles_excluded()
{
    // Generate random vehicles with is_sold=0 and is_sold=1
    // Verify only non-sold vehicles appear in report
}

/**
 * Feature: vehicle-financial-report, Property 9: Positive Balance Formatting
 * Iterations: 100
 */
public function test_positive_balance_has_plus_prefix()
{
    // Generate random positive balance amounts
    // Verify rendered output contains "+" prefix and green styling
}

/**
 * Feature: vehicle-financial-report, Property 11: Drill-Down Filtering
 * Iterations: 100
 */
public function test_drill_down_shows_only_vehicle_entries()
{
    // Generate random entries for multiple vehicles
    // Open drill-down for one vehicle
    // Verify only that vehicle's entries appear
}

/**
 * Feature: vehicle-financial-report, Property 19: Chronological Sorting
 * Iterations: 100
 */
public function test_entries_sorted_newest_first()
{
    // Generate random entries with different timestamps
    // Verify they're sorted by posted_at DESC
}

/**
 * Feature: vehicle-financial-report, Property 21: Search Filtering
 * Iterations: 100
 */
public function test_search_filters_case_insensitive()
{
    // Generate random entries with various field values
    // Generate random search queries with mixed case
    // Verify filtering works correctly and is case-insensitive
}

/**
 * Feature: vehicle-financial-report, Property 23: Permission-Based Access Control
 * Iterations: 100
 */
public function test_unauthorized_access_denied()
{
    // Generate random user permissions (without view_finances and non-admin)
    // Attempt to access the report
    // Verify redirect to index.php occurs
}
```

### Integration Testing

**Database Setup**:
- Use a test database with sample vehicles, reservations, clients, and ledger entries
- Include edge cases: sold vehicles, voided entries, security deposits, transfers
- Test with multiple periods to verify date filtering

**UI Testing**:
- Verify period selector renders correctly
- Verify summary cards display correct totals
- Verify vehicle table renders with correct data
- Verify drill-down panel opens and closes
- Verify search functionality works in panel

**Permission Testing**:
- Test with admin user (should have access)
- Test with user having view_finances permission (should have access)
- Test with user without permission (should be denied)

### Manual Testing Checklist

- [ ] Navigate to Reports screen and verify "Vehicle Financial Report" link exists
- [ ] Click link and verify navigation to vehicle financial report
- [ ] Verify default period is current period
- [ ] Change period and verify data updates
- [ ] Verify summary cards show correct totals
- [ ] Verify vehicle table shows all non-sold vehicles
- [ ] Click income amount and verify drill-down panel opens
- [ ] Verify drill-down shows correct entries for selected vehicle
- [ ] Search in drill-down panel and verify filtering works
- [ ] Verify footer total updates when filtering
- [ ] Close panel and verify it dismisses
- [ ] Click expense amount and verify drill-down panel opens
- [ ] Verify responsive design on mobile device
- [ ] Test with user without permissions and verify access denied
- [ ] Test with no vehicles and verify empty state
- [ ] Test with vehicle having no transactions and verify $0.00 display

### Performance Considerations

**Query Optimization**:
- Use indexed columns (posted_at, source_type, source_id, vehicle_id)
- Limit drill-down queries to single vehicle and period
- Consider caching period calculations

**Expected Load**:
- Typical fleet size: 10-100 vehicles
- Typical transactions per period: 100-1000 entries
- Expected response time: < 2 seconds for report load
- Expected response time: < 500ms for drill-down panel

**Scalability**:
- Current design handles up to 1000 vehicles efficiently
- For larger fleets, consider pagination in vehicle table
- For high transaction volumes, consider server-side drill-down filtering
