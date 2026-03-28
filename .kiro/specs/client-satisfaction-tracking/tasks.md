# Implementation Plan: Client Satisfaction Tracking

## Overview

This feature adds optional client satisfaction tracking to the vehicle return process. Staff can record whether the client was satisfied (yes/no) and capture a brief comment when processing returns. The data is stored in the `reservations` table and displayed in the reservation list for completed reservations.

Implementation follows existing oRentPHP patterns: dark theme styling, graceful degradation, idempotent migrations, and optional data capture.

## Tasks

- [x] 1. Create database migration for satisfaction tracking columns
  - Create migration file `migrations/releases/2026-03-28_client_satisfaction_tracking.sql`
  - Add `client_satisfied` ENUM('yes', 'no') NULL column to reservations table
  - Add `client_comment` VARCHAR(255) NULL column to reservations table
  - Use `ADD COLUMN IF NOT EXISTS` pattern for idempotency
  - Follow existing migration file format with header comments
  - _Requirements: 2.1, 2.2, 4.1, 4.2, 4.3, 4.4_

- [x] 2. Update PRODUCTION_DB_STEPS.md with pending migration
  - Add entry for 2026-03-28_client_satisfaction_tracking.sql under "Pending" section
  - Document the two new columns and their purpose
  - _Requirements: 4.5_

- [x] 3. Add satisfaction fields to return form
  - [x] 3.1 Add satisfaction radio buttons to return.php form
    - Insert after client rating hidden inputs (around line 600)
    - Add "Client Satisfied?" label with Yes/No radio buttons
    - Use `name="client_satisfied"` with values "yes" and "no"
    - Style with existing dark theme patterns (mb-surface, mb-accent)
    - Make field optional (no required attribute)
    - _Requirements: 1.1, 1.3, 5.1, 5.2_

  - [x] 3.2 Add comment textarea to return.php form
    - Insert after satisfaction radio buttons
    - Add "Client Comment (Optional)" label
    - Use `name="client_comment"` with maxlength="255"
    - Add character count hint "Maximum 255 characters"
    - Style with existing form input patterns
    - _Requirements: 1.2, 1.3, 5.1, 5.2_

- [x] 4. Update return form POST handler to capture satisfaction data
  - [x] 4.1 Extract satisfaction data from POST in return.php
    - Add `$clientSatisfied` variable extraction (line ~160 area)
    - Add `$clientComment` variable extraction with trim and substr to 255 chars
    - Handle NULL values when fields not submitted
    - _Requirements: 1.4, 1.5, 2.3, 2.4, 2.5_

  - [x] 4.2 Update reservations UPDATE query to include satisfaction columns
    - Modify the UPDATE query in the transaction block (around line 430)
    - Add `client_satisfied=?` and `client_comment=?` to SET clause
    - Add corresponding parameters to execute() array
    - Place after `deposit_held_at` column
    - _Requirements: 1.4, 1.5, 2.3, 2.4_

- [x] 5. Update reservation list query to include satisfaction columns
  - Modify SELECT query in reservations/index.php (around line 50)
  - Add `client_satisfied, client_comment` to the SELECT clause
  - Ensure graceful degradation if columns don't exist yet
  - _Requirements: 3.1_

- [x] 6. Add satisfaction display to reservation list
  - [x] 6.1 Add satisfaction badge rendering in index.php
    - Insert after existing status badge in the Status column (around line 180)
    - Check if `$r['status'] === 'completed'` and `isset($r['client_satisfied'])`
    - Display green checkmark badge for 'yes' with title "Client satisfied"
    - Display red X badge for 'no' with title "Client not satisfied"
    - Use existing badge styling patterns (bg-green-500/10, border-green-500/30)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 5.3, 5.4_

  - [x] 6.2 Add comment display in index.php
    - Insert after satisfaction badge in Status column
    - Check if `$r['status'] === 'completed'` and `!empty($r['client_comment'])`
    - Truncate comment to 50 characters with ellipsis if longer
    - Display full comment in title attribute for tooltip
    - Use subtle gray text styling (text-mb-subtle, text-xs)
    - Add `truncate max-w-xs` classes to prevent overflow
    - _Requirements: 3.5, 3.6, 5.3, 5.5_

- [x] 7. Checkpoint - Test the complete feature flow
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- All satisfaction fields are optional - staff can complete returns without providing this data
- The feature uses graceful degradation - code checks for column existence before querying
- Migration is idempotent and can be run multiple times safely
- Styling follows existing oRentPHP dark theme patterns throughout
- Comment truncation at 50 characters prevents layout issues in the reservation list
- Satisfaction data only displays for completed reservations
