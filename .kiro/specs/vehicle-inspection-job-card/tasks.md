# Implementation Plan: Vehicle Inspection Job Card

## Overview

This feature implements a standalone digital vehicle inspection job card system that replicates the physical inspection checklist. Staff can perform comprehensive 37-point vehicle inspections independent of reservations through a dedicated page (`vehicles/job_card.php`). The system captures vehicle identification, inspection results for all 37 predefined items, and optional notes. All data is stored in new database tables designed specifically for standalone inspections.

Implementation follows existing oRentPHP patterns: dark theme styling, PDO database access, idempotent migrations, and permission-based access control.

## Tasks

- [x] 1. Create database migration for job card tables
  - Create migration file `migrations/releases/2026-03-30_vehicle_inspection_job_card.sql`
  - Add `vehicle_job_cards` table with columns: id, vehicle_id, inspection_date, created_by, created_at
  - Add `vehicle_job_card_items` table with columns: id, job_card_id, item_number, item_name, is_checked, note
  - Use `CREATE TABLE IF NOT EXISTS` pattern for idempotency
  - Add foreign key constraints: vehicle_id → vehicles(id), created_by → users(id), job_card_id → vehicle_job_cards(id)
  - Add index on vehicle_job_card_items.job_card_id for efficient retrieval
  - Follow existing migration file format with header comments
  - _Requirements: 6.1, 6.2, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 2. Create job card form page structure
  - [x] 2.1 Create vehicles/job_card.php with authentication and permission checks
    - Add authentication check using existing auth system
    - Add permission check for 'add_vehicles' permission
    - Redirect to login if unauthenticated
    - Display access denied error if user lacks permission
    - Initialize PDO database connection
    - _Requirements: 1.1, 9.1, 9.2, 9.3, 9.4_

  - [x] 2.2 Add company header section to job_card.php
    - Create header card with logo placeholder (vehicle icon SVG)
    - Display "Vehicle Inspection Job Card" title
    - Display "oRent Vehicle Management System" subtitle
    - Add contact information section (phone and email)
    - Use dark theme styling (bg-mb-surface, border-mb-subtle)
    - _Requirements: 1.2, 8.1, 8.2_

- [x] 3. Implement vehicle selector dropdown
  - [x] 3.1 Add vehicle selection form section
    - Query vehicles table for all non-sold vehicles
    - Display dropdown with brand, model, and license plate for each vehicle
    - Show vehicle status (rented, maintenance) if not available
    - Add "Select Vehicle" placeholder option
    - Mark field as required with red asterisk
    - Use dark theme form styling (bg-mb-black, border-mb-subtle)
    - _Requirements: 2.1, 2.2, 2.3, 2.5, 8.2, 8.5_

  - [ ]* 3.2 Write property test for vehicle dropdown completeness
    - **Property 1: Vehicle Dropdown Completeness**
    - **Validates: Requirements 2.1, 2.2**
    - For any set of non-sold vehicles, verify dropdown contains exactly those vehicles with correct display format

- [x] 4. Implement 37-item inspection checklist table
  - [x] 4.1 Create inspection items array and table structure
    - Define array of 37 inspection items in correct order
    - Create table with columns: S No., Content, Check, Note
    - Use dark theme table styling (bg-mb-black/40 header, divide-y borders)
    - Add hover effects on table rows (hover:bg-mb-black/20)
    - Make table responsive with overflow-x-auto wrapper
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 8.1, 8.4, 8.5_

  - [x] 4.2 Render inspection item rows with input fields
    - Loop through 37 items and render table rows
    - Display serial number (1-37) in first column
    - Display item name in Content column
    - Add checkbox input in Check column with name="items[N][checked]"
    - Add hidden input for item name with name="items[N][name]"
    - Add text input in Note column with name="items[N][note]", maxlength="255"
    - Style inputs with dark theme (bg-mb-black, border-mb-subtle, text-white)
    - _Requirements: 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 4.5, 8.2_

  - [ ]* 4.3 Write property test for inspection item row completeness
    - **Property 2: Inspection Item Row Completeness**
    - **Validates: Requirements 3.3, 3.4, 4.1, 4.2**
    - For any item position N (1-37), verify row contains serial number, item name, checkbox, and text input

- [x] 5. Add form action buttons
  - Add Cancel button linking to vehicles/index.php
  - Add Save Inspection submit button
  - Style buttons with dark theme (border-mb-subtle for cancel, bg-mb-accent for save)
  - Position buttons at bottom right of form
  - _Requirements: 5.1, 8.3_

- [x] 6. Implement form validation logic
  - [x] 6.1 Add POST request handler with validation
    - Extract vehicle_id from POST data
    - Extract items array from POST data
    - Validate vehicle_id is not empty
    - Validate vehicle exists in database
    - Validate items array contains exactly 37 entries
    - Store validation errors in $errors array
    - Display validation errors above form sections
    - _Requirements: 2.4, 5.2, 10.1, 10.3, 10.4, 10.5_

  - [ ]* 6.2 Write property test for valid submission no errors
    - **Property 13: Valid Submission No Errors**
    - **Validates: Requirements 10.4**
    - For any valid form submission, verify no validation errors are displayed

- [x] 7. Implement database save logic
  - [x] 7.1 Add transaction-based save operation
    - Begin database transaction
    - Insert header record into vehicle_job_cards table
    - Capture last insert ID as job_card_id
    - Loop through 37 items and insert into vehicle_job_card_items table
    - Set is_checked to 1 if checkbox submitted, 0 otherwise
    - Truncate note text to 255 characters using substr()
    - Set note to NULL if empty string
    - Set created_by to current user's ID from session
    - Commit transaction on success
    - Rollback transaction on error
    - _Requirements: 5.3, 6.3, 6.4, 6.5, 9.5, 10.2_

  - [ ]* 7.2 Write property test for complete inspection persistence
    - **Property 6: Complete Inspection Persistence**
    - **Validates: Requirements 5.3, 6.3, 6.4, 10.3**
    - For any valid submission, verify exactly 1 header and 37 items are saved with matching job_card_id

  - [ ]* 7.3 Write property test for checkbox state persistence
    - **Property 3: Checkbox State Persistence**
    - **Validates: Requirements 4.3**
    - For any inspection item, verify checkbox state persists from submission to database

  - [ ]* 7.4 Write property test for note text truncation
    - **Property 4: Note Text Truncation**
    - **Validates: Requirements 4.4, 10.2**
    - For any note exceeding 255 characters, verify saved value is exactly 255 characters

  - [ ]* 7.5 Write property test for optional note validation
    - **Property 5: Optional Note Validation**
    - **Validates: Requirements 4.5**
    - For any item without a note, verify submission is valid and saves NULL

  - [ ]* 7.6 Write property test for inspection data round-trip
    - **Property 7: Inspection Data Round-Trip**
    - **Validates: Requirements 5.3, 6.4**
    - For any inspection data, verify saving and retrieving returns exact same checkbox states and notes

  - [ ]* 7.7 Write property test for timestamp recording
    - **Property 9: Timestamp Recording**
    - **Validates: Requirements 6.5**
    - For any saved inspection, verify created_at is set to current timestamp

  - [ ]* 7.8 Write property test for audit trail recording
    - **Property 12: Audit Trail Recording**
    - **Validates: Requirements 9.5**
    - For any inspection by authenticated user, verify created_by is set to user's ID

- [x] 8. Add success feedback and form reset
  - Log successful save using app_log() with job card ID and vehicle ID
  - Display success flash message "Vehicle inspection saved successfully."
  - Redirect to job_card.php to clear form
  - _Requirements: 5.4, 5.5_

  - [ ]* 8.1 Write property test for success feedback and form reset
    - **Property 8: Success Feedback and Form Reset**
    - **Validates: Requirements 5.4, 5.5**
    - For any successful save, verify success message appears and form is cleared

- [x] 9. Add error handling and logging
  - Catch database exceptions during save operation
  - Rollback transaction if in progress
  - Log error details using app_log() with exception message and context
  - Display user-friendly error message "Could not save inspection. Please try again."
  - Handle foreign key constraint violations gracefully
  - _Requirements: 5.3, 6.3_

- [x] 10. Checkpoint - Test complete feature flow
  - Ensure all tests pass, ask the user if questions arise.

- [ ]* 11. Write property test for access control enforcement
  - **Property 11: Access Control Enforcement**
  - **Validates: Requirements 9.3, 9.4**
  - For any request, verify authentication and permission are checked before access

- [ ]* 12. Write property test for migration idempotency
  - **Property 10: Migration Idempotency**
  - **Validates: Requirements 7.3**
  - For any number of migration executions, verify database ends in same state without errors

## Notes

- All inspection fields are optional except vehicle selection
- The feature uses transaction-based saves for data integrity
- Migration is idempotent and can be run multiple times safely
- Styling follows existing oRentPHP dark theme patterns throughout
- Note truncation at 255 characters prevents database constraint violations
- Access control uses existing 'add_vehicles' permission
- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties
- Checkpoints ensure incremental validation
