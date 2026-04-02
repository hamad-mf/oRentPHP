# Design Document: Vehicle Inspection Job Card

## Overview

This feature implements a standalone digital vehicle inspection job card system that replicates the physical inspection checklist. The job card is a dedicated page (`vehicles/job_card.php`) where staff can perform comprehensive 37-point vehicle inspections independent of reservations. The form captures vehicle identification, inspection results for all 37 predefined items, and optional notes for each item. All data is stored in new database tables designed specifically for standalone inspections, separate from the existing reservation-based `vehicle_inspections` table.

The implementation follows oRentPHP patterns: dark theme styling (mb-surface, mb-accent, mb-subtle), PDO database access, idempotent migrations with IF NOT EXISTS guards, and permission-based access control.

## Architecture

### Data Flow

1. **Access**: Staff navigates to `vehicles/job_card.php` → authentication check → permission verification
2. **Display**: Form renders → company header → vehicle selector → 37-item checklist table
3. **Input**: Staff selects vehicle → checks items → adds notes → clicks Save
4. **Validation**: Form submission → vehicle selection required → note length validation
5. **Storage**: PHP processes POST → database transaction → header record + 37 item records inserted
6. **Confirmation**: Success message displayed → form cleared for next inspection

### Component Interaction

```
job_card.php (Form Page)
    ↓
Authentication & Permission Check
    ↓
Vehicle Dropdown (from vehicles table)
    ↓
37-Item Checklist Table
    ↓
POST Submission
    ↓
Validation (vehicle required, notes ≤255 chars)
    ↓
Database Transaction
    ├─ INSERT INTO vehicle_job_cards (header)
    └─ INSERT INTO vehicle_job_card_items (37 rows)
    ↓
Success Message & Form Reset
```

## Components and Interfaces

### 1. Database Schema

**New Table: `vehicle_job_cards`** (Header/Master Record)

```sql
CREATE TABLE IF NOT EXISTS vehicle_job_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    inspection_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**New Table: `vehicle_job_card_items`** (Detail Records)

```sql
CREATE TABLE IF NOT EXISTS vehicle_job_card_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    item_number INT NOT NULL COMMENT 'Serial number 1-37',
    item_name VARCHAR(100) NOT NULL,
    is_checked TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL DEFAULT NULL,
    FOREIGN KEY (job_card_id) REFERENCES vehicle_job_cards(id) ON DELETE CASCADE,
    INDEX idx_job_card (job_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Design Rationale**:
- Separate tables for header and items (normalized design)
- `vehicle_id` links to vehicles table (not reservations)
- `created_by` tracks which staff member performed inspection
- `item_number` preserves display order (1-37)
- `item_name` stored with each record for data integrity
- `is_checked` uses TINYINT(1) for boolean (0=unchecked, 1=checked)
- `note` is optional (NULL allowed), max 255 characters
- Foreign keys with CASCADE delete (cleanup when vehicle/job card deleted)
- Index on `job_card_id` for efficient item retrieval

### 2. Job Card Form Page (`vehicles/job_card.php`)

**File Location**: `vehicles/job_card.php`

**Access Control**:
```php
<?php
require_once __DIR__ . '/../config/db.php';

// Permission check: require 'add_vehicles' permission
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to access vehicle inspection job cards.');
    redirect('../index.php');
}

$pdo = db();
```

**Company Header Section**:
```php
<!-- Company Header -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <!-- Logo placeholder -->
            <div class="w-16 h-16 bg-mb-accent/10 rounded-lg flex items-center justify-center">
                <svg class="w-8 h-8 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-light text-white">Vehicle Inspection Job Card</h1>
                <p class="text-sm text-mb-subtle mt-1">oRent Vehicle Management System</p>
            </div>
        </div>
        <div class="text-right text-sm text-mb-subtle">
            <p>Contact: +1 (555) 123-4567</p>
            <p>Email: info@orent.com</p>
        </div>
    </div>
</div>
```

**Vehicle Selector**:
```php
<!-- Vehicle Selection -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
    <label class="block text-sm font-medium text-mb-silver mb-3">
        Select Vehicle <span class="text-red-400">*</span>
    </label>
    <select name="vehicle_id" id="vehicleSelect" required
            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 
                   text-white focus:outline-none focus:border-mb-accent transition-colors">
        <option value="">-- Select Vehicle --</option>
        <?php
        $vehicles = $pdo->query("SELECT id, brand, model, license_plate, status 
                                 FROM vehicles 
                                 WHERE status != 'sold' 
                                 ORDER BY brand, model")->fetchAll();
        foreach ($vehicles as $v):
        ?>
            <option value="<?= $v['id'] ?>">
                <?= e($v['brand']) ?> <?= e($v['model']) ?> - <?= e($v['license_plate']) ?>
                <?php if ($v['status'] !== 'available'): ?>
                    (<?= ucfirst($v['status']) ?>)
                <?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($errors['vehicle_id'])): ?>
        <p class="text-red-400 text-sm mt-2"><?= e($errors['vehicle_id']) ?></p>
    <?php endif; ?>
</div>
```

**Inspection Checklist Table**:
```php
<!-- Inspection Checklist -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-mb-subtle/10">
        <h3 class="text-white font-light">Inspection Checklist</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-mb-black/40">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider w-20">
                        S No.
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider">
                        Content
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-mb-subtle uppercase tracking-wider w-32">
                        Check
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider">
                        Note
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mb-subtle/10">
                <?php
                $inspectionItems = [
                    'Car Number', 'Kilometer', 'Scratches', 'Service Kilometer Checkin',
                    'Alignment Kilometer Checkin', 'Tyre Condition', 'Tyre Pressure',
                    'Engine Oil', 'Air Filter', 'Coolant', 'Brake Fluid', 'Fuel Filter',
                    'Washer Fluid', 'Electric Checking', 'Brake Pads', 'Hand Brake',
                    'Head Lights', 'Indicators', 'Seat Belts', 'Wipers', 'Battery Terminal',
                    'Battery Water', 'AC', 'AC filter', 'Music System', 'Lights',
                    'Stepni Tyre', 'Jacky', 'Interior Cleaning', 'Washing',
                    'Car Small Checking', 'Seat Condition and Cleaning', 'Tyre Polishing',
                    'Papers Checking', 'Fine Checking', 'Complaints', 'Final Check Up and Note'
                ];
                
                foreach ($inspectionItems as $index => $itemName):
                    $serialNumber = $index + 1;
                ?>
                <tr class="hover:bg-mb-black/20 transition-colors">
                    <td class="px-4 py-3 text-sm text-mb-silver">
                        <?= $serialNumber ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-white">
                        <?= e($itemName) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" 
                               name="items[<?= $serialNumber ?>][checked]" 
                               value="1"
                               class="w-5 h-5 rounded border-mb-subtle/30 bg-mb-black 
                                      text-mb-accent focus:ring-mb-accent focus:ring-offset-0 
                                      focus:ring-2 cursor-pointer">
                        <input type="hidden" 
                               name="items[<?= $serialNumber ?>][name]" 
                               value="<?= e($itemName) ?>">
                    </td>
                    <td class="px-4 py-3">
                        <input type="text" 
                               name="items[<?= $serialNumber ?>][note]" 
                               maxlength="255"
                               placeholder="Optional note..."
                               class="w-full bg-mb-black border border-mb-subtle/20 rounded 
                                      px-3 py-2 text-sm text-white focus:outline-none 
                                      focus:border-mb-accent transition-colors">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
```

**Form Actions**:
```php
<!-- Form Actions -->
<div class="flex items-center justify-end gap-4">
    <a href="index.php" 
       class="px-6 py-3 border border-mb-subtle/30 text-mb-silver rounded-lg 
              hover:border-white/30 hover:text-white transition-all">
        Cancel
    </a>
    <button type="submit" 
            class="px-6 py-3 bg-mb-accent text-white rounded-lg 
                   hover:bg-mb-accent/80 transition-colors font-medium">
        Save Inspection
    </button>
</div>
```

### 3. Form Processing Logic

**POST Handler**:
```php
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $items = $_POST['items'] ?? [];
    
    // Validation
    if ($vehicleId <= 0) {
        $errors['vehicle_id'] = 'Please select a vehicle.';
    } else {
        // Verify vehicle exists
        $vCheck = $pdo->prepare('SELECT id FROM vehicles WHERE id = ?');
        $vCheck->execute([$vehicleId]);
        if (!$vCheck->fetch()) {
            $errors['vehicle_id'] = 'Selected vehicle does not exist.';
        }
    }
    
    // Validate items array structure
    if (count($items) !== 37) {
        $errors['items'] = 'Invalid inspection data. Please refresh and try again.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert header record
            $headerStmt = $pdo->prepare(
                'INSERT INTO vehicle_job_cards (vehicle_id, created_by) 
                 VALUES (?, ?)'
            );
            $headerStmt->execute([
                $vehicleId,
                $_SESSION['user']['id'] ?? null
            ]);
            $jobCardId = (int) $pdo->lastInsertId();
            
            // Insert all 37 item records
            $itemStmt = $pdo->prepare(
                'INSERT INTO vehicle_job_card_items 
                 (job_card_id, item_number, item_name, is_checked, note) 
                 VALUES (?, ?, ?, ?, ?)'
            );
            
            foreach ($items as $itemNumber => $itemData) {
                $itemName = trim($itemData['name'] ?? '');
                $isChecked = isset($itemData['checked']) && $itemData['checked'] === '1' ? 1 : 0;
                $note = isset($itemData['note']) && trim($itemData['note']) !== '' 
                    ? trim(substr($itemData['note'], 0, 255)) 
                    : null;
                
                $itemStmt->execute([
                    $jobCardId,
                    (int) $itemNumber,
                    $itemName,
                    $isChecked,
                    $note
                ]);
            }
            
            $pdo->commit();
            
            app_log('ACTION', "Vehicle inspection job card created (ID: $jobCardId, Vehicle: $vehicleId)");
            flash('success', 'Vehicle inspection saved successfully.');
            redirect('job_card.php'); // Redirect to clear form
            
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log('ERROR', 'Job card save failed: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'vehicle_id' => $vehicleId
            ]);
            $errors['db'] = 'Could not save inspection. Please try again.';
        }
    }
}
```

### 4. Migration File

**Filename**: `migrations/releases/2026-03-30_vehicle_inspection_job_card.sql`

**Content**:
```sql
-- Release: 2026-03-30_vehicle_inspection_job_card
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds standalone vehicle inspection job card system.
--        vehicle_job_cards — header table for inspection records
--        vehicle_job_card_items — detail table for 37 inspection items

SET FOREIGN_KEY_CHECKS = 0;

-- Create job card header table
CREATE TABLE IF NOT EXISTS vehicle_job_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    inspection_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create job card items table
CREATE TABLE IF NOT EXISTS vehicle_job_card_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    item_number INT NOT NULL COMMENT 'Serial number 1-37',
    item_name VARCHAR(100) NOT NULL,
    is_checked TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL DEFAULT NULL,
    FOREIGN KEY (job_card_id) REFERENCES vehicle_job_cards(id) ON DELETE CASCADE,
    INDEX idx_job_card (job_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
```

## Data Models

### Entity Relationship

```
vehicles (existing)
    ↓ (1:N)
vehicle_job_cards
    ↓ (1:37)
vehicle_job_card_items

users (existing)
    ↓ (1:N)
vehicle_job_cards
```

### vehicle_job_cards Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique job card identifier |
| vehicle_id | INT | NOT NULL, FOREIGN KEY | Links to vehicles.id |
| inspection_date | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | When inspection was performed |
| created_by | INT | NULL, FOREIGN KEY | Links to users.id (staff member) |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Record creation timestamp |

### vehicle_job_card_items Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PRIMARY KEY, AUTO_INCREMENT | Unique item identifier |
| job_card_id | INT | NOT NULL, FOREIGN KEY, INDEXED | Links to vehicle_job_cards.id |
| item_number | INT | NOT NULL | Serial number (1-37) |
| item_name | VARCHAR(100) | NOT NULL | Inspection item name |
| is_checked | TINYINT(1) | NOT NULL, DEFAULT 0 | Checkbox status (0=unchecked, 1=checked) |
| note | VARCHAR(255) | NULL | Optional note text |

### 37 Inspection Items (Fixed List)

1. Car Number
2. Kilometer
3. Scratches
4. Service Kilometer Checkin
5. Alignment Kilometer Checkin
6. Tyre Condition
7. Tyre Pressure
8. Engine Oil
9. Air Filter
10. Coolant
11. Brake Fluid
12. Fuel Filter
13. Washer Fluid
14. Electric Checking
15. Brake Pads
16. Hand Brake
17. Head Lights
18. Indicators
19. Seat Belts
20. Wipers
21. Battery Terminal
22. Battery Water
23. AC
24. AC filter
25. Music System
26. Lights
27. Stepni Tyre
28. Jacky
29. Interior Cleaning
30. Washing
31. Car Small Checking
32. Seat Condition and Cleaning
33. Tyre Polishing
34. Papers Checking
35. Fine Checking
36. Complaints
37. Final Check Up and Note


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, I identified the following redundancies:

- **Redundancy 1**: Properties 4.1 and 4.2 (checkbox and text input exist for each item) can be combined with 3.3 and 3.4 (serial number and item name display) into a single comprehensive property about complete row rendering.
- **Redundancy 2**: Properties 6.3 and 6.4 (header record creation and 37 item records creation) can be combined into a single property about complete inspection persistence.
- **Redundancy 3**: Properties 5.3, 5.4, and 5.5 (save to database, success message, form clear) can be tested as separate properties since they validate different aspects of the save operation.
- **Redundancy 4**: Properties 9.3 and 9.4 (authentication check and permission check) can be combined into a single access control property.

After reflection, the following properties provide unique validation value:

### Property 1: Vehicle Dropdown Completeness

*For any* set of non-sold vehicles in the database, the vehicle selector dropdown should contain exactly those vehicles with their brand, model, and license plate displayed.

**Validates: Requirements 2.1, 2.2**

### Property 2: Inspection Item Row Completeness

*For any* inspection item at position N (where 1 ≤ N ≤ 37), the rendered table row should contain: serial number N, the item name, a checkbox input, and a text input for notes.

**Validates: Requirements 3.3, 3.4, 4.1, 4.2**

### Property 3: Checkbox State Persistence

*For any* inspection item, setting the checkbox to checked or unchecked should result in that state being preserved when the form is submitted and saved to the database.

**Validates: Requirements 4.3**

### Property 4: Note Text Truncation

*For any* note text exceeding 255 characters, the system should truncate it to exactly 255 characters before saving to the database.

**Validates: Requirements 4.4, 10.2**

### Property 5: Optional Note Validation

*For any* inspection item submitted without a note, the form should accept the submission as valid and save the item with a NULL note value.

**Validates: Requirements 4.5**

### Property 6: Complete Inspection Persistence

*For any* valid form submission with a selected vehicle, the system should create exactly one header record in vehicle_job_cards and exactly 37 item records in vehicle_job_card_items, all linked by the job_card_id.

**Validates: Requirements 5.3, 6.3, 6.4, 10.3**

### Property 7: Inspection Data Round-Trip

*For any* inspection with specific checkbox states and note values for all 37 items, saving and then retrieving the inspection should return the exact same checkbox states and note values.

**Validates: Requirements 5.3, 6.4**

### Property 8: Success Feedback and Form Reset

*For any* successful inspection save, the system should display a success message and clear all form inputs (vehicle selection, checkboxes, and notes).

**Validates: Requirements 5.4, 5.5**

### Property 9: Timestamp Recording

*For any* saved inspection, the created_at timestamp in the vehicle_job_cards table should be set to the current timestamp at the time of save.

**Validates: Requirements 6.5**

### Property 10: Migration Idempotency

*For any* number of times the migration script is executed, the database should end up in the same state with both tables created and no errors thrown.

**Validates: Requirements 7.3**

### Property 11: Access Control Enforcement

*For any* request to the job card page, the system should verify both user authentication and the 'add_vehicles' permission before allowing access to the form or processing submissions.

**Validates: Requirements 9.3, 9.4**

### Property 12: Audit Trail Recording

*For any* inspection saved by an authenticated user, the created_by field in the vehicle_job_cards table should be set to that user's ID.

**Validates: Requirements 9.5**

### Property 13: Valid Submission No Errors

*For any* form submission with a valid vehicle selection and properly formatted data, the system should not display any validation errors.

**Validates: Requirements 10.4**

## Error Handling

### Form Submission Errors

**Scenario**: No vehicle selected
- **Handling**: Validation error displayed: "Please select a vehicle."
- **User Experience**: Error message shown above vehicle selector, form data preserved
- **Edge Case**: Covered by validation in POST handler

**Scenario**: Invalid vehicle ID submitted (tampered form data)
- **Handling**: Database query returns no vehicle, validation error displayed
- **User Experience**: Error message: "Selected vehicle does not exist."
- **Security**: Prevents injection of non-existent vehicle IDs

**Scenario**: Note text exceeds 255 characters
- **Handling**: PHP truncates to 255 characters using `substr()` before database insert
- **User Experience**: Silent truncation (no error shown, data saved)
- **Rationale**: Graceful degradation, database constraint prevents longer values

**Scenario**: Missing or malformed items array
- **Handling**: Validation checks count($items) === 37, displays error if mismatch
- **User Experience**: Error message: "Invalid inspection data. Please refresh and try again."
- **Security**: Prevents partial or corrupted submissions

**Scenario**: Database connection failure during save
- **Handling**: Transaction rollback, exception caught, error logged
- **User Experience**: Error message: "Could not save inspection. Please try again."
- **Logging**: Full exception details logged via app_log()

**Scenario**: Foreign key constraint violation (vehicle deleted mid-submission)
- **Handling**: Database exception caught, transaction rolled back
- **User Experience**: Error message: "Could not save inspection. Please try again."
- **Logging**: Exception logged with vehicle_id context

### Access Control Errors

**Scenario**: Unauthenticated user accesses job_card.php
- **Handling**: Redirect to login page via auth check in config/db.php
- **User Experience**: Redirected to login, flash message: "Please log in to continue."
- **Security**: No form data exposed to unauthenticated users

**Scenario**: Authenticated user without 'add_vehicles' permission
- **Handling**: Permission check fails, flash error, redirect to index
- **User Experience**: Error message: "You do not have permission to access vehicle inspection job cards."
- **Security**: Permission verified before any form rendering or processing

### Migration Errors

**Scenario**: Migration run on database without vehicles table
- **Handling**: Foreign key constraint fails, migration aborts
- **User Experience**: Admin sees SQL error in phpMyAdmin
- **Resolution**: Base schema must exist before running migration

**Scenario**: Migration run multiple times
- **Handling**: IF NOT EXISTS guards prevent duplicate table errors
- **User Experience**: No errors, silent success
- **Idempotency**: Safe to re-run without side effects

**Scenario**: Migration run with insufficient privileges
- **Handling**: MySQL permission error
- **User Experience**: Admin sees permission denied error
- **Resolution**: Ensure database user has CREATE TABLE privileges

### Display Errors

**Scenario**: No vehicles in database
- **Handling**: Dropdown shows only "-- Select Vehicle --" option
- **User Experience**: Form still functional, validation will catch empty selection
- **Graceful**: No errors displayed, form remains usable

**Scenario**: Vehicle deleted after form loaded but before submission
- **Handling**: Validation query returns no vehicle, error displayed
- **User Experience**: Error message: "Selected vehicle does not exist."
- **Recovery**: User can refresh and select a different vehicle

## Testing Strategy

### Unit Testing

**Form Rendering Tests**:
- Test 1: Verify page loads with 200 status for authenticated user with permission
- Test 2: Verify company header section renders with logo and contact info
- Test 3: Verify vehicle dropdown contains all non-sold vehicles
- Test 4: Verify exactly 37 table rows render with correct item names
- Test 5: Verify each row has checkbox and text input
- Test 6: Verify Save button renders at bottom of form

**Database Schema Tests**:
- Test 7: Verify vehicle_job_cards table exists with correct columns
- Test 8: Verify vehicle_job_card_items table exists with correct columns
- Test 9: Verify foreign key constraints exist (vehicle_id, job_card_id, created_by)
- Test 10: Verify index exists on vehicle_job_card_items.job_card_id

**Validation Tests**:
- Test 11: Submit form without vehicle selection → validation error displayed
- Test 12: Submit form with invalid vehicle ID → validation error displayed
- Test 13: Submit form with valid data → no validation errors
- Test 14: Submit note with 300 characters → truncated to 255 in database

**Access Control Tests**:
- Test 15: Unauthenticated request → redirect to login
- Test 16: Authenticated user without permission → access denied error
- Test 17: Authenticated user with permission → form renders

**Migration Tests**:
- Test 18: Run migration on fresh database → tables created successfully
- Test 19: Run migration twice → no errors (idempotency)
- Test 20: Verify migration filename matches pattern YYYY-MM-DD_feature_name.sql

### Property-Based Testing

**Configuration**: Minimum 100 iterations per property test using a PHP property-based testing library (e.g., Eris or php-quickcheck).

**Property Test 1: Vehicle Dropdown Completeness**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 1: 
 * For any set of non-sold vehicles, the dropdown should contain 
 * exactly those vehicles with brand, model, and license plate.
 */
function test_vehicle_dropdown_completeness() {
    // Generate random set of vehicles (mix of available, rented, maintenance)
    // Insert into database
    // Render job card page
    // Parse vehicle dropdown options
    // Assert: dropdown count === vehicle count
    // Assert: each vehicle appears with correct format
}
```

**Property Test 2: Inspection Item Row Completeness**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 2: 
 * For any inspection item at position N, the row should contain 
 * serial number N, item name, checkbox, and text input.
 */
function test_inspection_item_row_completeness() {
    // For each item position 1-37
    // Render job card page
    // Parse table row at position N
    // Assert: serial number === N
    // Assert: item name matches expected
    // Assert: checkbox input exists
    // Assert: text input exists
}
```

**Property Test 3: Checkbox State Persistence**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 3: 
 * For any inspection item, checkbox state should persist 
 * from form submission to database storage.
 */
function test_checkbox_state_persistence() {
    // Generate random checkbox states for all 37 items
    // Submit form with those states
    // Query database for saved inspection
    // Assert: each item's is_checked matches submitted state
}
```

**Property Test 4: Note Text Truncation**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 4: 
 * For any note exceeding 255 characters, the saved value 
 * should be exactly 255 characters.
 */
function test_note_text_truncation() {
    // Generate random note text (256-500 characters)
    // Submit form with that note for a random item
    // Query database for saved inspection
    // Assert: note length === 255
    // Assert: note === first 255 chars of original
}
```

**Property Test 5: Optional Note Validation**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 5: 
 * For any inspection item without a note, the submission 
 * should be valid and save NULL for that note.
 */
function test_optional_note_validation() {
    // Generate random subset of items to have notes
    // Submit form with notes only for that subset
    // Query database for saved inspection
    // Assert: items with notes have non-NULL values
    // Assert: items without notes have NULL values
}
```

**Property Test 6: Complete Inspection Persistence**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 6: 
 * For any valid submission, exactly 1 header and 37 items 
 * should be saved, all linked by job_card_id.
 */
function test_complete_inspection_persistence() {
    // Generate random vehicle and inspection data
    // Submit form
    // Query database for header records
    // Assert: exactly 1 header record created
    // Query database for item records with that job_card_id
    // Assert: exactly 37 item records created
    // Assert: all items have matching job_card_id
}
```

**Property Test 7: Inspection Data Round-Trip**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 7: 
 * For any inspection data, saving and retrieving should 
 * return the exact same checkbox states and notes.
 */
function test_inspection_data_round_trip() {
    // Generate random checkbox states and notes for all 37 items
    // Submit form with that data
    // Query database for saved inspection
    // Assert: each item's is_checked matches original
    // Assert: each item's note matches original (or NULL)
}
```

**Property Test 8: Success Feedback and Form Reset**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 8: 
 * For any successful save, a success message should appear 
 * and the form should be cleared.
 */
function test_success_feedback_and_form_reset() {
    // Generate random valid inspection data
    // Submit form
    // Assert: success flash message set
    // Assert: redirect to job_card.php (form cleared)
    // Load redirected page
    // Assert: vehicle dropdown reset to empty
    // Assert: all checkboxes unchecked
    // Assert: all note inputs empty
}
```

**Property Test 9: Timestamp Recording**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 9: 
 * For any saved inspection, created_at should be set to 
 * current timestamp.
 */
function test_timestamp_recording() {
    // Record current timestamp before submission
    // Generate random inspection data
    // Submit form
    // Query database for saved inspection
    // Assert: created_at is within 5 seconds of recorded timestamp
}
```

**Property Test 10: Migration Idempotency**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 10: 
 * For any number of migration executions, the database 
 * should end in the same state without errors.
 */
function test_migration_idempotency() {
    // Drop tables if they exist (clean slate)
    // Run migration script
    // Assert: both tables exist
    // Run migration script again
    // Assert: no errors thrown
    // Assert: both tables still exist with same structure
    // Run migration script a third time
    // Assert: no errors thrown
}
```

**Property Test 11: Access Control Enforcement**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 11: 
 * For any request, authentication and permission should 
 * be verified before access is granted.
 */
function test_access_control_enforcement() {
    // Test unauthenticated request
    // Assert: redirect to login
    // Test authenticated user without permission
    // Assert: access denied error
    // Test authenticated user with permission
    // Assert: form renders successfully
}
```

**Property Test 12: Audit Trail Recording**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 12: 
 * For any inspection saved by an authenticated user, 
 * created_by should be set to that user's ID.
 */
function test_audit_trail_recording() {
    // Generate random user with permission
    // Authenticate as that user
    // Generate random inspection data
    // Submit form
    // Query database for saved inspection
    // Assert: created_by === user's ID
}
```

**Property Test 13: Valid Submission No Errors**
```php
/**
 * Feature: vehicle-inspection-job-card, Property 13: 
 * For any valid form submission, no validation errors 
 * should be displayed.
 */
function test_valid_submission_no_errors() {
    // Generate random valid inspection data (vehicle selected, notes ≤255 chars)
    // Submit form
    // Assert: no validation errors in response
    // Assert: success message displayed
}
```

### Integration Testing

**End-to-End Flow**:
1. Authenticate as user with 'add_vehicles' permission
2. Navigate to vehicles/job_card.php
3. Verify form renders with company header, vehicle dropdown, and 37-item table
4. Select a vehicle from dropdown
5. Check random subset of checkboxes (e.g., items 1, 5, 12, 20, 37)
6. Add notes to random subset of items (e.g., items 3, 15, 30)
7. Click Save button
8. Verify success message displayed
9. Verify redirect to job_card.php with cleared form
10. Query database to verify 1 header record and 37 item records saved
11. Verify checkbox states match submitted values
12. Verify notes match submitted values (or NULL for empty notes)

**Negative Cases**:
- Submit form without vehicle selection → verify validation error
- Submit form with 300-character note → verify truncated to 255 in database
- Access page as unauthenticated user → verify redirect to login
- Access page as user without permission → verify access denied error
- Delete vehicle mid-submission → verify validation error on submit

### Manual Testing Checklist

- [ ] Page accessible at vehicles/job_card.php
- [ ] Company header displays with logo placeholder and contact info
- [ ] Vehicle dropdown populated with all non-sold vehicles
- [ ] Vehicle dropdown shows brand, model, and license plate for each vehicle
- [ ] Exactly 37 table rows render with correct item names
- [ ] Serial numbers 1-37 display correctly in first column
- [ ] Each row has a checkbox in Check column
- [ ] Each row has a text input in Note column
- [ ] Checkboxes can be checked and unchecked
- [ ] Text inputs accept alphanumeric text
- [ ] Save button displays at bottom of form
- [ ] Form validates vehicle selection (error if empty)
- [ ] Form submits successfully with valid data
- [ ] Success message displays after save
- [ ] Form clears after successful save
- [ ] Database contains 1 header record after save
- [ ] Database contains 37 item records after save
- [ ] Checkbox states persist correctly in database
- [ ] Notes persist correctly in database (or NULL if empty)
- [ ] Notes exceeding 255 characters are truncated
- [ ] created_at timestamp is set correctly
- [ ] created_by is set to current user's ID
- [ ] Unauthenticated users redirected to login
- [ ] Users without permission see access denied error
- [ ] Migration creates both tables successfully
- [ ] Migration can be run multiple times without errors
- [ ] Dark theme styling matches existing oRentPHP pages
- [ ] Layout is responsive on mobile and desktop

## Implementation Notes

### Dark Theme Styling

All UI elements follow the existing oRentPHP dark theme patterns:

- **Backgrounds**: `bg-mb-surface` (cards), `bg-mb-black` (inputs, table header), `bg-mb-black/40` (table rows)
- **Borders**: `border-mb-subtle/20` (default), `border-mb-accent` (focus states)
- **Text**: `text-white` (primary), `text-mb-silver` (labels), `text-mb-subtle` (hints, secondary)
- **Accent**: `bg-mb-accent` (buttons), `text-mb-accent` (links, focused elements)
- **Hover States**: `hover:bg-mb-black/20` (table rows), `hover:bg-mb-accent/80` (buttons)

### Responsive Design

- Table uses `overflow-x-auto` wrapper for horizontal scrolling on mobile
- Form inputs use full width (`w-full`) to adapt to container
- Company header stacks vertically on small screens (flex-col on mobile)
- Buttons use appropriate padding for touch targets on mobile

### Performance Considerations

- Single query to load all vehicles (no N+1 problem)
- Transaction used for atomic save (header + 37 items)
- Index on `job_card_id` for efficient item retrieval
- No additional queries needed for form rendering

### Security Considerations

- Permission check before form access (`add_vehicles` required)
- Vehicle ID validated against database before save
- Note text sanitized with `e()` helper on display (XSS prevention)
- Note text truncated to 255 characters before insert (SQL injection prevention)
- Transaction rollback on any database error (data integrity)
- User ID captured from session (audit trail)

### Backward Compatibility

- New tables do not modify existing schema
- Separate from reservation-based `vehicle_inspections` table
- No impact on existing vehicle or reservation functionality
- Migration is additive only (no data modifications)

### Code Reusability

- Uses existing `auth_has_perm()` for permission checks
- Uses existing `flash()` and `redirect()` helpers
- Uses existing `e()` helper for output escaping
- Uses existing `app_log()` for error logging
- Follows existing form styling patterns from other pages

## Deployment Checklist

1. **Code Changes**:
   - [ ] Create `vehicles/job_card.php` with form and POST handler
   - [ ] Add navigation link to job card page (if needed)
   - [ ] Test form rendering on local environment
   - [ ] Test form submission on local environment

2. **Database Migration**:
   - [ ] Create migration file `migrations/releases/2026-03-30_vehicle_inspection_job_card.sql`
   - [ ] Test migration on local database
   - [ ] Verify tables created with correct structure
   - [ ] Verify foreign keys and indexes created
   - [ ] Backup production database
   - [ ] Run migration on production via phpMyAdmin
   - [ ] Verify migration success on production

3. **Testing**:
   - [ ] Run unit tests for form rendering and validation
   - [ ] Run property-based tests (100+ iterations each)
   - [ ] Perform manual testing on staging environment
   - [ ] Test access control (unauthenticated, unauthorized, authorized)
   - [ ] Test edge cases (no vehicle, long notes, etc.)

4. **Documentation**:
   - [ ] Update user guide with job card instructions (if exists)
   - [ ] Document 37 inspection items for staff reference
   - [ ] Add entry to release notes

5. **Production Verification**:
   - [ ] Verify page accessible at vehicles/job_card.php
   - [ ] Verify permission check working correctly
   - [ ] Submit test inspection and verify database records
   - [ ] Verify no errors in production logs

## Future Enhancements

- **Inspection History**: View past inspections for a vehicle on vehicles/show.php
- **PDF Export**: Generate printable PDF of completed inspection
- **Photo Attachments**: Allow staff to attach photos to specific inspection items
- **Inspection Templates**: Create custom inspection templates for different vehicle types
- **Scheduled Inspections**: Set up recurring inspection reminders for vehicles
- **Inspection Analytics**: Dashboard showing inspection trends and common issues
- **Mobile App**: Native mobile app for field inspections
- **Barcode Scanning**: Scan vehicle barcode to auto-select vehicle
- **Digital Signature**: Capture staff signature on completed inspections
- **Inspection Comparison**: Compare current inspection with previous inspections to track changes

