# Requirements Document

## Introduction

This feature adds a dedicated scratch/damage photo section to the vehicle delivery and return flows in the car rental CRM. Currently, inspection photos (front, back, left, right, odometer, interior) document the vehicle's general condition. This feature introduces a separate category — **scratch photos** — that staff can upload at delivery and at return to specifically document pre-existing or new scratches and damage. Up to 15 scratch photos can be uploaded per event. These photos are stored in a new `reservation_scratch_photos` table (separate from `inspection_photos`) and are displayed as two distinct sections on the reservation detail page: "Delivery Scratch Photos" and "Return Scratch Photos".

## Glossary

- **Scratch_Photo_System**: The subsystem responsible for capturing, storing, and displaying scratch/damage photos during delivery and return events.
- **Scratch_Photo**: A photo specifically documenting a scratch, dent, or damage area on the vehicle, uploaded during a delivery or return event.
- **Scratch_Event**: Either a delivery or return action that may have associated Scratch_Photos.
- **Scratch_Slot**: One of up to 15 numbered upload positions for scratch photos within a single Scratch_Event.
- **Delivery_Scratch_Section**: The UI section on `deliver.php` where staff upload Scratch_Photos at delivery time.
- **Return_Scratch_Section**: The UI section on `return.php` where staff upload Scratch_Photos at return time.
- **reservation_scratch_photos**: The new database table that stores all Scratch_Photos, keyed by `reservation_id` and `event_type` (`delivery` or `return`).

---

## Requirements

### Requirement 1: Upload Scratch Photos at Delivery

**User Story:** As a staff member processing a vehicle delivery, I want to upload scratch/damage photos in a dedicated section, so that pre-existing damage is clearly documented before the rental begins.

#### Acceptance Criteria

1. THE Scratch_Photo_System SHALL display a dedicated Delivery_Scratch_Section on the delivery form, separate from the standard inspection photo fields.
2. THE Scratch_Photo_System SHALL provide at least 1 and up to 15 Scratch_Slots on the Delivery_Scratch_Section.
3. WHEN a delivery form is submitted, THE Scratch_Photo_System SHALL treat all Scratch_Slots as optional — zero scratch photos is a valid submission.
4. WHEN a valid Scratch_Photo file is uploaded for a delivery slot, THE Scratch_Photo_System SHALL save the file to the `uploads/scratch_photos/` directory and insert a record into `reservation_scratch_photos` with `event_type = 'delivery'`.
5. IF a Scratch_Photo file upload fails for any slot, THEN THE Scratch_Photo_System SHALL skip that slot without aborting the delivery submission.
6. WHEN a delivery form is submitted with more than 15 Scratch_Photo files, THE Scratch_Photo_System SHALL reject the submission and display a validation error.

---

### Requirement 2: Upload Scratch Photos at Return

**User Story:** As a staff member processing a vehicle return, I want to upload scratch/damage photos in a dedicated section, so that any new damage since delivery is clearly documented.

#### Acceptance Criteria

1. THE Scratch_Photo_System SHALL display a dedicated Return_Scratch_Section on the return form, separate from the standard inspection photo fields.
2. THE Scratch_Photo_System SHALL provide at least 1 and up to 15 Scratch_Slots on the Return_Scratch_Section.
3. WHEN a return form is submitted, THE Scratch_Photo_System SHALL treat all Scratch_Slots as optional — zero scratch photos is a valid submission.
4. WHEN a valid Scratch_Photo file is uploaded for a return slot, THE Scratch_Photo_System SHALL save the file to the `uploads/scratch_photos/` directory and insert a record into `reservation_scratch_photos` with `event_type = 'return'`.
5. IF a Scratch_Photo file upload fails for any slot, THEN THE Scratch_Photo_System SHALL skip that slot without aborting the return submission.
6. WHEN a return form is submitted with more than 15 Scratch_Photo files, THE Scratch_Photo_System SHALL reject the submission and display a validation error.

---

### Requirement 3: Dynamic Scratch Photo Slots on Forms

**User Story:** As a staff member, I want to add more scratch photo slots on demand up to the maximum, so that I only upload as many photos as needed without being forced to fill all slots.

#### Acceptance Criteria

1. THE Scratch_Photo_System SHALL display 1 Scratch_Slot by default on both the delivery and return forms.
2. WHEN a user activates the "Add another scratch photo" control, THE Scratch_Photo_System SHALL add one additional Scratch_Slot, up to a maximum of 15 total slots.
3. WHEN the total number of visible Scratch_Slots reaches 15, THE Scratch_Photo_System SHALL disable the "Add another scratch photo" control.
4. WHEN a user activates the "Remove" control on a Scratch_Slot that is not the first slot, THE Scratch_Photo_System SHALL remove that slot from the form.

---

### Requirement 4: Store Scratch Photos in Dedicated Table

**User Story:** As a system administrator, I want scratch photos stored separately from inspection photos, so that delivery scratch photos and return scratch photos can be queried and displayed independently.

#### Acceptance Criteria

1. THE Scratch_Photo_System SHALL store all Scratch_Photos in a new `reservation_scratch_photos` table with columns: `id`, `reservation_id`, `event_type` (ENUM `delivery`/`return`), `slot_index` (1–15), `file_path`, `created_at`.
2. THE Scratch_Photo_System SHALL enforce a foreign key from `reservation_scratch_photos.reservation_id` to `reservations.id` with `ON DELETE CASCADE`.
3. THE Scratch_Photo_System SHALL create the `reservation_scratch_photos` table via a dedicated SQL migration file under `migrations/releases/`.
4. WHEN a Scratch_Photo is saved, THE Scratch_Photo_System SHALL record the `slot_index` matching the upload slot number (1–15) for ordered display.

---

### Requirement 5: Display Scratch Photos on Reservation Detail

**User Story:** As a staff member viewing a reservation, I want to see delivery scratch photos and return scratch photos in two clearly labelled sections, so that I can compare damage before and after the rental.

#### Acceptance Criteria

1. WHEN the reservation detail page is loaded, THE Scratch_Photo_System SHALL fetch all `reservation_scratch_photos` records for the reservation, grouped by `event_type`, ordered by `slot_index`.
2. WHEN a reservation has one or more Scratch_Photos with `event_type = 'delivery'`, THE Scratch_Photo_System SHALL display them in a section labelled "Delivery Scratch Photos" on the reservation detail page.
3. WHEN a reservation has one or more Scratch_Photos with `event_type = 'return'`, THE Scratch_Photo_System SHALL display them in a section labelled "Return Scratch Photos" on the reservation detail page.
4. WHEN a reservation has zero Scratch_Photos for a given event type, THE Scratch_Photo_System SHALL display a "No scratch photos recorded" placeholder for that section.
5. WHEN a Scratch_Photo thumbnail is activated, THE Scratch_Photo_System SHALL open the full-size image for viewing.
