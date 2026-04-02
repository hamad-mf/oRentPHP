# Requirements Document

## Introduction

This feature adds a highlighted "Today" section at the top of the Hope Window list view to provide immediate visibility of today's expected and actual income data without requiring scrolling or clicking to expand. The highlighted section will display today's complete breakdown information in an expanded format, while today's row remains in the chronological list below.

## Glossary

- **Hope_Window**: The financial dashboard page that displays daily income targets, expected income, and actual income across a date range
- **List_View**: The Hope Window view mode that shows all days in a period as rows in a table
- **Day_View**: The Hope Window view mode that shows detailed information for a single selected day
- **Today_Section**: The new highlighted box displaying today's data at the top of the list view
- **Expected_Income_Breakdown**: The detailed list of scheduled reservation events (bookings, deliveries, returns, extensions) and predictions that contribute to expected income for a date
- **Actual_Income_Breakdown**: The detailed list of actual ledger entries recorded for a date
- **Predictions**: Custom income predictions added by users for specific dates
- **Today_Row**: The existing row in the day-by-day list that represents today's date

## Requirements

### Requirement 1: Display Today Section in List View

**User Story:** As a user viewing the Hope Window, I want to see today's complete income data highlighted at the top of the page, so that I can immediately understand today's financial status without scrolling or clicking.

#### Acceptance Criteria

1. WHEN the Hope Window is displayed in list view, THE Hope_Window SHALL render a Today_Section before the first day row
2. THE Today_Section SHALL display only when the current date falls within the selected period range
3. WHEN the current date is outside the selected period range, THE Hope_Window SHALL NOT display the Today_Section
4. THE Today_Section SHALL remain visible at the top of the list view regardless of scroll position within the viewport
5. THE Today_Section SHALL display the same date label format as used in day rows (e.g., "Mon, 25 Mar")

### Requirement 2: Today Section Content Display

**User Story:** As a user, I want the Today Section to show all the same information as an expanded day row, so that I have complete visibility of today's financial details.

#### Acceptance Criteria

1. THE Today_Section SHALL display the target amount for today
2. THE Today_Section SHALL display the expected income amount for today
3. THE Today_Section SHALL display the actual income amount for today
4. THE Today_Section SHALL display the variance between actual and expected income
5. THE Today_Section SHALL display the Expected_Income_Breakdown with all booking, delivery, return, extension, and prediction items
6. WHEN today is in the past or is the current date, THE Today_Section SHALL display the Actual_Income_Breakdown with all ledger entries
7. WHEN today is in the future, THE Today_Section SHALL NOT display the Actual_Income_Breakdown
8. THE Today_Section SHALL display the Predictions section with all custom predictions for today
9. THE Today_Section SHALL use the same rendering functions as the expanded day row (hope_render_breakdown, hope_render_actual_breakdown)

### Requirement 3: Visual Distinction and Styling

**User Story:** As a user, I want the Today Section to be visually distinct from regular day rows, so that I can immediately identify it as special highlighted content.

#### Acceptance Criteria

1. THE Today_Section SHALL use accent color highlighting to distinguish it from regular day rows
2. THE Today_Section SHALL use a border color that matches or complements the mb-accent theme color
3. THE Today_Section SHALL use a background color that provides visual emphasis while maintaining readability
4. THE Today_Section SHALL be larger or more prominent than regular day rows
5. THE Today_Section SHALL include a visual indicator (such as a label or icon) identifying it as "Today"
6. THE Today_Section SHALL maintain consistent spacing and padding with the overall Hope Window design system

### Requirement 4: Today Row Preservation in List

**User Story:** As a user, I want today's row to remain in the chronological day list, so that I can see today in context with other days and maintain the complete timeline.

#### Acceptance Criteria

1. THE Hope_Window SHALL continue to display today's date as a row in the chronological day list
2. THE Today_Row SHALL maintain its existing highlighting (border-mb-accent/60 bg-mb-accent/10)
3. THE Today_Row SHALL remain clickable to expand and show breakdown details
4. THE Today_Row SHALL display the same data as other day rows (target, expected, actual, predictions count, variance)
5. THE Today_Row SHALL appear in its chronological position within the list based on the date

### Requirement 5: Today Section Interactivity

**User Story:** As a user, I want to interact with items in the Today Section, so that I can navigate to related reservations and view detailed information.

#### Acceptance Criteria

1. WHEN a reservation link is displayed in the Today_Section Expected_Income_Breakdown, THE Hope_Window SHALL render it as a clickable link to the reservation detail page
2. WHEN a reservation link is displayed in the Today_Section Actual_Income_Breakdown, THE Hope_Window SHALL render it as a clickable link to the reservation detail page
3. WHEN a user clicks a reservation link in the Today_Section, THE Hope_Window SHALL navigate to the corresponding reservation show page
4. THE Today_Section SHALL display all monetary amounts using the hope_format_currency function for consistent formatting
5. THE Today_Section SHALL display client names using the e() function for proper HTML escaping

### Requirement 6: Today Section Data Accuracy

**User Story:** As a user, I want the Today Section to display accurate and up-to-date information, so that I can trust the data for financial decision-making.

#### Acceptance Criteria

1. THE Today_Section SHALL retrieve today's data from the same $dayByDate array used for rendering day rows
2. THE Today_Section SHALL retrieve today's breakdown data from the same $breakdownMap used for expanded day rows
3. THE Today_Section SHALL retrieve today's actual income breakdown using the hope_fetch_actual_breakdown function
4. THE Today_Section SHALL display the same target amount as shown in the Today_Row
5. THE Today_Section SHALL display the same expected income amount as shown in the Today_Row
6. THE Today_Section SHALL display the same actual income amount as shown in the Today_Row
7. THE Today_Section SHALL calculate variance using the same formula as the Today_Row (actual - expected)

### Requirement 7: Today Section Responsive Behavior

**User Story:** As a user on different devices, I want the Today Section to display properly on various screen sizes, so that I can access today's information on any device.

#### Acceptance Criteria

1. THE Today_Section SHALL use responsive grid layouts that adapt to different screen widths
2. WHEN displayed on mobile devices, THE Today_Section SHALL stack content vertically for readability
3. WHEN displayed on tablet devices, THE Today_Section SHALL use a multi-column layout where appropriate
4. WHEN displayed on desktop devices, THE Today_Section SHALL use the full available width efficiently
5. THE Today_Section SHALL maintain readability and usability at all supported screen sizes

### Requirement 8: Today Section Position and Spacing

**User Story:** As a user, I want the Today Section to be clearly separated from the day list, so that I can distinguish between the highlighted today view and the chronological list.

#### Acceptance Criteria

1. THE Hope_Window SHALL render the Today_Section after the summary cards and before the "Daily Targets" section
2. THE Today_Section SHALL have vertical spacing (margin) that separates it from surrounding content
3. THE Today_Section SHALL be contained within the same max-width container as other Hope Window content
4. THE Today_Section SHALL align with the left and right edges of other Hope Window sections
5. THE Today_Section SHALL include a clear visual separator (such as additional spacing or a border) between itself and the day list table
