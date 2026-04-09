# Design Document: Vehicle Permanent Scratches

## Overview

This feature introduces a permanent scratch tracking system for vehicles that persists across all reservations. Unlike the existing per-reservation scratch photos (`reservation_scratch_photos`), permanent scratches are vehicle-specific and automatically displayed during delivery and return inspections. This allows fleet managers to document pre-existing vehicle damage once and have it consistently visible during all rental transactions, reducing disputes and improving damage accountability.

The system integrates with the existing inspection workflow by:
- Adding a new submenu under Vehicles for permanent scratch management
- Creating a dedicated database table for permanent scratch storage
- Auto-populating permanent scratches in delivery and return interfaces
- Maintaining clear visual distinction between permanent and per-reservation scratches

## Architecture

### System Components

1. **Permanent Scratch Management Interface** (`vehicles/permanent_scratches.php`)
   - Vehicle selection dropdown
   - Scratch list display with photos and descriptions
   - Add/delete scratch functionality
   - Photo upload handling

2. **Database Layer** (`vehicle_permanent_scratches` table)
   - Stores permanent scratch records with vehicle association
   - Maintains photo file paths and descriptions
   - Tracks creation metadata (timestamp, user)

3. **Integration Layer** (modifications to `reservations/deliver.php` and `reservations/return.php`)
   - Fetches permanent scratches for the reservation's vehicle
   - Displays permanent scratches alongside reservation-specific scratch photos
   - Prevents modification of permanent scratches from inspection interfaces

4. **File Storage** (`uploads/permanent_scratches/` directory)
   - Stores permanent scratch photos separately from reservation photos
   - Uses consistent naming convention: `permanent_{vehicle_id}_{slot_index}_{timestamp}.{ext}`

### Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│                  Permanent Scratch Management                │
│                 (vehicles/permanent_scratches.php)           │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ CRUD Operations
                         ▼
┌─────────────────────────────────────────────────────────────┐
│          vehicle_permanent_scratches Table                   │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ id | vehicle_id | description | file_path | ...      │  │
│  └──────────────────────────────────────────────────────┘  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ Query by vehicle_id
                         ▼
┌─────────────────────────────────────────────────────────────┐
│     Delivery/Return Inspection Interfaces                    │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Permanent Scratches (read-only, labeled)            │  │
│  │  + Reservation Scratch Photos (editable)             │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Technology Stack

- **Backend**: PHP 7.4+ with PDO for database access
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Tailwind CSS, Alpine.js for interactivity
- **File Handling**: PHP file upload with validation
- **Authentication**: Existing auth system with permission checks

## Components and Interfaces

### 1. Database Schema

#### New Table: `vehicle_permanent_scratches`

```sql
CREATE TABLE IF NOT EXISTS vehicle_permanent_scratches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    KEY idx_vps_vehicle (vehicle_id),
    CONSTRAINT fk_vps_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Column Descriptions:**
- `id`: Primary key, auto-increment
- `vehicle_id`: Foreign key to vehicles table, indexed for query performance
- `description`: Text description of the scratch/damage (max 255 chars)
- `file_path`: Relative path to photo file (e.g., `uploads/permanent_scratches/permanent_5_1_1234567890.jpg`)
- `created_at`: Timestamp when scratch was added
- `created_by`: User ID who added the scratch (nullable for backward compatibility)

**Indexes:**
- Primary key on `id`
- Index on `vehicle_id` for efficient vehicle-specific queries
- Foreign key constraint with CASCADE delete (when vehicle is deleted, all its permanent scratches are removed)

### 2. File Structure

#### New File: `vehicles/permanent_scratches.php`

**Purpose**: Main interface for managing permanent scratches per vehicle

**Key Sections:**
- Vehicle selection dropdown (populated from vehicles table)
- Current permanent scratches display (grid layout with photos and descriptions)
- Add scratch form (photo upload + description input)
- Delete scratch action (with confirmation)

**Permissions**: Requires `add_vehicles` permission or admin role

**URL Pattern**: `vehicles/permanent_scratches.php?vehicle_id={id}`

#### Modified Files

**`reservations/deliver.php`** (lines ~200-300, photo section):
- Add query to fetch permanent scratches for `$r['vehicle_id']`
- Display permanent scratches in read-only section above reservation scratch photo inputs
- Add visual indicator (e.g., badge "Permanent") to distinguish from new scratches

**`reservations/return.php`** (lines ~200-300, photo section):
- Add query to fetch permanent scratches for `$r['vehicle_id']`
- Display permanent scratches in read-only section above reservation scratch photo inputs
- Add visual indicator (e.g., badge "Permanent") to distinguish from new scratches

#### New Directory

**`uploads/permanent_scratches/`**
- Created with 0777 permissions (or 0755 for production)
- Stores all permanent scratch photos
- Separate from `uploads/scratch_photos/` (reservation-specific) and `uploads/inspections/` (inspection photos)

### 3. Navigation Integration

**Sidebar Menu Modification** (`includes/header.php`, lines ~400-450):

Add submenu item under Vehicles section:

```php
echo '<a href="' . $root . 'vehicles/permanent_scratches.php" 
    class="block text-xs px-3 py-1.5 rounded-lg ' . 
    ($currentPage === 'permanent_scratches.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . 
    ' transition-colors">Permanent Scratches</a>';
```

Position: After "Job Card" submenu item, before closing the Vehicles submenu div.

### 4. Photo Upload Mechanism

**Upload Handler Pattern** (consistent with existing inspection photo uploads):

```php
// In vehicles/permanent_scratches.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_scratch'])) {
    $vehicleId = (int) $_POST['vehicle_id'];
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($description)) {
        $errors['description'] = 'Description is required.';
    }
    if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors['photo'] = 'Photo is required.';
    }
    
    if (empty($errors)) {
        $dir = __DIR__ . '/../uploads/permanent_scratches/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Get next slot index for this vehicle
        $slotStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 AS next_slot FROM vehicle_permanent_scratches WHERE vehicle_id = ?");
        $slotStmt->execute([$vehicleId]);
        $slotIndex = (int) $slotStmt->fetchColumn();
        
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $filename = "permanent_{$vehicleId}_{$slotIndex}_" . time() . ".{$ext}";
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
            $pdo->prepare("INSERT INTO vehicle_permanent_scratches (vehicle_id, description, file_path, created_by) VALUES (?, ?, ?, ?)")
                ->execute([$vehicleId, $description, 'uploads/permanent_scratches/' . $filename, $_SESSION['user']['id'] ?? null]);
            
            flash('success', 'Permanent scratch added successfully.');
            redirect("permanent_scratches.php?vehicle_id={$vehicleId}");
        }
    }
}
```

**File Validation:**
- Allowed extensions: jpg, jpeg, png, gif
- Max file size: 5MB (configurable)
- MIME type validation for security

### 5. UI/UX Design

#### Permanent Scratch Management Page

**Layout:**
```
┌─────────────────────────────────────────────────────────────┐
│  Permanent Scratches                                         │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Vehicle Selection                                        ││
│  │ [Dropdown: Select Vehicle by License Plate]             ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Current Permanent Scratches (3)                          ││
│  │ ┌──────────┐ ┌──────────┐ ┌──────────┐                 ││
│  │ │  Photo   │ │  Photo   │ │  Photo   │                 ││
│  │ │  [img]   │ │  [img]   │ │  [img]   │                 ││
│  │ │ Desc...  │ │ Desc...  │ │ Desc...  │                 ││
│  │ │ [Delete] │ │ [Delete] │ │ [Delete] │                 ││
│  │ └──────────┘ └──────────┘ └──────────┘                 ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Add New Permanent Scratch                                ││
│  │ Photo: [Choose File]                                     ││
│  │ Description: [Text Input]                                ││
│  │ [Add Scratch Button]                                     ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

**Visual Design:**
- Card-based layout with `bg-mb-surface` background
- Grid display for scratch photos (3 columns on desktop, 1 on mobile)
- Photo thumbnails: 200x200px with object-fit cover
- Delete button: Red with confirmation modal
- Add form: Inline below scratch list

#### Delivery/Return Integration

**Scratch Photo Section Enhancement:**

```
┌─────────────────────────────────────────────────────────────┐
│  Scratch/Damage Photos (Optional, max 15)                   │
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Permanent Scratches (Pre-existing)                       ││
│  │ ┌──────────┐ ┌──────────┐                               ││
│  │ │  Photo   │ │  Photo   │                               ││
│  │ │  [img]   │ │  [img]   │                               ││
│  │ │ Desc...  │ │ Desc...  │                               ││
│  │ │ [BADGE]  │ │ [BADGE]  │  ← "Permanent" badge          ││
│  │ └──────────┘ └──────────┘                               ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ New Scratches (This Reservation)                         ││
│  │ Slot 1: [Choose File]                                    ││
│  │ Slot 2: [Choose File]                                    ││
│  │ ...                                                       ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

**Visual Indicators:**
- Permanent scratches: Blue badge with "Permanent" label
- Permanent scratches: Slightly dimmed/grayed background
- Permanent scratches: No delete button
- Clear section separator between permanent and new scratches

## Data Models

### VehiclePermanentScratch

**Properties:**
- `id` (int): Primary key
- `vehicle_id` (int): Associated vehicle
- `description` (string): Scratch description
- `file_path` (string): Relative path to photo
- `created_at` (DateTime): Creation timestamp
- `created_by` (int|null): User who created the record

**Methods:**
- `getAll(PDO $pdo, int $vehicleId): array` - Fetch all permanent scratches for a vehicle
- `create(PDO $pdo, int $vehicleId, string $description, string $filePath, ?int $userId): int` - Create new scratch record
- `delete(PDO $pdo, int $id): bool` - Delete scratch record and file
- `getById(PDO $pdo, int $id): ?array` - Fetch single scratch by ID

**Example Usage:**
```php
// Fetch permanent scratches for delivery page
$permanentScratches = $pdo->prepare("SELECT * FROM vehicle_permanent_scratches WHERE vehicle_id = ? ORDER BY created_at ASC");
$permanentScratches->execute([$r['vehicle_id']]);
$permanentScratches = $permanentScratches->fetchAll();
```

### Integration with Existing Models

**Reservation Model** (no changes required):
- Continues to use `reservation_scratch_photos` for per-reservation scratches
- Permanent scratches are fetched separately via vehicle_id

**Vehicle Model** (no changes required):
- No new columns needed
- Permanent scratches are in separate table with foreign key

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property Reflection

After analyzing all acceptance criteria, I've identified the following properties and their relationships:

**Redundancy Analysis:**

1. **Properties 4.1 and 5.1** (fetching permanent scratches on delivery/return pages) are identical in logic - both query by vehicle_id. These can be combined into a single property about inspection page data fetching.

2. **Properties 4.3 and 5.3** (displaying descriptions alongside photos) are identical for delivery and return. These can be combined into a single property about UI completeness.

3. **Properties 3.2 and 3.3** (database deletion and file deletion) are both part of the same deletion operation. These should be combined into a single comprehensive property about complete scratch removal.

4. **Properties 2.4, 2.6, and 2.7** (storing data, file location, filename pattern) are all part of the same scratch creation operation. These can be combined into a single comprehensive property about correct scratch persistence.

**Consolidated Properties:**
- Scratch creation property (combines 2.4, 2.6, 2.7)
- Scratch deletion property (combines 3.2, 3.3)
- Inspection page data fetching property (combines 4.1, 5.1)
- Description display property (combines 4.3, 5.3)

**Unique Properties Retained:**
- Description validation (2.3)
- Foreign key cascade (6.2)
- Data separation (7.3)
- Reservation photo limit (7.5)
- Vehicle selector ordering (8.2)
- Vehicle-specific filtering (8.3)
- Access control (9.1)
- Audit trail (9.3)

### Property 1: Description Validation

*For any* permanent scratch submission, if the description is empty or exceeds 255 characters, the system should reject the submission and maintain the current state.

**Validates: Requirements 2.3**

### Property 2: Complete Scratch Persistence

*For any* valid permanent scratch submission (with photo and description), the system should store the record in the vehicle_permanent_scratches table with correct vehicle_id, description, file_path starting with "uploads/permanent_scratches/", filename matching the pattern "permanent_{vehicle_id}_{slot_index}_{timestamp}.{extension}", and creation timestamp.

**Validates: Requirements 2.4, 2.6, 2.7**

### Property 3: Complete Scratch Deletion

*For any* permanent scratch deletion, the system should remove both the database record from vehicle_permanent_scratches table and the associated photo file from the filesystem.

**Validates: Requirements 3.2, 3.3**

### Property 4: Inspection Page Data Fetching

*For any* reservation on delivery or return pages, the system should retrieve all permanent scratches associated with the reservation's vehicle_id.

**Validates: Requirements 4.1, 5.1**

### Property 5: Description Display Completeness

*For any* permanent scratch displayed on delivery or return pages, the system should render both the photo and its associated description text.

**Validates: Requirements 4.3, 5.3**

### Property 6: Foreign Key Cascade Deletion

*For any* vehicle deletion, all permanent scratches associated with that vehicle_id should be automatically deleted from the vehicle_permanent_scratches table due to the ON DELETE CASCADE constraint.

**Validates: Requirements 6.2**

### Property 7: Data Separation

*For any* new reservation scratch photo added during delivery or return, the system should store it in the reservation_scratch_photos table (not vehicle_permanent_scratches table) with the correct reservation_id and event_type.

**Validates: Requirements 7.3**

### Property 8: Reservation Photo Limit Enforcement

*For any* delivery or return event, attempting to upload more than 15 reservation scratch photos should be rejected with a validation error.

**Validates: Requirements 7.5**

### Property 9: Vehicle Selector Ordering

*For any* set of vehicles in the system, the permanent scratch manager's vehicle selector should list them ordered by license_plate in ascending order.

**Validates: Requirements 8.2**

### Property 10: Vehicle-Specific Filtering

*For any* vehicle selection in the permanent scratch manager, the system should display only permanent scratches where vehicle_id matches the selected vehicle.

**Validates: Requirements 8.3**

### Property 11: Access Control

*For any* user without vehicle management permissions (not admin and without 'add_vehicles' permission), attempting to access the permanent scratch manager should result in denial (redirect to dashboard with error message).

**Validates: Requirements 9.1**

### Property 12: Audit Trail

*For any* permanent scratch creation, the system should populate the created_by field with the current authenticated user's ID.

**Validates: Requirements 9.3**

## Error Handling

### Validation Errors

**Photo Upload Errors:**
- Missing photo file → "Photo is required."
- Invalid file type (not jpg/jpeg/png/gif) → "Invalid file type. Only JPG, PNG, and GIF are allowed."
- File size exceeds 5MB → "File size must not exceed 5MB."
- Upload failure (move_uploaded_file fails) → "Failed to upload photo. Please try again."

**Description Errors:**
- Empty description → "Description is required."
- Description exceeds 255 characters → "Description must not exceed 255 characters."

**Permission Errors:**
- Unauthorized access → Redirect to dashboard with flash message: "You do not have permission to manage permanent scratches."

**Database Errors:**
- Foreign key violation (invalid vehicle_id) → "Invalid vehicle selected."
- Duplicate entry (unlikely with auto-increment) → "Failed to save scratch. Please try again."
- Connection failure → "Database error. Please contact support."

### File System Errors

**Directory Creation:**
- If `uploads/permanent_scratches/` doesn't exist and mkdir fails → Log error, display: "Failed to create upload directory. Please contact support."

**File Deletion:**
- If photo file doesn't exist during deletion → Log warning, continue with database deletion (graceful degradation)
- If unlink fails → Log error, display: "Failed to delete photo file. Database record removed."

### Recovery Strategies

**Orphaned Files:**
- If database insert fails after successful file upload → Delete uploaded file to prevent orphans
- Implement cleanup script to remove files without database records (future enhancement)

**Orphaned Records:**
- If file deletion fails but database deletion succeeds → Log error for manual cleanup
- Display warning to user: "Scratch removed, but photo file may need manual cleanup."

**Transaction Handling:**
- Wrap database operations in try-catch blocks
- Use PDO error mode: `PDO::ERRMODE_EXCEPTION`
- Roll back on failure where applicable (though single-operation inserts/deletes don't require explicit transactions)

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests for comprehensive coverage:

**Unit Tests** focus on:
- Specific examples of valid scratch creation and deletion
- Edge cases (empty description, oversized file, invalid vehicle_id)
- Error conditions (missing permissions, database failures)
- Integration points (delivery/return page rendering with permanent scratches)

**Property-Based Tests** focus on:
- Universal properties that hold for all inputs (description validation, filename patterns)
- Comprehensive input coverage through randomization (various vehicle IDs, descriptions, file types)
- Invariants (foreign key cascade, data separation between tables)

### Property-Based Testing Configuration

**Library:** PHPUnit with `quickcheck-php` or `eris` for property-based testing

**Test Configuration:**
- Minimum 100 iterations per property test
- Each test tagged with feature name and property reference
- Tag format: `@group vehicle-permanent-scratches @property {number}`

### Property Test Implementations

#### Property 1: Description Validation
```php
/**
 * @group vehicle-permanent-scratches
 * @property 1: For any permanent scratch submission, if the description is empty or exceeds 255 characters, the system should reject the submission
 */
public function testDescriptionValidation()
{
    $this->forAll(
        Generator::string(),
        Generator::int(1, 100) // vehicle_id
    )->then(function ($description, $vehicleId) {
        $isValid = strlen($description) > 0 && strlen($description) <= 255;
        $result = $this->submitScratch($vehicleId, $description, $this->validPhoto());
        
        if ($isValid) {
            $this->assertTrue($result->success);
        } else {
            $this->assertFalse($result->success);
            $this->assertContains('description', array_keys($result->errors));
        }
    })->runs(100);
}
```

#### Property 2: Complete Scratch Persistence
```php
/**
 * @group vehicle-permanent-scratches
 * @property 2: For any valid permanent scratch submission, the system should store all required data correctly
 */
public function testCompleteScratchPersistence()
{
    $this->forAll(
        Generator::int(1, 100), // vehicle_id
        Generator::string(1, 255), // description
        Generator::elements(['jpg', 'png', 'gif']) // file extension
    )->then(function ($vehicleId, $description, $ext) {
        $photo = $this->generateTestPhoto($ext);
        $result = $this->submitScratch($vehicleId, $description, $photo);
        
        $this->assertTrue($result->success);
        
        $record = $this->fetchScratchById($result->scratchId);
        $this->assertEquals($vehicleId, $record['vehicle_id']);
        $this->assertEquals($description, $record['description']);
        $this->assertStringStartsWith('uploads/permanent_scratches/', $record['file_path']);
        $this->assertMatchesRegularExpression(
            "/permanent_{$vehicleId}_\d+_\d+\.{$ext}/",
            $record['file_path']
        );
        $this->assertFileExists(__DIR__ . '/../' . $record['file_path']);
    })->runs(100);
}
```

#### Property 3: Complete Scratch Deletion
```php
/**
 * @group vehicle-permanent-scratches
 * @property 3: For any permanent scratch deletion, both database record and file should be removed
 */
public function testCompleteScratchDeletion()
{
    $this->forAll(
        Generator::int(1, 100), // vehicle_id
        Generator::string(1, 255) // description
    )->then(function ($vehicleId, $description) {
        // Create scratch
        $photo = $this->generateTestPhoto('jpg');
        $result = $this->submitScratch($vehicleId, $description, $photo);
        $scratchId = $result->scratchId;
        $filePath = $this->fetchScratchById($scratchId)['file_path'];
        
        // Delete scratch
        $deleteResult = $this->deleteScratch($scratchId);
        $this->assertTrue($deleteResult->success);
        
        // Verify both database and file are gone
        $this->assertNull($this->fetchScratchById($scratchId));
        $this->assertFileDoesNotExist(__DIR__ . '/../' . $filePath);
    })->runs(100);
}
```

#### Property 6: Foreign Key Cascade Deletion
```php
/**
 * @group vehicle-permanent-scratches
 * @property 6: For any vehicle deletion, all its permanent scratches should be automatically deleted
 */
public function testForeignKeyCascadeDeletion()
{
    $this->forAll(
        Generator::int(1, 100), // vehicle_id
        Generator::int(1, 5) // number of scratches
    )->then(function ($vehicleId, $scratchCount) {
        // Create vehicle
        $this->createTestVehicle($vehicleId);
        
        // Create multiple scratches for this vehicle
        $scratchIds = [];
        for ($i = 0; $i < $scratchCount; $i++) {
            $result = $this->submitScratch($vehicleId, "Scratch $i", $this->validPhoto());
            $scratchIds[] = $result->scratchId;
        }
        
        // Delete vehicle
        $this->deleteVehicle($vehicleId);
        
        // Verify all scratches are gone
        foreach ($scratchIds as $scratchId) {
            $this->assertNull($this->fetchScratchById($scratchId));
        }
    })->runs(100);
}
```

#### Property 7: Data Separation
```php
/**
 * @group vehicle-permanent-scratches
 * @property 7: For any reservation scratch photo, it should be stored in reservation_scratch_photos table, not vehicle_permanent_scratches
 */
public function testDataSeparation()
{
    $this->forAll(
        Generator::int(1, 100), // reservation_id
        Generator::elements(['delivery', 'return']), // event_type
        Generator::int(1, 15) // slot_index
    )->then(function ($reservationId, $eventType, $slotIndex) {
        $photo = $this->generateTestPhoto('jpg');
        $result = $this->submitReservationScratchPhoto($reservationId, $eventType, $slotIndex, $photo);
        
        $this->assertTrue($result->success);
        
        // Verify it's in reservation_scratch_photos
        $record = $this->fetchReservationScratchPhoto($reservationId, $eventType, $slotIndex);
        $this->assertNotNull($record);
        $this->assertEquals($reservationId, $record['reservation_id']);
        
        // Verify it's NOT in vehicle_permanent_scratches
        $permanentCount = $this->countPermanentScratchesByFilePath($record['file_path']);
        $this->assertEquals(0, $permanentCount);
    })->runs(100);
}
```

#### Property 9: Vehicle Selector Ordering
```php
/**
 * @group vehicle-permanent-scratches
 * @property 9: For any set of vehicles, the selector should list them ordered by license_plate
 */
public function testVehicleSelectorOrdering()
{
    $this->forAll(
        Generator::seq(Generator::string(5, 10)) // array of license plates
    )->then(function ($licensePlates) {
        // Create vehicles with these license plates
        foreach ($licensePlates as $plate) {
            $this->createTestVehicle(null, $plate);
        }
        
        // Fetch vehicle selector options
        $options = $this->getVehicleSelectorOptions();
        $optionPlates = array_column($options, 'license_plate');
        
        // Verify ordering
        $sortedPlates = $licensePlates;
        sort($sortedPlates);
        $this->assertEquals($sortedPlates, $optionPlates);
    })->runs(100);
}
```

### Unit Test Examples

#### Test: Unauthorized Access Redirect
```php
public function testUnauthorizedAccessRedirect()
{
    // Create user without vehicle management permissions
    $user = $this->createTestUser(['permissions' => ['view_finances']]);
    $this->actingAs($user);
    
    // Attempt to access permanent scratch manager
    $response = $this->get('/vehicles/permanent_scratches.php');
    
    // Verify redirect and error message
    $this->assertRedirect('/index.php');
    $this->assertSessionHas('error', 'You do not have permission to manage permanent scratches.');
}
```

#### Test: Permanent Scratches Display on Delivery Page
```php
public function testPermanentScratchesDisplayOnDeliveryPage()
{
    // Create vehicle with permanent scratches
    $vehicle = $this->createTestVehicle();
    $scratch1 = $this->createPermanentScratch($vehicle->id, 'Front bumper dent');
    $scratch2 = $this->createPermanentScratch($vehicle->id, 'Rear door scratch');
    
    // Create reservation for this vehicle
    $reservation = $this->createTestReservation(['vehicle_id' => $vehicle->id, 'status' => 'confirmed']);
    
    // Load delivery page
    $response = $this->get("/reservations/deliver.php?id={$reservation->id}");
    
    // Verify permanent scratches are displayed
    $response->assertSee('Permanent Scratches');
    $response->assertSee('Front bumper dent');
    $response->assertSee('Rear door scratch');
    $response->assertSee($scratch1->file_path);
    $response->assertSee($scratch2->file_path);
    
    // Verify they have "Permanent" badge
    $response->assertSee('Permanent', false); // case-insensitive
}
```

#### Test: Maximum 15 Reservation Scratch Photos
```php
public function testMaximum15ReservationScratchPhotos()
{
    $reservation = $this->createTestReservation(['status' => 'confirmed']);
    
    // Attempt to upload 16 scratch photos
    $photos = [];
    for ($i = 1; $i <= 16; $i++) {
        $photos["scratch_photos[$i]"] = $this->generateTestPhoto('jpg');
    }
    
    $response = $this->post("/reservations/deliver.php?id={$reservation->id}", [
        'fuel_level' => 100,
        'mileage' => 10000,
        'km_limit' => 1000,
        'extra_km_price' => 0.5,
        'photos' => $this->requiredInspectionPhotos(),
        'scratch_photos' => $photos
    ]);
    
    // Verify validation error
    $response->assertSessionHasErrors('scratch_photos');
    $this->assertStringContainsString('maximum of 15', session('errors')['scratch_photos']);
}
```

### Integration Testing

**Test Scenarios:**
1. Complete workflow: Add permanent scratch → View on delivery page → Complete delivery → View on return page
2. Cascade deletion: Create vehicle with scratches → Delete vehicle → Verify scratches removed
3. Permission enforcement: Test access with various user roles (admin, staff with permissions, staff without)
4. File upload edge cases: Large files, invalid types, corrupted uploads
5. Concurrent access: Multiple users managing scratches for same vehicle

### Manual Testing Checklist

- [ ] Navigation: Permanent Scratches submenu appears under Vehicles
- [ ] Vehicle selection: Dropdown populated with all vehicles, ordered by license plate
- [ ] Add scratch: Photo upload + description saves correctly
- [ ] Add scratch: Filename follows pattern `permanent_{vehicle_id}_{slot_index}_{timestamp}.{ext}`
- [ ] Add scratch: File stored in `uploads/permanent_scratches/`
- [ ] Delete scratch: Confirmation modal appears
- [ ] Delete scratch: Both database record and file removed
- [ ] Delivery page: Permanent scratches displayed with "Permanent" badge
- [ ] Delivery page: Permanent scratches are read-only (no delete button)
- [ ] Delivery page: Can still add reservation scratch photos (up to 15)
- [ ] Return page: Permanent scratches displayed with "Permanent" badge
- [ ] Return page: Permanent scratches are read-only (no delete button)
- [ ] Return page: Can still add reservation scratch photos (up to 15)
- [ ] Permissions: Unauthorized users redirected with error message
- [ ] Validation: Empty description rejected
- [ ] Validation: Description > 255 chars rejected
- [ ] Validation: Missing photo rejected
- [ ] Validation: Invalid file type rejected
- [ ] Mobile responsive: All interfaces work on mobile devices

