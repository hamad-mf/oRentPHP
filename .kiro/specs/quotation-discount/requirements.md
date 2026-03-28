# Requirements Document

## Introduction

This document specifies requirements for adding discount functionality to the quotation builder in the vehicles list screen. The discount feature allows users with appropriate permissions to apply per-vehicle discounts (percentage or fixed amount) to quotations before PDF generation. Discounts are temporary and not persisted to the database.

## Glossary

- **Quotation_Builder**: The `vehicles/quotation_builder.php` screen where users create quotations by adding vehicles, rental types, and charges
- **Vehicle_Block**: A single vehicle entry within the quotation builder containing vehicle details and rental rates
- **Grand_Total**: The sum of all rental rates, delivery charge, return charge, and optional additional charge for a vehicle
- **Discount_Type**: Either "percent" (percentage-based) or "amount" (fixed dollar amount)
- **Discount_Value**: The numeric value of the discount (e.g., 10 for 10% or 500 for $500)
- **Discounted_Total**: The grand total after applying the discount
- **User**: A staff member with `add_vehicles` permission who can create quotations

## Requirements

### Requirement 1: Per-Vehicle Discount Input

**User Story:** As a user, I want to set a discount for each vehicle in the quotation, so that I can offer competitive pricing to customers.

#### Acceptance Criteria

1. WHEN a Vehicle_Block is displayed, THE Quotation_Builder SHALL display discount input controls within that Vehicle_Block
2. THE Quotation_Builder SHALL provide a dropdown to select Discount_Type with options "percent" and "amount"
3. THE Quotation_Builder SHALL provide a numeric input field for Discount_Value
4. THE Quotation_Builder SHALL initialize Discount_Type to null and Discount_Value to 0 for new Vehicle_Blocks
5. WHERE a Vehicle_Block exists, THE discount controls SHALL be visually consistent with the existing booking discount feature in `reservations/show.php`

### Requirement 2: Discount Validation

**User Story:** As a user, I want the system to prevent invalid discounts, so that I don't create incorrect quotations.

#### Acceptance Criteria

1. WHEN Discount_Type is "percent" AND Discount_Value exceeds 100, THE Quotation_Builder SHALL cap Discount_Value at 100
2. WHEN Discount_Type is "amount" AND Discount_Value exceeds Grand_Total, THE Quotation_Builder SHALL cap Discount_Value at Grand_Total
3. THE Quotation_Builder SHALL enforce Discount_Value to be non-negative
4. WHEN Discount_Value is 0 OR Discount_Type is null, THE Quotation_Builder SHALL treat the discount as not applied

### Requirement 3: Real-Time Discount Calculation

**User Story:** As a user, I want to see the discounted price update immediately when I change the discount, so that I can verify the final amount.

#### Acceptance Criteria

1. WHEN Discount_Type OR Discount_Value changes, THE Quotation_Builder SHALL recalculate the discount amount for that Vehicle_Block
2. WHEN Discount_Type is "percent", THE Quotation_Builder SHALL calculate discount amount as (Grand_Total × Discount_Value ÷ 100)
3. WHEN Discount_Type is "amount", THE Quotation_Builder SHALL use Discount_Value as the discount amount
4. THE Quotation_Builder SHALL display the Discounted_Total for each Vehicle_Block
5. THE Quotation_Builder SHALL round all monetary calculations to 2 decimal places

### Requirement 4: Quotation PDF Generation with Discount

**User Story:** As a user, I want the quotation PDF to show the discount breakdown, so that customers can see the savings clearly.

#### Acceptance Criteria

1. WHEN a discount is applied to a Vehicle_Block, THE Quotation_Builder SHALL include a discount line in the PDF for that vehicle
2. THE discount line SHALL display the Discount_Type indicator (e.g., "10%" or "$500")
3. THE Quotation_Builder SHALL display the subtotal (Grand_Total before discount) in the PDF
4. THE Quotation_Builder SHALL display the discount amount as a negative value or clearly marked deduction
5. THE Quotation_Builder SHALL display the Discounted_Total as the final price for that vehicle
6. WHEN no discount is applied to a Vehicle_Block, THE Quotation_Builder SHALL NOT display discount lines for that vehicle

### Requirement 5: Permission Control

**User Story:** As a system administrator, I want only authorized users to set discounts, so that pricing control is maintained.

#### Acceptance Criteria

1. THE Quotation_Builder SHALL allow discount input only for users with `add_vehicles` permission
2. WHEN a user lacks `add_vehicles` permission, THE Quotation_Builder SHALL NOT display the quotation builder page
3. THE permission check SHALL be consistent with the existing quotation builder access control

### Requirement 6: Multi-Vehicle Independence

**User Story:** As a user, I want to set different discounts for each vehicle in the quotation, so that I can offer flexible pricing.

#### Acceptance Criteria

1. THE Quotation_Builder SHALL maintain separate discount settings for each Vehicle_Block
2. WHEN a discount is set on one Vehicle_Block, THE Quotation_Builder SHALL NOT affect discount settings on other Vehicle_Blocks
3. WHEN a Vehicle_Block is removed, THE Quotation_Builder SHALL discard that vehicle's discount settings
4. THE Quotation_Builder SHALL preserve discount settings when the quotation preview is generated

### Requirement 7: Temporary Discount Storage

**User Story:** As a user, I want discounts to be used only for PDF generation, so that they don't affect the database or other systems.

#### Acceptance Criteria

1. THE Quotation_Builder SHALL NOT persist discount data to any database table
2. THE Quotation_Builder SHALL store discount data only in browser memory during the quotation creation session
3. WHEN the quotation builder page is closed or refreshed, THE Quotation_Builder SHALL discard all discount data
4. THE Quotation_Builder SHALL use discount data only for PDF generation calculations

### Requirement 8: UI Consistency with Booking Discount

**User Story:** As a user, I want the discount UI to match the booking discount feature, so that I have a familiar experience.

#### Acceptance Criteria

1. THE Quotation_Builder SHALL use the same dropdown style for Discount_Type as `reservations/show.php` lines 22-64
2. THE Quotation_Builder SHALL use the same input field style for Discount_Value as `reservations/show.php` lines 22-64
3. THE Quotation_Builder SHALL use consistent color schemes and spacing with the booking discount feature
4. THE Quotation_Builder SHALL display discount controls in a logical position within each Vehicle_Block

