# Session Rules — 2026-03-07

## Purpose
Rules and steps for the March 7, 2026 update session.

## Hard Rules (Inherited from UPDATE_SESSION_RULES.md)
1. Never run `wipe_and_reset.sql` on production.
2. Do not run DB migrations automatically.
3. Every DB change → separate SQL file, reviewed first.
4. SQL must be idempotent when possible.
5. Apply DB SQL manually on production (phpMyAdmin) before or with code deploy.
6. Make code edits in main project only; do not manually edit `SERVER UPDATE`.

8. Every DB change → also add to `PRODUCTION_DB_STEPS.md` under "Pending".

## Session Changes Log

| # | Change | File(s) Modified | DB Change? |
|---|--------|-----------------|------------|
| 1 | Vehicles & Staff sidebar menus made expandable/collapsible | `includes/header.php` | No |
| 2 | Optional alternative number support for Clients and Leads | `clients/create.php`, `clients/edit.php`, `clients/index.php`, `clients/show.php`, `includes/client_helpers.php`, `leads/create.php`, `leads/edit.php`, `leads/show.php`, `leads/pipeline.php`, `leads/convert.php`, `migrations/releases/2026-03-07_client_lead_alternative_numbers.sql`, `PRODUCTION_DB_STEPS.md`, `UPDATE_SESSION_RULES.md` | Yes |
| 3 | Vehicle list ordering changed to business priority: rented by nearest time-left first, then available, then maintenance | `vehicles/index.php` | No |
| 4 | Vehicle cards now show how long a rented vehicle has been in use | `vehicles/index.php` | No |
| 5 | Optional Insurance Docs and Pollution Docs uploads added to vehicle create/edit (stored in existing documents table) | `vehicles/create.php`, `vehicles/edit.php` | No |
| 6 | Vehicle details now support saving optional condition notes | `vehicles/show.php`, `migrations/releases/2026-03-08_vehicle_condition_notes.sql`, `PRODUCTION_DB_STEPS.md` | Yes |
| 7 | Insurance type + expiry added on vehicle forms and risky insurance cards now show red blinking alert border | `vehicles/create.php`, `vehicles/edit.php`, `vehicles/index.php`, `migrations/releases/2026-03-08_vehicle_insurance_metadata.sql`, `PRODUCTION_DB_STEPS.md` | Yes |
| 8 | Vehicle details page now shows insurance type, expiry date, and insurance status/risk summary | `vehicles/show.php` | No |
| 9 | Security deposit ledger tracking added with configurable bank account; deposit entries now excluded from income/target KPI calculations | `settings/general.php`, `includes/ledger_helpers.php`, `reservations/deliver.php`, `reservations/return.php`, `accounts/index.php`, `accounts/targets.php`, `accounts/targets_backup_15th_logic.php` | No |
| 10 | Per-reservation client review history now stored in new `client_reviews` table; review saved on each return, displayed as timeline on client profile | `reservations/return.php`, `clients/show.php`, `wipe_and_reset.sql`, `migrations/releases/2026-03-08_client_reviews_table.sql`, `PRODUCTION_DB_STEPS.md`, `UPDATE_SESSION_RULES.md` | Yes |
| 11 | Pre-payroll staff advance: given from staff profile before payroll generation; tagged to specific month/year; deduction only applies to matching payroll period; Give Advance removed from payroll screen | `staff/show.php`, `payroll/index.php` | No |
| 12 | Added configurable Expense Categories in Settings and wired Accounts expense entry/filter to use configured list plus existing system categories | `settings/expense_categories.php`, `includes/settings_helpers.php`, `accounts/index.php`, `includes/ledger_helpers.php`, `settings/general.php`, `settings/damage_costs.php`, `settings/lead_sources.php`, `settings/staff_permissions.php`, `settings/attendance.php`, `PRODUCTION_DB_STEPS.md`, `UPDATE_SESSION_RULES.md` | No |
| 13 | Admin can now force punch-out active staff sessions and edit any attendance record (times + reasons). Adds Admin Note + Manual Edit flag for audit trail. | `attendance/index.php`, `attendance/admin_actions.php` (new), `migrations/releases/2026-03-21_attendance_admin_controls.sql` (new), `PRODUCTION_DB_STEPS.md`, `UPDATE_SESSION_RULES.md` | Yes |
| 14 | Held deposit tracking with configurable alert threshold, dashboard warnings, reservation list badges, and test mode for fast testing | `reservations/return.php`, `reservations/resolve_held_deposit.php`, `reservations/index.php`, `index.php`, `settings/general.php`, `includes/reservation_payment_helpers.php`, `migrations/releases/2026-03-24_held_deposit_tracking.sql`, `PRODUCTION_DB_STEPS.md` | Yes |
