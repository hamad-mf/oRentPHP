# Pagination Implementation Plan

Last updated: 2026-03-04  
Scope owner: Hamad + Codex  
Execution mode: One screen at a time, test, confirm, then continue.

## 1. Objective

Implement safe, consistent pagination across list-heavy screens without breaking existing working behavior.

This plan is the single source of truth for:

1. What screens are already paginated.
2. What screens are partially implemented.
3. What screens are still pending.
4. The exact rollout process and QA gate for each step.

## 2. Non-Negotiable Safety Rules

1. Do not refactor unrelated code while adding pagination.
2. Change only the current target screen for that step.
3. Preserve existing filters, search, sort, tabs, and totals.
4. Keep current UI/UX behavior unless pagination UI is missing.
5. Pagination controls must be centered and use the shared visible style from `render_pagination()`.
6. Run syntax checks on every touched PHP file.
7. If any DB structure change is needed, update `wipe_and_reset.sql` in the same step.
8. After each screen change, provide:
   1. Seed SQL (dummy data for that screen).
   2. Cleanup SQL (remove dummy data safely).
   3. Manual test checklist.
9. Move to next screen only after your confirmation.

## 3. Existing Foundation (Already in Code)

1. Per-page setting exists in Settings:
   - `settings/general.php` (`per_page` key).
2. Global helpers already exist:
   - `includes/settings_helpers.php`
   - `get_per_page(PDO $pdo, int $default = 25)`
   - `paginate_query(...)`
   - `render_pagination(...)`
3. Seed default for `per_page` already exists in:
   - `wipe_and_reset.sql`

## 4. Current Coverage Snapshot

Status values:

- `done_in_code`: pagination helper + UI pager found.
- `partial_in_code`: pagination query exists, pager UI or behavior incomplete.
- `pending`: no pagination integration yet.
- `review_needed`: integrated but needs manual verification in this rollout.

| Module | Screen File | Status | Notes |
|---|---|---|---|
| Vehicles | `vehicles/index.php` | done_in_code + review_needed | Target first verification step. |
| Vehicle Requests | `vehicles/requests.php` | partial_in_code | Uses `paginate_query`, pager UI not rendered yet. |
| Reservations | `reservations/index.php` | done_in_code | Already wired with pager. |
| GPS Tracking | `gps/index.php` | done_in_code + review_needed | Shared centered pager wired; count query aligned with tracking filters. |
| Accounts Ledger | `accounts/index.php` | done_in_code | Ledger pagination present. |
| Targets | `accounts/targets.php` | done_in_code + review_needed | Daily breakdown uses shared centered pagination (in-memory rows) with month/year params preserved. |
| Clients | `clients/index.php` | done_in_code | Pager present. |
| Pipeline | `leads/pipeline.php` | done_in_code + review_needed | Added shared centered pagination on filtered lead set while preserving Kanban columns and drag/drop behavior. |
| Staff List | `staff/index.php` | done_in_code | Pager present. |
| Staff Tasks (Admin) | `staff/tasks.php` | done_in_code + review_needed | Added shared centered pagination with user/status filters preserved. |
| Attendance | `attendance/index.php` | done_in_code + review_needed | Added shared centered pagination for staff rows while preserving date filter and attendance summaries. |
| Payroll | `payroll/index.php` | done_in_code | Pager present. |
| Investments | `investments/index.php` | done_in_code | Pager present. |
| Expenses | `expenses/index.php` | pending | To be added in extended scope. |
| Challans | `challans/index.php` | pending | To be added in extended scope. |
| Papers | `papers/index.php` | pending | To be added in extended scope. |

## 5. Rollout Order

Agreed execution is sequential. Start one screen, test, confirm, then proceed.

1. Vehicles (`vehicles/index.php`) verification/hardening.
2. Vehicle Requests (`vehicles/requests.php`) complete pager UI.
3. Reservations verification pass.
4. GPS Tracking (`gps/index.php`) complete pager UI.
5. Accounts Ledger verification pass.
6. Targets pagination decision (or explicit no-pagination rationale).
7. Clients verification pass.
8. Pipeline pagination design and implementation.
9. Staff list verification pass.
10. Staff tasks pagination.
11. Attendance pagination.
12. Payroll verification pass.
13. Investments verification pass.
14. Extended scope: Expenses, Challans, Papers.

## 6. Standard Implementation Pattern Per Screen

For every screen step:

1. Read current screen fully.
2. Confirm existing filters/search/sort and current SQL.
3. Add or fix pagination using:
   1. `$perPage = get_per_page($pdo);`
   2. `$page = max(1, (int)($_GET['page'] ?? 1));`
   3. `paginate_query(...)`
   4. `render_pagination(...)`
4. Ensure query params are preserved in pagination links.
5. Keep empty states and totals correct.
6. Run `php -l` on changed files.
7. Provide:
   1. Seed SQL.
   2. Cleanup SQL.
   3. Test checklist.
8. Wait for user confirmation before next screen.

## 7. Dummy Data SQL Rules

For each screen, SQL delivery must include:

1. `INSERT` block to create enough records to exceed current `per_page`.
2. Safe unique marker in text fields (example: prefix `PAGTEST_YYYYMMDD_...`).
3. Cleanup block using the same marker.
4. No destructive updates to real data.
5. Avoid changing IDs already used by live records.

## 8. QA Checklist Template (Per Screen)

Use this exact checklist after each screen update:

1. Open screen with no filters, confirm page 1 loads.
2. Navigate to page 2 and back.
3. Apply search/filter/sort, confirm page links preserve filters.
4. Confirm total row count and displayed range are correct.
5. Confirm empty result behavior still works.
6. Confirm action buttons still work on paginated rows.
7. Confirm no PHP warnings/errors in UI or logs.

## 9. Step Tracker

Update this section after each completed screen.

| Step | Screen | Status | Code Updated | Dummy SQL Given | User Tested | User Confirmed | Notes |
|---|---|---|---|---|---|---|---|
| 1 | Vehicles (`vehicles/index.php`) | completed | yes | yes | yes | yes | Confirmed by user; min 12 per page and centered visible pager retained. |
| 2 | Vehicle Requests (`vehicles/requests.php`) | completed | yes | yes | yes | yes | Confirmed by user; pager added, action state preserved, centered shared style applied. |
| 3 | Reservations (`reservations/index.php`) | ready_for_test | no | yes | no | no | Already wired to shared centered pager; verification complete, awaiting your test confirmation. |
| 4 | GPS (`gps/index.php`) | ready_for_test | yes | yes | no | no | Added shared centered pager, fixed tracking-filter count SQL, and preserved page on Save. |
| 5 | Accounts (`accounts/index.php`) | ready_for_test | no | yes | no | no | Verification complete; shared centered pager already in use with filter params preserved. |
| 6 | Targets (`accounts/targets.php`) | ready_for_test | yes | yes | no | no | Implemented array-based daily breakdown pagination using shared centered pager and preserved `m/y` params. |
| 7 | Clients (`clients/index.php`) | ready_for_test | no | yes | no | no | Verification complete; shared centered pager already in use with search/filter params preserved. |
| 8 | Pipeline (`leads/pipeline.php`) | completed | yes | yes | yes | yes | Confirmed by user. Includes settings toggle for pipeline-only pagination and shared centered pager when enabled. |
| 9 | Staff (`staff/index.php`) | ready_for_test | no | yes | no | no | Verification complete; shared centered pager already in use with search param preserved. |
| 10 | Staff Tasks (`staff/tasks.php`) | completed | yes | yes | yes | yes | Confirmed by user after seed fix (required at least one active non-admin user). |
| 11 | Attendance (`attendance/index.php`) | ready_for_test | yes | yes | no | no | Added in-memory pagination with shared centered pager; preserves `date` filter. |
| 12 | Payroll (`payroll/index.php`) | ready_for_test | no | yes | no | no | Verification complete; shared centered pager already in use with `month/year` params preserved. |
| 13 | Investments (`investments/index.php`) | ready_for_test | yes | yes | no | no | Finalized pagination query wiring, min 12/page, and shared centered pager output. |
| 14 | Expenses (`expenses/index.php`) | pending | no | no | no | no | Extended scope. |
| 15 | Challans (`challans/index.php`) | pending | no | no | no | no | Extended scope. |
| 16 | Papers (`papers/index.php`) | pending | no | no | no | no | Extended scope. |

## 10. Handoff Notes for Any Future AI/Developer

1. Follow this file strictly.
2. Do not skip user confirmation gate between steps.
3. Do not batch multiple screens in one step unless user explicitly asks.
4. If schema changes occur, patch `wipe_and_reset.sql` immediately.
5. Update the Step Tracker table after each step with factual state only.
