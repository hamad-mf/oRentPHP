# Design Document: Vehicle Sold Status

## Overview

This feature adds a permanent `'sold'` lifecycle state to the vehicle fleet. Once marked as sold, a vehicle is retired from all operational workflows — it cannot be reserved, quoted, expensed, or have challans created against it. All historical data (reservations, expenses, booking calendar) remains fully visible for audit purposes.

The implementation touches the database schema, the vehicle detail page (show.php), the fleet list (index.php), and every screen that selects vehicles for operational use.

---

## Architecture

The change is additive and follows the existing pattern used for `'maintenance'` status guards throughout the codebase:

1. **DB layer**: Extend the `vehicles.status` ENUM and add a `sold_at` timestamp column via an idempotent migration.
2. **Write path** (`vehicles/show.php`): New `mark_sold` POST action handler with pre-condition guards.
3. **Read/UI path** (`vehicles/show.php`): Conditional rendering based on `$v['status'] === 'sold'`.
4. **Fleet list** (`vehicles/index.php`): Exclude sold from default view and counts; add Sold card.
5. **Operational screens**: Add `AND v.status != 'sold'` (or equivalent) to vehicle selection queries in `reservations/create.php`, `vehicles/quotation_builder.php`, `vehicles/catalog.php`, and challan creation.
6. **Guard handlers**: Early-exit checks in `vehicles/add_expense.php`, `vehicles/edit.php`, `vehicles/delete.php`.

No new tables are required. No new includes or helper files are needed — all logic is inline, consistent with the existing codebase style.

```mermaid
flowchart TD
    A[Admin clicks Mark as Sold] --> B{Vehicle status?}
    B -- rented --> C[Error: currently rented]
    B -- maintenance --> D[Error: in maintenance]
    B -- available --> E{Future reservations?}
    E -- yes --> F[Error: cancel them first]
    E -- no --> G[UPDATE status='sold', sold_at=NOW()]
    G --> H[Redirect with success flash]

    I[Any operational screen] --> J{v.status = 'sold'?}
    J -- yes --> K[Excluded from query / blocked on POST]
    J -- no --> L[Normal flow]
```

---

## Components and Interfaces

### 1. DB Migration — `migrations/releases/2026-03-25_vehicle_sold_status.sql`

Idempotent SQL file that:
- Modifies `vehicles.status` ENUM to add `'sold'`
- Adds `sold_at DATETIME NULL` column

Guards use `information_schema` to avoid errors on re-run.

### 2. `vehicles/show.php` — Mark as Sold Action Handler

New POST action block inserted at the top of the file (before HTML output), after the existing `save_storage` handler:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'mark_sold') {
    if ($id <= 0) { flash('error', 'Invalid vehicle.'); redirect('index.php'); }
    // Admin-only
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        flash('error', 'Only admins can mark a vehicle as sold.');
        redirect("show.php?id=$id");
    }
    // Fetch vehicle
    $sv = $pdo->prepare('SELECT status FROM vehicles WHERE id=?');
    $sv->execute([$id]);
    $svRow = $sv->fetch();
    if (!$svRow) { flash('error', 'Vehicle not found.'); redirect('index.php'); }
    // Guard: rented
    if ($svRow['status'] === 'rented') {
        flash('error', 'Vehicle is currently rented and cannot be sold.');
        redirect("show.php?id=$id");
    }
    // Guard: maintenance
    if ($svRow['status'] === 'maintenance') {
        flash('error', 'Vehicle is in maintenance and cannot be sold.');
        redirect("show.php?id=$id");
    }
    // Guard: future reservations
    $futureCount = (int)$pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE vehicle_id=? AND start_date > NOW()
           AND status NOT IN ('cancelled','completed')"
    )->execute([$id]) ? $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
         WHERE vehicle_id=? AND start_date > NOW()
           AND status NOT IN ('cancelled','completed')"
    )->fetchColumn() : 0;
    // (simplified — actual implementation uses a single prepared statement)
    if ($futureCount > 0) {
        flash('error', "Vehicle has $futureCount upcoming reservation(s). Cancel them first before marking as sold.");
        redirect("show.php?id=$id");
    }
    // Execute
    $pdo->prepare("UPDATE vehicles SET status='sold', sold_at=NOW() WHERE id=?")->execute([$id]);
    app_log('ACTION', "Marked vehicle as sold (ID: $id)");
    flash('success', 'Vehicle has been marked as sold.');
    redirect("show.php?id=$id");
}
```

### 3. `vehicles/show.php` — UI Rendering Changes

**Status badge array** — add entry:
```php
'sold' => 'bg-amber-500/10 text-amber-400 border-amber-500/30'
```

**Actions area** — conditional rendering:
- Show "Mark as Sold" button only when `$isAdmin && $v['status'] !== 'sold'`
- When `$v['status'] === 'sold'`:
  - Show amber "Sold" badge
  - Show read-only banner: "This vehicle has been sold. No further actions can be performed."
  - Hide: Edit Vehicle, Add Expense, Generate Quotation, Delete buttons

### 4. `vehicles/index.php` — Fleet List Changes

**Count queries** — exclude sold:
```php
$totalCount  = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status != 'sold'")->fetchColumn();
$available   = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn();
$rented      = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='rented'")->fetchColumn();
$maintenance = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='maintenance'")->fetchColumn();
$sold        = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='sold'")->fetchColumn();
```

**Default view base query** — add exclusion when no status filter is active:
```php
// In $where construction, when $status === '':
$where[] = "v.status != 'sold'";
```

**ORDER BY** — sold vehicles last (add `ELSE 4` for sold):
```php
CASE
    WHEN v.status = "rented"       THEN 0
    WHEN v.status = "available"    THEN 1
    WHEN v.status = "maintenance"  THEN 2
    WHEN v.status = "sold"         THEN 4
    ELSE 3
END
```

**Status cards array** — add Sold card with amber styling.

**Badge/dot arrays** — add `'sold'` entries with amber styling.

### 5. `vehicles/add_expense.php` — Sold Guard

After the existing vehicle existence check:
```php
if ($vehicle['status'] === 'sold') {
    flash('error', 'Cannot add expenses to a sold vehicle.');
    redirect('show.php?id=' . $vehicleId);
}
```

### 6. `vehicles/edit.php` — Sold Guard

After the existing vehicle fetch:
```php
if ($v['status'] === 'sold') {
    flash('error', 'Cannot edit a sold vehicle.');
    redirect('show.php?id=' . $id);
}
```

### 7. `vehicles/delete.php` — Sold Guard

After the existing vehicle fetch (alongside the existing rented guard):
```php
if ($vehicle['status'] === 'sold') {
    flash('error', 'Cannot delete a sold vehicle.');
    redirect('index.php');
}
```

### 8. `reservations/create.php` — Exclude Sold from Vehicle Selection

In `fetchAvailableVehiclesForRange()`, add to the WHERE clause:
```sql
AND v.status != 'sold'
```

### 9. `vehicles/quotation_builder.php` — Exclude Sold

In the vehicle fetch AJAX/inline query, add:
```sql
AND status != 'sold'
```

### 10. `vehicles/catalog.php` — Exclude Sold

In the non-single-vehicle-mode `$where` array:
```php
$where[] = "v.status != 'sold'";
```

For single-vehicle mode, after fetching `$selectedVehicle`:
```php
if ($selectedVehicle && $selectedVehicle['status'] === 'sold') {
    // Render "Vehicle not available" message and exit
}
```

### 11. Challan Creation — Exclude Sold

In the vehicle picker query (wherever challans are created), add:
```sql
AND v.status != 'sold'
```

On POST, validate:
```php
if ($vehicle['status'] === 'sold') {
    flash('error', 'Cannot create a challan for a sold vehicle.');
    redirect('...');
}
```

---

## Data Models

### `vehicles` table changes

| Column | Change |
|--------|--------|
| `status` | ENUM extended: `'available','rented','maintenance','sold'` |
| `sold_at` | New column: `DATETIME NULL DEFAULT NULL` |

No other tables are modified.

### Migration SQL sketch

```sql
-- Release: 2026-03-25_vehicle_sold_status
-- Safe: idempotent via information_schema guards

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Extend status ENUM to include 'sold'
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'vehicles'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE LIKE '%sold%'
);
SET @alter_status = IF(@col_exists = 0,
    'ALTER TABLE vehicles MODIFY COLUMN status ENUM(''available'',''rented'',''maintenance'',''sold'') NOT NULL DEFAULT ''available''',
    'SELECT ''status ENUM already includes sold'' AS skip_reason');
PREPARE stmt FROM @alter_status; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add sold_at column if not present
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS sold_at DATETIME NULL DEFAULT NULL AFTER status;

SET FOREIGN_KEY_CHECKS = 1;
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Mark as Sold button visibility is inverse of sold status

*For any* vehicle, the "Mark as Sold" button should appear in the Vehicle_Detail actions area if and only if the vehicle's status is not `'sold'`.

**Validates: Requirements 1.1, 1.8**

---

### Property 2: Mark as Sold DB update round-trip

*For any* vehicle in `'available'` status with no future reservations, after the `mark_sold` action is executed, querying the database for that vehicle should return `status = 'sold'` and a non-null `sold_at` timestamp.

**Validates: Requirements 1.2, 9.1, 9.2**

---

### Property 3: Non-admin mark_sold rejection

*For any* user whose session role is not `'admin'`, submitting the `mark_sold` POST action for any vehicle should be rejected and the vehicle's status should remain unchanged.

**Validates: Requirements 1.4**

---

### Property 4: Unsellable state rejection

*For any* vehicle in `'rented'` or `'maintenance'` status, or any vehicle with one or more future reservations (start_date > NOW(), status not cancelled/completed), the `mark_sold` action should be rejected with an appropriate error message and the vehicle's status should remain unchanged.

**Validates: Requirements 1.5, 1.6, 1.7**

---

### Property 5: Sold vehicle UI state

*For any* vehicle with `status = 'sold'`, the Vehicle_Detail page should: (a) display the amber "Sold" badge, (b) display the read-only sold notice banner, and (c) not render the Edit Vehicle, Add Expense, Generate Quotation, or Delete action buttons.

**Validates: Requirements 1.9, 1.10, 1.11, 7.2**

---

### Property 6: Sold vehicles excluded from operational count cards

*For any* fleet state, the Total Fleet, Available, Rented, and Workshop count values displayed on Fleet_List should equal the count of vehicles with the respective non-sold statuses (i.e., no sold vehicle contributes to any of these four counts).

**Validates: Requirements 2.1, 2.2**

---

### Property 7: Default fleet list excludes sold vehicles

*For any* fleet state, when Fleet_List is loaded with no status filter, the returned vehicle rows should contain no vehicles with `status = 'sold'`.

**Validates: Requirements 2.3**

---

### Property 8: Sold filter returns only sold vehicles

*For any* fleet state, when Fleet_List is filtered by `status = 'sold'`, every returned vehicle row should have `status = 'sold'` and no non-sold vehicle should appear.

**Validates: Requirements 2.4, 2.5**

---

### Property 9: Sold vehicles excluded from all operational vehicle queries

*For any* fleet state containing at least one sold vehicle, the vehicle selection results from Reservation_Create, Quotation_Builder, Catalog (list mode), and Challan_Create should contain no vehicles with `status = 'sold'`.

**Validates: Requirements 3.1, 3.2, 4.1, 4.2, 5.1, 6.1**

---

### Property 10: Sold vehicle POST actions rejected without side effects

*For any* sold vehicle, a POST request to Expense_Add or Challan_Create referencing that vehicle should be rejected (error flash, redirect) and no new ledger entry or challan record should be created.

**Validates: Requirements 6.2, 7.1**

---

### Property 11: Historical data preserved for sold vehicles

*For any* vehicle with `status = 'sold'` that has past reservations and/or expenses, the Vehicle_Detail page should still render the full reservation history table, the full expense history table, and the booking calendar section.

**Validates: Requirements 8.1, 8.2, 8.3**

---

### Property 12: Migration idempotence

*For any* database state (whether the migration has been applied zero or more times), running the migration SQL file again should complete without error and leave the schema in the same final state.

**Validates: Requirements 9.3**

---

## Error Handling

| Scenario | Handler | Error Message | Outcome |
|----------|---------|---------------|---------|
| Non-admin attempts mark_sold | `show.php` POST | "Only admins can mark a vehicle as sold." | Redirect to show.php, no DB change |
| Vehicle is rented | `show.php` POST | "Vehicle is currently rented and cannot be sold." | Redirect to show.php, no DB change |
| Vehicle is in maintenance | `show.php` POST | "Vehicle is in maintenance and cannot be sold." | Redirect to show.php, no DB change |
| Vehicle has future reservations | `show.php` POST | "Vehicle has X upcoming reservation(s). Cancel them first before marking as sold." | Redirect to show.php, no DB change |
| Expense POST for sold vehicle | `add_expense.php` | "Cannot add expenses to a sold vehicle." | Redirect to show.php, no ledger entry |
| Edit GET/POST for sold vehicle | `edit.php` | "Cannot edit a sold vehicle." | Redirect to show.php |
| Delete for sold vehicle | `delete.php` | "Cannot delete a sold vehicle." | Redirect to index.php |
| Challan POST for sold vehicle | challan handler | "Cannot create a challan for a sold vehicle." | Redirect, no challan created |
| Catalog single-vehicle mode, sold | `catalog.php` | "Vehicle not available" message rendered | Page shows not-available state |

All error paths use the existing `flash('error', ...)` + `redirect(...)` pattern consistent with the rest of the codebase.

---

## Testing Strategy

### Unit Tests

Focus on specific examples and edge cases:

- Verify that a vehicle in `'available'` status with no future reservations can be marked as sold (happy path).
- Verify that a vehicle in `'rented'` status is rejected with the correct error message.
- Verify that a vehicle in `'maintenance'` status is rejected with the correct error message.
- Verify that a vehicle with exactly 1 future reservation is rejected with the message "1 upcoming reservation(s)".
- Verify that a vehicle with 3 future reservations is rejected with the message "3 upcoming reservation(s)".
- Verify that the catalog single-vehicle mode shows the not-available message for a sold vehicle.
- Verify that the migration SQL can be run twice on a fresh schema without error.

### Property-Based Tests

Use a property-based testing library (e.g., **fast-check** for JS test harnesses, or **QuickCheck**-style for PHP via a lightweight generator). Each property test should run a minimum of **100 iterations**.

Each test must be tagged with a comment in the format:
`// Feature: vehicle-sold-status, Property N: <property_text>`

**Property 1** — Mark as Sold button visibility
- Generator: random vehicle with random status
- Assert: button present iff status != 'sold'
- Tag: `Feature: vehicle-sold-status, Property 1: Mark as Sold button visibility is inverse of sold status`

**Property 2** — Mark as Sold DB update round-trip
- Generator: random available vehicle with no future reservations
- Action: execute mark_sold
- Assert: DB row has status='sold' and sold_at IS NOT NULL
- Tag: `Feature: vehicle-sold-status, Property 2: Mark as Sold DB update round-trip`

**Property 3** — Non-admin rejection
- Generator: random non-admin session + random vehicle
- Assert: mark_sold rejected, vehicle status unchanged
- Tag: `Feature: vehicle-sold-status, Property 3: Non-admin mark_sold rejection`

**Property 4** — Unsellable state rejection
- Generator: random vehicle in rented/maintenance status OR random vehicle with 1–10 future reservations
- Assert: mark_sold rejected, vehicle status unchanged
- Tag: `Feature: vehicle-sold-status, Property 4: Unsellable state rejection`

**Property 5** — Sold vehicle UI state
- Generator: random sold vehicle
- Assert: badge present, banner present, action buttons absent
- Tag: `Feature: vehicle-sold-status, Property 5: Sold vehicle UI state`

**Property 6** — Sold vehicles excluded from operational count cards
- Generator: random fleet with mix of statuses including sold
- Assert: total/available/rented/maintenance counts exclude sold vehicles
- Tag: `Feature: vehicle-sold-status, Property 6: Sold vehicles excluded from operational count cards`

**Property 7** — Default fleet list excludes sold vehicles
- Generator: random fleet with mix of statuses
- Assert: default query result contains no sold vehicles
- Tag: `Feature: vehicle-sold-status, Property 7: Default fleet list excludes sold vehicles`

**Property 8** — Sold filter returns only sold vehicles
- Generator: random fleet with mix of statuses
- Assert: status='sold' filter returns only sold vehicles
- Tag: `Feature: vehicle-sold-status, Property 8: Sold filter returns only sold vehicles`

**Property 9** — Sold vehicles excluded from all operational vehicle queries
- Generator: random fleet with at least one sold vehicle
- Assert: reservation/quotation/catalog/challan queries return no sold vehicles
- Tag: `Feature: vehicle-sold-status, Property 9: Sold vehicles excluded from all operational vehicle queries`

**Property 10** — Sold vehicle POST actions rejected without side effects
- Generator: random sold vehicle
- Assert: expense/challan POST rejected, no new DB records created
- Tag: `Feature: vehicle-sold-status, Property 10: Sold vehicle POST actions rejected without side effects`

**Property 11** — Historical data preserved for sold vehicles
- Generator: random sold vehicle with past reservations and expenses
- Assert: reservation history, expense history, and calendar sections present in rendered output
- Tag: `Feature: vehicle-sold-status, Property 11: Historical data preserved for sold vehicles`

**Property 12** — Migration idempotence
- Action: run migration SQL twice on a test schema
- Assert: no error on second run, schema state identical after both runs
- Tag: `Feature: vehicle-sold-status, Property 12: Migration idempotence`

---

## Release Artifacts

Per `UPDATE_SESSION_RULES.md`:

- **Release ID**: `2026-03-25_vehicle_sold_status`
- **SQL file**: `migrations/releases/2026-03-25_vehicle_sold_status.sql`
- **PRODUCTION_DB_STEPS.md**: Add entry under Pending
- **UPDATE_SESSION_RULES.md**: Add row to Release Log
