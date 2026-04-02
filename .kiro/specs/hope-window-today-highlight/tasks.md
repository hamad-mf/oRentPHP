# Implementation Plan: Hope Window Today Highlight

## Overview

This implementation adds a highlighted "Today" section at the top of the Hope Window list view. The Today Section displays comprehensive income data for the current date using existing data structures and rendering functions. The implementation is straightforward since it leverages existing functions (hope_render_breakdown, hope_render_actual_breakdown, hope_fetch_actual_breakdown) and data structures ($dayByDate, $breakdownMap).

## Tasks

- [x] 1. Implement hope_render_today_section() function
  - Create new function in accounts/hope_window.php after existing rendering functions
  - Accept parameters: $dayData, $breakdownMap, $pdo, $today
  - Return HTML string for the Today Section
  - Use existing hope_render_breakdown() for expected income
  - Use existing hope_fetch_actual_breakdown() and hope_render_actual_breakdown() for actual income
  - Apply consistent formatting with hope_format_currency() and e()
  - Include visual indicator (icon/label) identifying it as "Today"
  - Use accent color styling (border-2 border-mb-accent/60, bg-mb-accent/10)
  - _Requirements: 1.1, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

- [ ]* 1.1 Write property test for Today Section conditional display
  - **Property 1: Today Section Conditional Display**
  - **Validates: Requirements 1.2, 1.3**

- [ ]* 1.2 Write property test for date label format consistency
  - **Property 2: Date Label Format Consistency**
  - **Validates: Requirements 1.5**

- [ ]* 1.3 Write property test for required data fields display
  - **Property 3: Required Data Fields Display**
  - **Validates: Requirements 2.1, 2.2, 2.3, 2.4**

- [ ]* 1.4 Write property test for expected income breakdown completeness
  - **Property 4: Expected Income Breakdown Completeness**
  - **Validates: Requirements 2.5**

- [ ]* 1.5 Write property test for actual income breakdown conditional display
  - **Property 5: Actual Income Breakdown Conditional Display**
  - **Validates: Requirements 2.6, 2.7**

- [ ]* 1.6 Write property test for predictions display completeness
  - **Property 6: Predictions Display Completeness**
  - **Validates: Requirements 2.8**

- [x] 2. Add Today Section rendering logic to main page
  - Add conditional check after summary cards rendering to determine if today is within selected period range
  - If today is in range, retrieve today's data from $dayByDate array
  - Call hope_render_today_section() with appropriate parameters
  - Output the Today Section HTML before the "Daily Targets" section
  - Ensure proper spacing with mb-6 margin
  - _Requirements: 1.1, 1.2, 1.3, 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ]* 2.1 Write property test for today row preservation
  - **Property 7: Today Row Preservation**
  - **Validates: Requirements 4.1**

- [ ]* 2.2 Write property test for today row data completeness
  - **Property 8: Today Row Data Completeness**
  - **Validates: Requirements 4.4**

- [ ]* 2.3 Write property test for today row chronological position
  - **Property 9: Today Row Chronological Position**
  - **Validates: Requirements 4.5**

- [x] 3. Verify today row preservation in day list
  - Confirm that today's row continues to render in the chronological day list
  - Verify today row maintains existing highlighting (border-mb-accent/60 bg-mb-accent/10)
  - Ensure today row remains clickable and expandable
  - Verify today row displays all standard data fields
  - Confirm today row appears in correct chronological position
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [ ]* 3.1 Write property test for reservation links rendering
  - **Property 10: Reservation Links Rendering**
  - **Validates: Requirements 5.1, 5.2**

- [ ]* 3.2 Write property test for currency formatting consistency
  - **Property 11: Currency Formatting Consistency**
  - **Validates: Requirements 5.4**

- [ ]* 3.3 Write property test for HTML escaping
  - **Property 12: HTML Escaping for Client Names**
  - **Validates: Requirements 5.5**

- [ ]* 3.4 Write property test for data consistency between Today Section and today row
  - **Property 13: Data Consistency Between Today Section and Today Row**
  - **Validates: Requirements 6.4, 6.5, 6.6, 6.7**

- [x] 4. Test responsive behavior and styling
  - Verify Today Section displays correctly on mobile, tablet, and desktop screen sizes
  - Confirm accent color styling is applied correctly
  - Test that Today Section is visually distinct from day rows
  - Verify spacing and alignment with other Hope Window sections
  - Test reservation links are clickable and navigate correctly
  - Confirm currency formatting and HTML escaping work correctly
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 5.1, 5.2, 5.3, 5.4, 5.5, 7.1, 7.2, 7.3, 7.4, 7.5_

- [x] 5. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The implementation leverages existing rendering functions to ensure consistency
- No database schema changes are required
- All data comes from existing $dayByDate and $breakdownMap structures
- Property tests should run a minimum of 100 iterations each
- Use a PHP property-based testing library such as Eris or php-quickcheck for property tests
