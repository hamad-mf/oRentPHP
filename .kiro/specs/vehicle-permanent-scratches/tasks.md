# Implementation Plan: Vehicle Permanent Scratches

## Overview

This implementation adds a permanent scratch tracking system for vehicles that persists across all reservations. The system includes a dedicated management interface under the Vehicles menu, database storage for permanent scratch records, and automatic display of permanent scratches in delivery and return inspection interfaces.

## Tasks

- [x] 1. Create database migration and verify schema
  - Create migration file `migrations/releases/2026-04-05_vehicle_permanent_scratches.sql` (already exists)
  - Verify migration follows idempotent pattern (CREATE TABLE IF NOT EXISTS)
  - Verify foreign key constraint on vehicle_id with ON DELETE CASCADE
  - Verify index on vehicle_id for query performance
  - Update PRODUCTION_DB_STEPS.md to move migration from Pending to Applied after manual execution
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 2. Create permanent scratch management page
  - [x] 2.1 Create vehicles/permanent_scratches.php with vehicle selection interface
    - Implement permission check (require 'add_vehicles' permission or admin role)
    - Create vehicle selection dropdown ordered by license_plate
    - Display vehicle identification (brand, model, license_plate) when selected
    - _Requirements: 1.1, 1.2, 1.3, 8.1, 8.2, 8.4, 9.1, 9.2_

  - [x] 2.2 Implement permanent scratch display and add functionality
    - Query and display all permanent scratches for selected vehicle
    - Implement photo upload form with description input (max 255 chars)
    - Create uploads/permanent_scratches/ directory if not exists
    - Generate unique filenames: permanent_{vehicle_id}_{slot_index}_{timestamp}.{ext}
    - Store records in vehicle_permanent_scratches table with created_by tracking
    - Validate photo file type (jpg, jpeg, png, gif) and size (max 5MB)
    - Validate description (required, max 255 chars)
    - Display success/error flash messages
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 8.3, 9.3_

  - [ ]* 2.3 Write property test for description validation
    - **Property 1: Description Validation**
    - **Validates: Requirements 2.3**

  - [ ]* 2.4 Write property test for complete scratch persistence
    - **Property 2: Complete Scratch Persistence**
    - **Validates: Requirements 2.4, 2.6, 2.7**

  - [x] 2.5 Implement permanent scratch deletion functionality
    - Add delete action with confirmation modal
    - Delete database record from vehicle_permanent_scratches table
    - Delete associated photo file from filesystem
    - Handle graceful degradation if file doesn't exist
    - Display success/error flash messages
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ]* 2.6 Write property test for complete scratch deletion
    - **Property 3: Complete Scratch Deletion**
    - **Validates: Requirements 3.2, 3.3**

- [x] 3. Add navigation menu integration
  - Modify includes/header.php to add "Permanent Scratches" submenu under Vehicles
  - Position after "Job Card" submenu item
  - Apply active state styling when on permanent_scratches.php page
  - Ensure submenu only visible to users with vehicle management permissions
  - _Requirements: 1.1_

- [ ] 4. Integrate permanent scratches into delivery page
  - [x] 4.1 Modify reservations/deliver.php to fetch and display permanent scratches
    - Query vehicle_permanent_scratches table by vehicle_id from reservation
    - Display permanent scratches in read-only section above reservation scratch photo inputs
    - Add visual indicator (badge "Permanent") to distinguish from new scratches
    - Display description alongside each permanent scratch photo
    - Ensure permanent scratches cannot be deleted from delivery interface
    - Maintain existing 15-photo limit for reservation scratch photos
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ]* 4.2 Write property test for inspection page data fetching
    - **Property 4: Inspection Page Data Fetching**
    - **Validates: Requirements 4.1, 5.1**

  - [ ]* 4.3 Write property test for description display completeness
    - **Property 5: Description Display Completeness**
    - **Validates: Requirements 4.3, 5.3**

- [ ] 5. Integrate permanent scratches into return page
  - [x] 5.1 Modify reservations/return.php to fetch and display permanent scratches
    - Query vehicle_permanent_scratches table by vehicle_id from reservation
    - Display permanent scratches in read-only section above reservation scratch photo inputs
    - Add visual indicator (badge "Permanent") to distinguish from new scratches
    - Display description alongside each permanent scratch photo
    - Ensure permanent scratches cannot be deleted from return interface
    - Maintain existing 15-photo limit for reservation scratch photos
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 7.1, 7.2, 7.3, 7.4, 7.5_

  - [ ]* 5.2 Write property test for data separation
    - **Property 7: Data Separation**
    - **Validates: Requirements 7.3**

  - [ ]* 5.3 Write property test for reservation photo limit enforcement
    - **Property 8: Reservation Photo Limit Enforcement**
    - **Validates: Requirements 7.5**

- [x] 6. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Additional property tests for database integrity
  - [ ]* 7.1 Write property test for foreign key cascade deletion
    - **Property 6: Foreign Key Cascade Deletion**
    - **Validates: Requirements 6.2**

  - [ ]* 7.2 Write property test for vehicle selector ordering
    - **Property 9: Vehicle Selector Ordering**
    - **Validates: Requirements 8.2**

  - [ ]* 7.3 Write property test for vehicle-specific filtering
    - **Property 10: Vehicle-Specific Filtering**
    - **Validates: Requirements 8.3**

  - [ ]* 7.4 Write property test for access control
    - **Property 11: Access Control**
    - **Validates: Requirements 9.1**

  - [ ]* 7.5 Write property test for audit trail
    - **Property 12: Audit Trail**
    - **Validates: Requirements 9.3**

- [x] 8. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The migration file already exists and follows idempotent pattern
- Permanent scratches are stored separately from reservation scratch photos
- Visual distinction (badge, styling) prevents confusion between permanent and per-reservation scratches
- Permission checks ensure only authorized users can manage permanent scratches
- File naming convention ensures uniqueness and traceability
- Foreign key cascade ensures data integrity when vehicles are deleted
- Property tests validate universal correctness properties across all inputs
- Unit tests (not listed) should be written for specific examples and edge cases
