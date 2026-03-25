# Design Document: Reservation Scratch Photos

## Overview

This feature adds a dedicated scratch/damage photo upload section to the vehicle delivery and return flows. Scratch photos are stored in a new `reservation_scratch_photos` table — completely separate from the existing `inspection_photos` table — and displayed as two labelled sections ("Delivery Scratch Photos" / "Return Scratch Photos") on the reservation detail page.

Key design decisions:
- **Separate table**: scratch photos are not mixed with inspection photos, enabling independent querying per event type.
- **Optional upload**: zero scratch photos is always valid; the feature never blocks delivery or return completion.
- **Max 15 slots**: matches the existing interior photo pattern in `deliver.php` / `return.php`, reusing the same dynamic-slot JS approach.
- **No runtime ALTER TABLE**: the table is created via a dedicated SQL migration file; no inline schema guards are added to PHP files.

Files changed:
- `reservations/deliver.php` — add scratch photo section (HTML + validation + save loop)
- `reservations/return.php` — add scratch photo section (HTML + validation + save loop)
- `reservations/show.php` — fetch and display delivery/return scratch photo sections
- `migrations/releases/2026-03-25_reservation_scratch_photos.sql` — new table (idempotent)
- `PRODUCTION_DB_STEPS.md` — new Pending entry

---

## Architecture

```
Browser (form)
  └─ scratch_photos[1] … scratch_photos[N]   (multipart POST, N ≤ 15)
        │
        ▼
deliver.php / return.php  (PHP)
  ├─ Validation: count submitted scratch slots, reject >15 (0 is OK)
  ├─ Save loop: iterate slots 1–15, skip failed uploads
  └─ INSERT reservation_scratch_photos
       (reservation_id, event_type, slot_index, file_path)
        │
        ▼
reservation_scratch_photos table
  └─ event_type: 'delivery' | 'return'
     slot_index: 1–15

show.php  (PHP)
  └─ SELECT * FROM reservation_scratch_photos
       WHERE reservation_id = ?
       ORDER BY event_type, slot_index
     → render two sections: Delivery Scratch Photos / Return Scratch Photos
```

---

## Components and Interfaces

### 1. Validation Logic (deliver.php & return.php)

Scratch photos are **optional**, so validation only rejects submissions with more than 15 slots. The count is computed by iterating `scratch_photos[1]` … `scratch_photos[15]` and counting slots where `UPLOAD_ERR_OK`.

```php
// Count submitted scratch photo slots
$scratchCount = 0;
for ($n = 1; $n <= 15; $n++) {
    if (!empty($_FILES['scratch_photos']['name'][$n])
        && $_FILES['scratch_photos']['error'][$n] === UPLOAD_ERR_OK) {
        $scratchCount++;
    }
}
// Count all attempted (including errors) to enforce max
$scratchAttempted = 0;
for ($n = 1; $n <= 15; $n++) {
    if (!empty($_FILES['scratch_photos']['name'][$n])
        && $_FILES['scratch_photos']['name'][$n] !== '') {
        $scratchAttempted++;
    }
}
if ($scratchAttempted > 15) {
    $errors['scratch_photos'] = 'A maximum of 15 scratch photos is allowed.';
}
```

This runs inside the existing `if ($_SERVER['REQUEST_METHOD'] === 'POST')` block, after the existing photo validations.

### 2. Photo Save Loop (deliver.php & return.php)

Runs inside the existing `if (empty($errors))` block, after the inspection record is inserted and the standard photo loop completes.

```php
// Save scratch photos
$scratchDir = __DIR__ . '/../uploads/scratch_photos/';
if (!is_dir($scratchDir)) {
    mkdir($scratchDir, 0777, true);
}
if (isset($_FILES['scratch_photos'])) {
    for ($n = 1; $n <= 15; $n++) {
        if (empty($_FILES['scratch_photos']['name'][$n])
            || $_FILES['scratch_photos']['error'][$n] !== UPLOAD_ERR_OK) {
            continue; // skip missing or failed slots
        }
        $name = $_FILES['scratch_photos']['name'][$n];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $filename = 'scratch_' . $id . '_' . $eventType . '_' . $n . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['scratch_photos']['tmp_name'][$n], $scratchDir . $filename)) {
            $pdo->prepare(
                'INSERT INTO reservation_scratch_photos
                 (reservation_id, event_type, slot_index, file_path)
                 VALUES (?, ?, ?, ?)'
            )->execute([$id, $eventType, $n, 'uploads/scratch_photos/' . $filename]);
        }
    }
}
```

`$eventType` is `'delivery'` in `deliver.php` and `'return'` in `return.php`.

### 3. HTML Form — Dynamic Scratch Slots (deliver.php & return.php)

A new section is added below the existing inspection photo section. It mirrors the interior photo dynamic-slot pattern exactly, using a different container ID and field name.

```html
<!-- Scratch Photos Section -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6">
    <h3 class="text-white font-light border-l-2 border-orange-500 pl-3 mb-4">
        Scratch / Damage Photos <span class="text-mb-subtle text-xs font-normal">(optional, max 15)</span>
    </h3>
    <?php if (isset($errors['scratch_photos'])): ?>
        <p class="text-red-400 text-xs mb-3"><?= e($errors['scratch_photos']) ?></p>
    <?php endif; ?>
    <div id="scratch-slots-container" class="space-y-2">
        <div class="scratch-slot flex items-center gap-2" data-slot="1">
            <input type="file" name="scratch_photos[1]" accept="image/*"
                   class="block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4
                          file:rounded-full file:border-0 file:text-xs file:font-semibold
                          file:bg-mb-surface file:text-orange-400 hover:file:bg-mb-surface/80 cursor-pointer">
        </div>
    </div>
    <button type="button" id="add-scratch-btn"
            class="mt-3 text-xs text-orange-400 hover:text-white border border-orange-500/30
                   hover:border-orange-500/60 px-3 py-1.5 rounded-full transition-colors">
        + Add another scratch photo
    </button>
</div>
```

**JS (same pattern as interior slots):**
```js
(function() {
    const container = document.getElementById('scratch-slots-container');
    const addBtn    = document.getElementById('add-scratch-btn');
    const MAX = 15;

    function updateAddBtn() {
        const count = container.querySelectorAll('.scratch-slot').length;
        addBtn.disabled = count >= MAX;
        addBtn.classList.toggle('opacity-40', count >= MAX);
    }

    function reindex() {
        container.querySelectorAll('.scratch-slot').forEach(function(slot, i) {
            const n = i + 1;
            slot.dataset.slot = n;
            const input = slot.querySelector('input[type=file]');
            if (input) input.name = 'scratch_photos[' + n + ']';
        });
    }

    addBtn.addEventListener('click', function() {
        const count = container.querySelectorAll('.scratch-slot').length;
        if (count >= MAX) return;
        const n = count + 1;
        const slot = document.createElement('div');
        slot.className = 'scratch-slot flex items-center gap-2';
        slot.dataset.slot = n;
        const input = document.createElement('input');
        input.type = 'file';
        input.name = 'scratch_photos[' + n + ']';
        input.accept = 'image/*';
        input.className = 'block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-orange-400 hover:file:bg-mb-surface/80 cursor-pointer';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = 'Remove';
        removeBtn.className = 'text-xs text-red-400 hover:text-red-300 border border-red-500/30 px-2 py-1 rounded-full transition-colors';
        removeBtn.addEventListener('click', function() {
            slot.remove();
            reindex();
            updateAddBtn();
        });
        slot.appendChild(input);
        slot.appendChild(removeBtn);
        container.appendChild(slot);
        updateAddBtn();
    });

    updateAddBtn();
})();
```

### 4. Display on show.php

After the existing inspection photo fetch, query scratch photos grouped by event type:

```php
// Fetch scratch photos
$spStmt = $pdo->prepare(
    'SELECT * FROM reservation_scratch_photos
     WHERE reservation_id = ?
     ORDER BY event_type, slot_index'
);
$spStmt->execute([$id]);
$scratchPhotos = $spStmt->fetchAll();
$deliveryScratch = array_filter($scratchPhotos, fn($p) => $p['event_type'] === 'delivery');
$returnScratch   = array_filter($scratchPhotos, fn($p) => $p['event_type'] === 'return');
```

Two sections are rendered in the HTML, each showing a photo grid or a "No scratch photos recorded" placeholder:

```php
<!-- Delivery Scratch Photos -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
    <h3 class="text-white font-light border-l-2 border-orange-500 pl-3 mb-4">
        Delivery Scratch Photos
    </h3>
    <?php if (empty($deliveryScratch)): ?>
        <p class="text-mb-subtle text-sm">No scratch photos recorded.</p>
    <?php else: ?>
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
            <?php foreach ($deliveryScratch as $sp): ?>
                <a href="../<?= e($sp['file_path']) ?>" target="_blank">
                    <img src="../<?= e($sp['file_path']) ?>" class="rounded-lg object-cover w-full aspect-square">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Return Scratch Photos -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
    <h3 class="text-white font-light border-l-2 border-orange-500 pl-3 mb-4">
        Return Scratch Photos
    </h3>
    <?php if (empty($returnScratch)): ?>
        <p class="text-mb-subtle text-sm">No scratch photos recorded.</p>
    <?php else: ?>
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
            <?php foreach ($returnScratch as $sp): ?>
                <a href="../<?= e($sp['file_path']) ?>" target="_blank">
                    <img src="../<?= e($sp['file_path']) ?>" class="rounded-lg object-cover w-full aspect-square">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
```

The `target="_blank"` on the anchor opens the full-size image in a new tab, satisfying Requirement 5.5.

---

## Data Models

### reservation_scratch_photos (new table)

| Column           | Type                        | Notes                                          |
|------------------|-----------------------------|------------------------------------------------|
| id               | INT UNSIGNED AUTO_INCREMENT PK |                                             |
| reservation_id   | INT UNSIGNED NOT NULL       | FK → reservations.id ON DELETE CASCADE         |
| event_type       | ENUM('delivery','return')   | Which event the photo belongs to               |
| slot_index       | TINYINT UNSIGNED NOT NULL   | 1–15, matches the upload slot number           |
| file_path        | VARCHAR(255) NOT NULL       | Relative path: `uploads/scratch_photos/…`      |
| created_at       | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP |                              |

**Migration file**: `migrations/releases/2026-03-25_reservation_scratch_photos.sql`

```sql
CREATE TABLE IF NOT EXISTS `reservation_scratch_photos` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `reservation_id` INT UNSIGNED     NOT NULL,
    `event_type`     ENUM('delivery','return') NOT NULL,
    `slot_index`     TINYINT UNSIGNED NOT NULL,
    `file_path`      VARCHAR(255)     NOT NULL,
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rsp_reservation` (`reservation_id`),
    CONSTRAINT `fk_rsp_reservation`
        FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### File Naming Convention

| Slot | Filename pattern                                      |
|------|-------------------------------------------------------|
| 1    | `scratch_{reservation_id}_delivery_1_{timestamp}.jpg` |
| 2    | `scratch_{reservation_id}_delivery_2_{timestamp}.jpg` |
| …    | …                                                     |
| 15   | `scratch_{reservation_id}_return_15_{timestamp}.jpg`  |

### Upload Directory

`uploads/scratch_photos/` — created with `mkdir($dir, 0777, true)` on first use if absent.

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Slot count is bounded [1, 15]

*For any* rendered delivery or return form, the number of visible scratch photo file-input slots must be at least 1 and at most 15. Clicking "Add another scratch photo" from a state with N slots (N < 15) must produce N+1 slots; clicking it at N = 15 must have no effect (button disabled).

**Validates: Requirements 1.2, 2.2, 3.2, 3.3**

---

### Property 2: Scratch photos are optional

*For any* delivery or return form submission that contains zero scratch photo files, the submission must succeed without any scratch-photo-related validation error, and no rows must be inserted into `reservation_scratch_photos`.

**Validates: Requirements 1.3, 2.3**

---

### Property 3: Save round-trip with correct event_type and slot_index

*For any* valid scratch photo upload at slot N (1 ≤ N ≤ 15) during a delivery or return event, after a successful form submission the `reservation_scratch_photos` table must contain a record with the correct `reservation_id`, `event_type` matching the event, `slot_index = N`, and a `file_path` pointing to a file that exists under `uploads/scratch_photos/`.

**Validates: Requirements 1.4, 2.4, 4.1, 4.4**

---

### Property 4: Partial upload failure resilience

*For any* submission where at least 1 scratch photo slot uploads successfully and 1 or more other slots fail, the submission must succeed and only the successfully uploaded slots must be persisted — no error is raised for the failed slots.

**Validates: Requirements 1.5, 2.5**

---

### Property 5: More than 15 scratch photos are rejected (edge case)

*For any* delivery or return form submission where more than 15 scratch photo slots are attempted, the validation function must return an error and no scratch photo records must be inserted.

**Validates: Requirements 1.6, 2.6**

---

### Property 6: Display ordered by slot_index

*For any* reservation with multiple scratch photos of the same event_type, the records returned by the display query must be ordered by `slot_index` ascending, so that slot 1 always precedes slot 2, etc.

**Validates: Requirement 5.1**

---

### Property 7: Display sections by event_type

*For any* reservation, the detail page must render both a "Delivery Scratch Photos" section and a "Return Scratch Photos" section. When photos exist for an event type, they must appear in the corresponding section. When no photos exist for an event type, a "No scratch photos recorded" placeholder must appear in that section.

**Validates: Requirements 5.2, 5.3, 5.4**

---

### Property 8: Remove slot decreases count

*For any* form state with N visible scratch slots (N > 1), activating the "Remove" control on a non-first slot must result in exactly N−1 visible slots, with the removed slot's input no longer present in the DOM.

**Validates: Requirement 3.4**

---

## Error Handling

| Scenario | Behaviour |
|---|---|
| Zero scratch photos submitted | No error — submission proceeds normally. |
| More than 15 scratch slots attempted | Validation error: "A maximum of 15 scratch photos is allowed." Form not submitted. |
| Individual slot upload fails (`UPLOAD_ERR_*`) | Slot is silently skipped in the save loop. Submission proceeds if other parts are valid. |
| `uploads/scratch_photos/` directory missing | `mkdir($dir, 0777, true)` is called before the save loop. |
| Non-image file submitted | Browser `accept="image/*"` provides first-line filtering; server-side extension is inherited from the file's original name (same as existing inspection photo handling). |
| `reservation_scratch_photos` table missing | Will throw a PDO exception. The table must be created via the migration before deploying. No runtime ALTER TABLE guard is added. |
| Reservation deleted | `ON DELETE CASCADE` on the FK automatically removes all associated scratch photo records. |

---

## Testing Strategy

### Unit Tests

Focus on specific examples and edge cases:

- Validation accepts submission with zero scratch photos (no error).
- Validation rejects submission with 16 scratch photo slots.
- Validation accepts submission with exactly 1 scratch photo.
- Validation accepts submission with exactly 15 scratch photos.
- Save loop inserts correct `event_type = 'delivery'` for deliver.php.
- Save loop inserts correct `event_type = 'return'` for return.php.
- Save loop inserts correct `slot_index` values (1, 2, … N) for each uploaded slot.
- Save loop skips a slot with `UPLOAD_ERR_NO_FILE` without throwing.
- Display query returns photos ordered by `slot_index` ascending.
- Display renders "No scratch photos recorded" when no photos exist for an event type.
- Cascade delete: deleting a reservation removes its scratch photo records.

### Property-Based Tests

Use a property-based testing library appropriate for the target language (e.g. **eris** for PHP, **fast-check** for JS) with a minimum of **100 iterations per property**.

Each test must be tagged with a comment:
`Feature: reservation-scratch-photos, Property {N}: {property_text}`

| Property | Test description |
|---|---|
| P1: Slot count bounded | Generate random click counts (0–20); verify rendered slot count always stays in [1, 15] and add button is disabled at 15. |
| P2: Scratch photos optional | Generate random valid delivery/return submissions with 0 scratch files; verify no scratch-photo error and 0 rows inserted. |
| P3: Save round-trip | Generate random valid scratch uploads for slots 1–15 with random event_type; verify each produces a matching DB row with correct event_type and slot_index. |
| P4: Partial failure resilience | Generate mixed success/failure upload sets (≥1 success); verify submission succeeds and only successes are persisted. |
| P5: >15 rejected (edge) | Generate submissions with 16–30 scratch slots; verify validation always errors and 0 rows inserted. |
| P6: Ordered fetch | Generate random sets of slot_index values for a reservation; verify query result is sorted ascending by slot_index within each event_type. |
| P7: Display sections | Generate reservations with 0–15 delivery and 0–15 return scratch photos; verify both sections always render, with photos or placeholder as appropriate. |
| P8: Remove decreases count | Generate random slot counts N (2–15); simulate remove on a non-first slot; verify count becomes N−1. |

**Configuration note**: Property tests must run in single-execution mode (not watch mode). Minimum 100 iterations per property.
