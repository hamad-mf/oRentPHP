# Implementation Plan: Delivery Inspection Preview on Return

## Overview

This implementation adds a read-only delivery inspection preview section to the vehicle return screen (reservations/return.php). The preview displays delivery location, mileage, fuel level, notes, and photos (standard views, interior, and scratch/damage) to help staff compare delivery and return conditions. All changes are contained within reservations/return.php with no database migrations needed.

## Tasks

- [x] 1. Query delivery inspection data and photos
  - Add database queries after existing reservation query to fetch delivery inspection record, standard photos, and scratch photos
  - Query delivery_location from reservations table
  - Handle null/missing data gracefully with appropriate fallback values
  - _Requirements: 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

- [ ]* 1.1 Write property test for delivery data display completeness
  - **Property 1: Delivery data display completeness**
  - **Validates: Requirements 1.3, 1.4, 1.5**

- [ ] 2. Implement preview section HTML structure
  - [x] 2.1 Create collapsible preview container with header
    - Add preview section before existing return form
    - Implement "Delivery Inspection Reference" header with collapse toggle
    - Add distinct background color and border styling using Tailwind classes
    - _Requirements: 1.1, 4.1, 4.2, 4.4_
  
  - [x] 2.2 Display delivery metadata (location, mileage, fuel, notes)
    - Render delivery_location, mileage, fuel_level, and notes in read-only format
    - Show "Location not recorded" when delivery_location is null
    - Show "No delivery inspection data available" when inspection record is null
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 3.1, 3.2, 4.3_
  
  - [x] 2.3 Create collapsed summary view
    - Display summary showing only delivery location and mileage when collapsed
    - _Requirements: 4.5_

- [ ]* 2.4 Write property test for read-only data display
  - **Property 4: Read-only data display**
  - **Validates: Requirements 4.3**

- [ ] 3. Implement photo display grids
  - [x] 3.1 Create standard photos grid (front, back, left, right, odometer, with_customer)
    - Group photos by view_name from inspection_photos table
    - Display thumbnails with max-width for layout consistency
    - Show "No photos available" when photo array is empty
    - _Requirements: 1.6, 2.1, 2.4, 3.3_
  
  - [x] 3.2 Create interior photos grid (interior_1 through interior_15)
    - Display interior photos in grid layout
    - Filter photos where view_name starts with 'interior_'
    - _Requirements: 1.6, 2.1, 2.5_
  
  - [x] 3.3 Create scratch/damage photos section
    - Display scratch photos from reservation_scratch_photos table separately
    - Filter by event_type='delivery' and order by slot_index
    - _Requirements: 1.7, 2.2_

- [ ]* 3.4 Write property test for photo display completeness
  - **Property 2: Photo display completeness**
  - **Validates: Requirements 1.6, 2.1**

- [ ]* 3.5 Write property test for scratch photo separation
  - **Property 3: Scratch photo separation**
  - **Validates: Requirements 1.7, 2.2**

- [ ] 4. Add JavaScript for interactive features
  - [x] 4.1 Implement collapse/expand toggle functionality
    - Write toggleDeliveryPreview() function to show/hide content
    - Toggle summary visibility and rotate icon
    - _Requirements: 4.4, 4.5_
  
  - [x] 4.2 Implement photo lightbox/modal
    - Write openPhotoModal() function to display full-size images
    - Add click handlers to all photo thumbnails
    - Include close button and click-outside-to-close functionality
    - _Requirements: 2.3_

- [x] 5. Add CSS styling for preview section
  - Apply Tailwind classes for visual distinction (bg-mb-surface, border-mb-subtle/20, text-mb-silver)
  - Add distinct border color (border-blue-500/30) to differentiate from return form
  - Style photo grids with consistent spacing and responsive layout
  - _Requirements: 4.1, 4.2_

- [ ]* 5.1 Write property test for location display
  - **Property 5: Location display**
  - **Validates: Requirements 1.2**

- [ ] 6. Test backward compatibility and error handling
  - [x] 6.1 Test with old reservations (no delivery inspection data)
    - Verify "No delivery inspection data available" message displays
    - Confirm return form continues to function normally
    - _Requirements: 3.1, 3.4_
  
  - [x] 6.2 Test with partial data scenarios
    - Test inspection exists but no location (shows "Location not recorded")
    - Test inspection exists but no photos (shows "No photos available")
    - Test with missing photo files (graceful degradation)
    - _Requirements: 3.2, 3.3_

- [x] 7. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- All changes are in reservations/return.php only (no database migrations)
- Follow UPDATE_SESSION_RULES.md strictly (no auto-migrations, manual SQL review)
- Use existing Tailwind CSS classes from the application
- Verify user has `do_return` permission (already checked at page top)
- Use `e()` function for all output to prevent XSS
- Photo file paths should be validated to prevent directory traversal
