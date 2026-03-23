# 🚀 Deployment Instructions — oRent Production Update

> **Server:** https://orentin.abrarfuturetech.com  
> **Prepared:** 2026-02-27

---

## Step 1 — Run the Database Migration (FIRST!)

1. Open **phpMyAdmin** on Hostinger
2. Select your production database
3. Go to the **SQL** tab
4. Open the file `full_server_migration.sql` from this folder and paste the entire contents
5. Click **Go** / Execute

✅ The script is **safe to run multiple times** — it uses `CREATE TABLE IF NOT EXISTS` and checks for column existence before `ALTER TABLE`. It will only add what's missing.

---

## Step 2 — Upload PHP Files

Upload **everything in this folder EXCEPT:**
- ❌ `config/db.php` — keep the server's existing DB credentials
- ❌ `*.sql` files — already handled in Step 1
- ❌ `*.md` files — documentation only
- ❌ `Archive.zip` — not needed

**What IS new and must be uploaded:**
| New Directory / File | Purpose |
|---|---|
| `auth/login.php` | Login page |
| `auth/logout.php` | Logout handler |
| `staff/` (all files) | Staff management module |
| `settings/staff_permissions.php` | Permissions manager |
| `vehicles/catalog.php` | Public shareable vehicle catalog |
| `includes/activity_log.php` | Staff activity logging helper |
| `includes/reservation_payment_helpers.php` | Payment calculation helpers |

---

## Step 3 — Verify config/db.php on Server

Make sure the server's `config/db.php` has the correct Hostinger DB credentials (do NOT overwrite with local version).

---

## Step 4 — First Login After Deploy

- Go to: `https://orentin.abrarfuturetech.com/auth/login.php`
- Default credentials: **admin** / **admin123**
- ⚠️ **Change the admin password immediately after first login!**

---

## Step 5 — Test Key Features

- [ ] Login works
- [ ] Dashboard loads
- [ ] Leads pipeline with staff/date filters
- [ ] Vehicle catalog: `https://orentin.abrarfuturetech.com/vehicles/catalog.php`
- [ ] Share Catalog button on vehicles page
- [ ] Staff management (create/edit/delete staff)
- [ ] Staff permissions settable from Settings
- [ ] Reservations: create, deliver, return (with permission guards)

---

## Notes

- The **catalog share link** auto-detects the server hostname — it will automatically show `https://orentin.abrarfuturetech.com/vehicles/catalog.php` on the live server. No code change needed.
- The `uploads/` folder should NOT be replaced — it contains existing files on the server.
