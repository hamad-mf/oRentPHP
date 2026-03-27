# Design Document: Hope Window Expected Breakdown

## Overview

This feature adds a detailed breakdown section to the Hope Window interface that displays the individual components contributing to each day's expected income. Currently, the Hope Window shows only the total expected income amount, making it difficult for users to understand or verify the calculation. The breakdown will show:

- Booking events (advance + prepaid delivery charge collected when reservation is created)
- Delivery events (payment due when vehicle is delivered)
- Return events (payment due when vehicle is returned)
- Extension payments (collected when reservation is extended)
- Custom predictions (manually entered expected income)

The breakdown will be visible in Day View by default and expandable in List View via row clicks. This transparency helps financial managers verify projections, identify revenue sources, and understand daily income composition.

## Architecture

### High-Level Design

The breakdown feature extends the existing Hope Window implementation without modifying the core calculation logic. The architecture follows these principles:

1. **Data Retrieval Layer**: New SQL queries fetch detailed reservation and extension data with client information
2. **Calculation Layer**: Reuse existing `hope_calc_delivery_due()` and `hope_calc_return_due()` functions
3. **Presentation Layer**: New UI components display breakdown items in both List and Day views
4. **Progressive Enhancement**: Feature degrades gracefully if `reservation_extensions` table doesn't exist

### Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│  1. Query reservations with client names (JOIN clients)     │
│  2. Query extensions with client names (if table exists)    │
│  3. Query custom predictions                                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Build breakdown arrays per date:                           │
│  - booking_events[]                                         │
│  - delivery_events[]                                        │
│  - return_events[]                                          │
│  - extension_events[]                                       │
│  - prediction_events[]                                      │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  Render breakdown in UI:                                    │
│  - Day View: Always visible                                 │
│  - List View: Expandable per row                            │
└─────────────────────────────────────────────────────────────┘
```

### Integration Points

- **Existing Calculation Functions**: `hope_calc_delivery_due()`, `hope_calc_return_due()`
- **Existing Data Structures**: `$days` array, `$dayByDate` map
- **Existing UI Components**: Day View cards, List View rows
- **Database Tables**: `reservations`, `clients`, `reservation_extensions`, `hope_daily_predictions`

## Components and Interfaces

### 1. Data Retrieval Functions

#### `hope_fetch_breakdown_data(PDO $pdo, string $rangeStart, string $rangeEnd): array`

Fetches all data needed for breakdown display.

**Parameters:**
- `$pdo`: Database connection
- `$rangeStart`: Start date (Y-m-d format)
- `$rangeEnd`: End date (Y-m-d format)

**Returns:**
```php
[
    'reservations' => [
        [
            'id' => int,
            'client_id' => int,
            'client_name' => string,
            'status' => string,
            'created_at' => string,
            'start_date' => string,
            'end_date' => string,
            // ... all reservation fields for calculations
        ],
        // ...
    ],
    'extensions' => [
        [
            'id' => int,
            'reservation_id' => int,
            'client_name' => string,
            'amount' => float,
            'created_at' => string,
        ],
        // ...
    ],
    'predictions' => [
        [
            'target_date' => string,
            'label' => string,
            'amount' => float,
        ],
        // ...
    ],
]
```

### 2. Breakdown Builder Functions

#### `hope_build_breakdown_map(array $data, string $rangeStart, string $rangeEnd): array`

Builds a map of breakdown items organized by date.

**Parameters:**
- `$data`: Output from `hope_fetch_breakdown_data()`
- `$rangeStart`: Start date for filtering
- `$rangeEnd`: End date for filtering

**Returns:**
```php
[
    '2026-03-15' => [
        'booking' => [
            ['res_id' => 123, 'client_name' => 'John Doe', 'amount' => 5000.00],
            // ...
        ],
        'delivery' => [
            ['res_id' => 120, 'client_name' => 'Jane Smith', 'amount' => 15000.00],
            // ...
        ],
        'return' => [
            ['res_id' => 118, 'client_name' => 'Bob Wilson', 'amount' => 2500.00],
            // ...
        ],
        'extension' => [
            ['ext_id' => 45, 'res_id' => 119, 'client_name' => 'Alice Brown', 'amount' => 3000.00],
            // ...
        ],
        'prediction' => [
            ['label' => 'Tesla Model 3 booking', 'amount' => 10000.00],
            // ...
        ],
        'total' => 35500.00,
    ],
    // ... other dates
]
```

### 3. UI Rendering Functions

#### `hope_render_breakdown(array $breakdown, string $date): string`

Renders the breakdown HTML for a specific date.

**Parameters:**
- `$breakdown`: Breakdown map from `hope_build_breakdown_map()`
- `$date`: Date to render (Y-m-d format)

**Returns:** HTML string containing the breakdown section

### 4. Currency Formatting

#### `hope_format_currency(float $amount): string`

Formats currency values consistently.

**Parameters:**
- `$amount`: Numeric amount

**Returns:** Formatted string (e.g., "$1,234.56")

## Data Models

### Breakdown Item Structure

Each breakdown item follows a consistent structure:

```php
[
    'type' => 'booking' | 'delivery' | 'return' | 'extension' | 'prediction',
    'label' => string,  // Display label
    'amount' => float,  // Amount in currency
    'res_id' => ?int,   // Reservation ID (null for predictions)
    'ext_id' => ?int,   // Extension ID (only for extensions)
    'client_name' => ?string,  // Client name (null for predictions)
]
```

### Database Queries

#### Reservations with Client Names

```sql
SELECT 
    r.id, r.status, r.created_at, r.start_date, r.end_date,
    r.total_price, r.extension_paid_amount, r.voucher_applied, r.advance_paid,
    r.delivery_charge, r.delivery_manual_amount, r.delivery_charge_prepaid,
    r.delivery_discount_type, r.delivery_discount_value,
    r.return_voucher_applied, r.overdue_amount, r.km_overage_charge, 
    r.damage_charge, r.additional_charge, r.chellan_amount, 
    r.discount_type, r.discount_value,
    r.client_id,
    COALESCE(c.name, 'Unknown Client') AS client_name
FROM reservations r
LEFT JOIN clients c ON r.client_id = c.id
WHERE r.status <> 'cancelled'
  AND (
      DATE(r.created_at) BETWEEN ? AND ?
      OR DATE(r.start_date) BETWEEN ? AND ?
      OR DATE(r.end_date) BETWEEN ? AND ?
  )
ORDER BY r.created_at
```

#### Extensions with Client Names

```sql
SELECT 
    e.id, e.reservation_id, e.amount, e.created_at,
    COALESCE(c.name, 'Unknown Client') AS client_name
FROM reservation_extensions e
INNER JOIN reservations r ON e.reservation_id = r.id
LEFT JOIN clients c ON r.client_id = c.id
WHERE r.status <> 'cancelled'
  AND DATE(e.created_at) BETWEEN ? AND ?
ORDER BY e.created_at
```

#### Custom Predictions

```sql
SELECT target_date, label, amount
FROM hope_daily_predictions
WHERE target_date BETWEEN ? AND ?
ORDER BY target_date, id
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Events appear on correct dates

*For any* reservation with a booking date, start date, or end date within the viewed range, the breakdown should display booking events on the booking date (created_at), delivery events on the start_date, and return events on the end_date.

**Validates: Requirements 1.1, 2.1, 3.1**

### Property 2: Extension events appear on creation date

*For any* extension with a created_at date within the viewed range, the breakdown should display the extension event on the date the extension was created.

**Validates: Requirements 4.1**

### Property 3: Custom predictions appear on target date

*For any* custom prediction with a target_date within the viewed range, the breakdown should display the prediction on its target date.

**Validates: Requirements 5.1**

### Property 4: Zero-amount items are excluded

*For any* breakdown date, all displayed items (booking, delivery, return, extension, prediction) should have amounts greater than zero.

**Validates: Requirements 1.2, 2.2, 3.2, 4.2, 5.2**

### Property 5: Breakdown total matches expected income

*For any* date in the breakdown, the sum of all breakdown items (booking + delivery + return + extension + prediction amounts) should equal the Expected_Income amount displayed for that date.

**Validates: Requirements 6.1, 6.3**

### Property 6: Cancelled reservations are excluded

*For any* reservation with status 'cancelled', no booking, delivery, or return events for that reservation should appear in the breakdown, and no extensions associated with that reservation should appear.

**Validates: Requirements 9.1, 9.2**

### Property 7: Client names are displayed correctly

*For any* reservation event in the breakdown, if the reservation has a valid client_id that exists in the clients table, the breakdown should display the client's name; otherwise it should display "Unknown Client".

**Validates: Requirements 10.1, 10.2**

### Property 8: Items are sorted by amount descending

*For any* date's breakdown in Day View, all breakdown items should be sorted by amount in descending order (highest amount first).

**Validates: Requirements 7.3**

### Property 9: Currency formatting is consistent

*For any* amount displayed in the breakdown, the formatted string should include a currency symbol ($), exactly two decimal places, and thousand separators (commas) for amounts >= 1000.

**Validates: Requirements 11.1, 11.2, 11.3**

### Property 10: Breakdown format strings are correct

*For any* breakdown item, the formatted label should match the expected pattern for its type:
- Booking: "Res #{id} - {client_name} - Booking: ${amount}"
- Delivery: "Res #{id} - {client_name} - Delivery: ${amount}"
- Return: "Res #{id} - {client_name} - Return: ${amount}"
- Extension: "Extension #{ext_id} (Res #{res_id} - {client_name}): ${amount}"
- Prediction: "Custom: {description} - ${amount}"
- Total: "Total Expected: ${sum}"

**Validates: Requirements 1.3, 2.3, 3.3, 4.3, 5.3, 6.2**

### Property 11: Delivery amount calculation is correct

*For any* reservation with a delivery event, the amount displayed in the breakdown should equal the result of calling `hope_calc_delivery_due()` with that reservation's data.

**Validates: Requirements 2.1**

### Property 12: Return amount calculation is correct

*For any* reservation with a return event, the amount displayed in the breakdown should equal the result of calling `hope_calc_return_due()` with that reservation's data.

**Validates: Requirements 3.1**

## Error Handling

### Missing Data Scenarios

1. **Missing Client**: If a reservation's client_id is NULL or references a non-existent client, display "Unknown Client"
2. **Missing Extension Table**: Check for table existence before querying; if missing, skip extension events without error
3. **Missing Prediction Table**: Already handled by existing code; if missing, skip prediction events

### Invalid Data Scenarios

1. **Negative Amounts**: All amount calculations use `max(0, ...)` to prevent negative values
2. **Invalid Dates**: Date filtering ensures only dates within the viewed range are processed
3. **Cancelled Reservations**: Explicitly filtered out in SQL queries using `WHERE status <> 'cancelled'`

### Database Errors

1. **Query Failures**: Wrap extension table queries in try-catch blocks to handle missing table gracefully
2. **Connection Issues**: Rely on existing PDO error handling in hope_window.php
3. **Foreign Key Violations**: Use LEFT JOIN for clients to handle missing relationships

### UI Error States

1. **Empty Breakdown**: Display "No expected income sources for this date" when breakdown is empty
2. **Calculation Mismatch**: Log warning if breakdown total doesn't match expected income (should never happen if properties hold)

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests to ensure comprehensive coverage:

- **Unit tests**: Verify specific examples, edge cases, and error conditions
- **Property tests**: Verify universal properties across all inputs
- Both approaches are complementary and necessary for complete validation

### Unit Testing Focus

Unit tests should cover:

1. **Specific Examples**:
   - A reservation with booking, delivery, and return events on different dates
   - An extension payment appearing on the extension creation date
   - A custom prediction appearing on its target date
   - Multiple events on the same date summing correctly

2. **Edge Cases**:
   - Reservation with zero advance_paid and zero delivery_charge_prepaid (no booking event)
   - Reservation where hope_calc_delivery_due() returns 0 (no delivery event)
   - Reservation where hope_calc_return_due() returns 0 (no return event)
   - Extension with zero amount (excluded from breakdown)
   - Client with NULL or missing client_id (displays "Unknown Client")
   - Date with no events (empty breakdown)

3. **Error Conditions**:
   - Missing reservation_extensions table (graceful degradation)
   - Cancelled reservation (excluded from breakdown)
   - Extension linked to cancelled reservation (excluded)

### Property-Based Testing Configuration

**Library**: Use PHPUnit with a property-based testing extension (e.g., Eris or php-quickcheck)

**Configuration**:
- Minimum 100 iterations per property test
- Each test must reference its design document property
- Tag format: **Feature: hope-window-expected-breakdown, Property {number}: {property_text}**

**Property Test Coverage**:

1. **Property 1-3**: Generate random reservations, extensions, and predictions; verify they appear on correct dates
2. **Property 4**: Generate random events with various amounts including zero; verify zero amounts are excluded
3. **Property 5**: Generate random breakdown data; verify sum matches expected income
4. **Property 6**: Generate reservations with various statuses; verify cancelled ones are excluded
5. **Property 7**: Generate reservations with valid and invalid client_ids; verify client name handling
6. **Property 8**: Generate random breakdown items; verify sorting by amount descending
7. **Property 9**: Generate random amounts; verify currency formatting
8. **Property 10**: Generate random events; verify format strings match patterns
9. **Property 11-12**: Generate random reservations; verify calculation functions are called correctly

### Test Data Generators

Property tests require generators for:

- **Reservations**: Random dates, amounts, statuses, client_ids
- **Extensions**: Random amounts, dates, reservation associations
- **Predictions**: Random labels, amounts, dates
- **Clients**: Random names, ids
- **Date Ranges**: Random start/end dates within valid bounds

### Integration Testing

1. **Database Integration**: Test with actual database schema including all tables
2. **UI Integration**: Test that breakdown renders correctly in both Day and List views
3. **Calculation Integration**: Verify breakdown uses existing `hope_calc_delivery_due()` and `hope_calc_return_due()` functions

### Manual Testing Checklist

1. View Day View and verify breakdown is visible by default
2. View List View and verify breakdown is hidden by default
3. Click a row in List View and verify breakdown expands
4. Click an expanded row and verify breakdown collapses
5. Verify only one row can be expanded at a time in List View
6. Verify breakdown items are sorted by amount descending
7. Verify currency formatting includes $, 2 decimals, and thousand separators
8. Verify cancelled reservations don't appear in breakdown
9. Verify breakdown total matches expected income total
10. Test with missing reservation_extensions table (graceful degradation)

