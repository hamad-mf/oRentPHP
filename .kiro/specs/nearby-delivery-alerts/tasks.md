# Implementation Plan: Nearby Delivery Alerts

## Overview

This implementation adds proactive dashboard notifications for confirmed reservations approaching their delivery date. The feature integrates seamlessly with the existing dashboard alert pattern (held deposits, EMI alerts) and uses the established settings management system. All code changes are made in the main project only, following session rules with no database migrations required.

## Tasks

- [x] 1. Implement alert query and urgency calculation functions
  - [x] 1.1 Create `get_upcoming_delivery_alerts()` function in index.php
    - Query confirmed reservations with start_date within threshold
    - Use single optimized query with JOINs for clients and vehicles
    - Include error handling with app_log() and return empty array on failure
    - Limit results to 10 records maximum
    - Order by start_date ASC (nearest delivery first)
    - _Requirements: 1.1, 1.5, 1.6, 6.1, 6.2, 6.3, 6.4, 7.2, 7.4, 7.5, 10.1, 10.2, 10.4_
  
  - [ ]* 1.2 Write property test for query threshold filtering
    - **Property 1: Query Threshold Filtering**
    - **Validates: Requirements 1.1, 2.5**
  
  - [ ]* 1.3 Write property test for delivery date ordering
    - **Property 3: Delivery Date Ordering**
    - **Validates: Requirements 1.5**
  
  - [ ]* 1.4 Write property test for result set limit
    - **Property 4: Result Set Limit**
    - **Validates: Requirements 1.6, 6.3**
  
  - [x] 1.5 Create `calculate_delivery_urgency()` function in index.php
    - Calculate days until delivery from today
    - Return urgency level, display text, and CSS classes
    - Handle "Due Today", "Tomorrow", and "In X days" cases
    - _Requirements: 3.1, 3.2, 3.3_
  
  - [ ]* 1.6 Write unit tests for urgency calculation
    - Test "Due Today" badge for today's date
    - Test "Tomorrow" badge for tomorrow's date
    - Test "In X days" text for future dates (2+ days)
    - _Requirements: 3.1, 3.2, 3.3_
  
  - [ ]* 1.7 Write property test for urgency text generation
    - **Property 7: Urgency Text for Future Dates**
    - **Validates: Requirements 3.3**

- [x] 2. Integrate alert section into dashboard (index.php)
  - [x] 2.1 Add alert query execution in admin dashboard section
    - Retrieve threshold setting using settings_get()
    - Call get_upcoming_delivery_alerts() with threshold
    - Store results and count for rendering
    - Position after fleet status section, before EMI alerts
    - _Requirements: 1.1, 1.2, 2.1, 2.5_
  
  - [x] 2.2 Implement alert section HTML template
    - Render section only when count > 0
    - Display header with icon, title, count badge, and threshold description
    - Loop through alerts and render individual alert cards
    - Include vehicle icon, reservation details, formatted date, and urgency badge
    - Make each alert a clickable link to reservations/show.php
    - _Requirements: 1.2, 1.3, 1.4, 4.1, 4.2, 5.1, 5.2, 5.3, 9.1, 9.2, 9.3_
  
  - [ ]* 2.3 Write property test for required field presence
    - **Property 2: Required Field Presence**
    - **Validates: Requirements 1.3**
  
  - [ ]* 2.4 Write property test for navigation link correctness
    - **Property 9: Navigation Link Correctness**
    - **Validates: Requirements 4.1**
  
  - [ ]* 2.5 Write property test for alert structure completeness
    - **Property 10: Alert Structure Completeness**
    - **Validates: Requirements 5.3**
  
  - [ ]* 2.6 Write unit test for empty result set behavior
    - Verify section is not displayed when no alerts exist
    - _Requirements: 1.4_
  
  - [ ]* 2.7 Write property test for date format consistency
    - **Property 8: Date Format Consistency**
    - **Validates: Requirements 3.5**

- [x] 3. Checkpoint - Verify dashboard integration
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implement settings configuration (settings/general.php)
  - [x] 4.1 Add threshold input field to Delivery Settings section
    - Position after "Default Return Pickup Charge" field
    - Add number input with min=1, max=30, step=1
    - Display current value using settings_get() with default of 3
    - Include helper text explaining the threshold behavior
    - _Requirements: 2.2, 2.3, 8.1, 8.2, 8.3_
  
  - [x] 4.2 Add POST handler for threshold setting
    - Validate and clamp input to range [1, 30]
    - Use settings_set() to persist the value
    - Include in existing success flash message
    - _Requirements: 2.4, 2.5, 8.4, 8.5_
  
  - [ ]* 4.3 Write property test for input validation clamping
    - **Property 6: Input Validation Clamping**
    - **Validates: Requirements 2.3**
  
  - [ ]* 4.4 Write property test for settings round trip
    - **Property 5: Settings Round Trip**
    - **Validates: Requirements 2.4**
  
  - [ ]* 4.5 Write unit test for default threshold value
    - Verify default is 3 when setting doesn't exist
    - _Requirements: 2.1_
  
  - [ ]* 4.6 Write unit test for settings page helper text
    - Verify helper text is present and correct
    - _Requirements: 8.3_

- [x] 5. Implement error handling and graceful degradation
  - [x] 5.1 Add NULL start_date filtering in query
    - Add WHERE clause to exclude NULL start_date
    - _Requirements: 7.2_
  
  - [x] 5.2 Add COALESCE for missing foreign key references
    - Use COALESCE for client name, vehicle brand, and vehicle model
    - Provide fallback values: "Unknown Client", "Unknown", "Vehicle"
    - _Requirements: 7.3_
  
  - [x] 5.3 Verify error logging includes context
    - Ensure app_log() calls include file, line, screen, and threshold_days
    - _Requirements: 7.5_
  
  - [ ]* 5.4 Write property test for error handling graceful degradation
    - **Property 11: Error Handling Graceful Degradation**
    - **Validates: Requirements 6.4, 7.4**
  
  - [ ]* 5.5 Write property test for error logging context
    - **Property 12: Error Logging Context**
    - **Validates: Requirements 7.5**
  
  - [ ]* 5.6 Write unit test for missing system_settings table
    - Verify default threshold is used when table doesn't exist
    - _Requirements: 7.1_
  
  - [ ]* 5.7 Write unit test for NULL start_date handling
    - Verify reservations with NULL start_date are excluded
    - _Requirements: 7.2_
  
  - [ ]* 5.8 Write unit test for missing client/vehicle records
    - Verify "Unknown" fallback values are displayed
    - _Requirements: 7.3_

- [x] 6. Implement responsive styling and visual consistency
  - [x] 6.1 Apply alert section styling
    - Use bg-blue-500/10 background and border-blue-500/30 border
    - Match existing alert section pattern (held deposits, EMI alerts)
    - Use consistent spacing, padding, and rounded corners
    - _Requirements: 5.1, 5.4_
  
  - [x] 6.2 Style individual alert cards
    - Use bg-mb-surface/40 background with border-blue-500/15 border
    - Add hover effect with border-blue-500/35
    - Include vehicle icon with blue-400 color
    - Apply responsive layout with flex and gap utilities
    - _Requirements: 5.3, 5.4_
  
  - [x] 6.3 Style urgency badges
    - Apply conditional classes based on urgency level
    - Red pulsing badge for "Due Today" (animate-pulse)
    - Orange badge for "Tomorrow"
    - Blue badge for future dates
    - _Requirements: 3.1, 3.2, 3.3, 3.4_
  
  - [x] 6.4 Implement responsive layout
    - Test on mobile viewport (truncate text, hide secondary info)
    - Test on desktop viewport (show all details)
    - Use hidden sm:inline for optional elements
    - _Requirements: 5.4_
  
  - [ ]* 6.5 Write unit test for count badge display
    - Verify count badge is displayed with correct styling
    - _Requirements: 9.2_

- [x] 7. Checkpoint - Verify styling and responsiveness
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Write property-based tests for universal properties
  - [ ]* 8.1 Write property test for count accuracy
    - **Property 13: Count Accuracy**
    - **Validates: Requirements 9.1**
  
  - [ ]* 8.2 Write property test for status filtering
    - **Property 14: Status Filtering**
    - **Validates: Requirements 10.1, 10.2**
  
  - [ ]* 8.3 Write property test for status change reactivity
    - **Property 15: Status Change Reactivity**
    - **Validates: Requirements 10.3**

- [ ] 9. Performance testing and optimization
  - [ ]* 9.1 Benchmark query execution time
    - Test with 10,000 reservations
    - Verify execution time is under 100ms
    - Test with various threshold values (1, 7, 15, 30 days)
    - _Requirements: 6.5_
  
  - [ ]* 9.2 Measure dashboard load time impact
    - Verify alert section doesn't block other components
    - Test with maximum alert count (10 alerts)
    - _Requirements: 6.5_

- [ ] 10. Integration testing
  - [ ]* 10.1 Test dashboard rendering with alerts
    - Verify alert section appears when deliveries exist
    - Verify alert section is hidden when no deliveries exist
    - _Requirements: 1.2, 1.4_
  
  - [ ]* 10.2 Test alert navigation
    - Click alert and verify navigation to correct reservation page
    - Verify user session is preserved
    - _Requirements: 4.1, 4.3, 4.4_
  
  - [ ]* 10.3 Test settings integration
    - Change threshold in settings and verify dashboard query updates
    - Test invalid threshold values are rejected
    - Verify settings persist across page reloads
    - _Requirements: 2.3, 2.4, 2.5, 8.4, 8.5_
  
  - [ ]* 10.4 Test error handling integration
    - Simulate database query failure
    - Verify dashboard loads successfully with zero alerts
    - Verify error is logged with context
    - _Requirements: 6.4, 7.4, 7.5_

- [x] 11. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- No database migrations required - uses existing tables and columns
- All code changes in main project only (session rules compliance)
- Feature follows established patterns from held deposits and EMI alerts
- Settings integration uses existing settings_helpers.php functions
- Error handling ensures graceful degradation without breaking dashboard
