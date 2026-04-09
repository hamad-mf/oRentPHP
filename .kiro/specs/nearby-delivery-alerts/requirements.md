# Requirements Document

## Introduction

The Nearby Delivery Alerts feature provides proactive dashboard notifications for confirmed reservations approaching their delivery date. This helps staff prepare for upcoming vehicle deliveries by displaying alerts on the main dashboard (index.php) with configurable threshold settings, visual urgency indicators, and direct navigation to reservation details.

## Glossary

- **Dashboard**: The main index.php page displayed after login
- **Alert_System**: The notification component that displays upcoming delivery warnings
- **Confirmed_Reservation**: A reservation with status='confirmed' waiting to be delivered
- **Delivery_Date**: The start_date field of a reservation indicating when the vehicle should be delivered
- **Alert_Threshold**: Configurable number of days before delivery_date when alerts should appear
- **Settings_Manager**: The system_settings table and settings_helpers.php functions
- **Urgency_Indicator**: Visual badge showing time remaining until delivery (colors, icons)
- **Reservation_Details**: The reservations/show.php page displaying full reservation information

## Requirements

### Requirement 1: Display Upcoming Delivery Alerts on Dashboard

**User Story:** As a staff member, I want to see upcoming vehicle deliveries on the dashboard, so that I can prepare vehicles and coordinate with clients proactively.

#### Acceptance Criteria

1. WHEN the Dashboard loads, THE Alert_System SHALL query all Confirmed_Reservations with Delivery_Date within the Alert_Threshold
2. THE Alert_System SHALL display alerts in a dedicated section on the Dashboard between fleet status and daily operations
3. THE Alert_System SHALL show vehicle details (brand, model, license_plate), client name, and Delivery_Date for each alert
4. WHEN no upcoming deliveries exist within the threshold, THE Alert_System SHALL NOT display the alert section
5. THE Alert_System SHALL order alerts by Delivery_Date ascending (nearest delivery first)
6. THE Alert_System SHALL limit display to a maximum of 10 alerts with scrollable overflow

### Requirement 2: Configurable Alert Threshold

**User Story:** As an administrator, I want to configure how many days in advance delivery alerts appear, so that I can adjust the notification timing to match our operational needs.

#### Acceptance Criteria

1. THE Settings_Manager SHALL store an 'upcoming_delivery_alert_days' setting with a default value of 3 days
2. WHEN accessing Settings > General, THE Settings_Manager SHALL display an "Upcoming Delivery Alerts" configuration section
3. THE Settings_Manager SHALL provide an input field for Alert_Threshold with minimum value of 1 day and maximum value of 30 days
4. WHEN the administrator saves settings, THE Settings_Manager SHALL validate and persist the Alert_Threshold value
5. THE Alert_System SHALL use the configured Alert_Threshold when querying upcoming deliveries

### Requirement 3: Visual Urgency Indicators

**User Story:** As a staff member, I want to quickly identify which deliveries are most urgent, so that I can prioritize my preparation tasks.

#### Acceptance Criteria

1. WHEN Delivery_Date equals today, THE Urgency_Indicator SHALL display a red pulsing badge with "Due Today" text
2. WHEN Delivery_Date is tomorrow, THE Urgency_Indicator SHALL display an orange badge with "Tomorrow" text
3. WHEN Delivery_Date is 2+ days away, THE Urgency_Indicator SHALL display a blue badge with "In X days" text
4. THE Urgency_Indicator SHALL use consistent styling with existing alert sections (held deposits, EMI alerts)
5. THE Alert_System SHALL display the formatted Delivery_Date in "dd MMM, hh:mm AM/PM" format

### Requirement 4: Navigation to Reservation Details

**User Story:** As a staff member, I want to click on a delivery alert to view full reservation details, so that I can review client information and delivery requirements.

#### Acceptance Criteria

1. WHEN a staff member clicks an alert, THE Alert_System SHALL navigate to reservations/show.php with the reservation ID
2. THE Alert_System SHALL render each alert as a clickable link with hover effects
3. THE Alert_System SHALL maintain consistent hover styling with other dashboard alert sections
4. THE Alert_System SHALL preserve the current user session during navigation

### Requirement 5: Alert Section Styling and Layout

**User Story:** As a user, I want delivery alerts to match the existing dashboard design, so that the interface feels cohesive and professional.

#### Acceptance Criteria

1. THE Alert_System SHALL use the same visual style as existing alert sections (bg-blue-500/10, border-blue-500/30)
2. THE Alert_System SHALL display a header with icon, title "🚗 Upcoming Deliveries", count badge, and threshold description
3. THE Alert_System SHALL render individual alerts with vehicle icon, reservation details, and urgency badge
4. THE Alert_System SHALL implement responsive layout that works on mobile and desktop viewports
5. THE Alert_System SHALL use the existing color palette (mb-surface, mb-subtle, mb-accent)

### Requirement 6: Database Query Performance

**User Story:** As a system administrator, I want delivery alerts to load quickly, so that dashboard performance remains optimal.

#### Acceptance Criteria

1. THE Alert_System SHALL use a single optimized SQL query with JOINs to fetch all required data
2. THE Alert_System SHALL use indexed columns (status, start_date) in WHERE clauses
3. THE Alert_System SHALL limit results to 10 records maximum
4. WHEN the query fails, THE Alert_System SHALL log the error and display zero alerts without breaking the dashboard
5. THE Alert_System SHALL execute in under 100ms for databases with up to 10,000 reservations

### Requirement 7: Graceful Degradation

**User Story:** As a developer, I want the alert system to handle missing data gracefully, so that the dashboard remains stable during schema migrations.

#### Acceptance Criteria

1. WHEN the system_settings table does not exist, THE Alert_System SHALL use the default threshold of 3 days
2. WHEN a reservation has NULL start_date, THE Alert_System SHALL exclude it from alerts
3. WHEN a client or vehicle record is missing, THE Alert_System SHALL display "Unknown Client" or "Unknown Vehicle"
4. WHEN the query encounters an error, THE Alert_System SHALL catch the exception and continue rendering the dashboard
5. THE Alert_System SHALL log all errors using app_log() with context information

### Requirement 8: Settings Page Integration

**User Story:** As an administrator, I want delivery alert settings grouped with related configuration options, so that I can manage all delivery-related settings in one place.

#### Acceptance Criteria

1. THE Settings_Manager SHALL add the Alert_Threshold configuration to the "Delivery Settings" section in settings/general.php
2. THE Settings_Manager SHALL display the configuration after the "Default Return Pickup Charge" field
3. THE Settings_Manager SHALL include helper text: "Alert will trigger when a confirmed reservation is due for delivery within this many days"
4. WHEN settings are saved, THE Settings_Manager SHALL use settings_set() to persist the value
5. THE Settings_Manager SHALL display a success message after saving

### Requirement 9: Alert Count Badge

**User Story:** As a staff member, I want to see the total number of upcoming deliveries at a glance, so that I can quickly assess my workload.

#### Acceptance Criteria

1. THE Alert_System SHALL count all Confirmed_Reservations within the Alert_Threshold
2. THE Alert_System SHALL display the count in a badge next to the alert section title
3. THE Alert_System SHALL use the same badge styling as other alert sections (bg-blue-500/20, rounded-full)
4. WHEN the count is zero, THE Alert_System SHALL NOT display the alert section
5. THE Alert_System SHALL update the count dynamically when the page is refreshed

### Requirement 10: Reservation Status Filtering

**User Story:** As a staff member, I want to see only confirmed reservations in delivery alerts, so that I don't receive notifications for pending or cancelled bookings.

#### Acceptance Criteria

1. THE Alert_System SHALL filter reservations WHERE status='confirmed'
2. THE Alert_System SHALL exclude reservations with status='pending', 'active', 'completed', or 'cancelled'
3. WHEN a reservation is delivered (status changes to 'active'), THE Alert_System SHALL immediately stop showing it in alerts
4. THE Alert_System SHALL use the existing reservations.status ENUM column
5. THE Alert_System SHALL NOT require any database schema changes for status filtering
