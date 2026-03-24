# Requirements Document

## Introduction

Currently, the vehicle inspection flow (delivery and return) captures a single interior photo per event. This feature extends that to support **up to 15 interior photos** per delivery or return event, while keeping all other photo slots (front, back, left, right, odometer, with_customer) as single-photo fields. The multiple interior photos are stored in the existing `inspection_photos` table using indexed `view_name` values (e.g. `interior_1`, `interior_2`, …), and are displayed in the reservation detail page alongside the other inspection photos.

## Glossary

- **Inspection_System**: The subsystem responsible for capturing, storing, and displaying vehicle inspection photos during delivery and return events.
- **Interior_Photo**: A photo of the vehicle interior captured during a delivery or return inspection.
- **Inspection_Event**: A single delivery or return action that creates one `vehicle_inspections` record and its associated `inspection_photos` records.
- **Interior_Slot**: One of up to 15 numbered positions (`interior_1` through `interior_15`) used as the `view_name` for interior photos in the `inspection_photos` table.
- **Photo_Upload**: A file submitted via the multipart form field `photos[interior_N]` where N is 1–15.

---

## Requirements

### Requirement 1: Multiple Interior Photos at Delivery

**User Story:** As a staff member processing a vehicle delivery, I want to upload multiple interior photos, so that the vehicle's interior condition is thoroughly documented before the rental begins.

#### Acceptance Criteria

1. THE Inspection_System SHALL provide at least 1 and up to 15 Interior_Slot upload fields on the delivery form.
2. WHEN a delivery form is submitted, THE Inspection_System SHALL require at least 1 Interior_Photo to be uploaded.
3. WHEN a delivery form is submitted with more than 15 Interior_Photo files, THE Inspection_System SHALL reject the submission and display a validation error.
4. WHEN a valid Interior_Photo file is uploaded for a slot, THE Inspection_System SHALL save the file to the `uploads/inspections/` directory and insert a record into `inspection_photos` with the corresponding `view_name` (e.g. `interior_1`).
5. IF an Interior_Photo file upload fails for any slot, THEN THE Inspection_System SHALL skip that slot without aborting the entire submission, provided at least 1 Interior_Photo was successfully saved.

---

### Requirement 2: Multiple Interior Photos at Return

**User Story:** As a staff member processing a vehicle return, I want to upload multiple interior photos, so that any interior damage or changes since delivery can be fully documented.

#### Acceptance Criteria

1. THE Inspection_System SHALL provide at least 1 and up to 15 Interior_Slot upload fields on the return form.
2. WHEN a return form is submitted, THE Inspection_System SHALL require at least 1 Interior_Photo to be uploaded.
3. WHEN a return form is submitted with more than 15 Interior_Photo files, THE Inspection_System SHALL reject the submission and display a validation error.
4. WHEN a valid Interior_Photo file is uploaded for a slot, THE Inspection_System SHALL save the file to the `uploads/inspections/` directory and insert a record into `inspection_photos` with the corresponding `view_name`.
5. IF an Interior_Photo file upload fails for any slot, THEN THE Inspection_System SHALL skip that slot without aborting the entire submission, provided at least 1 Interior_Photo was successfully saved.

---

### Requirement 3: Display Multiple Interior Photos on Reservation Detail

**User Story:** As a staff member viewing a reservation, I want to see all interior photos captured at delivery and return, so that I can compare the vehicle's interior condition between the two events.

#### Acceptance Criteria

1. WHEN a reservation detail page is loaded, THE Inspection_System SHALL fetch all `inspection_photos` records for each Inspection_Event ordered by `view_name`.
2. WHEN an Inspection_Event has multiple Interior_Photos, THE Inspection_System SHALL display all of them in a grouped section labelled "Interior".
3. WHEN an Inspection_Event has zero Interior_Photos, THE Inspection_System SHALL display a placeholder or omit the Interior section for that event.

---

### Requirement 4: Dynamic Interior Photo Slots on Forms

**User Story:** As a staff member, I want to add more interior photo slots on demand up to the maximum, so that I only upload as many photos as needed without being forced to fill all 15 slots.

#### Acceptance Criteria

1. THE Inspection_System SHALL display 1 Interior_Slot by default on both the delivery and return forms.
2. WHEN a user activates the "Add another interior photo" control, THE Inspection_System SHALL add one additional Interior_Slot, up to a maximum of 15 total slots.
3. WHEN the total number of visible Interior_Slots reaches 15, THE Inspection_System SHALL disable the "Add another interior photo" control.
4. WHEN a user activates the "Remove" control on an Interior_Slot that is not the first slot, THE Inspection_System SHALL remove that slot from the form.

---

### Requirement 5: Backward Compatibility with Existing Single Interior Photo

**User Story:** As a system administrator, I want existing inspection records with a single `interior` view_name to continue displaying correctly, so that historical data is not broken by this change.

#### Acceptance Criteria

1. WHEN the reservation detail page loads an Inspection_Event that has an `inspection_photos` record with `view_name = 'interior'`, THE Inspection_System SHALL display that photo in the Interior section alongside any `interior_N` records.
2. THE Inspection_System SHALL NOT require a database migration to support the new Interior_Slots; the existing `view_name VARCHAR(50)` column accommodates values such as `interior_1` through `interior_15`.
