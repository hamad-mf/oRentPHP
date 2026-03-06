# Mobile UI Implementation Plan (No Logic / No DB Changes)

## Objective
Make the existing UI mobile-responsive and user-friendly in controlled, testable batches without changing business logic or database schema.

## Rules
- No business-logic changes.
- No DB/schema changes.
- Follow `UPDATE_SESSION_RULES.md` and `PRODUCTION_DB_STEPS.md`.
- Small batches only.
- After each batch: user tests on phone, confirms, then mark as `Verified`.

## Batch Tracker
| Batch | Scope | Status | User Test | Verified By |
|---|---|---|---|---|
| 1 | Global shell (header/sidebar/content spacing) | Implemented (Awaiting Full Test) | Pending | Pending |
| 2 | Shared UI patterns (forms/cards/buttons/modals) | Implemented (Awaiting Full Test) | Pending | Pending |
| 3 | Tables mobile behavior (scroll/sticky/overflow) | Implemented (Awaiting Full Test) | Pending | Pending |
| 4A | Leads pages responsiveness | Implemented (Awaiting Full Test) | Pending | Pending |
| 4B | Payroll pages responsiveness | Implemented (Awaiting Full Test) | Pending | Pending |
| 4C | Vehicles pages responsiveness | Implemented (Awaiting Full Test) | Pending | Pending |
| 4D | Reservations pages responsiveness | Implemented (Awaiting Full Test) | Pending | Pending |
| 4E | Accounts + Staff + Settings responsiveness | Implemented (Awaiting Full Test) | Pending | Pending |
| 5 | Final polish + consistency pass | Implemented (Awaiting Full Test) | Pending | Pending |

## Detailed Tasks

### Batch 1 - Global Shell (Smallest first)
- Make top header compress correctly on <= 768px.
- Implement mobile sidebar/drawer toggle.
- Ensure page content does not clip and has proper mobile padding.
- Fix global overflow/scroll conflicts for mobile viewport.
- Keep desktop layout unchanged.

Acceptance checks:
- Login, dashboard, and at least one internal page open cleanly on phone portrait.
- No horizontal page-level scroll caused by shell.
- Sidebar is usable on mobile.

### Batch 2 - Shared UI Patterns
- Normalize responsive utility classes used by cards/forms/buttons.
- Ensure modal width/height are usable on mobile.
- Fix touch target sizes for primary actions.
- Improve spacing rhythm for readability.

Acceptance checks:
- Forms are readable/usable with keyboard open.
- Modals are fully usable without clipping.

### Batch 3 - Tables
- Standard approach: horizontal scroll wrapper + minimum column widths.
- Keep actions visible and avoid broken wrapping.
- Ensure status badges and key cells do not split awkwardly.

Acceptance checks:
- Data tables usable on mobile with predictable scroll.
- No text overlap or broken row height.

### Batch 4 - Module Passes
- 4A Leads
- 4B Payroll
- 4C Vehicles
- 4D Reservations
- 4E Accounts/Staff/Settings

For each module:
- Apply only responsive UI fixes.
- Keep behavior and logic intact.
- Run quick smoke test.
- Ask user to test and confirm before marking verified.

### Batch 5 - Final Polish
- Cross-module spacing consistency.
- Final mobile touch pass for top 5 frequently used screens.
- Regression scan for desktop.

## Verification Workflow
1. Mark batch `In Progress`.
2. Implement only that batch scope.
3. Run local syntax/smoke checks.
4. Send user exact screens to test on phone.
5. On user confirmation, mark batch `Verified` and move next.

## Notes for Handoff
- This file is the single source for UI rollout progress.
- Any new contributor must continue from the first non-verified batch.
