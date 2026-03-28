# Design Document: Client Satisfaction Tracking

## Overview

This feature adds client satisfaction tracking to the vehicle return process. When staff process a return at `reservations/return.php`, they can optionally record whether the client was satisfied (yes/no) and capture a brief comment. This data is stored in the `reservations` table and displayed in the reservation list at `reservations/index.php` for completed reservations.

The implementation follows the existing oRentPHP patterns: dark theme styling, graceful degradation for missing columns, and idempotent migrations. The feature is entirely optional - staff can complete returns without providing satisfaction data.

## Architecture

### Data Flow

1. **Capture**: Staff view return form → optional satisfaction radio buttons + comment field displayed after client rating modal
2. **Storage**: Form submission → PHP validation → database INSERT/UPDATE → satisfaction data stored in `reservations` table
3. **Display**: Reservation list query → completed reservations with satisfaction data → badges/icons rendered in status column

### Component Interaction

```
return.php (Form)
    ↓
POST submission
    ↓
Validation (optional fields)
    ↓
Database UPDATE (reservations table)
    ↓
index.php (List)
    ↓
Query completed reservations
    ↓
Render satisfaction badges
```

## Components and Interfaces

### 1. Database Schema Changes

**Table**: `reservations`

**New Columns**:
- `client_satisfied` ENUM('yes', 'no') NULL DEFAULT NULL
  - Stores whether client was satisfied with rental
  - NULL indicates no response captured
  
- `client_comment` VARCHAR(255) NULL DEFAULT NULL
  - Stores optional client feedback
  - Maximum 255 characters
  - NULL indicates no comment provided

**Migration Strategy**: Use `ADD COLUMN IF NOT EXISTS` pattern for idempotency (graceful degradation if columns already exist).

### 2. Return Form UI (`reservations/return.php`)

**Location**: After client rating modal confirmation, before form submission

**Form Fields**:

```html
<!-- Client Satisfaction Section -->
<div class="space-y-4">
    <label class="block text-sm font-medium text-mb-silver">
        Client Satisfied?
    </label>
    <div class="flex gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="client_satisfied" value="yes" 
                   class="accent-green-500">
            <span class="text-white">Yes</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="client_satisfied" value="no" 
                   class="accent-red-500">
            <span class="text-white">No</span>
        </label>
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-mb-silver mb-2">
        Client Comment (Optional)
    </label>
    <textarea name="client_comment" rows="2" maxlength="255"
              class="w-full bg-mb-surface border border-mb-subtle/20 
                     rounded-lg px-4 py-3 text-white focus:outline-none 
                     focus:border-mb-accent transition-colors"
              placeholder="Brief feedback from client..."></textarea>
    <p class="text-xs text-mb-subtle mt-1">Maximum 255 characters</p>
</div>
```

**Styling**: Matches existing return.php form patterns (dark theme, mb-surface backgrounds, mb-accent focus states)

**Validation**: None required (both fields are optional)

**PHP Processing**:
```php
$clientSatisfied = isset($_POST['client_satisfied']) 
    ? $_POST['client_satisfied'] 
    : null;
$clientComment = isset($_POST['client_comment']) 
    ? trim(substr($_POST['client_comment'], 0, 255)) 
    : null;

// In UPDATE query
$pdo->prepare("UPDATE reservations SET 
    status='completed', 
    client_satisfied=?, 
    client_comment=?, 
    ... 
    WHERE id=?")
    ->execute([
        $clientSatisfied,
        $clientComment,
        ...
        $id
    ]);
```

### 3. Reservation List Display (`reservations/index.php`)

**Location**: Status column, after existing status badge

**Display Logic**:

```php
<?php if ($r['status'] === 'completed' && isset($r['client_satisfied'])): ?>
    <?php if ($r['client_satisfied'] === 'yes'): ?>
        <span class="px-2 py-0.5 rounded text-xs border 
                     bg-green-500/10 text-green-400 border-green-500/30" 
              title="Client satisfied">
            ✓
        </span>
    <?php elseif ($r['client_satisfied'] === 'no'): ?>
        <span class="px-2 py-0.5 rounded text-xs border 
                     bg-red-500/10 text-red-400 border-red-500/30" 
              title="Client not satisfied">
            ✗
        </span>
    <?php endif; ?>
<?php endif; ?>

<?php if ($r['status'] === 'completed' && !empty($r['client_comment'])): ?>
    <p class="text-xs text-mb-subtle mt-1 truncate max-w-xs" 
       title="<?= e($r['client_comment']) ?>">
        <?= e(mb_substr($r['client_comment'], 0, 50)) ?>
        <?php if (mb_strlen($r['client_comment']) > 50): ?>...<?php endif; ?>
    </p>
<?php endif; ?>
```

**Styling**:
- Satisfied: Green badge (matches existing green status badges)
- Not satisfied: Red badge (matches existing red status badges)
- Comment: Subtle gray text, truncated with ellipsis, full text in title attribute

**Query Update**: Add `client_satisfied, client_comment` to SELECT clause in reservations list query.

### 4. Migration File

**Filename**: `migrations/releases/2026-03-28_client_satisfaction_tracking.sql`

**Content**:
```sql
-- Release: 2026-03-28_client_satisfaction_tracking
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds client satisfaction tracking to reservations table.
--        client_satisfied ENUM('yes','no') NULL — satisfaction indicator
--        client_comment VARCHAR(255) NULL — optional feedback text

SET FOREIGN_KEY_CHECKS = 0;

-- Add satisfaction indicator column
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS client_satisfied ENUM('yes', 'no') NULL DEFAULT NULL
    AFTER return_paid_amount;

-- Add comment column
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS client_comment VARCHAR(255) NULL DEFAULT NULL
    AFTER client_satisfied;

SET FOREIGN_KEY_CHECKS = 1;
```

**Idempotency**: Uses `IF NOT EXISTS` guard to prevent errors on re-run.

**Documentation**: Entry added to `PRODUCTION_DB_STEPS.md` under "Pending" section.

## Data Models

### Reservations Table Extension

```
reservations
├── ... (existing columns)
├── return_paid_amount DECIMAL(10,2)
├── client_satisfied ENUM('yes', 'no') NULL      ← NEW
├── client_comment VARCHAR(255) NULL             ← NEW
└── ... (remaining columns)
```

**Constraints**:
- Both columns are nullable (satisfaction tracking is optional)
- `client_satisfied` restricted to 'yes' or 'no' values
- `client_comment` limited to 255 characters at database level
- No foreign keys or indexes required

**Default Values**: NULL for both columns (no satisfaction data captured)

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Satisfaction Data Round-Trip

*For any* reservation return with a satisfaction selection ('yes' or 'no'), submitting the return form should result in the same satisfaction value being stored in the database `client_satisfied` column.

**Validates: Requirements 1.4, 2.3**

### Property 2: Comment Data Round-Trip

*For any* reservation return with a client comment (up to 255 characters), submitting the return form should result in the same comment text being stored in the database `client_comment` column.

**Validates: Requirements 1.5, 2.4**

### Property 3: Comment Truncation Display

*For any* completed reservation with a client comment, if the comment length exceeds 50 characters, the displayed text in the reservation list should be truncated to 50 characters followed by an ellipsis, and the full comment should be available in the title attribute.

**Validates: Requirements 3.5**

### Property 4: Satisfaction Indicator Display

*For any* completed reservation with a non-NULL `client_satisfied` value, the reservation list should display a satisfaction indicator badge (green checkmark for 'yes', red X for 'no').

**Validates: Requirements 3.1**

## Error Handling

### Form Submission Errors

**Scenario**: Database connection failure during return processing
- **Handling**: Existing transaction rollback in return.php catches all database errors
- **User Experience**: Error message displayed, form data preserved for retry

**Scenario**: Comment exceeds 255 characters
- **Handling**: PHP truncates to 255 characters using `substr()` before database insert
- **User Experience**: Silent truncation (database constraint prevents longer values)

### Display Errors

**Scenario**: Missing database columns (pre-migration state)
- **Handling**: Graceful degradation - check column existence before querying
- **User Experience**: No satisfaction data displayed, no errors shown

**Scenario**: Invalid ENUM value in database
- **Handling**: PHP conditional checks for 'yes' or 'no' explicitly
- **User Experience**: No indicator displayed for invalid values

### Migration Errors

**Scenario**: Migration run on table without reservations table
- **Handling**: Migration will fail (expected - table must exist)
- **User Experience**: Admin sees SQL error, must create base schema first

**Scenario**: Migration run multiple times
- **Handling**: `IF NOT EXISTS` guard prevents duplicate column errors
- **User Experience**: No errors, idempotent execution

## Testing Strategy

### Unit Testing

**Form Rendering Tests**:
- Test 1: Verify satisfaction radio buttons render after client rating field
- Test 2: Verify comment textarea renders with maxlength=255 attribute
- Test 3: Verify form submits successfully without satisfaction data (NULL case)

**Database Tests**:
- Test 4: Verify schema includes `client_satisfied` ENUM column
- Test 5: Verify schema includes `client_comment` VARCHAR(255) column
- Test 6: Verify NULL values stored when no satisfaction data provided

**Display Tests**:
- Test 7: Verify green badge displays for satisfied='yes'
- Test 8: Verify red badge displays for satisfied='no'
- Test 9: Verify no badge displays for satisfied=NULL

**Migration Tests**:
- Test 10: Verify migration runs without errors on fresh database
- Test 11: Verify migration runs without errors when re-executed (idempotency)
- Test 12: Verify migration filename matches pattern `YYYY-MM-DD_feature_name.sql`

### Property-Based Testing

**Configuration**: Minimum 100 iterations per property test

**Property Test 1: Satisfaction Round-Trip**
```php
/**
 * Feature: client-satisfaction-tracking, Property 1: 
 * For any reservation return with a satisfaction selection, 
 * the stored value should match the submitted value.
 */
function test_satisfaction_round_trip() {
    // Generate random satisfaction value ('yes' or 'no')
    // Submit return form with that value
    // Query database for reservation
    // Assert: stored client_satisfied === submitted value
}
```

**Property Test 2: Comment Round-Trip**
```php
/**
 * Feature: client-satisfaction-tracking, Property 2: 
 * For any reservation return with a comment (up to 255 chars), 
 * the stored value should match the submitted value.
 */
function test_comment_round_trip() {
    // Generate random comment string (1-255 characters)
    // Submit return form with that comment
    // Query database for reservation
    // Assert: stored client_comment === submitted comment
}
```

**Property Test 3: Comment Truncation**
```php
/**
 * Feature: client-satisfaction-tracking, Property 3: 
 * For any comment exceeding 50 characters, the display should 
 * truncate to 50 chars + ellipsis with full text in title.
 */
function test_comment_truncation_display() {
    // Generate random comment string (51-255 characters)
    // Render reservation list with that comment
    // Assert: displayed text === first 50 chars + "..."
    // Assert: title attribute === full comment text
}
```

**Property Test 4: Satisfaction Indicator Display**
```php
/**
 * Feature: client-satisfaction-tracking, Property 4: 
 * For any completed reservation with satisfaction data, 
 * the correct indicator badge should be displayed.
 */
function test_satisfaction_indicator_display() {
    // Generate random satisfaction value ('yes' or 'no')
    // Create completed reservation with that value
    // Render reservation list
    // Assert: if 'yes', green badge present
    // Assert: if 'no', red badge present
}
```

### Integration Testing

**End-to-End Flow**:
1. Create active reservation
2. Navigate to return form
3. Complete return with satisfaction='yes' and comment="Great customer"
4. Verify success message
5. Navigate to reservation list
6. Verify green badge and comment displayed for completed reservation

**Negative Cases**:
- Submit return without satisfaction data → verify NULL stored
- Submit return with 300-character comment → verify truncated to 255
- View list with pre-migration database → verify no errors (graceful degradation)

### Manual Testing Checklist

- [ ] Return form displays satisfaction fields after rating modal
- [ ] Radio buttons styled consistently with existing form elements
- [ ] Comment textarea enforces 255 character limit
- [ ] Form submits successfully with satisfaction data
- [ ] Form submits successfully without satisfaction data
- [ ] Reservation list displays green badge for satisfied='yes'
- [ ] Reservation list displays red badge for satisfied='no'
- [ ] Reservation list displays no badge for satisfied=NULL
- [ ] Long comments truncate at 50 characters with ellipsis
- [ ] Hover over truncated comment shows full text in tooltip
- [ ] Layout remains intact on mobile viewport
- [ ] Migration runs successfully on production database
- [ ] Migration can be re-run without errors

## Implementation Notes

### Dark Theme Styling

All new UI elements follow the existing oRentPHP dark theme patterns:

- **Backgrounds**: `bg-mb-surface` (form inputs), `bg-mb-black/40` (containers)
- **Borders**: `border-mb-subtle/20` (default), `border-mb-accent` (focus)
- **Text**: `text-white` (primary), `text-mb-silver` (labels), `text-mb-subtle` (hints)
- **Badges**: Match existing status badge patterns (green for positive, red for negative)

### Responsive Design

- Form fields stack vertically on mobile (`flex-col` on small screens)
- Comment text truncates with `truncate` class to prevent overflow
- Badges use `text-xs` for compact display on mobile

### Performance Considerations

- No additional database queries required (columns added to existing SELECT)
- No indexes needed (satisfaction data not used for filtering/sorting)
- Minimal JavaScript (no dynamic updates, form submission only)

### Backward Compatibility

- Graceful degradation: code checks for column existence before querying
- Existing returns without satisfaction data display normally (NULL values)
- Migration is additive only (no data modifications)

### Security Considerations

- Comment input sanitized with `e()` helper on display (XSS prevention)
- Comment truncated to 255 characters before database insert (SQL injection prevention)
- No user-facing SQL errors (graceful error handling)

## Deployment Checklist

1. **Code Changes**:
   - [ ] Update `reservations/return.php` with satisfaction form fields
   - [ ] Update `reservations/return.php` POST handler to capture satisfaction data
   - [ ] Update `reservations/index.php` query to include new columns
   - [ ] Update `reservations/index.php` display to show satisfaction badges

2. **Database Migration**:
   - [ ] Create migration file `migrations/releases/2026-03-28_client_satisfaction_tracking.sql`
   - [ ] Add entry to `PRODUCTION_DB_STEPS.md` under "Pending"
   - [ ] Test migration on local database
   - [ ] Backup production database
   - [ ] Run migration on production via phpMyAdmin

3. **Testing**:
   - [ ] Run unit tests for form rendering and database operations
   - [ ] Run property-based tests (100+ iterations each)
   - [ ] Perform manual testing on staging environment
   - [ ] Verify graceful degradation on pre-migration database

4. **Documentation**:
   - [ ] Update `UPDATE_SESSION_RULES.md` release log
   - [ ] Document satisfaction tracking in user guide (if exists)

## Future Enhancements

- **Satisfaction Analytics**: Dashboard showing satisfaction trends over time
- **Filtering**: Add satisfaction filter to reservation list (show only satisfied/unsatisfied)
- **Reporting**: Export satisfaction data in reservation reports
- **Notifications**: Alert management when client is not satisfied
- **Follow-up**: Automatic follow-up task creation for unsatisfied clients
