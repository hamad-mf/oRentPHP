# Production DB Migration Steps

This file tracks all database changes that need to be applied to **production** (via phpMyAdmin or CLI) before or alongside each code deploy.

**How to use:**
1. Before deploying new code, check the "Pending" section below.
2. Run each SQL step in order on production phpMyAdmin.
3. After confirmed, move the entry from "Pending" to "Applied".

---

## Pending

### 2026-03-11 - Client Proof Documents
**SQL file:** `migrations/releases/2026-03-11_client_proofs.sql`
**Notes:** Adds `client_proofs` table for storing up to 5 proof documents per client.

### 2026-03-11 - Reservation Notes
**SQL file:** `migrations/releases/2026-03-11_reservation_notes.sql`
**Notes:** Adds optional `note` column to reservations table. Note can be added during reservation creation and displayed on reservation details page.

### 2026-03-11 - Staff Admin Dashboard Toggle
**SQL file:** `migrations/releases/2026-03-11_staff_admin_dashboard_toggle.sql`
**Notes:** Adds `enable_admin_dashboard` column to staff table. When enabled, staff can view full admin dashboard.

### 2026-03-11 - Staff Incentives
**SQL file:** `migrations/releases/2026-03-11_staff_incentives.sql`
**Notes:** Creates `staff_incentives` table for tracking monthly incentives per staff member. Allows adding incentives from staff profile and auto-includes them in payroll generation.

### 2026-03-10 - Reservation Advance Payment
**SQL file:** `migrations/releases/2026-03-10_reservation_advance_payment.sql`
**Notes:** Adds `advance_paid`, `advance_payment_method`, `advance_bank_account_id` columns to the `reservations` table. Required before deploying the advance payment feature.

### 2026-03-09 - Configurable Expense Categories (No DB change needed)
**SQL file:** `None`
**Notes:** Expense categories are now managed in Settings > Expense Categories and stored in existing `system_settings` table (`expense_categories` key).

### 2026-03-06 - Lead Auto-Close After Follow-ups (No DB change needed)
**SQL file:** `None`
**Notes:** Auto-close lead to Lost after X follow-ups. Uses existing `system_settings` table. Configure in Settings > General.

---

## Applied

| Date | Release ID | SQL File | Notes |
|------|------------|----------|-------|
| 2026-03-06 | vehicle_collation_hotfix | `migrations/releases/2026-03-06_vehicle_collation_hotfix.sql` | Applied manually in production phpMyAdmin to align `vehicles`, `vehicle_images`, and `vehicle_requests` collations to `utf8mb4_unicode_ci`. Safe to rerun if needed. |
| 2026-03-06 | vehicle_availability | `migrations/releases/2026-03-06_vehicle_availability.sql` | Added `delivered_at` to track exact delivery time. Used by Vehicle Availability page. |
| 2026-03-06 | delivery_location | `migrations/releases/2026-03-06_delivery_location.sql` | Added `delivery_location` to store where the vehicle was delivered. |
| 2026-03-06 | payroll_staff_advances | `migrations/releases/2026-03-06_payroll_staff_advances.sql` | Created `payroll_advances` table and added payroll columns for deduction. |
| 2026-03-07 | client_lead_alternative_numbers | `migrations/releases/2026-03-07_client_lead_alternative_numbers.sql` | Added `alternative_number` to clients and leads. |
| 2026-03-08 | client_reviews_table | `migrations/releases/2026-03-08_client_reviews_table.sql` | Created `client_reviews` table for history. |
| 2026-03-08 | client_reviews_add_created_by | `migrations/releases/2026-03-08_client_reviews_add_created_by.sql` | Added `created_by` tracking to reviews. |
| 2026-03-08 | vehicle_insurance_metadata | `migrations/releases/2026-03-08_vehicle_insurance_metadata.sql` | Added insurance type and expiry date to vehicles. |
| 2026-03-08 | vehicle_condition_notes | `migrations/releases/2026-03-08_vehicle_condition_notes.sql` | Added condition notes field to vehicles. |
| 2026-03-08 | client_rating_review | `migrations/releases/2026-03-08_client_rating_review.sql` | Added `rating_review` cache column to clients table. |
