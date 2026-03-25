# Implementation Plan: Reservation Scratch Photos

## Overview

Add a dedicated scratch/damage photo upload section to the delivery and return flows. Photos are stored in a new `reservation_scratch_photos` table (separate from `inspection_photos`) and displayed as two labelled sections on the reservation detail page.

## Tasks

- [x] 1. Create SQL migration file and update PRODUCTION_DB_STEPS.md
  - Create `migrations/releases/2026-03-25_reservation_scratch_photos.sql` with `CREATE TABLE IF NOT EXISTS reservation_scratch_photos` (idempotent)
  - Include columns: `id`, `reservation_id` (FK → reservations.id ON DELETE CASCADE), `event_type` ENUM('delivery','return'), `slot_index` TINYINT UNSIGNED, `file_path` VARCHAR(255), `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
  - Add index on `reservation_id`
  - Add a new Pending entry to `PRODUCTION_DB_STEPS.md` for this migration
  - Do NOT run the migration automatically
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 2. Add scratch photo upload to deliver.php
  - [x] 2.1 Add scratch photo validation in the POST block
    - After the existing interior photo validation, count attempted scratch slots (`scratch_photos[1]`…`scratch_photos[15]`)
    - If more than 15 slots attempted, set `$errors['scratch_photos']` — zero is valid
    - _Requirements: 1.2, 1.3, 1.6_
  - [x] 2.2 Add scratch photo save loop in the `empty($errors)` block
    - After the existing inspection photo loop, create `uploads/scratch_photos/` if missing
    - Iterate slots 1–15, skip failed/missing slots silently
    - INSERT into `reservation_scratch_photos` with `event_type = 'delivery'` and correct `slot_index`
    - _Requirements: 1.4, 1.5, 4.4_
  - [x] 2.3 Add Scratch Photos HTML section and JS to deliver.php
    - Add a new card section below the existing inspection photos section
    - 1 default slot, "Add another scratch photo" button (disabled at 15), "Remove" button on added slots
    - Show `$errors['scratch_photos']` inline if set
    - Add JS (same dynamic-slot pattern as interior photos) using `scratch-slots-container` / `add-scratch-btn` IDs and `scratch_photos[N]` field names
    - _Requirements: 1.1, 3.1, 3.2, 3.3, 3.4_

- [x] 3. Checkpoint — Ensure deliver.php works end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Add scratch photo upload to return.php
  - [x] 4.1 Add scratch photo validation in the POST block
    - Same logic as deliver.php: count attempted slots, reject >15, allow 0
    - _Requirements: 2.2, 2.3, 2.6_
  - [x] 4.2 Add scratch photo save loop in the `empty($errors)` block
    - Same loop as deliver.php but with `event_type = 'return'`
    - _Requirements: 2.4, 2.5, 4.4_
  - [x] 4.3 Add Scratch Photos HTML section and JS to return.php
    - Same HTML/JS structure as deliver.php (orange accent, same IDs/field names)
    - _Requirements: 2.1, 3.1, 3.2, 3.3, 3.4_

- [x] 5. Display scratch photos on show.php
  - [x] 5.1 Fetch scratch photos after the existing inspection photo fetch
    - Query `reservation_scratch_photos WHERE reservation_id = ? ORDER BY event_type, slot_index`
    - Split into `$deliveryScratch` and `$returnScratch` arrays using `array_filter`
    - _Requirements: 5.1_
  - [x] 5.2 Render Delivery Scratch Photos section in the HTML
    - Show photo grid (3–5 columns) if photos exist; show "No scratch photos recorded." placeholder if empty
    - Each photo wrapped in `<a target="_blank">` linking to full-size image
    - _Requirements: 5.2, 5.4, 5.5_
  - [x] 5.3 Render Return Scratch Photos section in the HTML
    - Same structure as delivery section, labelled "Return Scratch Photos"
    - _Requirements: 5.3, 5.4, 5.5_

- [x] 6. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
