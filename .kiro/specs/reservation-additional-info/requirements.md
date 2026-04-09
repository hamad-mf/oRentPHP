# Requirements Document

## Introduction

This feature adds a dedicated section to the reservation details screen for storing and displaying supplementary information that is not used in business logic or calculations. The section will include three editable fields: delivery location, return location, and a general note field. These fields are for reference purposes only and will be stored in the database but not integrated into any other system workflows.

## Glossary

- **Reservation_Details_Screen**: The page that displays complete information about a single reservation (reservations/show.php)
- **Additional_Info_Section**: A dedicated UI section on the reservation details screen for displaying delivery location, return location, and notes
- **Database**: The MySQL database storing reservation data
- **Admin**: A user with administrative privileges who can edit reservation information
- **Staff**: A user with staff-level permissions who can view and potentially edit reservation information

## Requirements

### Requirement 1: Display Additional Information Section

**User Story:** As a user viewing a reservation, I want to see a dedicated section for additional information, so that I can quickly access delivery location, return location, and notes.

#### Acceptance Criteria

1. THE Reservation_Details_Screen SHALL display an Additional_Info_Section below the main reservation details
2. THE Additional_Info_Section SHALL display the delivery location field
3. THE Additional_Info_Section SHALL display the return location field
4. THE Additional_Info_Section SHALL display the note field
5. WHEN any of the three fields contain data, THE Additional_Info_Section SHALL display that data
6. WHEN all three fields are empty, THE Additional_Info_Section SHALL display a message indicating no additional information is available

### Requirement 2: Edit Additional Information

**User Story:** As an authorized user, I want to edit the delivery location, return location, and notes, so that I can update supplementary reservation information.

#### Acceptance Criteria

1. WHEN a user with edit permissions views the Additional_Info_Section, THE Reservation_Details_Screen SHALL display an edit button or inline edit controls
2. WHEN the edit control is activated, THE Reservation_Details_Screen SHALL display editable input fields for delivery location, return location, and note
3. THE Reservation_Details_Screen SHALL accept text input up to 255 characters for delivery location
4. THE Reservation_Details_Screen SHALL accept text input up to 255 characters for return location
5. THE Reservation_Details_Screen SHALL accept text input up to 1000 characters for the note field
6. WHEN the user saves the changes, THE Reservation_Details_Screen SHALL validate the input lengths
7. WHEN validation passes, THE Reservation_Details_Screen SHALL persist the data to the Database
8. WHEN the save operation succeeds, THE Reservation_Details_Screen SHALL display a success message
9. IF validation fails, THEN THE Reservation_Details_Screen SHALL display an error message indicating which field exceeded the character limit

### Requirement 3: Store Additional Information in Database

**User Story:** As a system administrator, I want the additional information fields stored in the database, so that the data persists across sessions.

#### Acceptance Criteria

1. THE Database SHALL store the delivery_location field in the reservations table
2. THE Database SHALL store the return_location field in the reservations table
3. THE Database SHALL store the additional_note field in the reservations table
4. THE Database SHALL allow NULL values for all three additional information fields
5. WHEN a reservation is created, THE Database SHALL initialize all three fields to NULL
6. WHEN a user updates any of the three fields, THE Database SHALL store the new values
7. WHEN a user clears any of the three fields, THE Database SHALL store NULL for that field

### Requirement 4: Database Migration

**User Story:** As a system administrator, I want a migration script to add the new columns, so that I can safely update the production database.

#### Acceptance Criteria

1. THE migration script SHALL add a delivery_location column of type VARCHAR(255) NULL to the reservations table
2. THE migration script SHALL add a return_location column of type VARCHAR(255) NULL to the reservations table
3. THE migration script SHALL add an additional_note column of type TEXT NULL to the reservations table
4. THE migration script SHALL be idempotent using IF NOT EXISTS or equivalent checks
5. THE migration script SHALL be placed in the migrations/releases directory with a date-prefixed filename
6. WHEN the migration is applied, THE Database SHALL contain the three new columns without affecting existing data

### Requirement 5: Follow Session Rules

**User Story:** As a developer, I want the implementation to follow the established session rules, so that the feature integrates consistently with the existing codebase.

#### Acceptance Criteria

1. THE implementation SHALL create a separate SQL migration file for database changes
2. THE implementation SHALL add the migration to PRODUCTION_DB_STEPS.md under the Pending section
3. THE implementation SHALL NOT run database migrations automatically
4. THE implementation SHALL use idempotent SQL statements in the migration file
5. THE implementation SHALL make code edits only in the main project directory, not in SERVER UPDATE

### Requirement 6: Display-Only Data

**User Story:** As a developer, I want to ensure the additional information fields are not used in any business logic, so that they remain purely informational.

#### Acceptance Criteria

1. THE Reservation_Details_Screen SHALL display the additional information fields for reference only
2. THE additional information fields SHALL NOT be used in any pricing calculations
3. THE additional information fields SHALL NOT be used in any reservation status logic
4. THE additional information fields SHALL NOT be used in any reporting or analytics features
5. THE additional information fields SHALL NOT be required for any reservation workflow transitions
6. THE additional information fields SHALL NOT affect any existing reservation functionality
