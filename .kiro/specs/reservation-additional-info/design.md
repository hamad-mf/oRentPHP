# Design Document: Reservation Additional Information

## Overview

This feature adds three supplementary information fields to the reservation details screen: delivery location, return location, and a general note field. These fields are purely informational and will not be integrated into any business logic, calculations, or workflows. The implementation follows the existing codebase patterns and session rules, using idempotent database migrations and inline editing patterns similar to other reservation features.

The feature consists of:
- Database schema changes (3 new nullable columns in the reservations table)
- UI section on reservations/show.php to display and edit the fields
- Inline editing functionality with validation
- Idempotent SQL migration file

## Architecture

### System Context

The feature integrates into the existing reservation management system:

```
┌─────────────────────────────────────────────────────────────┐
│                    Reservation Details Screen                │
│                    (reservations/show.php)                   │
├─────────────────────────────────────────────────────────────┤
│  Existing Sections:                                          │
│  - Main Info (client, vehicle, dates)                        │
│  - Pricing Summary                                           │
│  - Held Deposit Management                                   │
│  - Inspections (delivery/return)                             │
│  - Scratch Photos                                            │
│                                                              │
│  NEW: Additional Information Section                         │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ • Delivery Location (VARCHAR 255)                      │ │
│  │ • Return Location (VARCHAR 255)                        │ │
│  │ • Additional Note (TEXT 1000 chars)                    │ │
│  │ • Inline Edit Controls (for authorized users)          │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
                ┌───────────────────────┐
                │   MySQL Database      │
                │  reservations table   │
                │  + 3 new columns      │
                └───────────────────────┘
```

### Design Principles

1. **Display-Only Data**: These fields are for reference only and do not participate in any business logic
2. **Backward Compatibility**: Existing functionality remains unchanged; all new columns are nullable
3. **Idempotent Migrations**: Database changes use conditional logic to safely run multiple times
4. **Consistent UI Patterns**: Follow existing inline editing patterns from the codebase (e.g., booking discount widget)
5. **Session Rules Compliance**: Separate migration file, manual application, no automatic migrations

## Components and Interfaces

### Database Schema Changes

**Table**: `reservations`

**New Columns**:
```sql
delivery_location VARCHAR(255) NULL
return_location VARCHAR(255) NULL
additional_note TEXT NULL
```

All columns are nullable to ensure backward compatibility and to reflect that these fields are optional supplementary information.

### UI Components

#### 1. Additional Information Display Section

**Location**: reservations/show.php, after the scratch photos sections

**Behavior**:
- Displays when viewing any reservation
- Shows all three fields in a card layout
- If all fields are empty/NULL, displays "No additional information recorded"
- If any field has data, displays the field label and value
- Authorized users see an "Edit" button

**Visual Design**:
- Consistent with existing card-based sections
- Uses mb-surface background with mb-subtle borders
- Text fields displayed with proper labels
- Note field supports multi-line display with whitespace preservation

#### 2. Inline Edit Form

**Trigger**: Click "Edit Additional Info" button

**Behavior**:
- Replaces display section with editable form
- Three input fields: delivery_location, return_location, additional_note
- Character count indicators for each field
- Save and Cancel buttons
- Client-side validation for character limits
- Server-side validation on submit

**Form Fields**:
```
Delivery Location: <input maxlength="255">
Return Location:   <input maxlength="255">
Additional Note:   <textarea maxlength="1000" rows="4">
```

### Data Flow

```
User Action (Edit) → Form Display → User Input → Validation
                                                      │
                                                      ├─ Valid → POST to show.php
                                                      │           │
                                                      │           ▼
                                                      │        UPDATE reservations
                                                      │           │
                                                      │           ▼
                                                      │        Redirect with success
                                                      │
                                                      └─ Invalid → Display error message
```

### API/Endpoints

**Endpoint**: reservations/show.php (POST)

**Parameters**:
- `action`: "save_additional_info"
- `reservation_id`: int
- `delivery_location`: string (max 255 chars)
- `return_location`: string (max 255 chars)
- `additional_note`: string (max 1000 chars)

**Validation**:
- Reservation ID must match the current reservation
- Delivery location: 0-255 characters
- Return location: 0-255 characters
- Additional note: 0-1000 characters
- Empty strings are converted to NULL

**Response**:
- Success: Flash message + redirect to show.php?id={id}
- Error: Flash error message + redirect to show.php?id={id}

## Data Models

### Reservations Table Extension

```sql
CREATE TABLE reservations (
    -- ... existing columns ...
    
    -- New columns for additional information
    delivery_location VARCHAR(255) NULL COMMENT 'Optional delivery location for reference',
    return_location VARCHAR(255) NULL COMMENT 'Optional return location for reference',
    additional_note TEXT NULL COMMENT 'Optional general note (max 1000 chars in UI)',
    
    -- ... existing columns ...
);
```

**Field Specifications**:

| Field | Type | Nullable | Max Length | Purpose |
|-------|------|----------|------------|---------|
| delivery_location | VARCHAR(255) | YES | 255 | Where vehicle was/will be delivered |
| return_location | VARCHAR(255) | YES | 255 | Where vehicle was/will be returned |
| additional_note | TEXT | YES | 1000 (UI) | General supplementary notes |

**Notes**:
- TEXT type for additional_note allows flexibility, but UI enforces 1000 char limit
- All fields default to NULL
- No foreign keys or indexes needed (display-only data)
- No impact on existing queries or functionality

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Field Data Persistence

*For any* valid reservation and any valid values for delivery_location (≤255 chars), return_location (≤255 chars), and additional_note (≤1000 chars), when these values are saved, querying the database should return the same values.

**Validates: Requirements 2.7**

### Property 2: Input Length Validation for Delivery Location

*For any* string input for delivery_location, the system should accept inputs up to 255 characters and reject inputs exceeding 255 characters with an appropriate error message.

**Validates: Requirements 2.3**

### Property 3: Input Length Validation for Return Location

*For any* string input for return_location, the system should accept inputs up to 255 characters and reject inputs exceeding 255 characters with an appropriate error message.

**Validates: Requirements 2.4**

### Property 4: Input Length Validation for Additional Note

*For any* string input for additional_note, the system should accept inputs up to 1000 characters and reject inputs exceeding 1000 characters with an appropriate error message.

**Validates: Requirements 2.5**

### Property 5: Display of Non-Empty Fields

*For any* reservation where at least one of the three additional information fields contains a non-null, non-empty value, the rendered Additional_Info_Section should display that field's label and value.

**Validates: Requirements 1.5**

### Property 6: Migration Idempotence

*For any* database state, running the migration script multiple times should produce the same final schema without errors or data loss.

**Validates: Requirements 4.4**

### Property 7: Data Preservation During Migration

*For any* existing reservation data in the database, applying the migration should preserve all existing column values unchanged while adding the three new columns with NULL values.

**Validates: Requirements 4.6**

### Property 8: Pricing Calculation Independence

*For any* reservation, the total pricing calculations (base price, discounts, charges, total collected) should produce identical results regardless of the values in delivery_location, return_location, and additional_note fields.

**Validates: Requirements 6.2**

### Property 9: Status Logic Independence

*For any* reservation, status transition logic (pending → confirmed → active → completed) should behave identically regardless of the values in delivery_location, return_location, and additional_note fields.

**Validates: Requirements 6.3**

### Property 10: Workflow Transition Independence

*For any* reservation workflow transition (delivery, return, extension, cancellation), the transition should succeed with these fields being NULL or containing any valid values, demonstrating they are not required for any workflow.

**Validates: Requirements 6.5**

## Error Handling

### Validation Errors

**Character Limit Exceeded**:
- **Trigger**: User submits form with field exceeding max length
- **Response**: Flash error message specifying which field exceeded limit
- **Example**: "Delivery location cannot exceed 255 characters"
- **Recovery**: User corrects input and resubmits

**Invalid Reservation ID**:
- **Trigger**: POST request with mismatched reservation_id
- **Response**: Flash error "Invalid request" + redirect
- **Recovery**: User returns to correct reservation page

### Database Errors

**Connection Failure**:
- **Trigger**: Database unavailable during save
- **Response**: PHP exception caught, flash error "Unable to save changes"
- **Recovery**: User retries after connection restored

**Migration Errors**:
- **Trigger**: Migration script encounters unexpected schema state
- **Response**: SQL error logged, migration halts
- **Recovery**: Manual review and correction by administrator

### Edge Cases

**Empty String Handling**:
- Empty strings submitted via form are converted to NULL before database storage
- Ensures consistent NULL representation for "no data"

**Whitespace-Only Input**:
- Trimmed before validation
- If result is empty, treated as NULL

**Special Characters**:
- All input is properly escaped using prepared statements
- HTML entities encoded on display using e() helper

**Concurrent Edits**:
- Last write wins (no conflict detection needed for display-only data)
- No locking mechanism required

## Testing Strategy

### Unit Testing

**Input Validation Tests**:
- Test delivery_location accepts 255 chars, rejects 256
- Test return_location accepts 255 chars, rejects 256
- Test additional_note accepts 1000 chars, rejects 1001
- Test empty strings are converted to NULL
- Test whitespace-only strings are converted to NULL

**Display Logic Tests**:
- Test section displays "No additional information" when all fields NULL
- Test section displays field values when present
- Test edit button appears for authorized users
- Test edit button hidden for unauthorized users

**Database Interaction Tests**:
- Test successful save updates all three fields
- Test NULL values are stored correctly
- Test data retrieval returns saved values
- Test clearing a field stores NULL

**Migration Tests**:
- Test migration creates three columns with correct types
- Test migration is idempotent (can run multiple times)
- Test migration preserves existing reservation data
- Test migration handles table not existing gracefully

### Property-Based Testing

**Configuration**: Minimum 100 iterations per property test

**Property Test 1: Round-Trip Persistence**
- **Tag**: Feature: reservation-additional-info, Property 1: Field Data Persistence
- **Generator**: Random strings within valid lengths (0-255 for locations, 0-1000 for note)
- **Test**: Save values → query database → assert retrieved values match saved values

**Property Test 2: Delivery Location Length Validation**
- **Tag**: Feature: reservation-additional-info, Property 2: Input Length Validation for Delivery Location
- **Generator**: Random strings of varying lengths (0-300 chars)
- **Test**: Submit value → assert accepted if ≤255, rejected if >255

**Property Test 3: Return Location Length Validation**
- **Tag**: Feature: reservation-additional-info, Property 3: Input Length Validation for Return Location
- **Generator**: Random strings of varying lengths (0-300 chars)
- **Test**: Submit value → assert accepted if ≤255, rejected if >255

**Property Test 4: Additional Note Length Validation**
- **Tag**: Feature: reservation-additional-info, Property 4: Input Length Validation for Additional Note
- **Generator**: Random strings of varying lengths (0-1200 chars)
- **Test**: Submit value → assert accepted if ≤1000, rejected if >1000

**Property Test 5: Display Rendering**
- **Tag**: Feature: reservation-additional-info, Property 5: Display of Non-Empty Fields
- **Generator**: Random combinations of NULL and non-empty values for the three fields
- **Test**: Render section → assert non-empty fields appear in output

**Property Test 6: Migration Idempotence**
- **Tag**: Feature: reservation-additional-info, Property 6: Migration Idempotence
- **Generator**: Random initial database states (table exists/doesn't exist, columns exist/don't exist)
- **Test**: Run migration N times → assert final schema is correct and no errors occur

**Property Test 7: Migration Data Preservation**
- **Tag**: Feature: reservation-additional-info, Property 7: Data Preservation During Migration
- **Generator**: Random existing reservation records
- **Test**: Snapshot data → run migration → assert all original data unchanged

**Property Test 8: Pricing Independence**
- **Tag**: Feature: reservation-additional-info, Property 8: Pricing Calculation Independence
- **Generator**: Random reservation data with varying additional info field values
- **Test**: Calculate pricing with fields NULL → calculate with fields populated → assert results identical

**Property Test 9: Status Logic Independence**
- **Tag**: Feature: reservation-additional-info, Property 9: Status Logic Independence
- **Generator**: Random reservation states and additional info field values
- **Test**: Execute status transition with fields NULL → execute with fields populated → assert behavior identical

**Property Test 10: Workflow Independence**
- **Tag**: Feature: reservation-additional-info, Property 10: Workflow Transition Independence
- **Generator**: Random workflow transitions and additional info field values
- **Test**: Execute workflow with fields NULL → execute with fields populated → assert both succeed

### Integration Testing

**End-to-End Workflow**:
1. Create reservation (fields should be NULL)
2. View reservation details (should show "No additional information")
3. Click edit, enter data, save
4. Verify success message and data displayed
5. Edit again, clear fields, save
6. Verify fields return to NULL

**Permission Testing**:
- Verify admin users can edit
- Verify staff users can edit (if permitted)
- Verify unauthorized users cannot see edit controls

**Cross-Browser Testing**:
- Test form submission in Chrome, Firefox, Safari
- Test character count indicators work correctly
- Test textarea resizing and display

### Manual Testing Checklist

- [ ] Migration runs successfully on fresh database
- [ ] Migration runs successfully on existing database with data
- [ ] Migration can be run multiple times without errors
- [ ] Additional info section appears on reservation details page
- [ ] "No additional information" message shows when fields are empty
- [ ] Edit button appears for authorized users
- [ ] Form displays with correct field labels and limits
- [ ] Character count indicators update as user types
- [ ] Validation prevents exceeding character limits
- [ ] Success message appears after successful save
- [ ] Error message appears when validation fails
- [ ] Data persists correctly after save
- [ ] Clearing fields stores NULL in database
- [ ] Special characters display correctly
- [ ] Multi-line notes display with proper formatting
- [ ] Existing reservation functionality unaffected
- [ ] Pricing calculations unchanged
- [ ] Status transitions work normally
- [ ] Bill generation unaffected

## Implementation Notes

### File Changes

**New Files**:
- `migrations/releases/2026-04-15_reservation_additional_info.sql`

**Modified Files**:
- `reservations/show.php` (add display section and edit form handling)

### Code Patterns to Follow

**Database Query Pattern**:
```php
// Fetch additional info with main reservation query
$rStmt = $pdo->prepare('SELECT r.*, 
    r.delivery_location, 
    r.return_location, 
    r.additional_note,
    c.name AS client_name, 
    -- ... other fields
    FROM reservations r 
    JOIN clients c ON r.client_id=c.id 
    WHERE r.id=?');
```

**POST Handler Pattern** (similar to booking discount):
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['action']) && 
    $_POST['action'] === 'save_additional_info') {
    
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    if ($reservationId !== $id) {
        flash('error', 'Invalid request.');
        redirect("show.php?id=$id");
    }
    
    // Validation and save logic
}
```

**Inline Edit UI Pattern**:
- Use JavaScript to toggle between display and edit modes
- Similar to existing inline editing patterns in the codebase
- Maintain consistent styling with mb-* utility classes

### Migration File Structure

Follow the established pattern from existing migrations:
- Header comment with release name, author, safety note
- SET FOREIGN_KEY_CHECKS = 0
- Check if table exists
- Check if each column exists
- Conditional ALTER TABLE using prepared statements
- SET FOREIGN_KEY_CHECKS = 1

### Security Considerations

**Input Sanitization**:
- Use prepared statements for all database queries
- Use e() helper for HTML output escaping
- Trim and validate all user input

**Authorization**:
- Check user permissions before showing edit controls
- Verify permissions on POST handler
- Use existing auth_has_perm() function if available

**SQL Injection Prevention**:
- All queries use PDO prepared statements
- No string concatenation in SQL

### Performance Considerations

**Database Impact**:
- Three new nullable columns add minimal storage overhead
- No indexes needed (display-only data, no queries filter on these fields)
- No impact on existing query performance

**UI Impact**:
- Additional section adds minimal page weight
- No additional HTTP requests
- JavaScript for inline editing is lightweight

### Deployment Steps

1. Review and test migration file locally
2. Add migration to PRODUCTION_DB_STEPS.md under Pending section
3. Manually apply migration to production database via phpMyAdmin
4. Deploy code changes to production
5. Verify functionality on production
6. Move migration from Pending to Applied in PRODUCTION_DB_STEPS.md

