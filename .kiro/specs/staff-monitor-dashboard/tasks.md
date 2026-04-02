# Implementation Plan: Staff Monitor Dashboard

## Overview

This plan implements a read-only analytics dashboard that visualizes staff activity data from the existing `staff_activity_log` table. The implementation includes a main dashboard page with KPI metrics and staff cards, an AJAX timeline handler for detailed activity retrieval, and a slide-over modal UI with JavaScript interactions. No database migrations are required as the feature uses existing tables.

## Tasks

- [x] 1. Create main dashboard page structure and authentication
  - Create `staff_monitor/index.php` with authentication checks
  - Implement permission validation (admin OR `view_staff_monitor`)
  - Include standard header and database connection
  - Set up date parameter handling with validation (YYYY-MM-DD format)
  - _Requirements: 1.1, 1.2, 1.3, 3.1, 3.4_

- [x] 2. Implement KPI calculation and display
  - [x] 2.1 Build KPI data aggregation queries
    - Write SQL query for Total Actions count filtered by date
    - Write SQL query for Active Staff count (distinct user_ids)
    - Write SQL query for Lead Actions count (keyword matching)
    - Write SQL query for Reservation/Payment Actions count
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_
  
  - [ ]* 2.2 Write property test for KPI accuracy
    - **Property 1: KPI Accuracy**
    - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**
  
  - [x] 2.3 Create KPI panel UI with Tailwind styling
    - Render 4 metric cards using `bg-mb-surface` and `border-mb-subtle/20`
    - Display Total Actions, Active Staff, Lead Actions, Reservation/Payment Actions
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 3. Implement staff activity aggregation
  - [x] 3.1 Build staff data query with action categorization
    - Join users, staff, and staff_activity_log tables
    - Group by user_id and count actions by category
    - Calculate last_activity timestamp per staff member
    - Filter for active staff members only
    - _Requirements: 4.1, 4.2, 4.3, 8.1, 8.2, 8.3_
  
  - [ ]* 3.2 Write property test for staff card completeness
    - **Property 2: Staff Card Completeness**
    - **Validates: Requirements 4.1, 4.2, 4.3**
  
  - [ ]* 3.3 Write property test for action count accuracy
    - **Property 3: Action Count Accuracy**
    - **Validates: Requirements 4.3, 8.1, 8.2, 8.3**
  
  - [ ]* 3.4 Write property test for multi-category action counting
    - **Property 4: Multi-Category Action Counting**
    - **Validates: Requirements 8.4**

- [x] 4. Implement active status calculation logic
  - [x] 4.1 Create getActiveStatus() function
    - Calculate time difference between last activity and current time
    - Return "Active Now" with green indicator if within 15 minutes
    - Return "Last seen Xm/h/d ago" with red indicator otherwise
    - Handle null/empty last activity timestamps
    - _Requirements: 5.1, 5.2, 5.3, 5.4_
  
  - [ ]* 4.2 Write property test for active status threshold
    - **Property 5: Active Status Threshold**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4**
  
  - [ ]* 4.3 Write unit tests for active status edge cases
    - Test midnight boundary conditions
    - Test null/empty timestamps
    - Test exact 15-minute threshold
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 5. Build staff grid UI with responsive layout
  - [x] 5.1 Create staff card component
    - Implement responsive grid (1 col mobile, 2 col tablet, 3 col desktop)
    - Render staff name, role, and avatar/initials
    - Display active status indicator with color coding
    - Show 4 action count boxes (Total, Leads, Reservations, Payments)
    - Add onclick handler to open timeline modal
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 5.1, 5.2, 5.3, 10.1, 10.2, 10.3_
  
  - [ ]* 5.2 Write unit tests for staff card rendering
    - Test zero action counts display
    - Test empty state when no staff members exist
    - Test responsive grid breakpoints
    - _Requirements: 4.5, 10.1, 10.2, 10.3_

- [x] 6. Implement date navigation controls
  - [x] 6.1 Create date filter UI component
    - Build previous/next day navigation arrows
    - Display current selected date in readable format
    - Calculate previous and next dates from selected date
    - Disable next arrow when date equals today
    - Generate navigation URLs with date parameter
    - _Requirements: 3.1, 3.2, 3.3, 3.4_
  
  - [ ]* 6.2 Write property test for date parameter validation
    - **Property 10: Date Parameter Validation**
    - **Validates: Requirements 3.1, 3.4**
  
  - [ ]* 6.3 Write unit tests for date navigation
    - Test invalid date format defaults to today
    - Test future date handling
    - Test date boundary conditions
    - _Requirements: 3.1, 3.4_

- [x] 7. Checkpoint - Ensure main dashboard loads correctly
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Create AJAX timeline handler
  - [x] 8.1 Build ajax_timeline.php with authentication
    - Create `staff_monitor/ajax_timeline.php` file
    - Implement authentication and permission checks
    - Validate user_id and date parameters
    - Return HTTP 400 for invalid parameters
    - _Requirements: 1.1, 1.2, 1.3, 7.1_
  
  - [x] 8.2 Implement timeline data query
    - Query staff_activity_log filtered by user_id and date
    - Order results by created_at DESC (reverse chronological)
    - Fetch action, description, and created_at fields
    - _Requirements: 6.2, 6.5, 7.2, 7.3, 7.4_
  
  - [ ]* 8.3 Write property test for timeline chronological ordering
    - **Property 6: Timeline Chronological Ordering**
    - **Validates: Requirements 6.2, 7.3**
  
  - [ ]* 8.4 Write property test for timeline date filtering
    - **Property 8: Timeline Date Filtering**
    - **Validates: Requirements 6.5, 7.2**
  
  - [ ]* 8.5 Write unit tests for AJAX error handling
    - Test invalid user_id returns 400
    - Test missing parameters returns 400
    - Test non-existent user returns 404
    - _Requirements: 7.1_

- [x] 9. Implement action icon mapping
  - [x] 9.1 Create getActionIcon() function
    - Define action icon mapping array (delivery, return, reservation, lead, payment, default)
    - Implement keyword matching logic using stripos()
    - Return appropriate emoji or SVG icon based on action type
    - _Requirements: 9.2, 9.3, 9.4_
  
  - [ ]* 9.2 Write property test for icon assignment consistency
    - **Property 9: Icon Assignment Consistency**
    - **Validates: Requirements 9.2, 9.3, 9.4**
  
  - [ ]* 9.3 Write unit tests for icon mapping
    - Test payment action keywords
    - Test lead/client action keywords
    - Test multi-keyword actions
    - Test default icon fallback
    - _Requirements: 9.2, 9.3, 9.4_

- [x] 10. Build timeline HTML rendering
  - [x] 10.1 Create timeline entry HTML structure
    - Render timeline entries with vertical connecting line
    - Format timestamps in 12-hour AM/PM format
    - Display action icon, time, and description
    - Apply Tailwind styling consistent with design system
    - _Requirements: 6.3, 7.4, 9.1, 9.5_
  
  - [ ]* 10.2 Write property test for timeline data completeness
    - **Property 7: Timeline Data Completeness**
    - **Validates: Requirements 6.3, 7.4, 9.5**
  
  - [ ]* 10.3 Write unit tests for timeline rendering
    - Test empty timeline display
    - Test single activity entry
    - Test multiple activities with different types
    - _Requirements: 6.3, 7.4_

- [x] 11. Implement timeline modal UI and JavaScript
  - [x] 11.1 Create modal HTML structure
    - Build slide-over panel with fixed positioning
    - Add close button with dismiss functionality
    - Create content container for timeline injection
    - Apply responsive width (full on mobile, max-w-sm on desktop)
    - Implement translate-x-full transform for hidden state
    - _Requirements: 6.1, 6.4, 10.4_
  
  - [x] 11.2 Write openTimelineModal() JavaScript function
    - Extract user_id parameter from click event
    - Get current date from URL parameters
    - Show loading state in modal content
    - Fetch timeline data from ajax_timeline.php
    - Inject returned HTML into modal content
    - Remove translate-x-full class to show modal
    - Handle fetch errors with user-friendly message
    - _Requirements: 6.1, 7.1_
  
  - [x] 11.3 Write closeTimelineModal() JavaScript function
    - Add translate-x-full class to hide modal
    - Clear modal content on close
    - _Requirements: 6.4_
  
  - [ ]* 11.4 Write integration tests for modal flow
    - Test modal opens on staff card click
    - Test AJAX request sent with correct parameters
    - Test modal displays returned content
    - Test close button dismisses modal
    - Test error state display on fetch failure
    - _Requirements: 6.1, 6.4, 7.1_

- [x] 12. Implement authorization enforcement
  - [ ]* 12.1 Write property test for authorization enforcement
    - **Property 11: Authorization Enforcement**
    - **Validates: Requirements 1.2, 1.3**
  
  - [ ]* 12.2 Write unit tests for access control
    - Test unauthenticated user redirects to login
    - Test user without permission redirects to index
    - Test admin user granted access
    - Test user with view_staff_monitor permission granted access
    - _Requirements: 1.1, 1.2, 1.3_

- [x] 13. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- No database migrations required (uses existing staff_activity_log table)
- Permissions and navigation already implemented in the system
- Uses Tailwind `bg-mb-*` namespace for styling consistency
- PHP with PDO for database access, vanilla JavaScript for interactions
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties across all inputs
- Unit tests validate specific examples, edge cases, and error conditions
