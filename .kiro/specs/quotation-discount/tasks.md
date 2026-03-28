# Implementation Plan: Quotation Discount Feature

## Overview

This plan implements per-vehicle discount functionality for the quotation builder (`vehicles/quotation_builder.php`). The implementation adds discount controls to each vehicle block, manages discount state in JavaScript, calculates discounts in real-time, and integrates discount breakdowns into PDF generation. All discount data is client-side only and never persisted to the database.

## Tasks

- [x] 1. Add discount controls UI to vehicle template
  - Add HTML structure for discount controls inside the vehicle template
  - Include dropdown for discount type (None, Percent %, Fixed $)
  - Include numeric input for discount value (hidden by default)
  - Include discount preview section showing subtotal, discount, and total
  - Apply dark theme styling matching `reservations/show.php` booking discount
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 8.1, 8.2, 8.3, 8.4_

- [ ] 2. Implement JavaScript discount state management
  - [x] 2.1 Create global vehicleDiscounts Map for storing discount data per vehicle
    - Initialize Map inside IIFE scope
    - Structure: vehicleId => { type, value, subtotal, discountAmount, total }
    - _Requirements: 6.1, 7.2_

  - [x] 2.2 Implement getVehicleId() function
    - Generate unique ID using timestamp and random string
    - Store ID in vehicle block's data-vehicleId attribute
    - Return existing ID if already set
    - _Requirements: 6.1_

  - [ ]* 2.3 Write property test for vehicle discount independence
    - **Property 6: Vehicle Discount Independence**
    - **Validates: Requirements 6.1**

- [ ] 3. Implement discount calculation functions
  - [x] 3.1 Implement calculateVehicleSubtotal() function
    - Sum all rate prices for a given vehicle block
    - Parse rate-price inputs and handle invalid values
    - Return subtotal as number
    - _Requirements: 3.1, 4.3_

  - [x] 3.2 Implement calculateDiscountAmount() function
    - Accept subtotal, type, and value parameters
    - For percent type: calculate (subtotal × value ÷ 100), cap at 100%
    - For amount type: use value directly, cap at subtotal
    - Return 0 for null type or value <= 0
    - Round result to 2 decimal places
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.2, 3.3, 3.5_

  - [ ]* 3.3 Write property tests for discount calculations
    - **Property 1: Discount Validation and Capping**
    - **Validates: Requirements 2.1, 2.2, 2.3**
    - **Property 2: Percent Discount Calculation**
    - **Validates: Requirements 3.2, 3.5**
    - **Property 3: Fixed Amount Discount Calculation**
    - **Validates: Requirements 3.3, 3.5**
    - **Property 11: Monetary Precision**
    - **Validates: Requirements 3.5**

  - [x] 3.4 Implement updateVehicleDiscount() function
    - Read discount type and value from inputs
    - Show/hide value input based on type selection
    - Calculate subtotal, discount amount, and total
    - Store discount data in vehicleDiscounts Map
    - Update preview display with formatted values
    - Hide preview if no discount applied
    - _Requirements: 3.1, 3.4, 3.5_

  - [ ]* 3.5 Write property tests for discount updates
    - **Property 4: Discount Recalculation on Change**
    - **Validates: Requirements 3.1**
    - **Property 5: Discounted Total Calculation**
    - **Validates: Requirements 3.4, 4.5**

- [ ] 4. Checkpoint - Ensure calculation functions work correctly
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Add event listeners for discount inputs
  - [x] 5.1 Bind discount-type change event to updateVehicleDiscount()
    - Attach listener in addVehicle() function
    - Trigger recalculation when type changes
    - _Requirements: 3.1_

  - [x] 5.2 Bind discount-value input event to updateVehicleDiscount()
    - Attach listener in addVehicle() function
    - Trigger recalculation on value change
    - _Requirements: 3.1_

  - [x] 5.3 Bind rate-list input event to recalculate discount
    - Attach listener to rate-list container
    - Check if vehicle has discount before recalculating
    - Update discount when any rate price changes
    - _Requirements: 3.1_

  - [x] 5.4 Update remove-vehicle handler to clean up discount state
    - Delete vehicle's discount data from Map before removing block
    - _Requirements: 6.3, 7.3_

  - [ ]* 5.5 Write property test for discount cleanup
    - **Property 7: Discount Cleanup on Vehicle Removal**
    - **Validates: Requirements 6.3**

- [ ] 6. Integrate discount data into PDF generation
  - [x] 6.1 Modify buildPreview() to collect discount data per vehicle
    - Get vehicleId for each vehicle block
    - Retrieve discount data from vehicleDiscounts Map
    - Include discount object in vehicleData array
    - _Requirements: 4.1, 6.4_

  - [x] 6.2 Update PDF rendering to include discount breakdown
    - After rendering rate rows, check if discount exists
    - Add subtotal row if discount applied
    - Add discount row with type indicator (percentage or "Fixed")
    - Display discount amount as negative value with green color
    - Add total row with discounted amount
    - Use existing table styling for consistency
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [x] 6.3 Update grand total calculation to use discounted totals
    - Sum discounted vehicle totals instead of raw rates
    - Fall back to summing rates if no discount applied
    - Add delivery, return, and additional charges to grand total
    - _Requirements: 3.4, 4.5_

  - [ ]* 6.4 Write property tests for PDF discount inclusion
    - **Property 8: Discount Persistence Through Preview**
    - **Validates: Requirements 6.4**
    - **Property 9: PDF Discount Inclusion**
    - **Validates: Requirements 4.1**
    - **Property 10: PDF Discount Breakdown Format**
    - **Validates: Requirements 4.2, 4.3, 4.4**

- [x] 7. Final checkpoint - Ensure all tests pass and feature works end-to-end
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties across all input combinations
- Unit tests validate specific examples and edge cases
- The feature inherits existing permission control (`add_vehicles`) - no additional permission logic needed
- All discount data is temporary and lives only in JavaScript during the session
- Dark theme styling matches the booking discount feature in `reservations/show.php`
