# Requirements Document

## Introduction

This feature introduces a "Sold" lifecycle status for vehicles in the fleet management system. Once a vehicle is marked as sold, it is permanently retired from all operational workflows — it cannot be reserved, quoted, added to challans, or have expenses recorded against it. It also cannot be edited. However, all historical data (past reservations, expenses, booking calendars) remains fully visible for audit and reporting purposes. The vehicle list gains a "Sold" status card alongside the existing Available, Rented, and Workshop cards.

A vehicle can only be sold if it is in a sellable state: it must not be currently rented, must not be in maintenance, and must have no future (upcoming) reservations. If any future reservations exist, the admin must cancel them before the vehicle can be marked as sold.

## Glossary

- **Vehicle**: A fleet asset tracked in the `vehicles` table with a `status` column.
- **Sold_Status**: The value `'sold'` stored in `vehicles.status`, indicating the vehicle has been permanently retired from the fleet.
- **Fleet_List**: The `vehicles/index.php` screen showing all vehicles with status filter cards.
- **Vehicle_Detail**: The `vehicles/show.php` screen showing full details, history, and actions for a single vehicle.
- **Reservation_Create**: The `reservations/create.php` screen where a vehicle is selected for a new booking.
- **Quotation_Builder**: The `vehicles/quotation_builder.php` screen where vehicles are selected for quotations.
- **Catalog**: The `vehicles/catalog.php` public-facing page showing available vehicles.
- **Challan_Create**: The `vehicles/create_challan.php` screen for recording traffic violations against a vehicle.
- **Expense_Add**: The `vehicles/add_expense.php` handler that records a vehicle expense.
- **Admin**: A user with `role = 'admin'` in the session.
- **Mark_As_Sold_Action**: The POST action `mark_sold` handled in `vehicles/show.php` that transitions a vehicle to Sold_Status.
- **Future_Reservation**: A reservation for the vehicle with a `start_date` in the future and status not cancelled/completed.

## Requirements

### Requirement 1: Mark a Vehicle as Sold

**User Story:** As an Admin, I want to mark a vehicle as sold from its detail page, so that the fleet records accurately reflect that the vehicle is no longer part of the operational fleet.

#### Acceptance Criteria

1. WHEN an Admin views Vehicle_Detail for a vehicle whose status is not `'sold'`, THE Vehicle_Detail SHALL display a "Mark as Sold" button in the actions area.
2. WHEN an Admin submits the Mark_As_Sold_Action for a valid vehicle, THE Vehicle_Detail SHALL update `vehicles.status` to `'sold'` and record `sold_at = NOW()` in the database.
3. WHEN an Admin submits the Mark_As_Sold_Action for a valid vehicle, THE Vehicle_Detail SHALL redirect back to the vehicle detail page with a success flash message.
4. IF a non-Admin user submits the Mark_As_Sold_Action, THEN THE Vehicle_Detail SHALL reject the request with an error flash message and redirect to the vehicle detail page without modifying the vehicle.
5. IF the Mark_As_Sold_Action is submitted for a vehicle with status `'rented'` (i.e., currently active reservation), THEN THE Vehicle_Detail SHALL reject the request with an error flash message explaining the vehicle is currently rented.
6. IF the Mark_As_Sold_Action is submitted for a vehicle with status `'maintenance'`, THEN THE Vehicle_Detail SHALL reject the request with an error flash message explaining the vehicle is currently in maintenance.
7. IF the Mark_As_Sold_Action is submitted for a vehicle that has one or more Future_Reservations, THEN THE Vehicle_Detail SHALL reject the request with an error flash message listing the count of upcoming reservations and instructing the admin to cancel them first.
8. WHEN a vehicle has Sold_Status, THE Vehicle_Detail SHALL NOT display the "Mark as Sold" button.
9. WHEN a vehicle has Sold_Status, THE Vehicle_Detail SHALL display a prominent "Sold" badge in place of the normal status badge.
10. WHEN a vehicle has Sold_Status, THE Vehicle_Detail SHALL NOT display the "Edit Vehicle", "Add Expense", "Generate Quotation", or "Delete" action buttons.
11. WHEN a vehicle has Sold_Status, THE Vehicle_Detail SHALL display a read-only "Sold" notice banner indicating no further actions can be performed.

---

### Requirement 2: Sold Vehicle Excluded from Fleet List Counts and Filters

**User Story:** As an Admin, I want sold vehicles excluded from the main fleet counts and operational filters, so that fleet metrics reflect only active assets.

#### Acceptance Criteria

1. THE Fleet_List SHALL NOT include vehicles with Sold_Status in the "Total Fleet" count card.
2. THE Fleet_List SHALL NOT include vehicles with Sold_Status in the "Available", "Rented", or "Workshop" count cards.
3. THE Fleet_List SHALL NOT display vehicles with Sold_Status when no status filter is applied (default view).
4. THE Fleet_List SHALL display a "Sold" status card showing the count of vehicles with Sold_Status.
5. WHEN an Admin clicks the "Sold" status card on Fleet_List, THE Fleet_List SHALL display only vehicles with Sold_Status.
6. WHEN Fleet_List displays a vehicle with Sold_Status, THE Fleet_List SHALL render a "Sold" badge styled consistently with the existing Available/Rented/Workshop badges.

---

### Requirement 3: Sold Vehicle Excluded from Reservation Vehicle Selection

**User Story:** As a staff member creating a reservation, I want sold vehicles to be absent from the vehicle selection list, so that I cannot accidentally book a vehicle that is no longer in the fleet.

#### Acceptance Criteria

1. WHEN Reservation_Create fetches available vehicles for a date range, THE Reservation_Create SHALL exclude vehicles with Sold_Status from the result set.
2. THE Reservation_Create SQL query SHALL filter out vehicles where `v.status = 'sold'` in addition to the existing `maintenance` and active-booking exclusions.

---

### Requirement 4: Sold Vehicle Excluded from Quotation Builder

**User Story:** As an Admin creating a quotation, I want sold vehicles absent from the vehicle picker, so that quotations only reference active fleet assets.

#### Acceptance Criteria

1. WHEN Quotation_Builder loads the vehicle list for selection, THE Quotation_Builder SHALL exclude vehicles with Sold_Status.
2. THE Quotation_Builder vehicle query SHALL filter out vehicles where `status = 'sold'`.

---

### Requirement 5: Sold Vehicle Excluded from Public Catalog

**User Story:** As a prospective client browsing the catalog, I want to see only active available vehicles, so that I am not shown vehicles that cannot be rented.

#### Acceptance Criteria

1. WHEN Catalog renders the vehicle list, THE Catalog SHALL exclude vehicles with Sold_Status.
2. WHEN Catalog is accessed in single-vehicle mode (`vehicle_id` parameter) for a vehicle with Sold_Status, THE Catalog SHALL display a "Vehicle not available" message instead of the vehicle details.

---

### Requirement 6: Sold Vehicle Excluded from Challan Creation

**User Story:** As an Admin recording a traffic violation, I want sold vehicles absent from the vehicle picker, so that challans are only created for active fleet vehicles.

#### Acceptance Criteria

1. WHEN Challan_Create loads the vehicle selection list, THE Challan_Create SHALL exclude vehicles with Sold_Status.
2. IF a POST request to Challan_Create references a vehicle with Sold_Status, THEN THE Challan_Create SHALL reject the request with an error and redirect without creating the challan.

---

### Requirement 7: Expenses Cannot Be Added to a Sold Vehicle

**User Story:** As an Admin, I want expense recording blocked for sold vehicles, so that financial records are not polluted with post-sale entries.

#### Acceptance Criteria

1. IF Expense_Add receives a POST request for a vehicle with Sold_Status, THEN THE Expense_Add SHALL reject the request with an error flash message and redirect to the vehicle detail page without recording the expense.
2. WHEN a vehicle has Sold_Status, THE Vehicle_Detail SHALL NOT display the "Add Expense" modal trigger button.

---

### Requirement 8: Historical Data Remains Visible for Sold Vehicles

**User Story:** As an Admin reviewing a sold vehicle's history, I want all past reservations, expenses, and booking data to remain visible, so that I have a complete audit trail.

#### Acceptance Criteria

1. WHEN an Admin views Vehicle_Detail for a vehicle with Sold_Status, THE Vehicle_Detail SHALL display the full reservation history table.
2. WHEN an Admin views Vehicle_Detail for a vehicle with Sold_Status, THE Vehicle_Detail SHALL display the full expense history table.
3. WHEN an Admin views Vehicle_Detail for a vehicle with Sold_Status, THE Vehicle_Detail SHALL display the booking calendar (if present).
4. THE Fleet_List "Sold" filter view SHALL allow clicking through to Vehicle_Detail for any sold vehicle.

---

### Requirement 9: Database Schema Support

**User Story:** As a developer, I want the database to support the sold status and sold date, so that the feature is persisted correctly.

#### Acceptance Criteria

1. THE System SHALL extend the `vehicles.status` ENUM to include the value `'sold'` via a migration script.
2. THE System SHALL add a nullable `sold_at DATETIME` column to the `vehicles` table via the same migration script.
3. THE System SHALL apply the migration in a self-healing manner (using `ALTER TABLE ... MODIFY COLUMN` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`) so that re-running the migration on an already-migrated database does not produce an error.
