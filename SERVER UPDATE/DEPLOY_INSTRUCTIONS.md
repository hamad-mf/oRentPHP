# 🚀 Server Update Instructions — 2026-02-24

## Step 1: Upload Files
Upload the following files from this folder to your live server (keeping the same folder structure):

| File | Description |
|---|---|
| `includes/header.php` | Side menu updated (hidden items) |
| `vehicles/show.php` | New booking calendar |
| `reservations/create.php` | 12h time picker |
| `reservations/edit.php` | 12h time picker + all rental types |
| `reservations/index.php` | Accurate grand totals |
| `reservations/show.php` | Photo gallery + lightbox + correct totals |
| `reservations/deliver.php` | Photo upload on delivery |
| `reservations/return.php` | Photo upload on return |

> ⚠️ Do NOT overwrite `config/db.php` — that holds your live server credentials.

---

## Step 2: Run the SQL Migration
1. Open **phpMyAdmin** on your live server
2. Select your database (e.g. `orent_db`)
3. Click the **SQL** tab
4. Paste and run the contents of **`server_migration.sql`**

> ✅ The script uses `IF NOT EXISTS` so it's safe to run even if some columns already exist.

---

## Step 3: Create the Uploads Folder
Make sure this directory exists on your server and is writable:
```
uploads/inspections/
```
In your hosting file manager, create the folder if it doesn't exist and set permissions to **755** or **777**.

---

## What's New
- 📸 **Photo proofs** on delivery & return inspections
- 💰 **Accurate grand totals** everywhere (late fees + KM overage + damages + discounts)
- 📅 **Booking calendar** on vehicle details
- 🕐 **12h time pickers** on all reservation forms
- 🧭 **Cleaned up side menu** (Staff, GPS, Expenses etc. hidden for now)
