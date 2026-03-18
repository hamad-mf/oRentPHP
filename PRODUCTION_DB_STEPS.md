# Production DB Migration Steps

This file tracks all database changes that need to be applied to **production** (via phpMyAdmin or CLI) before or alongside each code deploy.

**How to use:**
1. Before deploying new code, check the "Pending" section below.
2. Run each SQL step in order on production phpMyAdmin.
3. After confirmed, move the entry from "Pending" to "Applied".

---

## Pending

_(nothing pending — all migrations applied as of 2026-03-19)_

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
| 2026-03-10 | reservation_advance_payment | `migrations/releases/2026-03-10_reservation_advance_payment.sql` | Added `advance_paid`, `advance_payment_method`, `advance_bank_account_id` columns to reservations. |
| 2026-03-11 | client_proofs | `migrations/releases/2026-03-11_client_proofs.sql` | Created `client_proofs` table for storing up to 5 proof documents per client. |
| 2026-03-11 | reservation_notes | `migrations/releases/2026-03-11_reservation_notes.sql` | Added optional `note` column to reservations table. |
| 2026-03-11 | staff_admin_dashboard_toggle | `migrations/releases/2026-03-11_staff_admin_dashboard_toggle.sql` | Added `enable_admin_dashboard` column to staff table. |
| 2026-03-11 | staff_incentives | `migrations/releases/2026-03-11_staff_incentives.sql` | Created `staff_incentives` table for tracking monthly incentives per staff member. |
| 2026-03-12 | delivery_prepaid_charge | `migrations/releases/2026-03-12_delivery_prepaid_charge.sql` | Added prepaid delivery charge fields on reservations. |
| 2026-03-12 | ledger_void_entries | `migrations/releases/2026-03-12_ledger_void_entries.sql` | Added void metadata to ledger entries for soft-voiding. |
| 2026-03-13 | reservation_extension_grace | `migrations/releases/2026-03-13_reservation_extension_grace.sql` | Added `reservation_extensions` table and `extension_paid_amount` on reservations. |
| 2026-03-13 | vehicle_parts_due_notes | `migrations/releases/2026-03-13_vehicle_parts_due_notes.sql` | Added `parts_due_notes` column on vehicles. |
| 2026-03-14 | vehicle_pollution_expiry_date | `migrations/releases/2026-03-14_vehicle_pollution_expiry_date.sql` | Added `pollution_expiry_date` column on vehicles. |
| 2026-03-14 | vehicle_storage_locations | `migrations/releases/2026-03-14_vehicle_storage_locations.sql` | Added `second_key_location` and `original_documents_location` columns on vehicles. |
| 2026-03-16 | gps_daily_checks | `migrations/releases/2026-03-16_gps_daily_checks.sql` | Added `gps_daily_checks` table for 3 daily GPS check slots per active reservation. |
| 2026-03-16 | notifications_emi_due | `migrations/releases/2026-03-16_notifications_emi_due.sql` | Added `emi_due` to notifications `type` ENUM. |
| 2026-03-17 | hope_window_daily_targets | `migrations/releases/2026-03-17_hope_window_daily_targets.sql` | Added `hope_daily_targets` table for per-day target overrides in Hope Window. |
| 2026-03-18 | hope_window_predictions | `migrations/releases/2026-03-18_hope_window_predictions.sql` | Added `hope_daily_predictions` table for manual prediction entries in Hope Window. |
| 2026-03-19 | payroll_overtime_pay | `migrations/releases/2026-03-18_payroll_overtime_pay.sql` | Added `overtime_pay` column to `payroll` table. |
