# oRentPHP Update Session Rules (Safe Production)

## Purpose
Use this file as the single source of truth for all future update sessions.
Goal: deploy safely without data loss and without using `wipe_and_reset.sql`.

## Hard Rules (Do Not Break)
1. Never run `wipe_and_reset.sql` on production.
2. Do not run DB migrations automatically in local or production.
3. Every DB change must be written as a separate SQL file and reviewed first.
4. SQL must be idempotent when possible (`IF NOT EXISTS`, guarded `ALTER`).
5. Apply DB SQL manually on production (phpMyAdmin) before or with code deploy.
6. Make code edits in main project only; do not manually edit `SERVER UPDATE`.
7. Use `sync_to_server_update.ps1` to mirror root -> `SERVER UPDATE`.
8. Every DB change must also be added to `PRODUCTION_DB_STEPS.md` under "Pending" so we have a clear checklist for production deployment.

## Required Release Artifacts
For each release, create:
1. A release ID: `YYYY-MM-DD_short_name`
2. A SQL file (if DB change exists): `migrations/releases/<release_id>.sql`
3. A short release note entry in this file under "Release Log"
4. An entry in `PRODUCTION_DB_STEPS.md` under "Pending" with the SQL and notes

## SQL File Template
```sql
-- Release: 2026-03-05_timezone_fix_example
-- Author: <name>
-- Safe: idempotent
-- Notes: <what this migration changes>

SET FOREIGN_KEY_CHECKS = 0;

-- Example patterns:
-- CREATE TABLE IF NOT EXISTS ...
-- ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...
-- UPDATE ... WHERE ... (safe condition)

SET FOREIGN_KEY_CHECKS = 1;
```

## Standard Deployment Flow (No Wipe/Reset)
1. Implement and test code changes in root project.
2. If DB changes are needed, write SQL in a new release file under `migrations/releases/`.
3. Validate PHP syntax on changed files (`php -l <file>`).
4. Run sync/deploy:
   `powershell -ExecutionPolicy Bypass -File ".\sync_to_server_update.ps1" -Deploy -Message "<release_id>"`
5. On production phpMyAdmin:
   1. Take DB backup/export first.
   2. Run the release SQL file manually.
6. Run smoke tests on production:
   1. Login
   2. Dashboard
   3. Vehicles
   4. Reservations create/deliver/return
   5. Leads pipeline/convert

## What `sync_to_server_update` Does
- Syncs code files from root to `SERVER UPDATE`
- Can push to GitHub with `-Deploy`
- Does not replace production data
- Does not run SQL migrations for you

## AI Handoff Prompt (Copy/Paste)
```text
Follow UPDATE_SESSION_RULES.md strictly.
Do not run wipe_and_reset.sql.
Do not auto-run DB migrations.
If schema changes are needed, create a new SQL file under migrations/releases and stop for approval before execution.
Edit only main project files, not SERVER UPDATE manually.
For every DB change, also update PRODUCTION_DB_STEPS.md with the pending migration step.
```

## Release Log
Add one row per release.

| Date | Release ID | Code Deploy | DB SQL File | Prod SQL Applied By | Notes |
|------|------------|-------------|-------------|----------------------|-------|
| 2026-03-05 | timezone_consistency_fix | Yes | None | N/A | IST timezone fix and NOW/CURDATE hardening |
| 2026-03-06 | lead_auto_close_followups | Yes | None | N/A | Auto-close lead to Lost after X follow-ups. Config in Settings. |
| 2026-03-06 | vehicle_availability | Yes | Yes | Applied (phpMyAdmin) | Vehicle availability page + delivery tracking |
| 2026-03-06 | vehicle_collation_hotfix | No (DB hotfix) | `migrations/releases/2026-03-06_vehicle_collation_hotfix.sql` | Applied manually (phpMyAdmin) | Production collation alignment for `vehicles`, `vehicle_images`, and `vehicle_requests`. |
| 2026-03-06 | payroll_staff_advances | Yes | `migrations/releases/2026-03-06_payroll_staff_advances.sql` | Applied (phpMyAdmin) | Staff advance tracking and payroll-time deduction. |
| 2026-03-07 | client_lead_alternative_numbers | Yes | `migrations/releases/2026-03-07_client_lead_alternative_numbers.sql` | Applied (phpMyAdmin) | Optional `alternative_number` added to clients and leads. |
| 2026-03-08 | vehicle_condition_notes | Yes | `migrations/releases/2026-03-08_vehicle_condition_notes.sql` | Applied (phpMyAdmin) | Added optional `condition_notes` on vehicles. |
| 2026-03-08 | vehicle_insurance_metadata | Yes | `migrations/releases/2026-03-08_vehicle_insurance_metadata.sql` | Applied (phpMyAdmin) | Added insurance type + expiry fields on vehicles. |
| 2026-03-08 | security_deposit_ledger_tracking | Yes | None | N/A | Added configurable bank account for security deposit tracking. |
| 2026-03-08 | client_reviews_table | Yes | `migrations/releases/2026-03-08_client_reviews_table.sql` | Applied (phpMyAdmin) | Added `client_reviews` table for history. |
| 2026-03-08 | pre_payroll_advance | Yes | None | N/A | Refined pre-payroll advances (month/year specific). |
| 2026-03-09 | configurable_expense_categories | Yes | None | N/A | Added Settings > Expense Categories and wired Accounts expense entry + category filter to use configured categories. |
| 2026-03-10 | reservation_advance_payment | Yes | `migrations/releases/2026-03-10_reservation_advance_payment.sql` | Pending | Advance payment at reservation creation with cash/credit/account method, ledger posting, delivery deduction, and show page display. |
| 2026-03-11 | staff_incentives | Yes | `migrations/releases/2026-03-11_staff_incentives.sql` | Pending | Staff incentives per month from profile, history tracking, auto-included in payroll generation. |
| 2026-03-11 | staff_admin_dashboard_toggle | Yes | `migrations/releases/2026-03-11_staff_admin_dashboard_toggle.sql` | Pending | Add toggle in staff profile to allow staff view admin dashboard. |
| 2026-03-11 | reservation_notes | Yes | `migrations/releases/2026-03-11_reservation_notes.sql` | Pending | Add optional note field when creating reservation, displayed on details page. |
| 2026-03-11 | client_proofs | Yes | `migrations/releases/2026-03-11_client_proofs.sql` | Pending | Multiple client proof uploads (max 5) via new client_proofs table. |
| 2026-03-12 | delivery_prepaid_charge | Yes | `migrations/releases/2026-03-12_delivery_prepaid_charge.sql` | Pending | Collect delivery charge at reservation creation; delivery screen shows it as already collected. |
| 2026-03-12 | ledger_void_entries | Yes | `migrations/releases/2026-03-12_ledger_void_entries.sql` | Pending | Soft-void ledger entries with reason; voided entries excluded from KPI totals. |
| 2026-03-13 | reservation_extension_grace | Yes | `migrations/releases/2026-03-13_reservation_extension_grace.sql` | Pending | Extend active reservations from today (grace), post extension payment to ledger, and track extension-paid amount. |
| 2026-03-13 | vehicle_parts_due_notes | Yes | `migrations/releases/2026-03-13_vehicle_parts_due_notes.sql` | Pending | Add parts-due notes on vehicles to track upcoming replacements. |
| 2026-03-13 | accounts_credit_net_balance | Yes | None | N/A | Accounts screen credit card + overall total now use net credit (income - expense). |
| 2026-03-13 | staff_my_profile_nav | Yes | None | N/A | Added My Profile page for staff (no manage_staff permission required) with read-only advances/incentives, plus sidebar/mobile nav link. |
| 2026-03-13 | vehicle_quotation | Yes | None | N/A | Added Generate Quotation button in vehicle details with printable vehicle rate sheet (delivery/return charges included). |
| 2026-03-13 | client_address_proof_required | Yes | None | N/A | Client address and proof document are now required on create form. |
| 2026-03-13 | client_phone_unique | Yes | None | N/A | Prevent duplicate client phone numbers on create/edit. |
| 2026-03-13 | vehicle_catalog_brand_share | Yes | None | N/A | Catalog branding updated, total fleet hidden, and single-vehicle share links auto-open the vehicle modal. |
| 2026-03-14 | vehicle_pollution_expiry_date | Yes | `migrations/releases/2026-03-14_vehicle_pollution_expiry_date.sql` | Pending | Add pollution certificate expiry date to vehicles with create/edit inputs and details display. |
| 2026-03-14 | vehicle_storage_locations | Yes | `migrations/releases/2026-03-14_vehicle_storage_locations.sql` | Pending | Add second key and original documents storage locations to vehicles, visible on create/edit/details. |
| 2026-03-14 | vehicle_storage_inline_edit | Yes | None | N/A | Vehicle details page now supports inline editing of storage locations. |
| 2026-03-14 | notifications_settings | Yes | None | N/A | Added Notifications settings tab to control which in-app notifications are generated. |
| 2026-03-14 | vehicle_quotation_builder_link | Yes | None | N/A | Added Create Quotation button on Vehicles list to open manual quotation builder. |
| 2026-03-14 | vehicle_quotation_pdf_style | Yes | None | N/A | Updated single-vehicle quotation PDF to match manual quotation builder style. |
| 2026-03-14 | pipeline_global_search | Yes | None | N/A | Added global search filter for leads pipeline (filters across all stages). |
| 2026-03-15 | pending_sql_idempotent_guards | Yes | None | N/A | Made pending SQL migrations safe to re-run using information_schema guards. |
| 2026-03-16 | gps_daily_checks | Yes | `migrations/releases/2026-03-16_gps_daily_checks.sql` | Applied (phpMyAdmin) | Adds `gps_daily_checks` table to track 3 daily GPS check slots per active reservation. |
| 2026-03-16 | gps_tracking_enhancements | Yes | None | N/A | Enhanced GPS tracking with per-day checks reset, date filtering, 5-day history pagination, timestamp saving, and duplicate history fixes. |
