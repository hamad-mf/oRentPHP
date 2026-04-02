# Requirements Document

## Introduction

This feature implements a digital vehicle inspection job card system as a standalone page in the oRentPHP system. The job card allows staff to perform comprehensive vehicle inspections by checking 37 predefined inspection items, recording their status, and adding notes. The system captures vehicle identification, inspection results, and staff observations, storing all data in the database for future reference and vehicle maintenance tracking.

## Glossary

- **Job_Card_Form**: The web form page for recording vehicle inspection data
- **Inspection_Item**: A single checkable element from the 37-item inspection checklist
- **Check_Status**: A boolean indicator (checked/unchecked) for each inspection item
- **Inspection_Record**: A complete saved inspection with all 37 items and their statuses
- **Vehicle_Selector**: A dropdown field for selecting which vehicle to inspect
- **Staff_User**: An authenticated user with permission to perform vehicle inspections
- **Inspection_Database**: The database table storing inspection records and item statuses

## Requirements

### Requirement 1: Display Job Card Form

**User Story:** As a staff member, I want to access a dedicated job card page, so that I can perform vehicle inspections in a structured manner.

#### Acceptance Criteria

1. THE Job_Card_Form SHALL be accessible at a dedicated URL path within the oRentPHP system
2. WHEN the Job_Card_Form is loaded, THE Job_Card_Form SHALL display the company header with logo and contact information
3. WHEN the Job_Card_Form is loaded, THE Job_Card_Form SHALL display a Vehicle_Selector dropdown at the top of the form
4. WHEN the Job_Card_Form is loaded, THE Job_Card_Form SHALL display all 37 Inspection_Items in a table format
5. THE Job_Card_Form SHALL use the existing oRentPHP dark theme styling (mb-surface, mb-accent, mb-subtle colors)

### Requirement 2: Vehicle Selection

**User Story:** As a staff member, I want to select which vehicle I am inspecting, so that the inspection data is associated with the correct vehicle.

#### Acceptance Criteria

1. THE Vehicle_Selector SHALL display all vehicles from the vehicles table
2. WHEN displaying vehicles in the Vehicle_Selector, THE Vehicle_Selector SHALL show the vehicle brand, model, and license plate
3. WHEN a Staff_User selects a vehicle, THE Job_Card_Form SHALL retain the selected vehicle value
4. WHEN the Job_Card_Form is submitted without a vehicle selection, THE Job_Card_Form SHALL display a validation error message
5. THE Vehicle_Selector SHALL use a searchable dropdown interface for easy vehicle lookup

### Requirement 3: Inspection Checklist Display

**User Story:** As a staff member, I want to see all 37 inspection items in a clear table format, so that I can systematically check each item.

#### Acceptance Criteria

1. THE Job_Card_Form SHALL display a table with columns for Serial Number, Content, Check Status, and Note
2. THE Job_Card_Form SHALL display all 37 Inspection_Items in the following order: Car Number, Kilometer, Scratches, Service Kilometer Checkin, Alignment Kilometer Checkin, Tyre Condition, Tyre Pressure, Engine Oil, Air Filter, Coolant, Brake Fluid, Fuel Filter, Washer Fluid, Electric Checking, Brake Pads, Hand Brake, Head Lights, Indicators, Seat Belts, Wipers, Battery Terminal, Battery Water, AC, AC filter, Music System, Lights, Stepni Tyre, Jacky, Interior Cleaning, Washing, Car Small Checking, Seat Condition and Cleaning, Tyre Polishing, Papers Checking, Fine Checking, Complaints, Final Check Up and Note
3. WHEN displaying each Inspection_Item, THE Job_Card_Form SHALL show a sequential serial number starting from 1
4. WHEN displaying each Inspection_Item, THE Job_Card_Form SHALL show the item name in the Content column
5. THE Job_Card_Form SHALL maintain consistent row spacing and alignment for readability

### Requirement 4: Inspection Item Input Fields

**User Story:** As a staff member, I want to check off items and add notes during inspection, so that I can record the vehicle condition accurately.

#### Acceptance Criteria

1. WHEN displaying each Inspection_Item, THE Job_Card_Form SHALL provide a checkbox input for the Check Status column
2. WHEN displaying each Inspection_Item, THE Job_Card_Form SHALL provide a text input field for the Note column
3. THE checkbox input SHALL accept checked or unchecked states
4. THE Note text input SHALL accept alphanumeric text up to 255 characters
5. THE Note text input SHALL be optional for each Inspection_Item

### Requirement 5: Save Inspection Data

**User Story:** As a staff member, I want to save the completed inspection, so that the vehicle inspection record is stored in the system.

#### Acceptance Criteria

1. THE Job_Card_Form SHALL display a Save button at the bottom of the form
2. WHEN the Save button is clicked, THE Job_Card_Form SHALL validate that a vehicle has been selected
3. WHEN the Save button is clicked with a valid vehicle selection, THE Job_Card_Form SHALL save the inspection data to the Inspection_Database
4. WHEN the inspection data is saved successfully, THE Job_Card_Form SHALL display a success message to the Staff_User
5. WHEN the inspection data is saved successfully, THE Job_Card_Form SHALL clear the form for a new inspection

### Requirement 6: Store Inspection Records

**User Story:** As a system administrator, I want inspection data stored in the database, so that vehicle inspection history is preserved.

#### Acceptance Criteria

1. THE Inspection_Database SHALL include a table for storing inspection header records with vehicle ID, inspection date, and staff user ID
2. THE Inspection_Database SHALL include a table for storing individual inspection item statuses with check status and note text
3. WHEN an inspection is saved, THE Inspection_Database SHALL create a new inspection header record
4. WHEN an inspection is saved, THE Inspection_Database SHALL create 37 inspection item records linked to the header record
5. THE Inspection_Database SHALL store the timestamp of when each inspection was created

### Requirement 7: Database Migration

**User Story:** As a system administrator, I want a safe database migration script, so that I can add the inspection tables to production without data loss.

#### Acceptance Criteria

1. THE Migration_Script SHALL create the inspection header table using CREATE TABLE IF NOT EXISTS pattern
2. THE Migration_Script SHALL create the inspection items table using CREATE TABLE IF NOT EXISTS pattern
3. THE Migration_Script SHALL be idempotent (safe to run multiple times)
4. THE Migration_Script SHALL follow the project migration file naming pattern (YYYY-MM-DD_feature_name.sql)
5. THE Migration_Script SHALL include foreign key constraints linking inspection items to inspection headers

### Requirement 8: Form Styling Consistency

**User Story:** As a staff member, I want the job card form to match the existing UI, so that the interface feels cohesive.

#### Acceptance Criteria

1. THE Job_Card_Form SHALL use the existing dark theme color scheme (mb-surface backgrounds, mb-accent highlights, mb-subtle borders)
2. THE Job_Card_Form SHALL use the existing form input styling patterns (border radius, padding, focus states)
3. THE Job_Card_Form SHALL use the existing button styling for the Save button
4. THE Job_Card_Form SHALL use the existing table styling patterns for the inspection checklist
5. THE Job_Card_Form SHALL maintain responsive layout on mobile and desktop viewports

### Requirement 9: Access Control

**User Story:** As a system administrator, I want to control who can access the job card form, so that only authorized staff can perform inspections.

#### Acceptance Criteria

1. WHEN an unauthenticated user attempts to access the Job_Card_Form, THE Job_Card_Form SHALL redirect to the login page
2. WHEN an authenticated user without inspection permissions attempts to access the Job_Card_Form, THE Job_Card_Form SHALL display an access denied message
3. THE Job_Card_Form SHALL verify user authentication before displaying the form
4. THE Job_Card_Form SHALL verify user permissions before allowing form submission
5. THE Inspection_Database SHALL record which Staff_User created each inspection record

### Requirement 10: Inspection Item Validation

**User Story:** As a staff member, I want the system to validate my input, so that I don't accidentally submit incomplete or invalid data.

#### Acceptance Criteria

1. WHEN the Job_Card_Form is submitted without a vehicle selection, THE Job_Card_Form SHALL display an error message indicating vehicle selection is required
2. WHEN a Note text input exceeds 255 characters, THE Job_Card_Form SHALL truncate the text to 255 characters before saving
3. WHEN the Job_Card_Form is submitted, THE Job_Card_Form SHALL save all 37 Inspection_Items regardless of whether they are checked or have notes
4. WHEN the Job_Card_Form is submitted with valid data, THE Job_Card_Form SHALL not display any validation errors
5. THE Job_Card_Form SHALL display validation errors in a visually distinct error message container

