# Requirements Document

## Introduction

This feature adds a delivery inspection data preview section to the return screen (reservations/return.php). When staff process a vehicle return, they need to reference the delivery inspection data (location, mileage, fuel level, photos, and notes) to compare against return readings and identify any changes or damage. This preview will display read-only delivery data for both new and existing reservations.

## Glossary

- **Return_Screen**: The web page (reservations/return.php) where staff process vehicle returns and complete inspections
- **Delivery_Inspection_Data**: The inspection data captured during vehicle delivery, including location, mileage, fuel level, photos, and notes
- **Delivery_Photos**: Photos captured during delivery inspection, including scratch/damage photos stored in reservation_scratch_photos table
- **Preview_Section**: A read-only display component showing delivery inspection data on the return screen
- **Inspection_Record**: A record in the vehicle_inspections table with type='delivery'
- **Scratch_Photo_Record**: A record in the reservation_scratch_photos table with event_type='delivery'

## Requirements

### Requirement 1: Display Delivery Inspection Preview

**User Story:** As a staff member processing a vehicle return, I want to see the delivery inspection data on the return screen, so that I can compare delivery and return conditions accurately.

#### Acceptance Criteria

1. WHEN the Return_Screen loads for an active reservation, THE Return_Screen SHALL display the Preview_Section before the return form
2. THE Preview_Section SHALL display delivery_location from the reservations table
3. THE Preview_Section SHALL display mileage from the Inspection_Record
4. THE Preview_Section SHALL display fuel_level from the Inspection_Record
5. THE Preview_Section SHALL display notes from the Inspection_Record
6. THE Preview_Section SHALL display all Delivery_Photos from the Inspection_Record
7. THE Preview_Section SHALL display all Scratch_Photo_Records for the reservation
8. WHERE no Inspection_Record exists for a reservation, THE Return_Screen SHALL display a message indicating no delivery data is available

### Requirement 2: Photo Display and Organization

**User Story:** As a staff member, I want to see delivery photos organized by type, so that I can quickly reference specific vehicle views and damage documentation.

#### Acceptance Criteria

1. THE Preview_Section SHALL group Delivery_Photos by view_name (front, back, left, right, odometer, with_customer, interior_1 through interior_15)
2. THE Preview_Section SHALL display Scratch_Photo_Records separately from standard Delivery_Photos
3. WHEN a photo is clicked, THE Return_Screen SHALL display the photo in a larger view
4. THE Preview_Section SHALL display photo thumbnails with a maximum width to maintain layout consistency
5. WHERE interior photos exist, THE Preview_Section SHALL display them in a grid layout

### Requirement 3: Backward Compatibility

**User Story:** As a system administrator, I want the preview to work for old reservations without delivery inspection data, so that the feature doesn't break existing workflows.

#### Acceptance Criteria

1. WHERE a reservation has no Inspection_Record, THE Preview_Section SHALL display "No delivery inspection data available"
2. WHERE a reservation has an Inspection_Record but no delivery_location, THE Preview_Section SHALL display "Location not recorded"
3. WHERE a reservation has an Inspection_Record but no Delivery_Photos, THE Preview_Section SHALL display "No photos available"
4. THE Return_Screen SHALL continue to function normally when the Preview_Section has no data to display

### Requirement 4: Visual Design and Layout

**User Story:** As a staff member, I want the delivery preview to be visually distinct from the return form, so that I don't confuse delivery data with return data.

#### Acceptance Criteria

1. THE Preview_Section SHALL use a distinct background color or border to differentiate it from the return form
2. THE Preview_Section SHALL include a header labeled "Delivery Inspection Reference"
3. THE Preview_Section SHALL display data in a read-only format with no editable fields
4. THE Preview_Section SHALL be collapsible to save screen space when not needed
5. WHERE the Preview_Section is collapsed, THE Return_Screen SHALL display a summary showing delivery location and mileage only
