# Implementation Plan: Vehicle Inspection Unified Tabs

## Overview

This implementation consolidates the Vehicle Inspection Job Card and Permanent Scratches features into a single unified page with client-side tab navigation. The implementation creates a new `vehicles/inspection.php` page that embeds both features with JavaScript-based tab switching, updates the navigation menu to show a single "Vehicle Inspection" item, and adds deprecation notices to the legacy pages while keeping them accessible via direct URL.

## Tasks

- [x] 1. Create unified inspection page structure
  - Create `vehicles/inspection.php` with authentication and permission checks
  - Implement tab navigation HTML structure with two tabs (Job Card and Permanent Scratches)
  - Add CSS styling for tab buttons (active/inactive states, hover effects)
  - Add JavaScript function for client-side tab switching
  - Implement URL parameter support for direct tab linking
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.5, 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 2. Implement Job Card tab content
  - [x] 2.1 Embed Job Card functionality in tab content area
    - Copy vehicle selection dropdown from `vehicles/job_card.php`
    - Copy 37-item inspection checklist table structure
    - Copy Save and Print buttons
    - Ensure form submission includes `action=save_job_card` hidden field
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_
  
  - [x] 2.2 Implement Job Card form processing
    - Add POST handler for `action=save_job_card`
    - Validate vehicle selection and inspection items
    - Insert into `vehicle_job_cards` and `vehicle_job_card_items` tables
    - Record authenticated user ID in `created_by` field
    - Redirect to `inspection.php?tab=job-card&vehicle_id={id}` on success
    - _Requirements: 8.1, 8.3, 8.5, 10.3, 10.4_
  
  - [x] 2.3 Implement Job Card data loading
    - Load latest job card for selected vehicle
    - Load all 37 job card items
    - Pre-populate form fields with loaded data
    - _Requirements: 4.5, 11.4_

- [x] 3. Implement Permanent Scratches tab content
  - [x] 3.1 Embed Permanent Scratches functionality in tab content area
    - Copy vehicle selection dropdown from `vehicles/permanent_scratches.php`
    - Copy existing scratches display grid
    - Copy add scratch form with photo upload
    - Copy delete scratch forms for each scratch
    - Ensure forms include appropriate `action` hidden fields
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  
  - [x] 3.2 Implement Permanent Scratches form processing
    - Add POST handler for `action=add_scratch`
    - Validate photo upload (file type, size)
    - Validate description (required, max 255 chars)
    - Upload photo to `uploads/permanent_scratches/` directory
    - Insert into `vehicle_permanent_scratches` table
    - Record authenticated user ID in `created_by` field
    - Redirect to `inspection.php?tab=permanent-scratches&vehicle_id={id}` on success
    - _Requirements: 8.2, 8.4, 8.5, 10.3, 10.4_
  
  - [x] 3.3 Implement scratch deletion
    - Add POST handler for `action=delete_scratch`
    - Delete record from `vehicle_permanent_scratches` table
    - Delete photo file from filesystem
    - Handle file deletion errors gracefully
    - Redirect to `inspection.php?tab=permanent-scratches&vehicle_id={id}` on success
    - _Requirements: 5.4, 8.2_
  
  - [x] 3.4 Implement Permanent Scratches data loading
    - Load all permanent scratches for selected vehicle
    - Display scratch photos and descriptions
    - _Requirements: 5.2, 11.4_

- [x] 4. Checkpoint - Test unified page functionality
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Implement vehicle selection synchronization
  - Add `syncVehicleSelection()` JavaScript function
  - Update both vehicle dropdowns when either changes
  - Update URL parameter with selected vehicle ID
  - Reload page to fetch vehicle-specific data
  - Handle vehicle selection on page load from URL parameter
  - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_

- [x] 6. Update navigation menu
  - Modify `includes/header.php` to add single "Vehicle Inspection" menu item
  - Link menu item to `vehicles/inspection.php`
  - Remove separate "Job Card" menu item
  - Remove separate "Permanent Scratches" menu item
  - Position new item where "Job Card" previously appeared
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 7. Add deprecation notices to legacy pages
  - [x] 7.1 Add deprecation notice to `vehicles/job_card.php`
    - Insert deprecation notice HTML at top of page content
    - Include link to `vehicles/inspection.php`
    - Style with yellow warning colors
    - _Requirements: 12.1, 12.3, 12.5_
  
  - [x] 7.2 Add deprecation notice to `vehicles/permanent_scratches.php`
    - Insert deprecation notice HTML at top of page content
    - Include link to `vehicles/inspection.php`
    - Style with yellow warning colors
    - _Requirements: 12.2, 12.4, 12.5_

- [x] 8. Final checkpoint - Verify all functionality
  - Test tab switching without page reload
  - Test form data preservation when switching tabs
  - Test vehicle selection synchronization
  - Test Job Card save and print functionality
  - Test Permanent Scratches add and delete functionality
  - Test permission enforcement
  - Test legacy page accessibility and deprecation notices
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- All tasks reference specific requirements for traceability
- The unified page uses the same database tables as legacy pages (no schema changes)
- Legacy pages remain fully functional for backward compatibility
- JavaScript handles tab switching client-side for instant response
- Vehicle selection triggers page reload to fetch vehicle-specific data
- All form submissions redirect back to the unified page with appropriate tab parameter
