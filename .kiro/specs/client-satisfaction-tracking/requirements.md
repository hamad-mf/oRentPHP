# Requirements Document

## Introduction

This feature adds client satisfaction tracking to the reservation return process. When returning a vehicle, staff will capture whether the client was satisfied with the rental experience and optionally record a comment. This data will be stored in the database and displayed in the reservation list for completed reservations, providing visibility into client satisfaction trends.

## Glossary

- **Return_Form**: The web form at `reservations/return.php` used to process vehicle returns
- **Reservation_List**: The table view at `reservations/index.php` showing all reservations
- **Client_Satisfaction_Status**: A yes/no indicator of whether the client was satisfied with their rental
- **Client_Comment**: Optional text feedback from the client about their rental experience
- **Completed_Reservation**: A reservation with status='completed' after vehicle return processing

## Requirements

### Requirement 1: Capture Client Satisfaction on Return

**User Story:** As a staff member, I want to record whether the client was satisfied when returning a vehicle, so that we can track client satisfaction trends.

#### Acceptance Criteria

1. WHEN the Return_Form is displayed, THE Return_Form SHALL display a "Client Satisfied?" field with Yes/No radio buttons after the client rating field
2. WHEN the Return_Form is displayed, THE Return_Form SHALL display an optional "Client Comment" text input field after the satisfaction radio buttons
3. WHEN the return form is submitted without selecting a satisfaction option, THE Return_Form SHALL accept the submission (satisfaction is optional)
4. WHEN the return form is submitted with a satisfaction selection, THE Return_Form SHALL save the selection to the database
5. WHEN the return form is submitted with a client comment, THE Return_Form SHALL save the comment to the database (maximum 255 characters)

### Requirement 2: Store Client Satisfaction Data

**User Story:** As a system administrator, I want client satisfaction data stored in the database, so that it persists and can be analyzed.

#### Acceptance Criteria

1. THE Database SHALL include a `client_satisfied` column in the `reservations` table with ENUM type ('yes', 'no', NULL)
2. THE Database SHALL include a `client_comment` column in the `reservations` table with VARCHAR(255) type, nullable
3. WHEN a reservation is returned with satisfaction data, THE Database SHALL store the satisfaction status in `client_satisfied`
4. WHEN a reservation is returned with a client comment, THE Database SHALL store the comment in `client_comment`
5. WHEN a reservation is returned without satisfaction data, THE Database SHALL store NULL in both fields

### Requirement 3: Display Satisfaction in Reservation List

**User Story:** As a staff member, I want to see client satisfaction status in the reservation list, so that I can quickly identify satisfied and unsatisfied clients.

#### Acceptance Criteria

1. WHEN viewing the Reservation_List, THE Reservation_List SHALL display a satisfaction indicator for each Completed_Reservation
2. WHEN a Completed_Reservation has `client_satisfied='yes'`, THE Reservation_List SHALL display a satisfied icon or badge
3. WHEN a Completed_Reservation has `client_satisfied='no'`, THE Reservation_List SHALL display a not-satisfied icon or badge
4. WHEN a Completed_Reservation has `client_satisfied=NULL`, THE Reservation_List SHALL display no satisfaction indicator
5. WHEN a Completed_Reservation has a `client_comment`, THE Reservation_List SHALL display the comment text (truncated if longer than 50 characters)
6. WHEN displaying a truncated comment, THE Reservation_List SHALL prevent text overflow and maintain layout integrity

### Requirement 4: Database Migration

**User Story:** As a system administrator, I want a safe database migration script, so that I can add the satisfaction tracking columns to production without data loss.

#### Acceptance Criteria

1. THE Migration_Script SHALL add `client_satisfied` column using ALTER TABLE IF NOT EXISTS pattern
2. THE Migration_Script SHALL add `client_comment` column using ALTER TABLE IF NOT EXISTS pattern
3. THE Migration_Script SHALL be idempotent (safe to run multiple times)
4. THE Migration_Script SHALL follow the project's migration file naming pattern (YYYY-MM-DD_feature_name.sql)
5. THE Migration_Script SHALL be documented in PRODUCTION_DB_STEPS.md under "Pending" section

### Requirement 5: UI Styling Consistency

**User Story:** As a staff member, I want the satisfaction fields to match the existing UI, so that the interface feels cohesive.

#### Acceptance Criteria

1. THE Return_Form satisfaction fields SHALL use the existing dark theme color scheme from return.php
2. THE Return_Form satisfaction fields SHALL use the existing form input styling patterns (border, padding, focus states)
3. THE Reservation_List satisfaction indicators SHALL use subtle badges or icons that do not clutter the layout
4. THE Reservation_List satisfaction indicators SHALL use color coding consistent with the existing status badge patterns
5. WHEN displaying satisfaction data, THE UI SHALL maintain responsive layout on mobile and desktop viewports
