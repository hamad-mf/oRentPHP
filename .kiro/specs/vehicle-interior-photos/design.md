# Design Document: Vehicle Interior Photos

## Overview

This feature extends the vehicle inspection flow to support **up to 15 interior photos** per delivery or return event. Currently, both `deliver.php` and `return.php` accept a single `photos[interior]` field. The change replaces that single slot with indexed slots `photos[interior_1]` through `photos[interior_15]`, stored as `view_name = 'interior_1'` etc. in the existing `inspection_photos` table.

No database migration is required. The `view_name VARCHAR(50)` column already accommodates the new values. Existing records with `view_name = 'interior'` continue to display correctly because `show.php` renders all photos generically by their `view_name`.

The scope of changes is limited to three files:
- `reservations/deliver.php` — validation, save loop, HTML form
- `reservations/return.php` — validation, save loop, HTML form
- `reservations/show.php` — no code changes needed (already renders generically)

---

## Architecture

The feature is entirely contained within the existing PHP/vanilla-JS stack. There are no new files, no new routes, and no new database tables or columns.

```
Browser (form)
  └─ photos[interior_1] … photos[interior_N]   (multipart POST)
        │
        ▼
deliver.php / return.php  (PHP)
  ├─ Validation: count interior_N slots, require ≥1, reject >15
  ├─ Save loop: iterate interior_1…interior_15, skip failed uploads
  └─ INSERT inspection_photos (view_name = 'interior_N')
        │
        ▼
inspection_photos table
  └─ view_name: 'interior_1', 'interior_2', … (existing VARCHAR(50))

show.php  (PHP)
  └─ SELECT * FROM inspection_photos WHERE inspection_id = ?
     ORDER BY view_name
     → renders each photo with its view_name label (no change needed)
```

---

## Components and Interfaces

### 1. Validation Logic (deliver.php & return.php)

Replace the single `interior` entry in `$requiredPhotos` with a function that counts how many `interior_N` slots were submitted with `UPLOAD_ERR_OK`.

**deliver.php** — current required list:
```php
$requiredPhotos = ['front','back','left','right','interior','odometer','with_customer'];
```
**New approach**: keep the non-interior keys in the loop as-is, and add a separate interior count check:
```php
$requiredPhotos = ['front','back','left','right','odometer','with_customer'];
// ... existing loop for non-interior photos ...

// Interior: count submitted slots
$interiorCount = 0;
for ($n = 1; $n <= 15; $n++) {
    $key = "interior_$n";
    if (isset($_FILES['photos']['name'][$key])
        && $_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
        $interiorCount++;
    }
}
if ($interiorCount === 0) {
    $errors['photo_interior'] = 'At least one interior photo is required.';
}
// Count all submitted interior slots (including errors) to enforce max
$interiorSubmitted = 0;
for ($n = 1; $n <= 15; $n++) {
    $key = "interior_$n";
    if (isset($_FILES['photos']['name'][$key])
        && $_FILES['photos']['name'][$key] !== '') {
        $interiorSubmitted++;
    }
}
if ($interiorSubmitted > 15) {
    $errors['photo_interior'] = 'A maximum of 15 interior photos is allowed.';
}
```

**return.php** — same logic, minus `with_customer`.

### 2. Photo Save Loop (deliver.php & return.php)

The existing loop already iterates `$_FILES['photos']['name']` as a flat key-value map and skips failed uploads. Because the form now submits `photos[interior_1]` … `photos[interior_15]` as named keys (not array syntax), the loop body is unchanged — it will naturally process each `interior_N` key the same way it processes `front`, `back`, etc.

No change to the save loop is required beyond ensuring the form field names match.

### 3. HTML Form — Dynamic Interior Slots (deliver.php & return.php)

Replace the single `interior` entry in `$photoViews` with a dedicated HTML block outside the `foreach` loop. The block renders one slot by default and includes vanilla JS to add/remove slots.

**Structure:**
```html
<!-- Static slots (front, back, left, right, odometer, [with_customer]) -->
<?php foreach ($photoViews as $areaKey => $areaLabel): ?>
  <!-- existing single-file input -->
<?php endforeach; ?>

<!-- Dynamic interior slots -->
<div id="interior-slots-container">
  <div class="interior-slot" data-slot="1">
    <label>Interior View 1</label>
    <input type="file" name="photos[interior_1]" accept="image/*" required>
    <!-- no Remove button on first slot -->
  </div>
</div>
<button type="button" id="add-interior-btn">+ Add another interior photo</button>
```

**JS behaviour:**
- `addInteriorBtn` click: clone a new slot div, increment slot index, append to container, update "Add" button disabled state.
- "Remove" button click: remove the slot div, re-index remaining slots, update "Add" button disabled state.
- Disable "Add" button when slot count reaches 15.
- The first slot never shows a "Remove" button.

### 4. show.php — No Changes

`show.php` already fetches all `inspection_photos` for each inspection and renders them in a grid with `view_name` as the label. Interior photos stored as `interior_1`, `interior_2`, etc. will appear automatically. Legacy `interior` records also display correctly.

---

## Data Models

### inspection_photos (existing, unchanged)

| Column          | Type         | Notes                                      |
|-----------------|--------------|--------------------------------------------|
| id              | INT PK       |                                            |
| inspection_id   | INT FK       | → vehicle_inspections.id                  |
| view_name       | VARCHAR(50)  | e.g. `front`, `interior_1`, `interior_15` |
| file_path       | VARCHAR(255) | relative path under `uploads/inspections/`|

New `view_name` values `interior_1` … `interior_15` fit within the existing `VARCHAR(50)` constraint (max 11 chars).

### Form Field Naming Convention

| Slot | Field name          | view_name stored |
|------|---------------------|------------------|
| 1    | `photos[interior_1]`| `interior_1`     |
| 2    | `photos[interior_2]`| `interior_2`     |
| …    | …                   | …                |
| 15   | `photos[interior_15]`| `interior_15`   |

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Interior slot count is bounded

*For any* rendered delivery or return form, the number of visible interior file-input slots must be at least 1 and at most 15.

**Validates: Requirements 1.1, 2.1, 4.1, 4.2**

---

### Property 2: At-least-one interior photo is required

*For any* form submission where zero `interior_N` slots contain a successfully uploaded file, the validation function must return an error and the inspection record must not be created.

**Validates: Requirements 1.2, 2.2**

---

### Property 3: More-than-15 interior photos are rejected (edge case)

*For any* form submission where more than 15 `interior_N` slots are submitted, the validation function must return an error and the inspection record must not be created.

**Validates: Requirements 1.3, 2.3**

---

### Property 4: Interior photo save round-trip

*For any* valid interior photo upload for slot N (where 1 ≤ N ≤ 15), after a successful form submission the `inspection_photos` table must contain a record with `view_name = 'interior_N'` and a `file_path` pointing to the saved file.

**Validates: Requirements 1.4, 2.4**

---

### Property 5: Partial upload failure resilience

*For any* submission where at least 1 interior slot uploads successfully and 1 or more other interior slots fail, the submission must succeed and only the successfully uploaded slots must be persisted — no error is raised for the failed slots.

**Validates: Requirements 1.5, 2.5**

---

### Property 6: Photos fetched ordered by view_name

*For any* inspection event with multiple photos, the list returned by the display query must be ordered by `view_name` ascending, so that `interior_1` always precedes `interior_2`, etc.

**Validates: Requirements 3.1**

---

### Property 7: Interior photos grouped in display (including legacy)

*For any* inspection event, all photos whose `view_name` matches `interior` or `interior_N` (for any N) must appear together in the rendered output under an "Interior" label — regardless of whether they are legacy `interior` records or new `interior_N` records.

**Validates: Requirements 3.2, 5.1**

---

### Property 8: Remove slot decreases count

*For any* form state with N visible interior slots (where N > 1), activating the "Remove" control on a non-first slot must result in exactly N−1 visible slots, with the removed slot's input no longer present in the DOM.

**Validates: Requirements 4.4**

---

## Error Handling

| Scenario | Behaviour |
|---|---|
| Zero interior photos submitted | Validation error: "At least one interior photo is required." Form not submitted. |
| More than 15 interior slots submitted | Validation error: "A maximum of 15 interior photos is allowed." Form not submitted. |
| Individual slot upload fails (`UPLOAD_ERR_*`) | Slot is silently skipped in the save loop. If at least 1 slot succeeded, submission proceeds. |
| All interior slots fail to upload | Treated as zero successful uploads → validation error (caught post-save or pre-save depending on implementation). |
| `uploads/inspections/` directory missing | `mkdir` is called with `0777, true` before the save loop (existing behaviour, unchanged). |
| Non-image file submitted | Browser `accept="image/*"` provides first-line filtering; server-side extension check is inherited from existing code. |

---

## Testing Strategy

### Unit Tests

Focus on specific examples and edge cases:

- Validation rejects submission with zero interior photos.
- Validation rejects submission with 16 interior photos.
- Validation accepts submission with exactly 1 interior photo.
- Validation accepts submission with exactly 15 interior photos.
- Save loop inserts correct `view_name` values (`interior_1`, `interior_2`, …) for each uploaded slot.
- Save loop skips a slot with `UPLOAD_ERR_NO_FILE` without throwing.
- Display query returns photos ordered by `view_name`.
- Legacy `view_name = 'interior'` record appears in the interior group alongside `interior_N` records.

### Property-Based Tests

Use a property-based testing library (e.g. **fast-check** for JS, or **QuickCheck**-style for PHP via **eris**) with a minimum of **100 iterations per property**.

Each test must be tagged with:
`Feature: vehicle-interior-photos, Property {N}: {property_text}`

| Property | Test description |
|---|---|
| P1: Slot count bounded | Generate random slot counts (0–20); verify rendered form always clamps to [1, 15]. |
| P2: At-least-one required | Generate random sets of uploaded files with 0 interior photos; verify validation always errors. |
| P3: >15 rejected (edge) | Generate submissions with 16–30 interior slots; verify validation always errors. |
| P4: Save round-trip | Generate random valid interior uploads for slots 1–15; verify each produces a matching `inspection_photos` row. |
| P5: Partial failure resilience | Generate mixed success/failure upload sets (≥1 success); verify submission succeeds and only successes are persisted. |
| P6: Ordered fetch | Generate random sets of `view_name` values; verify query result is sorted ascending. |
| P7: Interior grouping | Generate inspection events with mixed `interior` and `interior_N` records; verify all appear under the Interior label. |
| P8: Remove decreases count | Generate random slot counts N (2–15); simulate remove on a non-first slot; verify count becomes N−1. |

**Configuration note**: Property tests must run in single-execution mode (not watch mode). For JS: `vitest --run`. Minimum 100 iterations per property.
