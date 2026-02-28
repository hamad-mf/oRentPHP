CRM Lead & Pipeline System — Implementation Plan
Background
The O Rent CRM is a pure PHP + MySQL application located at d:\WORK\oRentPHP. It uses:

Backend: PHP 8+, PDO (no framework)
Frontend: Tailwind CSS (CDN), vanilla JS, Outfit font
DB Helper: config/db.php — provides db() function that returns a PDO instance
Helpers: e() for HTML escaping, flash() / getFlash() for flash messages, redirect() for redirects
Layout: Every page includes includes/header.php at top and includes/footer.php at bottom
Style pattern: Dark UI — bg-mb-black, bg-mb-surface, text-mb-accent (#00adef), text-mb-silver etc.
All existing patterns (clients, reservations) should be followed exactly for consistency.

Goal
Build a complete CRM Lead Management + Pipeline system under /leads/ that allows staff to:

Capture new leads (potential customers)
Track them visually through a Kanban Pipeline
Schedule and manage follow-up tasks
Log activity notes per lead
Convert a won lead to a Client + Reservation
Database Schema
Run these SQL statements on the live and local databases.

sql
-- Table 1: leads
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    inquiry_type ENUM('daily', 'weekly', 'monthly', 'other') DEFAULT 'daily',
    vehicle_interest VARCHAR(255) DEFAULT NULL COMMENT 'e.g. SUV, Sedan, specific model',
    source ENUM('walk_in', 'phone', 'whatsapp', 'instagram', 'referral', 'website', 'other') DEFAULT 'phone',
    status ENUM('new', 'contacted', 'interested', 'negotiation', 'closed_won', 'closed_lost') DEFAULT 'new',
    lost_reason TEXT DEFAULT NULL COMMENT 'Required when status = closed_lost',
    assigned_to VARCHAR(100) DEFAULT NULL COMMENT 'Staff member name or ID',
    notes TEXT DEFAULT NULL,
    converted_client_id INT DEFAULT NULL COMMENT 'FK to clients.id after conversion',
    converted_reservation_id INT DEFAULT NULL COMMENT 'FK to reservations.id after conversion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;
-- Table 2: lead_followups
CREATE TABLE IF NOT EXISTS lead_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    type ENUM('call', 'meeting', 'email', 'whatsapp') NOT NULL DEFAULT 'call',
    scheduled_at DATETIME NOT NULL COMMENT 'When the follow-up should happen',
    notes TEXT DEFAULT NULL COMMENT 'What to discuss / objective',
    is_done TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Table 3: lead_activities (activity log / notes)
CREATE TABLE IF NOT EXISTS lead_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;
File Structure to Create
leads/
├── index.php         — Lead list table with filters
├── create.php        — Add new lead form
├── edit.php          — Edit lead details + status
├── show.php          — Lead detail page (followups, activities, convert button)
├── delete.php        — Delete lead (POST, then redirect)
├── pipeline.php      — Visual Kanban board
├── convert.php       — Convert won lead to client+reservation (POST handler)
├── followup_save.php — Save/update a followup (POST handler)
├── followup_done.php — Mark followup as done (POST handler)
├── activity_save.php — Save an activity note (POST handler)
Feature-by-Feature Specification
1. leads/index.php — Lead List
Purpose: Show all leads in a table with status badges and quick filters.

PHP Logic:

Query: SELECT * FROM leads ORDER BY created_at DESC
Support GET filters: ?status=new, ?source=whatsapp, ?search=name_or_phone
Build WHERE clause dynamically (same pattern as reservations/index.php)
UI Layout:

Page title: "Leads"
Top bar: "Add Lead" button (→ create.php) + filter dropdowns (Status, Source) + search input
Table columns: # | Name | Phone | Inquiry | Source | Status | Created | Actions
Status badge colors:
new → blue (bg-sky-500/10 text-sky-400 border-sky-500/30)
contacted → yellow (bg-yellow-500/10 text-yellow-400 border-yellow-500/30)
interested → purple (bg-purple-500/10 text-purple-400 border-purple-500/30)
negotiation → orange (bg-orange-500/10 text-orange-400 border-orange-500/30)
closed_won → green (bg-green-500/10 text-green-400 border-green-500/30)
closed_lost → red/muted (bg-red-500/10 text-red-400/60 border-red-500/20)
Action buttons per row: View → show.php?id=X | Edit → edit.php?id=X
Empty state: centered placeholder text "No leads yet. Add your first lead."
2. leads/create.php — Add New Lead
Purpose: Form to capture a new potential customer.

PHP Logic (POST):

Validate: name required, phone required, email optional (validate format if given)
Insert into leads table
Auto-add an activity: INSERT INTO lead_activities (lead_id, note) VALUES (?, 'Lead created.')
flash('success', 'Lead added.') then redirect("show.php?id=$newId")
Form Fields:

Field	Type	Required	Notes
Name	text	✅	Full name
Phone	text	✅	WhatsApp/mobile
Email	email	❌	Optional
Inquiry Type	select	✅	daily/weekly/monthly/other
Vehicle Interest	text	❌	e.g., "SUV", "Toyota Camry"
Source	select	✅	walk_in/phone/whatsapp/instagram/referral/website/other
Assigned To	text	❌	Staff name
Notes	textarea	❌	Initial notes
UI: Same two-column form style as clients/create.php. Max width max-w-2xl.

3. leads/edit.php — Edit Lead
Purpose: Update any field. Crucially, also lets staff change the status.

PHP Logic:

GET: fetch lead by ?id=
POST: validate same as create, then UPDATE
Special rule: If status changed to closed_lost, require lost_reason field (show validation error if empty)
Special rule: If status changed to closed_won, show a prompt/info banner: "Ready to convert? Go to the lead page and click Convert."
Log activity on save: "Lead updated. Status changed to: {new_status}"
Form Fields: Same as create + status dropdown + lost_reason textarea (shown only when closed_lost is selected, via JavaScript show/hide).

JS snippet for lost_reason toggle:

javascript
document.getElementById('status').addEventListener('change', function() {
    document.getElementById('lost-reason-wrap').style.display = 
        this.value === 'closed_lost' ? 'block' : 'none';
});
4. leads/show.php — Lead Detail Page
Purpose: Full lead profile — info, follow-ups, activity log, convert button.

PHP Logic:

Fetch lead by ?id=
Fetch followups: SELECT * FROM lead_followups WHERE lead_id=? ORDER BY scheduled_at ASC
Fetch activities: SELECT * FROM lead_activities WHERE lead_id=? ORDER BY created_at DESC
Determine if overdue followups exist: scheduled_at < NOW() AND is_done = 0
Page Layout (3 sections):

Section 1 — Lead Info Card (top, full width)
Shows: Name, Phone, Email, Source, Inquiry Type, Vehicle Interest, Status badge, Assigned To, Notes
Action buttons: Edit Lead → edit.php?id=X | Delete (confirm dialog) → delete.php
If status = closed_won AND converted_client_id is not null: show green "✅ Converted — View Client" button
If status = closed_won AND not yet converted: show blue "🔄 Convert to Client & Reservation" button (→ convert.php)
Section 2 — Follow-ups Panel (left column)
List of all followups, sorted by scheduled_at
Each card shows: type icon (📞 call, 📧 email, 💬 whatsapp, 🤝 meeting), date/time, notes, done/overdue badge
Overdue (not done + past scheduled time) = red pulsing badge "Overdue"
Done = muted with strikethrough
Mark Done button → POST to followup_done.php
Add Follow-up Form (inline at bottom of section):
Fields: Type (select), Date (date input), Time (time input), Notes (textarea)
Submits POST to followup_save.php
Section 3 — Activity Log (right column)
Reverse-chronological list of all activities
Each entry: timestamp + note text (in a timeline style with a left border line)
Log Activity inline form at the top: just a textarea + "Save Note" button → POST to activity_save.php
5. leads/pipeline.php — Kanban Board
Purpose: Visual horizontal Kanban board showing all active leads grouped by status stage.

PHP Logic:

Fetch all leads where status NOT IN ('closed_won', 'closed_lost'): group by status
Also fetch leads for closed columns (optional, toggled by ?show_closed=1)
For each lead card, count overdue followups
Stages (columns, in order):

New
Contacted
Interested
Negotiation
Closed Won (optional, toggle)
Closed Lost (optional, toggle)
UI:

Horizontal scrolling flex container: <div class="flex gap-4 overflow-x-auto pb-4">
Each column: min-w-[280px] w-72 bg-mb-surface rounded-xl p-4
Column header: Stage name + count badge
Lead card (bg-mb-black/40 rounded-lg p-3 mb-2 cursor-pointer):
Name (bold white)
Phone (small, muted)
Inquiry type + Source badges (tiny pills)
⚠️ Red dot if has overdue followup
Click → show.php?id=X
Quick status update: Each card has a small "→" dropdown to move to next stage (submits POST to edit.php with just the status field change — use AJAX fetch() so page doesn't reload)
AJAX Stage Move (JS):

javascript
async function moveLeadStage(leadId, newStatus) {
    const body = new FormData();
    body.append('id', leadId);
    body.append('status', newStatus);
    body.append('quick_update', '1');
    await fetch('edit.php', { method: 'POST', body });
    location.reload();
}
edit.php should detect $_POST['quick_update'] === '1' and only update status + log activity, then return early (no redirect, just exit).

6. leads/delete.php — Delete Lead
PHP Logic (POST only):

php
$id = (int)($_POST['id'] ?? 0);
$pdo->prepare('DELETE FROM leads WHERE id=?')->execute([$id]);
flash('success', 'Lead deleted.');
redirect('index.php');
No GET handler. The delete button in show.php uses a form with method=POST.
7. leads/convert.php — Convert Lead to Client + Reservation
Purpose: When a lead is closed_won, create a Client record and optionally start a Reservation.

PHP Logic (POST):

Fetch lead
Check converted_client_id is null (prevent double conversion)
Insert into clients: name, phone, email from lead
Get $clientId = lastInsertId()
Update lead: converted_client_id = $clientId, status = 'closed_won'
Log activity: "Lead converted to client #X"
Redirect to ../clients/show.php?id=$clientId with flash success
The staff then manually creates a reservation from the client page (existing flow)
8. leads/followup_save.php — Save Follow-up
POST handler only:

php
$leadId  = (int)$_POST['lead_id'];
$type    = $_POST['type'];  // call/email/whatsapp/meeting
$dateStr = $_POST['date'];  // Y-m-d
$timeStr = $_POST['time'];  // H:i
$notes   = trim($_POST['notes'] ?? '');
$scheduledAt = $dateStr . ' ' . $timeStr . ':00';
$pdo->prepare('INSERT INTO lead_followups (lead_id, type, scheduled_at, notes) VALUES (?,?,?,?)')
    ->execute([$leadId, $type, $scheduledAt, $notes]);
// Log activity
$pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')
    ->execute([$leadId, "Follow-up scheduled: $type on $scheduledAt"]);
redirect("show.php?id=$leadId");
9. leads/followup_done.php — Mark Follow-up as Done
POST handler only:

php
$fid    = (int)$_POST['followup_id'];
$leadId = (int)$_POST['lead_id'];
$pdo->prepare('UPDATE lead_followups SET is_done=1 WHERE id=?')->execute([$fid]);
$pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')
    ->execute([$leadId, 'Follow-up marked as done.']);
redirect("show.php?id=$leadId");
10. leads/activity_save.php — Log Activity Note
POST handler only:

php
$leadId = (int)$_POST['lead_id'];
$note   = trim($_POST['note'] ?? '');
if ($note) {
    $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')
        ->execute([$leadId, $note]);
}
redirect("show.php?id=$leadId");
Sidebar Menu Update
In includes/header.php, add the Leads and Pipeline nav links. Find the existing /* Temporarily hidden block and add above it:

php
echo navLink("{$root}leads/index.php", 'Leads', $icons['leads'], $currentDir === 'leads' && $currentPage === 'index.php');
echo navLink("{$root}leads/pipeline.php", 'Pipeline', $icons['pipeline'], $currentDir === 'leads' && $currentPage === 'pipeline.php');
Add these icons to the $icons array:

php
'leads' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
'pipeline' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2z"/></svg>',
Also update the navLink active check for dashboard to exclude leads.

Dashboard Integration
In index.php (dashboard), add two new stat cards:

Total Leads — SELECT COUNT(*) FROM leads WHERE status NOT IN ('closed_won','closed_lost')
Overdue Follow-ups — Count of followups where scheduled_at < NOW() AND is_done = 0, displayed in red if > 0
Verification Plan
Manual Testing Checklist
Create a new lead → verify it appears in index and pipeline under "New"
Edit lead status to "contacted" → verify pipeline card moves column, activity logged
Add a follow-up (past date) → verify it shows as "Overdue" in red on show.php
Mark follow-up as done → verify it grays out, activity logged
Log an activity note → verify it appears in timeline
Change status to closed_lost without lost_reason → verify validation error
Change status to closed_won → click Convert → verify client is created, lead updated
Dashboard → verify Lead and Overdue Follow-up counts update
Automated
After each file is created, visit the page in browser and check for PHP errors (500)
Run the SQL schema and verify tables exist with correct columns