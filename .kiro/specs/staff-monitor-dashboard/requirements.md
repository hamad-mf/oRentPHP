# Requirements Document

## Introduction

The Staff Monitor Dashboard provides administrators with real-time visibility into staff operational activities. The system visualizes existing activity log data through a dashboard interface showing global KPIs, individual staff performance metrics, active status tracking, and detailed chronological timelines of staff actions.

## Glossary

- **Staff_Monitor_Dashboard**: The main analytics interface displaying staff activity metrics and timelines
- **Activity_Log**: The existing `staff_activity_log` database table containing timestamped staff actions
- **KPI_Panel**: The top-level overview section displaying aggregated metrics across all staff
- **Staff_Card**: An individual card component showing a single staff member's activity summary
- **Timeline_Modal**: A slide-over panel displaying chronological details of a staff member's actions
- **Active_Status**: A real-time indicator showing whether a staff member has performed actions within the last 15 minutes
- **Date_Filter**: A navigation control allowing users to view historical activity data for specific dates
- **Action_Category**: Classification of activities into types: Leads, Reservations, Payments, or general Actions

## Requirements

### Requirement 1: Dashboard Access Control

**User Story:** As an administrator, I want the Staff Monitor Dashboard to be accessible only to authorized users, so that sensitive operational data remains secure.

#### Acceptance Criteria

1. THE Staff_Monitor_Dashboard SHALL require authentication before displaying any content
2. WHEN a user without admin privileges accesses the dashboard, THE Staff_Monitor_Dashboard SHALL verify the user has `view_staff_monitor` permission
3. IF a user lacks both admin privileges and `view_staff_monitor` permission, THEN THE Staff_Monitor_Dashboard SHALL deny access and redirect to an unauthorized page

### Requirement 2: Global KPI Display

**User Story:** As an administrator, I want to see top-level metrics for all staff activity, so that I can quickly assess overall operational performance.

#### Acceptance Criteria

1. THE KPI_Panel SHALL display Total Actions count for the selected date
2. THE KPI_Panel SHALL display Active Staff count showing distinct staff members who performed at least one action on the selected date
3. THE KPI_Panel SHALL display Lead Actions count for actions involving lead management
4. THE KPI_Panel SHALL display Reservation/Payment Actions count for actions involving reservations or payments
5. WHEN the selected date changes, THE KPI_Panel SHALL recalculate all metrics based on the new date

### Requirement 3: Date Filtering

**User Story:** As an administrator, I want to view staff activity for specific dates, so that I can analyze historical performance patterns.

#### Acceptance Criteria

1. THE Date_Filter SHALL default to the current date when no date parameter is provided
2. WHEN a user selects a different date, THE Staff_Monitor_Dashboard SHALL reload displaying data for the selected date
3. THE Date_Filter SHALL provide navigation controls to move forward and backward by one day
4. THE Date_Filter SHALL accept dates in YYYY-MM-DD format via URL parameter

### Requirement 4: Staff Activity Summary Cards

**User Story:** As an administrator, I want to see individual staff member activity summaries, so that I can identify performance levels and engagement patterns.

#### Acceptance Criteria

1. THE Staff_Monitor_Dashboard SHALL display a Staff_Card for each active staff member
2. THE Staff_Card SHALL show the staff member's name and role
3. THE Staff_Card SHALL display action counts broken down by category: Total Actions, Leads, Reservations, and Payments
4. THE Staff_Card SHALL be clickable to open the detailed Timeline_Modal for that staff member
5. WHEN no actions exist for a staff member on the selected date, THE Staff_Card SHALL display zero counts for all categories

### Requirement 5: Active Status Tracking

**User Story:** As an administrator, I want to see which staff members are currently active, so that I can understand real-time operational capacity.

#### Acceptance Criteria

1. THE Staff_Card SHALL display an Active_Status indicator for each staff member
2. WHEN a staff member's most recent action occurred within the last 15 minutes, THE Active_Status SHALL display "Active Now" with a green indicator
3. WHEN a staff member's most recent action occurred more than 15 minutes ago, THE Active_Status SHALL display "Last seen X time ago" with a red indicator
4. THE Active_Status SHALL calculate time differences relative to the current server time

### Requirement 6: Detailed Activity Timeline

**User Story:** As an administrator, I want to view a chronological timeline of a staff member's actions, so that I can understand their daily workflow and identify specific activities.

#### Acceptance Criteria

1. WHEN a user clicks a Staff_Card, THE Timeline_Modal SHALL open displaying that staff member's detailed activity log
2. THE Timeline_Modal SHALL display actions in reverse chronological order (most recent first)
3. THE Timeline_Modal SHALL show the timestamp, action type, and description for each activity
4. THE Timeline_Modal SHALL include a close button to dismiss the panel
5. THE Timeline_Modal SHALL filter activities to match the currently selected date

### Requirement 7: Timeline Data Retrieval

**User Story:** As a system, I need to efficiently retrieve detailed activity data, so that timeline displays load quickly without blocking the main interface.

#### Acceptance Criteria

1. THE Staff_Monitor_Dashboard SHALL request timeline data asynchronously via AJAX
2. WHEN timeline data is requested, THE system SHALL query the Activity_Log filtered by user_id and date
3. THE system SHALL return timeline data ordered by created_at timestamp in descending order
4. THE system SHALL include action, description, and created_at fields in the timeline response

### Requirement 8: Action Categorization

**User Story:** As an administrator, I want staff actions automatically categorized, so that I can understand the distribution of work types.

#### Acceptance Criteria

1. THE Staff_Monitor_Dashboard SHALL classify actions containing "lead" keywords as Lead Actions
2. THE Staff_Monitor_Dashboard SHALL classify actions containing "reservation" or "payment" keywords as Reservation/Payment Actions
3. THE Staff_Monitor_Dashboard SHALL count all actions regardless of type in the Total Actions metric
4. WHEN an action matches multiple categories, THE Staff_Monitor_Dashboard SHALL increment counts for all applicable categories

### Requirement 9: Visual Timeline Presentation

**User Story:** As an administrator, I want timeline entries to be visually distinct and easy to scan, so that I can quickly locate specific types of activities.

#### Acceptance Criteria

1. THE Timeline_Modal SHALL display timeline entries connected by a vertical line
2. THE Timeline_Modal SHALL include visual icons representing action types
3. THE Timeline_Modal SHALL use payment-related icons for payment actions
4. THE Timeline_Modal SHALL use user-profile icons for lead and client actions
5. THE Timeline_Modal SHALL format timestamps in a human-readable 12-hour format with AM/PM

### Requirement 10: Responsive Layout

**User Story:** As an administrator, I want the dashboard to work on different screen sizes, so that I can monitor staff activity from various devices.

#### Acceptance Criteria

1. THE Staff_Monitor_Dashboard SHALL display Staff_Cards in a single column on mobile devices
2. THE Staff_Monitor_Dashboard SHALL display Staff_Cards in a two-column grid on tablet devices
3. THE Staff_Monitor_Dashboard SHALL display Staff_Cards in a three-column grid on desktop devices
4. THE Timeline_Modal SHALL occupy full width on mobile devices and a maximum width of 24rem on larger screens
