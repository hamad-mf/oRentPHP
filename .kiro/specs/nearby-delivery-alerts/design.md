# Design Document: Nearby Delivery Alerts

## Overview

The Nearby Delivery Alerts feature adds proactive dashboard notifications for confirmed reservations approaching their delivery date. The system displays a dedicated alert section on the main dashboard (index.php) showing upcoming deliveries within a configurable threshold, with visual urgency indicators and direct navigation to reservation details.

This feature follows the established pattern of dashboard alerts (held deposits, EMI alerts) and integrates seamlessly with the existing settings management system. The implementation prioritizes performance, graceful degradation, and consistency with the application's visual design language.

## Architecture

### Component Structure

```
┌─────────────────────────────────────────────────────────────┐
│                      Dashboard (index.php)                   │
│  ┌───────────────────────────────────────────────────────┐  │
│  │         Nearby Delivery Alerts Section                │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │  Alert Query Engine                             │  │  │
│  │  │  - Fetch confirmed reservations                 │  │  │
│  │  │  - Filter by threshold                          │  │  │
│  │  │  - Calculate urgency                            │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │  Alert Renderer                                 │  │  │
│  │  │  - Display alert cards                          │  │  │
│  │  │  - Apply urgency styling                        │  │  │
│  │  │  - Handle navigation                            │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              Settings Manager (settings/general.php)         │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Threshold Configuration                              │  │
│  │  - Input field (1-30 days)                            │  │
│  │  - Validation                                         │  │
│  │  - Persistence via settings_helpers.php               │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│              Database (system_settings table)                │
│  - Key: 'upcoming_delivery_alert_days'                      │
│  - Value: Integer (1-30), Default: 3                        │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Dashboard Load**: index.php loads and initializes alert system
2. **Settings Retrieval**: Query system_settings for 'upcoming_delivery_alert_days' (default: 3)
3. **Alert Query**: Fetch confirmed reservations with start_date within threshold
4. **Urgency Calculation**: Determine days until delivery for each reservation
5. **Rendering**: Display alerts with appropriate styling and urgency indicators
6. **Navigation**: Handle clicks to reservations/show.php

### Integration Points

- **Dashboard (index.php)**: Insert alert section after fleet status, before daily operations
- **Settings (settings/general.php)**: Add threshold configuration in "Delivery Settings" section
- **Settings Helpers (includes/settings_helpers.php)**: Use existing settings_get/settings_set functions
- **Database**: Use existing system_settings table (no schema changes required)
- **Reservations**: Query existing reservations table with status='confirmed' filter

## Components and Interfaces

### Alert Query Function

```php
/**
 * Get upcoming delivery alerts for confirmed reservations
 * 
 * @param PDO $pdo Database connection
 * @param int $thresholdDays Number of days to look ahead
 * @return array Array of reservation records with delivery details
 */
function get_upcoming_delivery_alerts(PDO $pdo, int $thresholdDays): array
{
    try {
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime("+{$thresholdDays} days"));
        
        $stmt = $pdo->prepare("
            SELECT r.id, r.start_date,
                   c.name AS client_name,
                   v.brand, v.model, v.license_plate
            FROM reservations r
            JOIN clients c ON r.client_id = c.id
            JOIN vehicles v ON r.vehicle_id = v.id
            WHERE r.status = 'confirmed'
              AND DATE(r.start_date) BETWEEN ? AND ?
            ORDER BY r.start_date ASC
            LIMIT 10
        ");
        
        $stmt->execute([$today, $futureDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        app_log('ERROR', 'Dashboard: upcoming delivery alerts query failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'index.php',
            'threshold_days' => $thresholdDays,
        ]);
        return [];
    }
}
```

### Urgency Calculator

```php
/**
 * Calculate urgency level and badge text for a delivery date
 * 
 * @param string $deliveryDate The start_date of the reservation
 * @return array ['level' => string, 'text' => string, 'class' => string]
 */
function calculate_delivery_urgency(string $deliveryDate): array
{
    $today = date('Y-m-d');
    $deliveryDay = date('Y-m-d', strtotime($deliveryDate));
    $daysUntil = (int) floor((strtotime($deliveryDay) - strtotime($today)) / 86400);
    
    if ($daysUntil === 0) {
        return [
            'level' => 'critical',
            'text' => 'Due Today',
            'class' => 'bg-red-500/15 text-red-400 border-red-500/30 animate-pulse'
        ];
    } elseif ($daysUntil === 1) {
        return [
            'level' => 'high',
            'text' => 'Tomorrow',
            'class' => 'bg-orange-500/15 text-orange-400 border-orange-500/30'
        ];
    } else {
        return [
            'level' => 'normal',
            'text' => "In {$daysUntil} days",
            'class' => 'bg-blue-500/10 text-blue-400 border-blue-500/20'
        ];
    }
}
```

### Alert Renderer (HTML Template)

```php
<?php
// In index.php, after fleet status section
$deliveryAlertThreshold = (int) settings_get($pdo, 'upcoming_delivery_alert_days', '3');
$upcomingDeliveries = get_upcoming_delivery_alerts($pdo, $deliveryAlertThreshold);
$deliveryAlertCount = count($upcomingDeliveries);

if ($deliveryAlertCount > 0):
?>
<section>
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg px-4 py-3">
        <div class="flex items-center gap-3 mb-2">
            <span class="text-blue-400 text-xs font-semibold uppercase tracking-wider">🚗 Upcoming Deliveries</span>
            <span class="bg-blue-500/20 text-blue-400 text-xs font-bold px-2 py-0.5 rounded-full"><?= $deliveryAlertCount ?></span>
            <span class="text-blue-400/60 text-xs">due within <?= $deliveryAlertThreshold ?> day<?= $deliveryAlertThreshold !== 1 ? 's' : '' ?></span>
        </div>
        <div class="space-y-1.5 max-h-40 overflow-y-auto">
            <?php foreach ($upcomingDeliveries as $delivery):
                $urgency = calculate_delivery_urgency($delivery['start_date']);
                $formattedDate = date('d M, h:i A', strtotime($delivery['start_date']));
            ?>
                <a href="reservations/show.php?id=<?= $delivery['id'] ?>"
                   class="flex items-center justify-between bg-mb-surface/40 border border-blue-500/15 rounded px-3 py-1.5 hover:border-blue-500/35 transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        <span class="text-white text-xs font-medium whitespace-nowrap">Res #<?= $delivery['id'] ?></span>
                        <span class="text-mb-subtle text-xs truncate"><?= e($delivery['client_name']) ?></span>
                        <span class="text-mb-subtle/60 text-xs hidden sm:inline">&bull;</span>
                        <span class="text-mb-subtle text-xs truncate hidden sm:inline"><?= e($delivery['brand']) ?> <?= e($delivery['model']) ?></span>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                        <span class="text-mb-silver text-xs whitespace-nowrap"><?= $formattedDate ?></span>
                        <span class="px-2 py-0.5 rounded text-xs border <?= $urgency['class'] ?> whitespace-nowrap">
                            <?= $urgency['text'] ?>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
```

### Settings Configuration (settings/general.php)

```php
// Add to "Delivery Settings" section, after "Default Return Pickup Charge"
<div>
    <label class="block text-sm text-mb-silver mb-2">Upcoming Delivery Alert Threshold (Days)</label>
    <input type="number" name="upcoming_delivery_alert_days" 
           value="<?= (int) settings_get($pdo, 'upcoming_delivery_alert_days', '3') ?>" 
           min="1" max="30" step="1" required
           class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" 
           placeholder="3">
    <p class="text-xs text-mb-subtle mt-1">Alert will trigger when a confirmed reservation is due for delivery within this many days.</p>
</div>
```

```php
// Add to POST handler in settings/general.php
$upcomingDeliveryAlertDays = max(1, min(30, (int) ($_POST['upcoming_delivery_alert_days'] ?? 3)));
settings_set($pdo, 'upcoming_delivery_alert_days', (string) $upcomingDeliveryAlertDays);
```

## Data Models

### Existing Tables (No Changes Required)

**reservations table**:
- `id` (INT, PRIMARY KEY)
- `client_id` (INT, FOREIGN KEY)
- `vehicle_id` (INT, FOREIGN KEY)
- `status` (ENUM: 'pending', 'confirmed', 'active', 'completed', 'cancelled')
- `start_date` (DATETIME) - Used as delivery_date
- `end_date` (DATETIME)
- `created_at` (TIMESTAMP)

**system_settings table**:
- `key` (VARCHAR(100), PRIMARY KEY)
- `value` (TEXT)
- `updated_at` (TIMESTAMP)

**clients table**:
- `id` (INT, PRIMARY KEY)
- `name` (VARCHAR(255))

**vehicles table**:
- `id` (INT, PRIMARY KEY)
- `brand` (VARCHAR(100))
- `model` (VARCHAR(100))
- `license_plate` (VARCHAR(50))

### Query Optimization

**Indexes Used**:
- `reservations.status` - Part of composite index for status filtering
- `reservations.start_date` - Used for date range filtering
- `reservations.client_id` - Foreign key index for JOIN
- `reservations.vehicle_id` - Foreign key index for JOIN

**Query Performance**:
- Single query with JOINs (no N+1 problem)
- LIMIT 10 to cap result set
- Date range filter uses indexed column
- Expected execution time: <50ms for 10,000 reservations


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property Reflection

After analyzing all acceptance criteria, I identified the following redundancies:

1. **Properties 1.6 and 6.3** both test that results are limited to 10 records - these can be combined into a single property
2. **Properties 6.4 and 7.4** both test error handling and returning empty arrays - these are the same property
3. **Properties 1.4 and 9.4** both test that the section is not displayed when count is zero - these are the same edge case
4. **Properties 10.1 and 10.2** test the same filtering behavior from different angles - can be combined into one property that tests only confirmed reservations are returned

After removing duplicates, the unique testable properties are:

**Properties (Universal Quantification)**:
- Query returns only reservations within threshold (1.1)
- Rendered output contains all required fields (1.3)
- Results are ordered by delivery date ascending (1.5)
- Results are limited to maximum 10 records (1.6)
- Input validation clamps values to 1-30 range (2.3)
- Settings round trip (save then retrieve returns same value) (2.4)
- System respects configured threshold (2.5)
- Future dates (2+ days) display correct urgency text (3.3)
- Date formatting matches expected pattern (3.5)
- Rendered links contain correct reservation ID (4.1)
- Each alert contains required structural elements (5.3)
- Query errors return empty array and log error (6.4)
- Error logging includes context information (7.5)
- Count matches number of returned reservations (9.1)
- Only confirmed status reservations are returned (10.1)
- Status changes immediately affect query results (10.3)

**Examples (Specific Cases)**:
- Default threshold is 3 when setting doesn't exist (2.1)
- Today's date shows "Due Today" badge (3.1)
- Tomorrow's date shows "Tomorrow" badge (3.2)
- Header contains required elements (5.2)
- Helper text is present in settings (8.3)
- Count badge is displayed (9.2)

**Edge Cases (Handled by generators/implementation)**:
- Empty result set doesn't display section (1.4)
- Missing system_settings table uses default (7.1)
- NULL start_date is excluded (7.2)
- Missing client/vehicle records show "Unknown" (7.3)

### Property 1: Query Threshold Filtering

*For any* threshold value T (1 ≤ T ≤ 30) and any set of confirmed reservations, the alert query should return only reservations where the delivery date is between today and T days in the future (inclusive).

**Validates: Requirements 1.1, 2.5**

### Property 2: Required Field Presence

*For any* alert rendered in the output, the HTML should contain the reservation ID, client name, vehicle brand, vehicle model, license plate, and formatted delivery date.

**Validates: Requirements 1.3**

### Property 3: Delivery Date Ordering

*For any* set of upcoming delivery alerts, the results should be ordered by delivery date in ascending order (nearest delivery first).

**Validates: Requirements 1.5**

### Property 4: Result Set Limit

*For any* query that matches more than 10 reservations, the alert system should return exactly 10 results.

**Validates: Requirements 1.6, 6.3**

### Property 5: Settings Round Trip

*For any* valid threshold value T (1 ≤ T ≤ 30), saving the setting and then retrieving it should return the same value.

**Validates: Requirements 2.4**

### Property 6: Input Validation Clamping

*For any* input value V, the settings manager should clamp it to the range [1, 30], such that values less than 1 become 1 and values greater than 30 become 30.

**Validates: Requirements 2.3**

### Property 7: Urgency Text for Future Dates

*For any* delivery date that is 2 or more days in the future, the urgency indicator should display "In X days" where X is the number of days until delivery.

**Validates: Requirements 3.3**

### Property 8: Date Format Consistency

*For any* valid datetime value, the formatted output should match the pattern "dd MMM, hh:mm AM/PM" (e.g., "15 Jan, 02:30 PM").

**Validates: Requirements 3.5**

### Property 9: Navigation Link Correctness

*For any* alert rendered, the href attribute should be "reservations/show.php?id=X" where X is the reservation ID.

**Validates: Requirements 4.1**

### Property 10: Alert Structure Completeness

*For any* individual alert, the rendered HTML should contain a vehicle icon, reservation details section, and urgency badge element.

**Validates: Requirements 5.3**

### Property 11: Error Handling Graceful Degradation

*For any* database query error, the alert system should catch the exception, return an empty array, and log the error without breaking the dashboard.

**Validates: Requirements 6.4, 7.4**

### Property 12: Error Logging Context

*For any* error that occurs in the alert system, the app_log call should include the error message, file location, line number, and screen context.

**Validates: Requirements 7.5**

### Property 13: Count Accuracy

*For any* set of upcoming deliveries, the displayed count badge should equal the number of alerts returned by the query.

**Validates: Requirements 9.1**

### Property 14: Status Filtering

*For any* query execution, the results should contain only reservations with status='confirmed' and exclude all other statuses (pending, active, completed, cancelled).

**Validates: Requirements 10.1, 10.2**

### Property 15: Status Change Reactivity

*For any* reservation that changes from status='confirmed' to status='active', the next query execution should not include that reservation in the results.

**Validates: Requirements 10.3**

## Error Handling

### Query Failures

**Strategy**: Wrap all database queries in try-catch blocks and return empty arrays on failure.

```php
try {
    // Query execution
    $stmt = $pdo->prepare("...");
    $stmt->execute([...]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    app_log('ERROR', 'Dashboard: upcoming delivery alerts query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'index.php',
        'threshold_days' => $thresholdDays,
    ]);
    return [];
}
```

**Behavior**: Dashboard continues to render normally with zero alerts displayed.

### Missing Settings

**Strategy**: Use default values when settings don't exist.

```php
$deliveryAlertThreshold = (int) settings_get($pdo, 'upcoming_delivery_alert_days', '3');
```

**Behavior**: System uses 3 days as the default threshold if the setting is not configured.

### NULL or Invalid Data

**Strategy**: Filter out invalid records in the query and use NULL-safe operators.

```php
WHERE r.status = 'confirmed'
  AND r.start_date IS NOT NULL
  AND DATE(r.start_date) BETWEEN ? AND ?
```

**Behavior**: Reservations with NULL start_date are automatically excluded from results.

### Missing Foreign Key References

**Strategy**: Use LEFT JOINs and provide fallback values.

```php
COALESCE(c.name, 'Unknown Client') AS client_name,
COALESCE(v.brand, 'Unknown') AS brand,
COALESCE(v.model, 'Vehicle') AS model
```

**Behavior**: Display "Unknown Client" or "Unknown Vehicle" instead of crashing.

### Settings Validation

**Strategy**: Clamp input values to valid range.

```php
$upcomingDeliveryAlertDays = max(1, min(30, (int) ($_POST['upcoming_delivery_alert_days'] ?? 3)));
```

**Behavior**: Values outside [1, 30] are automatically corrected to the nearest valid value.

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests to ensure comprehensive coverage:

**Unit Tests**: Verify specific examples, edge cases, and error conditions
- Test default threshold value (3 days)
- Test "Due Today" urgency indicator
- Test "Tomorrow" urgency indicator
- Test empty result set behavior
- Test NULL start_date handling
- Test missing client/vehicle records
- Test settings page helper text presence
- Test count badge display

**Property Tests**: Verify universal properties across all inputs
- Test threshold filtering with random threshold values and reservation sets
- Test required field presence with random alert data
- Test delivery date ordering with random date sets
- Test result set limit with varying numbers of matching reservations
- Test settings round trip with random valid threshold values
- Test input validation clamping with random input values
- Test urgency text for random future dates
- Test date format consistency with random datetime values
- Test navigation link correctness with random reservation IDs
- Test alert structure completeness with random alert data
- Test error handling with simulated database failures
- Test error logging context with various error scenarios
- Test count accuracy with random result sets
- Test status filtering with random reservation statuses
- Test status change reactivity with status transitions

### Property-Based Testing Configuration

**Library**: Use PHPUnit with a property-based testing extension (e.g., Eris or php-quickcheck)

**Configuration**:
- Minimum 100 iterations per property test
- Each test tagged with feature name and property reference
- Tag format: `@group Feature: nearby-delivery-alerts, Property X: [property_text]`

**Example Property Test**:

```php
/**
 * @group Feature: nearby-delivery-alerts, Property 1: Query Threshold Filtering
 */
public function test_query_returns_only_reservations_within_threshold(): void
{
    $this->forAll(
        Generator\choose(1, 30), // threshold
        Generator\seq(Generator\associative([
            'id' => Generator\pos(),
            'status' => Generator\constant('confirmed'),
            'start_date' => Generator\date('Y-m-d H:i:s', strtotime('+1 day'), strtotime('+60 days')),
            'client_name' => Generator\string(),
            'brand' => Generator\string(),
            'model' => Generator\string(),
            'license_plate' => Generator\string(),
        ]))
    )->then(function ($threshold, $reservations) {
        // Setup: Insert test reservations
        // Execute: Run alert query with threshold
        // Assert: All returned reservations have delivery_date within threshold
        // Assert: No reservations outside threshold are returned
    });
}
```

### Integration Testing

**Dashboard Rendering**:
- Test that alert section appears when deliveries exist
- Test that alert section is hidden when no deliveries exist
- Test that clicking an alert navigates to correct reservation page
- Test that dashboard loads successfully even when query fails

**Settings Integration**:
- Test that changing threshold in settings affects dashboard query
- Test that invalid threshold values are rejected
- Test that settings persist across page reloads

### Performance Testing

**Query Performance**:
- Benchmark query execution time with 10,000 reservations
- Verify execution time is under 100ms
- Test with various threshold values (1, 7, 15, 30 days)

**Dashboard Load Time**:
- Measure impact on overall dashboard load time
- Verify alert section doesn't block other dashboard components
- Test with maximum alert count (10 alerts)

