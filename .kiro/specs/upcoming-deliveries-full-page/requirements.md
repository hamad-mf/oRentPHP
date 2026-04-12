# Requirements Document

## Introduction

This feature adds a dedicated full-page view for all upcoming deliveries in the rental management system. Currently, the dashboard displays an "Upcoming Deliveries" alert that shows only confirmed reservations due within a configurable threshold (default 3 days). This new page will provide a comprehensive view of ALL upcoming deliveries without any day threshold limitation, accessible via a "View All" link from the dashboard alert.

## Glossary

- **Dashboard**: The main admin dashboard page (index.php) that displays system overview and alerts
- **Upcoming_Deliveries_Alert**: The dashboard widget that shows confirmed reservations due within the threshold period
- **Upcoming_Deliveries_Page**: The new dedicated page that displays all future confirmed deliveries
- **Confirmed_Reservation**: A reservation with status='confirmed' and a future start_date
- **Delivery_Threshold**: The configurable number of days used to filter dashboard alerts (default 3 days)
- **Start_Date**: The scheduled delivery date/time for a reservation
- **Delivery_Alert_System**: The existing system that queries and displays upcoming deliveries on the dashboard

## Requirements

### Requirement 1: Dashboard Alert Enhancement

**User Story:** As an admin, I want to see a "View All" link on the dashboard's upcoming deliveries alert, so that I can access the full list of upcoming deliveries.

#### Acceptance Criteria

1. WHEN the Upcoming_Deliveries_Alert contains one or more deliveries, THE Dashboard SHALL display a "View All" link or button
2. WHEN the admin clicks the "View All" link, THE Dashboard SHALL navigate to the Upcoming_Deliveries_Page
3. THE "View All" link SHALL be visually distinct and easily discoverable within the alert section
4. WHEN the Upcoming_Deliveries_Alert is empty, THE Dashboard SHALL NOT display the "View All" link

### Requirement 2: Full Deliveries Page Display

**User Story:** As an admin, I want to view all upcoming deliveries on a dedicated page, so that I can see the complete delivery schedule without day threshold limitations.

#### Acceptance Criteria

1. THE Upcoming_Deliveries_Page SHALL display all Confirmed_Reservations where start_date >= NOW()
2. THE Upcoming_Deliveries_Page SHALL NOT apply the Delivery_Threshold filter
3. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display client name, vehicle brand, vehicle model, license plate, and delivery date/time
4. THE Upcoming_Deliveries_Page SHALL order deliveries by start_date in ascending order (earliest first)
5. WHEN no upcoming deliveries exist, THE Upcoming_Deliveries_Page SHALL display an appropriate empty state message

### Requirement 3: Delivery Information Display

**User Story:** As an admin, I want to see comprehensive delivery information on the full page, so that I can prepare for upcoming vehicle handovers.

#### Acceptance Criteria

1. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display the reservation ID
2. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display the client name as a clickable link to the client details
3. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display the vehicle information (brand, model, license plate)
4. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display the delivery date and time in 12-hour format
5. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display an urgency indicator (Due Today, Tomorrow, In X days)
6. WHEN a delivery is due today, THE Upcoming_Deliveries_Page SHALL highlight it with a critical urgency indicator
7. WHEN a delivery is due tomorrow, THE Upcoming_Deliveries_Page SHALL highlight it with a high urgency indicator

### Requirement 4: Navigation and Access

**User Story:** As an admin, I want easy navigation between the dashboard and the full deliveries page, so that I can efficiently manage my workflow.

#### Acceptance Criteria

1. THE Upcoming_Deliveries_Page SHALL include a link back to the Dashboard
2. THE Upcoming_Deliveries_Page SHALL be accessible via direct URL
3. THE Upcoming_Deliveries_Page SHALL follow the existing application navigation structure
4. THE Upcoming_Deliveries_Page SHALL include the standard application header and footer

### Requirement 5: Filtering and Sorting Options

**User Story:** As an admin, I want to filter and sort the upcoming deliveries list, so that I can focus on specific time periods or vehicles.

#### Acceptance Criteria

1. THE Upcoming_Deliveries_Page SHALL provide a date range filter (from date, to date)
2. WHEN a date range filter is applied, THE Upcoming_Deliveries_Page SHALL display only deliveries within that range
3. THE Upcoming_Deliveries_Page SHALL provide a search filter for client name or vehicle information
4. WHEN a search filter is applied, THE Upcoming_Deliveries_Page SHALL display only matching deliveries
5. THE Upcoming_Deliveries_Page SHALL maintain the default sort order by start_date ascending
6. WHEN filters are cleared, THE Upcoming_Deliveries_Page SHALL return to showing all upcoming deliveries

### Requirement 6: Pagination Support

**User Story:** As an admin, I want the deliveries list to be paginated, so that the page loads quickly even with many upcoming deliveries.

#### Acceptance Criteria

1. WHEN the number of upcoming deliveries exceeds the per-page limit, THE Upcoming_Deliveries_Page SHALL display pagination controls
2. THE Upcoming_Deliveries_Page SHALL use the system's configured per-page setting
3. THE Upcoming_Deliveries_Page SHALL display the current page number and total pages
4. WHEN the admin navigates between pages, THE Upcoming_Deliveries_Page SHALL preserve active filters
5. THE Upcoming_Deliveries_Page SHALL display a count of total upcoming deliveries

### Requirement 7: Reservation Quick Actions

**User Story:** As an admin, I want quick action links for each delivery, so that I can efficiently manage reservations from the deliveries page.

#### Acceptance Criteria

1. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display a "View" link to the reservation details page
2. FOR EACH delivery, THE Upcoming_Deliveries_Page SHALL display a "Deliver" action button
3. WHEN the admin clicks "Deliver", THE Upcoming_Deliveries_Page SHALL navigate to the delivery inspection page
4. THE quick action links SHALL follow the existing permission system
5. WHEN a user lacks delivery permissions, THE Upcoming_Deliveries_Page SHALL NOT display the "Deliver" action

### Requirement 8: Responsive Design

**User Story:** As an admin, I want the deliveries page to work on different screen sizes, so that I can access it from various devices.

#### Acceptance Criteria

1. THE Upcoming_Deliveries_Page SHALL be responsive and functional on desktop screens
2. THE Upcoming_Deliveries_Page SHALL be responsive and functional on tablet screens
3. THE Upcoming_Deliveries_Page SHALL be responsive and functional on mobile screens
4. WHEN viewed on mobile, THE Upcoming_Deliveries_Page SHALL adapt the layout for smaller screens
5. THE Upcoming_Deliveries_Page SHALL follow the existing application's responsive design patterns

### Requirement 9: Performance and Error Handling

**User Story:** As an admin, I want the deliveries page to load quickly and handle errors gracefully, so that I have a reliable experience.

#### Acceptance Criteria

1. WHEN a database query fails, THE Upcoming_Deliveries_Page SHALL log the error using the application's logging system
2. WHEN a database query fails, THE Upcoming_Deliveries_Page SHALL display a user-friendly error message
3. THE Upcoming_Deliveries_Page SHALL execute queries efficiently using appropriate indexes
4. THE Upcoming_Deliveries_Page SHALL limit result sets using pagination to prevent performance issues
5. WHEN the page loads successfully, THE Upcoming_Deliveries_Page SHALL display results within 2 seconds under normal conditions
