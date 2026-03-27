# Vehicle-Level Target Breakdown Feature - Context Transfer

## User Request
Add a vehicle-level target breakdown feature to the Hope Window page.

## Detailed Requirements

### What the user wants:
1. **Entry Point**: Add a card/button on Hope Window page (after "Total Expected Income" or anywhere that fits)
2. **Navigation**: Clicking the card navigates to a new screen showing all vehicles
3. **Vehicle List Screen**: Shows all vehicles with:
   - Vehicle name (e.g., BMW, Audi, etc.)
   - Target amount for that vehicle (e.g., ₹2 lakhs out of ₹20 lakhs total)
   - Actual income achieved for that vehicle
   - Ability to edit each vehicle's target
4. **Bulk Operations**:
   - Set targets for all vehicles at once
   - Set targets for selected vehicles
   - Possibly distribute total target equally across vehicles

## Current Hope Window Structure

### Location
- File: `accounts/hope_window.php`
- Uses period: 15th of current month to 14th of next month

### Current Cards Displayed (top section):
1. **Month** - Shows the selected period (e.g., "15 Mar – 14 Apr")
2. **Default Daily Target** - A daily target amount stored in `system_settings` table as `daily_target`
3. **Total Expected Income** - Sum of all scheduled reservation collections + predictions for the month

### Key Variables in hope_window.php:
- `$defaultTarget` - Daily target from settings (line 401)
- `$totalExpected` - Sum of expected income (calculated from `$days` array)
- `$expectedMap` - Array mapping dates to expected income amounts
- `$actualMap` - Array mapping dates to actual income amounts

### Data Sources:
- **Expected Income**: Calculated from reservations (advance, delivery due, return due) + extensions + predictions
- **Actual Income**: From `ledger_entries` table where `txn_type = 'income'`
- **Reservations**: Linked to vehicles via `vehicle_id` column

## Technical Context

### Relevant Tables:
- `vehicles` - Contains vehicle information (id, brand, model, etc.)
- `reservations` - Has `vehicle_id` column linking to vehicles
- `ledger_entries` - Records actual income with `source_type` and `source_id`
- `hope_daily_targets` - Stores per-day target overrides
- `system_settings` - Stores default daily target

### Income Attribution:
- Reservations are already linked to vehicles via `vehicle_id`
- Ledger entries link back to reservations via `source_type='reservation'` and `source_id`
- This means we can trace income back to specific vehicles

## Design Considerations

### Target Distribution:
- Should vehicle targets sum to a monthly total?
- Or should they be independent daily/period targets per vehicle?
- Need validation if targets should match total expected income

### UI Flow Options:
1. **Option A**: Add 4th card "Vehicle Breakdown" that links to new page
2. **Option B**: Make "Total Expected Income" card clickable to drill down
3. **Option C**: Add a button/link below the 3 cards

### Features to Include:
- List all vehicles with their targets and actuals
- Edit individual vehicle targets
- Bulk set targets (all vehicles or selected)
- Show achievement percentage per vehicle
- Filter by active/sold vehicles
- Visual indicators (progress bars, color coding)

### Database Design:
New table needed: `hope_vehicle_targets`
```sql
CREATE TABLE hope_vehicle_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vehicle_period (vehicle_id, period_start),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);
```

## Questions to Clarify with User

1. **Target Type**: Should vehicle targets be:
   - Daily targets per vehicle?
   - Monthly/period targets per vehicle?
   - Percentage allocation of total expected income?

2. **Target Validation**: Should the sum of all vehicle targets equal:
   - The total expected income?
   - A separate monthly target?
   - No validation (independent targets)?

3. **Vehicle Filtering**: Should the list show:
   - All vehicles?
   - Only active/available vehicles?
   - Include sold vehicles?

4. **Income Calculation**: For "actual income per vehicle", should it include:
   - Only rental income (advance, delivery, return)?
   - Extensions?
   - All income sources linked to that vehicle?

5. **Time Period**: Should vehicle targets follow:
   - Same period as Hope Window (15th to 14th)?
   - Calendar month?
   - Custom date range?

## Next Steps

Once user provides clarity on the above questions:
1. Create a feature spec (requirements → design → tasks)
2. Implement the vehicle targets breakdown feature
3. Add navigation from Hope Window to the new screen
4. Test with existing reservation data

## Related Files to Review
- `accounts/hope_window.php` - Main Hope Window page
- `accounts/targets.php` - Monthly targets page (different from Hope Window)
- `vehicles/show.php` - Vehicle detail page
- `includes/ledger_helpers.php` - Ledger/income calculation helpers
- `includes/settings_helpers.php` - Settings management

## Current Status
- User wants this feature in Hope Window specifically
- Waiting for user to clarify exact requirements before creating spec
- Feature is feasible and makes business sense for fleet performance tracking
