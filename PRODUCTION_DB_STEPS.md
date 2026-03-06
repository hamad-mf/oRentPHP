# Production DB Migration Steps

This file tracks all database changes that need to be applied to **production** (via phpMyAdmin or CLI) before or alongside each code deploy.

**How to use:**
1. Before deploying new code, check the "Pending" section below.
2. Run each SQL step in order on production phpMyAdmin.
3. After confirmed, move the entry from "Pending" to "Applied".

---

## Pending

### 2026-03-06 - Vehicle Availability & Delivery Tracking
**SQL file:** `migrations/releases/2026-03-06_vehicle_availability.sql`
```sql
ALTER TABLE reservations ADD COLUMN delivered_at DATETIME DEFAULT NULL;
```
**Notes:** Adds `delivered_at` to track exact delivery time. Used by Vehicle Availability page to distinguish reserved vs delivered vehicles.

### 2026-03-06 - GPS Delivery Location
**SQL file:** `migrations/releases/2026-03-06_delivery_location.sql`
```sql
ALTER TABLE reservations ADD COLUMN delivery_location VARCHAR(255) DEFAULT NULL;
```
**Notes:** Adds a field to store where the vehicle was delivered. Used by GPS tracking page and delivery form.

### 2026-03-06 - Payroll Staff Advances
**SQL file:** `migrations/releases/2026-03-06_payroll_staff_advances.sql`
```sql
-- Creates payroll_advances table and adds payroll.advance_deducted + payroll.payable_salary
-- Uses guarded ALTER logic via information_schema, so it is safe to re-run.
```
**Notes:** Enables staff advance tracking and payroll-time deduction with clear net/advance/payable breakdown.

### 2026-03-06 - Lead Auto-Close After Follow-ups (No DB change needed)
**SQL file:** `None`
**Notes:** Auto-close lead to Lost after X follow-ups. Uses existing `system_settings` table. Configure in Settings > General.

---

## Applied

| Date | Release ID | SQL File | Notes |
|------|------------|----------|-------|
| 2026-03-06 | vehicle_collation_hotfix | `migrations/releases/2026-03-06_vehicle_collation_hotfix.sql` | Applied manually in production phpMyAdmin to align `vehicles`, `vehicle_images`, and `vehicle_requests` collations to `utf8mb4_unicode_ci`. Safe to rerun if needed. |
