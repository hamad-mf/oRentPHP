# Release SQL Folder

Store production-safe SQL files here.

Naming:
- `YYYY-MM-DD_short_name.sql`

Example:
- `2026-03-05_timezone_consistency_fix.sql`

Rules:
1. Do not include destructive wipe/reset commands.
2. Prefer idempotent statements.
3. Add a short header comment with release ID and purpose.
