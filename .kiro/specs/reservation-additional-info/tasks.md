# Implementation Plan: Reservation Additional Information

## Overview

This implementation adds three supplementary information fields to the reservation details screen: delivery location, return location, and a general note field. These fields are purely informational and will not be integrated into any business logic. The implementation follows existing codebase patterns with idempotent database migrations and inline editing similar to the booking discount feature.

## Tasks

- [x] 1. Create database migration file
  - Create `migrations/releases/2026-04-15_reservation_additional_info.sql`
  - Add three nullable columns to reservations table: `delivery_location VARCHAR(255)`, `return_location VARCHAR(255)`, `additional_note TEXT`
  - Use idempotent SQL with `IF NOT EXISTS` checks following existing migration patterns
  - Include proper header comment with release name, safety note, and description
  - Use `SET FOREIGN_KEY_CHECKS = 0/1` wrapper
  - _Requirements: 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [ ]* 1.1 Write property test for migration idempotence
  - **Property 6: Migration Idempotence**
  - **Validates: Requirements 4.4**
  - Test that running migration multiple times produces same final schema without errors

- [ ]* 1.2 Write property test for migration data preservation
  - **Property 7: Data Preservation During Migration**
  - **Validates: Requirements 4.6**
  - Test that existing reservation data remains unchanged after migration

- [x] 2. Update PRODUCTION_DB_STEPS.md
  - Add migration entry to the "Pending" section
  - Include date (2026-04-15), release ID (reservation_additional_info), SQL file path, and descriptive notes
  - Follow existing format in the documentation
  - _Requirements: 5.2_

- [x] 3. Implement display section on reservations/show.php
  - [x] 3.1 Fetch additional info fields in main reservation query
    - Add `delivery_location`, `return_location`, `additional_note` to SELECT statement
    - Modify existing `$rStmt` query to include new columns
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 3.2 Create Additional Information display section
    - Add new section after scratch photos sections (before cancellation section)
    - Display section title "Additional Information"
    - Show "No additional information recorded" when all fields are NULL/empty
    - Display field labels and values when data exists
    - Use consistent styling with existing sections (mb-surface, border, rounded-xl)
    - _Requirements: 1.1, 1.5, 1.6_

  - [ ]* 3.3 Write property test for display rendering
    - **Property 5: Display of Non-Empty Fields**
    - **Validates: Requirements 1.5**
    - Test that non-empty fields appear in rendered output

- [x] 4. Checkpoint - Verify display section renders correctly
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement inline edit functionality
  - [x] 5.1 Add edit button for authorized users
    - Display "Edit Additional Info" button when user has edit permissions
    - Use JavaScript to toggle between display and edit modes
    - Follow existing inline edit patterns from booking discount widget
    - _Requirements: 2.1_

  - [x] 5.2 Create inline edit form
    - Add form with three input fields: delivery_location (input), return_location (input), additional_note (textarea)
    - Set maxlength attributes: 255 for locations, 1000 for note
    - Add character count indicators for each field
    - Include Save and Cancel buttons
    - Use consistent styling with existing forms
    - _Requirements: 2.2, 2.3, 2.4, 2.5_

  - [x] 5.3 Add client-side validation
    - Validate character limits before form submission
    - Display error messages for exceeded limits
    - Trim whitespace from inputs
    - Convert empty strings to NULL
    - _Requirements: 2.6, 2.9_

  - [ ]* 5.4 Write property tests for input length validation
    - **Property 2: Input Length Validation for Delivery Location**
    - **Property 3: Input Length Validation for Return Location**
    - **Property 4: Input Length Validation for Additional Note**
    - **Validates: Requirements 2.3, 2.4, 2.5**
    - Test that inputs within limits are accepted and inputs exceeding limits are rejected

- [x] 6. Implement POST handler for saving data
  - [x] 6.1 Add POST handler in reservations/show.php
    - Check for `action=save_additional_info` POST request
    - Validate reservation_id matches current reservation
    - Extract and validate all three field values
    - Enforce character limits: 255 for locations, 1000 for note
    - Convert empty strings to NULL
    - _Requirements: 2.6, 2.7, 2.9, 3.6, 3.7_

  - [x] 6.2 Implement database update
    - Use prepared statement to update reservations table
    - Update delivery_location, return_location, additional_note columns
    - Handle NULL values correctly
    - _Requirements: 2.7, 3.6, 3.7_

  - [x] 6.3 Add success and error handling
    - Display success flash message after successful save
    - Display error flash message for validation failures
    - Redirect to show.php?id={id} after processing
    - _Requirements: 2.8, 2.9_

  - [ ]* 6.4 Write property test for data persistence
    - **Property 1: Field Data Persistence**
    - **Validates: Requirements 2.7**
    - Test that saved values can be retrieved unchanged from database

- [x] 7. Verify business logic independence
  - [x] 7.1 Confirm pricing calculations are unaffected
    - Review pricing calculation code to ensure new fields are not referenced
    - Verify total_price, discounts, charges calculations remain unchanged
    - _Requirements: 6.2_

  - [x] 7.2 Confirm status transitions are unaffected
    - Review status transition logic (pending → confirmed → active → completed)
    - Verify new fields are not used in any status checks
    - _Requirements: 6.3_

  - [x] 7.3 Confirm workflow transitions are unaffected
    - Review delivery, return, extension, cancellation workflows
    - Verify new fields are not required for any workflow
    - _Requirements: 6.5_

  - [ ]* 7.4 Write property tests for independence
    - **Property 8: Pricing Calculation Independence**
    - **Property 9: Status Logic Independence**
    - **Property 10: Workflow Transition Independence**
    - **Validates: Requirements 6.2, 6.3, 6.5**
    - Test that business logic behaves identically regardless of additional info field values

- [x] 8. Final checkpoint - End-to-end testing
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- The implementation follows existing codebase patterns (booking discount widget, scratch photos)
- Migration must be applied manually via phpMyAdmin (session rule compliance)
- All new database columns are nullable for backward compatibility
- These fields are display-only and do not participate in any business logic
