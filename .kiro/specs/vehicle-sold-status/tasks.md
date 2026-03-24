# Implementation Plan: Vehicle Sold Status

## Overview

Implement a permanent "sold" lifecycle status for vehicles. The migration extends the ENUM and adds `sold_at`, the show page gains a mark-sold handler and UI guards, and every operational screen (index, add_expense, edit, delete, reservations/create, quotation_builder, catalog, challans) excludes or blocks sold vehicles.

## Tasks

- [x] 1. Write the DB migration SQL file
  - Create `migrations/releases/2026-03-25_vehicle_sold_status.sql`
  - Use `ALTER TABLE vehicles MODIFY COLUMN status ENUM(...)` to add `'sold'` to the existing ENUM values (idempotent via `MODIFY COLUMN`)
  - Use `ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS sold_at DATETIME NULL DEFAULT NULL AFTER status`
  - Follow the SQL file template in UPDATE_SESSION_RULES.md (header comment, SET FOREIGN_KEY_CHECKS guards)
  - Do NOT run the migration — stop for manual review per UPDATE_SESSION_RULES.md
  - _Requirements: 9.1, 9.2, 9.3_

- [x] 2. Implement `vehicles/show.php` — mark_sold POST handler
  - [x] 2.1 Add `mark_sold` POST action handler
    - Reject non-admin with error flash + redirect (req 1.4)
    - Reject if `status = 'rented'` with error flash (req 1.5)
    - Reject if `status = 'maintenance'` with error flash (req 1.6)
    - Query future reservations (`start_date > CURDATE()` AND status not in `cancelled`, `completed`); reject with count message if any exist (req 1.7)
    - On success: `UPDATE vehicles SET status='sold', sold_at=NOW() WHERE id=?`; redirect with success flash (req 1.2, 1.3)
    - _Requirements: 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_

  - [x] 2.2 Update `vehicles/show.php` UI for sold state
    - Replace normal status badge with a "Sold" badge when `status = 'sold'` (req 1.9)
    - Show read-only "Sold" notice banner when sold (req 1.11)
    - Hide "Edit Vehicle", "Add Expense", "Generate Quotation", "Delete" buttons when sold (req 1.10)
    - Hide "Mark as Sold" button when already sold (req 1.8)
    - Show "Mark as Sold" button in actions area when status is NOT sold and user is admin (req 1.1)
    - Historical reservation and expense tables remain visible (req 8.1, 8.2, 8.3)
    - _Requirements: 1.1, 1.8, 1.9, 1.10, 1.11, 8.1, 8.2, 8.3_

- [x] 3. Update `vehicles/index.php` — fleet list sold support
  - [x] 3.1 Exclude sold from default view and existing count cards
    - Add `AND status != 'sold'` to the Total Fleet count query (req 2.1)
    - Ensure Available, Rented, Workshop count queries already filter by specific status (sold excluded implicitly); confirm and fix if needed (req 2.2)
    - Add `AND status != 'sold'` to the default vehicle list query (no filter applied) (req 2.3)
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.2 Add Sold status card and filter
    - Add a "Sold" count card querying `WHERE status = 'sold'` (req 2.4)
    - Wire the Sold card click to filter the list to `WHERE status = 'sold'` (req 2.5)
    - Render a "Sold" badge on vehicle rows when `status = 'sold'`, styled consistently with existing badges (req 2.6)
    - Ensure clicking through to Vehicle_Detail works from the Sold filter view (req 8.4)
    - _Requirements: 2.4, 2.5, 2.6, 8.4_

- [x] 4. Block sold vehicles in operational write screens
  - [x] 4.1 `vehicles/add_expense.php` — block if sold
    - After fetching the vehicle, check `status = 'sold'`; if so, set error flash and redirect to vehicle detail without recording the expense (req 7.1)
    - _Requirements: 7.1_

  - [x] 4.2 `vehicles/edit.php` — block if sold
    - After fetching the vehicle, check `status = 'sold'`; if so, set error flash and redirect to vehicle detail page (req 1.10 — edit button hidden, but guard the handler too)
    - _Requirements: 1.10_

  - [x] 4.3 `vehicles/delete.php` — block if sold
    - After fetching the vehicle, check `status = 'sold'`; if so, set error flash and redirect to vehicle detail page without deleting (req 1.10 — delete button hidden, but guard the handler too)
    - _Requirements: 1.10_

- [x] 5. Exclude sold from vehicle selection queries
  - [x] 5.1 `reservations/create.php` — exclude sold from vehicle picker
    - Add `AND v.status != 'sold'` to the available-vehicles query (req 3.1, 3.2)
    - _Requirements: 3.1, 3.2_

  - [x] 5.2 `vehicles/quotation_builder.php` — exclude sold from vehicle list
    - Add `WHERE status != 'sold'` (or `AND status != 'sold'`) to the vehicle fetch query (req 4.1, 4.2)
    - _Requirements: 4.1, 4.2_

  - [x] 5.3 `vehicles/catalog.php` — exclude sold from catalog
    - Add `AND status != 'sold'` to the catalog vehicle list query (req 5.1)
    - For single-vehicle mode (`vehicle_id` param): if the fetched vehicle has `status = 'sold'`, render "Vehicle not available" instead of vehicle details (req 5.2)
    - _Requirements: 5.1, 5.2_

  - [x] 5.4 `vehicles/challans.php` and `vehicles/create_challan.php` — exclude sold from challan vehicle picker
    - In `challans.php` vehicle list/filter: add `AND status != 'sold'` to vehicle queries (req 6.1)
    - In `create_challan.php` vehicle dropdown query: add `AND status != 'sold'` (req 6.1)
    - In `create_challan.php` POST handler: after fetching the vehicle, check `status = 'sold'`; if so, reject with error and redirect without creating the challan (req 6.2)
    - _Requirements: 6.1, 6.2_

- [x] 6. Checkpoint — review all guards
  - Verify all operational screens exclude/block sold vehicles as specified.
  - Ensure the migration SQL is idempotent and matches PRODUCTION_DB_STEPS.md entry.
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- The DB migration must NOT be run automatically — apply manually via phpMyAdmin per UPDATE_SESSION_RULES.md
- PRODUCTION_DB_STEPS.md already has the pending entry for `2026-03-25_vehicle_sold_status`
- UPDATE_SESSION_RULES.md Release Log already has the entry for `vehicle_sold_status`
- All historical data (reservations, expenses, calendar) remains visible on Vehicle_Detail for sold vehicles
