# Design Document: Hope Window Today Highlight

## Overview

This feature adds a highlighted "Today" section at the top of the Hope Window list view to provide immediate visibility of today's expected and actual income data. The Today Section displays the same comprehensive breakdown information as an expanded day row, but in a prominent, always-visible position at the top of the page.

The implementation leverages existing data structures and rendering functions to ensure consistency between the Today Section and the regular day rows. The Today Section appears only when the current date falls within the selected period range, and today's row remains in the chronological list to maintain timeline context.

## Architecture

### Component Structure

The implementation follows the existing Hope Window architecture:

```
Hope Window Page (accounts/hope_window.php)
├── Data Layer
│   ├── $dayByDate array (existing)
│   ├── $breakdownMap array (existing)
│   └── hope_fetch_actual_breakdown() function (existing)
├── Rendering Layer
│   ├── hope_render_breakdown() function (existing)
│   ├── hope_render_actual_breakdown() function (existing)
│   └── hope_render_today_section() function (new)
└── View Layer
    ├── Summary Cards (existing)
    ├── Today Section (new)
    └── Daily Targets Section (existing)
```

### Data Flow

1. Page loads and determines current date and selected period range
2. Existing data loading logic populates $dayByDate and $breakdownMap
3. New conditional logic checks if today is within the selected range
4. If yes, Today Section is rendered using existing data and rendering functions
5. Regular day list is rendered as before, including today's row

## Components and Interfaces

### New Function: hope_render_today_section()

```php
function hope_render_today_section(
    array $dayData,
    array $breakdownMap,
    PDO $pdo,
    string $today
): string
```

**Purpose**: Renders the highlighted Today Section HTML

**Parameters**:
- `$dayData`: The day data array from $dayByDate[$today]
- `$breakdownMap`: The breakdown map containing expected income details
- `$pdo`: Database connection for fetching actual breakdown
- `$today`: Today's date string in Y-m-d format

**Returns**: HTML string for the Today Section

**Behavior**:
- Renders a prominent card with accent color styling
- Displays target, expected, actual, and variance amounts
- Calls hope_render_breakdown() for expected income details
- Calls hope_fetch_actual_breakdown() and hope_render_actual_breakdown() for actual income (if today is not in the future)
- Displays predictions if present
- Uses consistent formatting with hope_format_currency() and e()

### Modified Rendering Logic

The main page rendering logic will be modified to:

1. Check if today's date is within the selected period range
2. If yes, retrieve today's data from $dayByDate
3. Call hope_render_today_section() to generate the Today Section HTML
4. Insert the Today Section HTML after the summary cards and before the Daily Targets section
5. Continue rendering the day list as normal (including today's row)

### HTML Structure

```html
<!-- Today Section (new) -->
<div class="bg-mb-surface border-2 border-mb-accent/60 rounded-xl overflow-hidden mb-6">
    <div class="px-5 py-4 bg-mb-accent/10 border-b border-mb-accent/20">
        <div class="flex items-center gap-2">
            <svg><!-- Today icon --></svg>
            <h3 class="text-white font-medium text-lg">Today</h3>
            <span class="text-mb-subtle text-sm"><!-- Date label --></span>
        </div>
    </div>
    <div class="p-5">
        <!-- Summary grid: Target, Expected, Actual, Variance -->
        <!-- Expected Income Breakdown -->
        <!-- Actual Income Breakdown (if applicable) -->
        <!-- Predictions (if present) -->
    </div>
</div>
```

## Data Models

No new database tables or schema changes are required. The feature uses existing data structures:

### Existing Data Structures

**$dayByDate array**:
```php
[
    'date' => string,        // Y-m-d format
    'label' => string,       // e.g., "Mon, 25 Mar"
    'target' => float,       // Daily target amount
    'expected' => float,     // Expected income
    'actual' => float,       // Actual income
    'is_today' => bool,      // Whether this is today
    'override' => bool,      // Whether target is overridden
    'pred_count' => int,     // Number of predictions
]
```

**$breakdownMap array**:
```php
[
    'date' => [
        'booking' => array,    // Booking items
        'delivery' => array,   // Delivery items
        'return' => array,     // Return items
        'extension' => array,  // Extension items
        'prediction' => array, // Prediction items
        'total' => float,      // Total expected
    ]
]
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Today Section Conditional Display

*For any* selected period range, the Today Section should be displayed if and only if the current date falls within that range (inclusive of start and end dates).

**Validates: Requirements 1.2, 1.3**

### Property 2: Date Label Format Consistency

*For any* date, the date label format used in the Today Section should be identical to the format used in day rows.

**Validates: Requirements 1.5**

### Property 3: Required Data Fields Display

*For any* valid today date within the selected range, the Today Section should display all required data fields: target amount, expected income amount, actual income amount, and variance (calculated as actual minus expected).

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

### Property 4: Expected Income Breakdown Completeness

*For any* date with expected income sources, the Today Section should display all booking, delivery, return, extension, and prediction items from the breakdown map.

**Validates: Requirements 2.5**

### Property 5: Actual Income Breakdown Conditional Display

*For any* date, the Today Section should display the actual income breakdown if and only if the date is today or in the past (not in the future).

**Validates: Requirements 2.6, 2.7**

### Property 6: Predictions Display Completeness

*For any* date with custom predictions, the Today Section should display all prediction items with their labels and amounts.

**Validates: Requirements 2.8**

### Property 7: Today Row Preservation

*For any* date that is today and falls within the selected range, that date should appear as a row in the chronological day list.

**Validates: Requirements 4.1**

### Property 8: Today Row Data Completeness

*For any* date that is today, the today row should display all standard data fields: target, expected, actual, predictions count, and variance.

**Validates: Requirements 4.4**

### Property 9: Today Row Chronological Position

*For any* date that is today, the today row should appear in its correct chronological position within the sorted day list.

**Validates: Requirements 4.5**

### Property 10: Reservation Links Rendering

*For any* reservation item in the expected or actual income breakdown, the rendered HTML should contain a clickable link with the correct href pointing to the reservation detail page.

**Validates: Requirements 5.1, 5.2**

### Property 11: Currency Formatting Consistency

*For any* monetary amount displayed in the Today Section, it should be formatted using the hope_format_currency() function to ensure consistent formatting.

**Validates: Requirements 5.4**

### Property 12: HTML Escaping for Client Names

*For any* client name displayed in the Today Section, it should be escaped using the e() function to prevent XSS vulnerabilities.

**Validates: Requirements 5.5**

### Property 13: Data Consistency Between Today Section and Today Row

*For any* date that is today, the target amount, expected income amount, actual income amount, and variance displayed in the Today Section should exactly match the values displayed in the today row.

**Validates: Requirements 6.4, 6.5, 6.6, 6.7**

## Error Handling

### Missing Data Scenarios

1. **Today not in selected range**: Today Section is not rendered (normal behavior)
2. **No breakdown data for today**: Display "No expected income sources for this date" message (existing behavior from hope_render_breakdown)
3. **No actual income for today**: Display "No actual income recorded for this date" message (existing behavior from hope_render_actual_breakdown)
4. **Missing predictions**: Predictions section is not rendered (normal behavior)

### Data Integrity

- All data retrieval uses existing, tested functions
- No new database queries are introduced
- All monetary calculations use existing, validated logic
- HTML escaping is applied consistently using e() function
- Currency formatting is applied consistently using hope_format_currency()

### Edge Cases

1. **Today is the first day of the period**: Today Section appears, today row is first in list
2. **Today is the last day of the period**: Today Section appears, today row is last in list
3. **Today is the only day in the period**: Today Section appears, only one row in list
4. **Period spans multiple months**: Today Section appears only if today is within the range

## Testing Strategy

### Unit Testing Approach

Unit tests should focus on specific examples and edge cases:

1. **Today Section rendering with complete data**: Verify HTML structure and content
2. **Today Section with no expected income**: Verify empty state message
3. **Today Section with no actual income**: Verify empty state message
4. **Today Section with predictions**: Verify predictions are displayed
5. **Today Section with no predictions**: Verify predictions section is omitted
6. **Date label formatting**: Verify format matches day row format
7. **Currency formatting**: Verify all amounts use hope_format_currency()
8. **HTML escaping**: Verify client names are escaped
9. **Reservation links**: Verify correct href attributes
10. **Today outside range**: Verify Today Section is not rendered
11. **Today at range boundaries**: Verify Today Section is rendered correctly

### Property-Based Testing Approach

Property-based tests should verify universal properties across many generated inputs. Each test should run a minimum of 100 iterations.

**Test Configuration**: Use a PHP property-based testing library such as Eris or php-quickcheck.

**Property Test 1: Today Section Conditional Display**
- Generate random period ranges and today dates
- Verify Today Section presence matches whether today is in range
- Tag: **Feature: hope-window-today-highlight, Property 1: For any selected period range, the Today Section should be displayed if and only if the current date falls within that range**

**Property Test 2: Date Label Format Consistency**
- Generate random dates
- Verify format function produces identical output for Today Section and day rows
- Tag: **Feature: hope-window-today-highlight, Property 2: For any date, the date label format used in the Today Section should be identical to the format used in day rows**

**Property Test 3: Required Data Fields Display**
- Generate random day data with various values
- Verify all required fields are present in rendered HTML
- Tag: **Feature: hope-window-today-highlight, Property 3: For any valid today date within the selected range, the Today Section should display all required data fields**

**Property Test 4: Expected Income Breakdown Completeness**
- Generate random breakdown data with various item types
- Verify all items are rendered in the output
- Tag: **Feature: hope-window-today-highlight, Property 4: For any date with expected income sources, the Today Section should display all items from the breakdown map**

**Property Test 5: Actual Income Breakdown Conditional Display**
- Generate random dates (past, present, future)
- Verify actual breakdown is shown only for past/present dates
- Tag: **Feature: hope-window-today-highlight, Property 5: For any date, the Today Section should display the actual income breakdown if and only if the date is today or in the past**

**Property Test 6: Predictions Display Completeness**
- Generate random prediction data
- Verify all predictions are rendered with correct labels and amounts
- Tag: **Feature: hope-window-today-highlight, Property 6: For any date with custom predictions, the Today Section should display all prediction items**

**Property Test 7: Today Row Preservation**
- Generate random period ranges where today is included
- Verify today row exists in the day list
- Tag: **Feature: hope-window-today-highlight, Property 7: For any date that is today and falls within the selected range, that date should appear as a row in the chronological day list**

**Property Test 8: Today Row Data Completeness**
- Generate random day data for today
- Verify today row contains all standard fields
- Tag: **Feature: hope-window-today-highlight, Property 8: For any date that is today, the today row should display all standard data fields**

**Property Test 9: Today Row Chronological Position**
- Generate random day lists with today at various positions
- Verify today row appears in correct chronological order
- Tag: **Feature: hope-window-today-highlight, Property 9: For any date that is today, the today row should appear in its correct chronological position**

**Property Test 10: Reservation Links Rendering**
- Generate random reservation items
- Verify all rendered links have correct href attributes
- Tag: **Feature: hope-window-today-highlight, Property 10: For any reservation item in the breakdown, the rendered HTML should contain a clickable link with the correct href**

**Property Test 11: Currency Formatting Consistency**
- Generate random monetary amounts
- Verify all are formatted using hope_format_currency()
- Tag: **Feature: hope-window-today-highlight, Property 11: For any monetary amount displayed in the Today Section, it should be formatted using the hope_format_currency() function**

**Property Test 12: HTML Escaping for Client Names**
- Generate random client names with special characters
- Verify all are escaped using e() function
- Tag: **Feature: hope-window-today-highlight, Property 12: For any client name displayed in the Today Section, it should be escaped using the e() function**

**Property Test 13: Data Consistency Between Today Section and Today Row**
- Generate random day data for today
- Verify all data fields match between Today Section and today row
- Tag: **Feature: hope-window-today-highlight, Property 13: For any date that is today, the data displayed in the Today Section should exactly match the values displayed in the today row**

### Integration Testing

Integration tests should verify:

1. Today Section integrates correctly with existing page layout
2. Today Section uses existing data structures correctly
3. Today Section rendering functions work with real database data
4. Page performance is not significantly impacted
5. Today Section works correctly with different period selections

### Manual Testing Checklist

1. Visual appearance matches design requirements
2. Today Section is visually distinct from day rows
3. Responsive layout works on mobile, tablet, and desktop
4. Spacing and alignment are consistent
5. Colors and styling match the design system
6. Links are clickable and navigate correctly
7. Today row remains in the list and is still expandable
8. Scrolling behavior is smooth
9. Today Section updates correctly when changing periods

## Implementation Notes

### Code Location

All changes will be made to `accounts/hope_window.php`.

### Implementation Steps

1. Add hope_render_today_section() function after existing rendering functions
2. Add conditional logic after summary cards rendering to check if today is in range
3. If yes, call hope_render_today_section() and output the HTML
4. Ensure today's row continues to be rendered in the day list
5. Add appropriate CSS classes for styling

### Styling Approach

Use existing Tailwind CSS classes with the mb-accent color scheme:
- Border: `border-2 border-mb-accent/60`
- Background: `bg-mb-accent/10` for header, `bg-mb-surface` for body
- Text colors: `text-white` for headings, `text-mb-subtle` for labels
- Spacing: `mb-6` for separation from Daily Targets section

### Performance Considerations

- No additional database queries (uses existing data)
- Minimal additional rendering overhead (one function call)
- HTML output is small (similar to one expanded day row)
- No JavaScript required for basic functionality

### Accessibility Considerations

- Use semantic HTML structure
- Ensure sufficient color contrast for text
- Include descriptive labels for screen readers
- Maintain keyboard navigation support for links

## Future Enhancements

Potential future improvements (not in scope for this feature):

1. Sticky positioning for Today Section (always visible while scrolling)
2. Collapsible Today Section for users who prefer more compact view
3. Quick actions in Today Section (e.g., add prediction, view details)
4. Real-time updates when data changes
5. Comparison with yesterday or same day last month
