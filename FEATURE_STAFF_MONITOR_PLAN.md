# Feature Implementation Plan: Staff Monitor Dashboard

**Feature Goal:** Build a "Staff Monitor" analytics dashboard tracking daily operational actions per staff member, complete with a top-level KPI overview, individual active status tracking, and a detailed chronological slide-over timeline for deep dives into an individual's log.

**Context:** The system already utilizes a `staff_activity_log` table which captures actions like `reservation` creation, `leads` addition, and `payments`. This feature visualizes that data cleanly for admins.

---

## Current Status (What has already been implemented)
The foundational setup for this feature is ALREADY DONE:

1. **Permissions (`settings/staff_permissions.php`)**:
   - `view_staff_monitor` was added to both the PHP (`$allPerms`) array and the javascript (`permLabels`) array.
   - Any AI continuing this should assume the permission `view_staff_monitor` already exists in the system.

2. **Sidebar Navigation (`includes/header.php`)**:
   - `staff_monitor` was added to the `$moduleDirs` array for active link highlighting.
   - A new top-level sidebar link ("Staff Monitor") was added just below the existing "Staff" dropdown.
   - The link points to `{$root}staff_monitor/index.php`.
   - It is wrapped in an access check: `if ($isAdmin || in_array('view_staff_monitor', $cuPerms, true))`

---

## Remaining Tasks (To Be Implemented)

An AI picking this up should execute the following steps to complete the feature:

### 1. Create the Main Dashboard Directory & File
- **Target:** Create `d:\WORK\oRentPHP\staff_monitor\index.php`.
- **Logic / Data Gathering:**
  - Secure the page: `auth_check()`. User must be `admin` or have `view_staff_monitor` permission.
  - Require the standard DB and Header (`includes/header.php`).
  - Read `$_GET['date']` allowing a user to view historical data. If missing, default to today's date (`Y-m-d`).
  - **Global KPIs:** Query `staff_activity_log` filtering by the chosen date to calculate:
    1. **Total Actions:** All rows for that date.
    2. **Active Staff:** Count of distinct `user_id`s who have made at least one action today.
    3. **Lead Actions:** Count of rows where action involves leads (e.g., `add_lead`, `edit_lead`).
    4. **Reservation/Payment Actions:** Count of rows where action involves reservations/payments (e.g., `create_reservation`, `record_payment`).
  - **Staff Cards Data:** 
    - Fetch all active staff users (`SELECT id, name, role FROM users WHERE is_active=1 AND role='staff'`).
    - Using SQL `GROUP BY user_id` on the `staff_activity_log` table limits to the chosen date, count the breakdown of actions for each staff member into custom buckets: "Actions" (System Total), "Leads", "Reservations", "Payments".
    - Determine their 'Last Seen' text by fetching the absolute latest `created_at` timestamp for each user. If within the last 15 minutes, label them "Active Now", otherwise format it like "Last seen 2h ago".

### 2. Build the Main View UI (`staff_monitor/index.php`)
- **Top Bar:** Title "Staff Monitor". Top right corner should have a custom date picker mechanism (e.g. `< April 2, 2026 >`). When toggling the arrows, it reloads `index.php?date=YYYY-MM-DD`.
- **KPI Row:** 4 large white cards layout displaying the Global KPIs calculated above. Use Tailwind (`bg-mb-surface`, `border-mb-subtle/20`).
- **Staff Grid:** Create a grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`.
  - For each active staff member, render a card showing:
    - User Avatar/Initials.
    - Name and Role.
    - The "Last Seen" badge (Green dot if active now, red dot if inactive).
    - 4 distinct colored counter boxes at the bottom mapping to Actions, Leads, Reservations, Payments.
  - Make the card clickable (`onclick="openTimelineModal(userId)"`).

### 3. Build the Detailed Timeline AJAX Handler
- **Target:** Create `d:\WORK\oRentPHP\staff_monitor\ajax_timeline.php`.
- **Purpose:** Serve the detailed chronological breakdown of a staff member's day.
- **Logic:**
  - Standard auth & permission checks.
  - Read `$_GET['user_id']` and `$_GET['date']`.
  - Query `staff_activity_log` grabbing `action`, `description`, `created_at` matching the user and date, ordered descending `ORDER BY created_at DESC`.
  - Output either raw JSON objects, or render raw HTML directly that can be injected into the modal. (HTML is usually easier for our current stack).

### 4. Build the Timeline Slide-Over Modal UI
- **Target:** Add to the bottom of `d:\WORK\oRentPHP\staff_monitor\index.php`.
- **Implementation:**
  - Include an empty hidden div mimicking a right-side drawer (styled similar to iOS drawer or standard slide-over: `fixed inset-y-0 right-0 w-full max-w-sm bg-mb-surface... transform translate-x-full`).
  - The drawer needs a close button.
  - Include javascript mapping the `openTimelineModal(userId)` function to fire a `fetch()` request to `ajax_timeline.php`.
  - On success, populate the drawer with the HTML timeline showing points connected by vertical lines (e.g. "9:40 AM - Created Transaction 3,000" mapping the action description).
  - Use generic recognizable SVG icons on the timeline nodes based on the action keywords (e.g., an Indian Rupee sign or Wallet for payments, a User profile shape for leads/clients).

---

## DB Details for AI Context
- The `staff_activity_log` table structure currently holds: `[id, user_id, action, entity_type, entity_id, description, created_at]`.
- It does **not** currently contain `ip_address`. Do not attempt to query `ip_address` or display it unless instructed to create a migration for it. Timestamp and description are fully sufficient.
- Follow the existing Tailwind `bg-mb-*` namespace layout system standard in the codebase.

---

## Important System Rules for AI execution
Before writing any code or making any system changes for this feature, the AI **MUST** read and strictly follow the instructions in these core project files:
1. `d:\WORK\oRentPHP\SESSION_RULES\SESSION_2026_03_07_RULES.md`
2. `d:\WORK\oRentPHP\UPDATE_SESSION_RULES.md`
3. `d:\WORK\oRentPHP\PRODUCTION_DB_STEPS.md` (Follow this strictly if adding any new DB columns or tables)
