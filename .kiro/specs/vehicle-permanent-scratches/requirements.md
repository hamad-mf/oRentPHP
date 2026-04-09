# Requirements Document

## Introduction

This feature enables permanent scratch and damage tracking for vehicles. Unlike per-reservation scratch photos (stored in `reservation_scratch_photos`), permanent scratches are vehicle-specific and automatically appear in both delivery and return inspection photo sections across all reservations. This allows staff to document pre-existing vehicle damage once and have it consistently visible during all future rental transactions.

## Glossary

- **Permanent_Scratch_Manager**: The new submenu under Vehicles for managing permanent scratches
- **Permanent_Scratch**: A scratch or damage record permanently associated with a specific vehicle
- **Scratch_Photo_Section**: The delivery or return inspection interface where scratch photos are displayed and captured
- **Reservation_Scratch_Photo**: A per-reservation scratch photo stored in the existing `reservation_scratch_photos` table
- **Vehicle_Permanent_Scratches_Table**: The new database table storing permanent scratch records
- **Auto_Populate**: The process of automatically displaying permanent scratches in delivery/return interfaces

## Requirements

### Requirement 1: Permanent Scratch Management Submenu

**User Story:** As a fleet manager, I want a dedicated submenu for managing permanent vehicle scratches, so that I can access scratch management features easily.

#### Acceptance Criteria

1. THE Permanent_Scratch_Manager SHALL appear as a submenu item under the Vehicles menu
2. WHEN a user with vehicle management permissions accesses the Permanent_Scratch_Manager, THE System SHALL display the permanent scratch management interface
3. THE Permanent_Scratch_Manager SHALL provide vehicle selection functionality before displaying scratch records

### Requirement 2: Add Permanent Scratches

**User Story:** As a fleet manager, I want to add permanent scratches to specific vehicles, so that pre-existing damage is documented and visible across all reservations.

#### Acceptance Criteria

1. WHEN a vehicle is selected in the Permanent_Scratch_Manager, THE System SHALL display an interface to add new permanent scratches
2. THE System SHALL allow uploading a photo file for each permanent scratch
3. THE System SHALL require a description text field for each permanent scratch (maximum 255 characters)
4. WHEN a permanent scratch is saved, THE System SHALL store the photo file path, description, vehicle ID, and creation timestamp in the Vehicle_Permanent_Scratches_Table
5. THE System SHALL support adding multiple permanent scratches per vehicle
6. WHEN a permanent scratch photo is uploaded, THE System SHALL store it in the uploads/permanent_scratches directory
7. THE System SHALL generate unique filenames using the pattern: permanent_{vehicle_id}_{slot_index}_{timestamp}.{extension}

### Requirement 3: Remove Permanent Scratches

**User Story:** As a fleet manager, I want to remove permanent scratches from vehicles, so that I can update records when damage is repaired or incorrectly documented.

#### Acceptance Criteria

1. WHEN viewing permanent scratches for a vehicle, THE System SHALL display a delete action for each scratch record
2. WHEN a user confirms deletion of a permanent scratch, THE System SHALL remove the record from the Vehicle_Permanent_Scratches_Table
3. WHEN a permanent scratch is deleted, THE System SHALL delete the associated photo file from the filesystem
4. THE System SHALL require confirmation before deleting a permanent scratch

### Requirement 4: Auto-Populate Permanent Scratches in Delivery Interface

**User Story:** As a delivery staff member, I want permanent scratches to automatically appear in the delivery scratch photo section, so that I can see pre-existing damage without manual lookup.

#### Acceptance Criteria

1. WHEN the delivery page loads for a reservation, THE System SHALL retrieve all permanent scratches for the associated vehicle
2. THE System SHALL display permanent scratch photos in the delivery Scratch_Photo_Section with a visual indicator distinguishing them from new scratches
3. THE System SHALL display permanent scratch descriptions alongside each photo
4. THE System SHALL prevent deletion or modification of permanent scratches from the delivery interface
5. THE System SHALL allow adding new Reservation_Scratch_Photos alongside displayed permanent scratches

### Requirement 5: Auto-Populate Permanent Scratches in Return Interface

**User Story:** As a return staff member, I want permanent scratches to automatically appear in the return scratch photo section, so that I can compare vehicle condition against known pre-existing damage.

#### Acceptance Criteria

1. WHEN the return page loads for a reservation, THE System SHALL retrieve all permanent scratches for the associated vehicle
2. THE System SHALL display permanent scratch photos in the return Scratch_Photo_Section with a visual indicator distinguishing them from new scratches
3. THE System SHALL display permanent scratch descriptions alongside each photo
4. THE System SHALL prevent deletion or modification of permanent scratches from the return interface
5. THE System SHALL allow adding new Reservation_Scratch_Photos alongside displayed permanent scratches

### Requirement 6: Database Schema for Permanent Scratches

**User Story:** As a system administrator, I want a dedicated database table for permanent scratches, so that data is stored reliably and separately from per-reservation scratch photos.

#### Acceptance Criteria

1. THE System SHALL create a Vehicle_Permanent_Scratches_Table with columns: id (INT AUTO_INCREMENT PRIMARY KEY), vehicle_id (INT NOT NULL), description (VARCHAR 255 NOT NULL), file_path (VARCHAR 255 NOT NULL), created_at (DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP), created_by (INT NULL)
2. THE Vehicle_Permanent_Scratches_Table SHALL include a foreign key constraint on vehicle_id referencing vehicles(id) with ON DELETE CASCADE
3. THE Vehicle_Permanent_Scratches_Table SHALL include an index on vehicle_id for query performance
4. THE System SHALL use idempotent SQL migration (CREATE TABLE IF NOT EXISTS) following UPDATE_SESSION_RULES.md
5. THE System SHALL add the migration to PRODUCTION_DB_STEPS.md under Pending

### Requirement 7: Preserve Existing Scratch Photo Functionality

**User Story:** As a system administrator, I want the existing per-reservation scratch photo functionality to remain unchanged, so that current workflows are not disrupted.

#### Acceptance Criteria

1. THE System SHALL continue to support adding Reservation_Scratch_Photos during delivery (up to 15 photos)
2. THE System SHALL continue to support adding Reservation_Scratch_Photos during return (up to 15 photos)
3. THE System SHALL continue to store Reservation_Scratch_Photos in the reservation_scratch_photos table
4. THE System SHALL display both permanent scratches and Reservation_Scratch_Photos in delivery and return interfaces
5. THE System SHALL maintain the existing 15-photo limit for Reservation_Scratch_Photos per event (delivery or return)

### Requirement 8: Vehicle-Specific Scratch Selection

**User Story:** As a fleet manager, I want to select which vehicle to manage scratches for, so that I can work with one vehicle at a time.

#### Acceptance Criteria

1. WHEN the Permanent_Scratch_Manager loads, THE System SHALL display a vehicle selection dropdown or search interface
2. THE System SHALL populate the vehicle selector with all vehicles in the system ordered by license plate
3. WHEN a vehicle is selected, THE System SHALL load and display all permanent scratches for that vehicle
4. THE System SHALL display vehicle identification (brand, model, license plate) in the scratch management interface

### Requirement 9: Permission Control for Permanent Scratch Management

**User Story:** As a system administrator, I want to control who can manage permanent scratches, so that only authorized staff can modify permanent damage records.

#### Acceptance Criteria

1. THE System SHALL restrict access to the Permanent_Scratch_Manager to users with vehicle management permissions
2. WHEN a user without vehicle management permissions attempts to access the Permanent_Scratch_Manager, THE System SHALL redirect to the dashboard with an error message
3. THE System SHALL record the user ID (created_by) when a permanent scratch is added
4. THE System SHALL allow users with vehicle management permissions to delete any permanent scratch regardless of who created it
