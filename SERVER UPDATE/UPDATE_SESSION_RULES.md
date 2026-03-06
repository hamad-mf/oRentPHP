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
Use this with any AI before starting:

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

