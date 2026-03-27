# Implementation Plan: Hope Window Expected Breakdown

## Overview

This implementation adds a detailed breakdown section to the Hope Window interface that displays individual components contributing to each day's expected income. The breakdown will show booking events, delivery events, return events, extension payments, and custom predictions with client names for easy identification.

## Tasks

- [x] 1. Implement data retrieval functions
  - [x] 1.1 Create hope_fetch_breakdown_data() function
    - Implement SQL query to fetch reservations with client names using LEFT JOIN on clients table
    - Implement SQL query to fetch extensions with client names (with table existence check)
    - Implement SQL query to fetch custom predictions from hope_daily_predictions
    - Return structured array with 'reservations', 'extensions', and 'predictions' keys
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1, 9.1, 10.1, 10.3, 12.1, 12.3_
  
  - [ ]* 1.2 Write property test for data retrieval
    - **Property 6: Cancelled reservations are excluded**
    - **Validates: Requirements 9.1, 9.2**

- [x] 2. Implement breakdown builder function
  - [x] 2.1 Create hope_build_breakdown_map() function
    - Initialize breakdown map with date keys for the entire range
    - Process reservations array to build booking, delivery, and return events
    - Calculate booking amounts (advance_paid + delivery_charge_prepaid) and filter out zero amounts
    - Calculate delivery amounts using hope_calc_delivery_due() and filter out zero amounts
    - Calculate return amounts using hope_calc_return_due() and filter out zero amounts
    - Process extensions array to build extension events and filter out zero amounts
    - Process predictions array to build prediction events and filter out zero amounts
    - Calculate total for each date by summing all event amounts
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2, 6.1, 6.3_
  
  - [ ]* 2.2 Write property test for breakdown builder
    - **Property 1: Events appear on correct dates**
    - **Validates: Requirements 1.1, 2.1, 3.1**
  
  - [ ]* 2.3 Write property test for zero-amount filtering
    - **Property 4: Zero-amount items are excluded**
    - **Validates: Requirements 1.2, 2.2, 3.2, 4.2, 5.2**
  
  - [ ]* 2.4 Write property test for breakdown total
    - **Property 5: Breakdown total matches expected income**
    - **Validates: Requirements 6.1, 6.3**

- [x] 3. Implement currency formatting function
  - [x] 3.1 Create hope_format_currency() function
    - Format amounts with dollar sign prefix
    - Format amounts with exactly two decimal places
    - Format amounts with thousand separators (commas)
    - _Requirements: 11.1, 11.2, 11.3_
  
  - [ ]* 3.2 Write property test for currency formatting
    - **Property 9: Currency formatting is consistent**
    - **Validates: Requirements 11.1, 11.2, 11.3**

- [x] 4. Implement breakdown rendering function
  - [x] 4.1 Create hope_render_breakdown() function
    - Accept breakdown map and date as parameters
    - Retrieve breakdown data for the specified date
    - Sort breakdown items by amount in descending order
    - Format booking events as "Res #{id} - {client_name} - Booking: ${amount}"
    - Format delivery events as "Res #{id} - {client_name} - Delivery: ${amount}"
    - Format return events as "Res #{id} - {client_name} - Return: ${amount}"
    - Format extension events as "Extension #{ext_id} (Res #{res_id} - {client_name}): ${amount}"
    - Format prediction events as "Custom: {description} - ${amount}"
    - Format total line as "Total Expected: ${sum}"
    - Return HTML string with breakdown section
    - _Requirements: 1.3, 2.3, 3.3, 4.3, 5.3, 6.2, 7.3, 10.2_
  
  - [ ]* 4.2 Write property test for item sorting
    - **Property 8: Items are sorted by amount descending**
    - **Validates: Requirements 7.3**
  
  - [ ]* 4.3 Write property test for format strings
    - **Property 10: Breakdown format strings are correct**
    - **Validates: Requirements 1.3, 2.3, 3.3, 4.3, 5.3, 6.2**

- [x] 5. Integrate breakdown into Day View
  - [x] 5.1 Modify Day View rendering in hope_window.php
    - Call hope_fetch_breakdown_data() to retrieve breakdown data
    - Call hope_build_breakdown_map() to build breakdown structure
    - Call hope_render_breakdown() for the displayed date
    - Insert breakdown HTML below or adjacent to Expected_Income total
    - Ensure breakdown is visible by default without user interaction
    - _Requirements: 7.1, 7.2, 7.3_

- [x] 6. Integrate breakdown into List View
  - [x] 6.1 Add expandable row functionality to List View
    - Add click handler to date rows in List View
    - Store breakdown data in JavaScript variable for client-side access
    - Implement expand/collapse logic for breakdown section
    - Ensure only one row can be expanded at a time
    - Call hope_render_breakdown() for the clicked date when expanding
    - _Requirements: 8.1, 8.2, 8.3, 8.4_

- [x] 7. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 8. Handle edge cases and error conditions
  - [x] 8.1 Add client name fallback handling
    - Ensure "Unknown Client" is displayed when client_id is NULL or client doesn't exist
    - Verify LEFT JOIN on clients table handles missing relationships
    - _Requirements: 10.2_
  
  - [x] 8.2 Add extension table existence check
    - Wrap extension queries in try-catch block
    - Check for table existence before querying reservation_extensions
    - Ensure breakdown works without errors when table is missing
    - _Requirements: 12.1, 12.2, 12.3_
  
  - [ ]* 8.3 Write unit tests for edge cases
    - Test reservation with zero booking amount (no booking event)
    - Test reservation with zero delivery due (no delivery event)
    - Test reservation with zero return due (no return event)
    - Test extension with zero amount (excluded from breakdown)
    - Test client with NULL client_id (displays "Unknown Client")
    - Test date with no events (empty breakdown)
    - Test missing reservation_extensions table (graceful degradation)

- [x] 9. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Breakdown reuses existing calculation functions (hope_calc_delivery_due, hope_calc_return_due)
- Feature is backward compatible with systems missing reservation_extensions table
- Client names are retrieved via LEFT JOIN to handle missing relationships gracefully
