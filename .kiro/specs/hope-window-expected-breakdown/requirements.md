# Requirements Document

## Introduction

The Hope Window currently displays a total "Expected Income" amount for each day but does not provide visibility into how that amount is calculated. This feature adds a detailed breakdown section that shows exactly which reservations, extensions, and custom predictions contribute to each day's expected income, helping users understand and verify the projections.

## Glossary

- **Hope_Window**: The financial projection interface that displays expected and actual income by date
- **Expected_Income**: The projected income amount for a specific date, calculated from multiple sources
- **Breakdown_Section**: A detailed list showing individual components that sum to the Expected_Income
- **Reservation**: A vehicle rental booking with associated payment events
- **Extension**: A reservation extension with an associated payment amount
- **Custom_Prediction**: A manually entered expected income entry from the hope_daily_predictions table
- **Booking_Event**: Payment collected when a reservation is created (advance + prepaid delivery charge)
- **Delivery_Event**: Payment due when a vehicle is delivered to the client
- **Return_Event**: Payment due when a vehicle is returned by the client
- **Day_View**: The detailed view showing a single date's financial information
- **List_View**: The tabular view showing multiple dates with summary information

## Requirements

### Requirement 1: Display Breakdown for Booking Events

**User Story:** As a financial manager, I want to see which reservations contributed booking payments on a specific date, so that I can verify the expected income calculation.

#### Acceptance Criteria

1. WHEN a reservation has a booking date matching the viewed date, THE Breakdown_Section SHALL display the reservation ID, client name, "Booking" label, and the sum of advance_paid and delivery_charge_prepaid
2. THE Breakdown_Section SHALL display booking events only when the sum of advance_paid and delivery_charge_prepaid is greater than zero
3. THE Breakdown_Section SHALL format booking entries as "Res #{id} - {client_name} - Booking: ${amount}"

### Requirement 2: Display Breakdown for Delivery Events

**User Story:** As a financial manager, I want to see which reservations have delivery payments due on a specific date, so that I can track expected income from vehicle deliveries.

#### Acceptance Criteria

1. WHEN a reservation has a start_date matching the viewed date, THE Breakdown_Section SHALL display the reservation ID, client name, "Delivery" label, and the amount calculated by hope_calc_delivery_due()
2. THE Breakdown_Section SHALL display delivery events only when the calculated delivery due amount is greater than zero
3. THE Breakdown_Section SHALL format delivery entries as "Res #{id} - {client_name} - Delivery: ${amount}"

### Requirement 3: Display Breakdown for Return Events

**User Story:** As a financial manager, I want to see which reservations have return payments due on a specific date, so that I can track expected income from vehicle returns.

#### Acceptance Criteria

1. WHEN a reservation has an end_date matching the viewed date, THE Breakdown_Section SHALL display the reservation ID, client name, "Return" label, and the amount calculated by hope_calc_return_due()
2. THE Breakdown_Section SHALL display return events only when the calculated return due amount is greater than zero
3. THE Breakdown_Section SHALL format return entries as "Res #{id} - {client_name} - Return: ${amount}"

### Requirement 4: Display Breakdown for Extension Payments

**User Story:** As a financial manager, I want to see which extensions contributed payments on a specific date, so that I can account for extension revenue in the expected income.

#### Acceptance Criteria

1. WHEN a reservation_extension has a created_at date matching the viewed date, THE Breakdown_Section SHALL display the extension ID, associated reservation ID, client name, and the extension amount
2. THE Breakdown_Section SHALL display extension events only when the extension amount is greater than zero
3. THE Breakdown_Section SHALL format extension entries as "Extension #{ext_id} (Res #{res_id} - {client_name}): ${amount}"

### Requirement 5: Display Breakdown for Custom Predictions

**User Story:** As a financial manager, I want to see custom predictions that contribute to a specific date's expected income, so that I can understand manually added projections.

#### Acceptance Criteria

1. WHEN a hope_daily_predictions entry exists for the viewed date, THE Breakdown_Section SHALL display the prediction description and amount
2. THE Breakdown_Section SHALL display custom prediction entries only when the amount is greater than zero
3. THE Breakdown_Section SHALL format custom prediction entries as "Custom: {description} - ${amount}"

### Requirement 6: Display Breakdown Total

**User Story:** As a financial manager, I want to see the sum of all breakdown items, so that I can verify it matches the displayed Expected_Income total.

#### Acceptance Criteria

1. THE Breakdown_Section SHALL display a total line summing all breakdown items
2. THE Breakdown_Section SHALL format the total as "Total Expected: ${sum}"
3. THE total displayed in the Breakdown_Section SHALL equal the Expected_Income amount shown for the date

### Requirement 7: Show Breakdown in Day View

**User Story:** As a financial manager, I want to see the breakdown when viewing a specific day, so that I can immediately understand the expected income composition.

#### Acceptance Criteria

1. WHEN the Hope_Window is in Day_View mode, THE Breakdown_Section SHALL be visible without requiring user interaction
2. THE Breakdown_Section SHALL appear below or adjacent to the Expected_Income total
3. THE Breakdown_Section SHALL display all breakdown items sorted by amount in descending order

### Requirement 8: Show Breakdown in List View

**User Story:** As a financial manager, I want to expand a date row in list view to see the breakdown, so that I can quickly investigate specific dates without switching views.

#### Acceptance Criteria

1. WHEN the Hope_Window is in List_View mode, THE Breakdown_Section SHALL be hidden by default
2. WHEN a user clicks on a date row in List_View, THE Breakdown_Section SHALL expand to show the breakdown items for that date
3. WHEN a user clicks on an expanded date row, THE Breakdown_Section SHALL collapse
4. THE Hope_Window SHALL allow only one date row to be expanded at a time

### Requirement 9: Handle Cancelled Reservations

**User Story:** As a financial manager, I want cancelled reservations excluded from the breakdown, so that I only see valid expected income sources.

#### Acceptance Criteria

1. THE Breakdown_Section SHALL exclude reservations with status equal to 'cancelled'
2. THE Breakdown_Section SHALL exclude extensions associated with cancelled reservations

### Requirement 10: Retrieve Client Names

**User Story:** As a financial manager, I want to see client names in the breakdown, so that I can quickly identify which customers are associated with each payment.

#### Acceptance Criteria

1. WHEN displaying a reservation event, THE Breakdown_Section SHALL retrieve and display the associated client name from the clients table
2. WHEN a client name is not available, THE Breakdown_Section SHALL display "Unknown Client"
3. THE Breakdown_Section SHALL join the reservations table with the clients table using the client_id foreign key

### Requirement 11: Format Currency Values

**User Story:** As a financial manager, I want currency amounts formatted consistently, so that I can easily read and compare values.

#### Acceptance Criteria

1. THE Breakdown_Section SHALL format all currency amounts with a currency symbol
2. THE Breakdown_Section SHALL format all currency amounts with two decimal places
3. THE Breakdown_Section SHALL format all currency amounts with thousand separators where applicable

### Requirement 12: Handle Missing Extension Table

**User Story:** As a system administrator, I want the breakdown to work even if the reservation_extensions table doesn't exist, so that the feature is backward compatible.

#### Acceptance Criteria

1. WHEN the reservation_extensions table does not exist, THE Breakdown_Section SHALL display booking, delivery, return, and custom prediction events without errors
2. WHEN the reservation_extensions table does not exist, THE Breakdown_Section SHALL not display extension events
3. THE Hope_Window SHALL check for the existence of the reservation_extensions table before querying it
