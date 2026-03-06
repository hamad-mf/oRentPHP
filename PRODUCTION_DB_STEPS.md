# Production DB Migration Steps

This file tracks all database changes that need to be applied to **production** (via phpMyAdmin or CLI) before or alongside each code deploy.

**How to use:**
1. Before deploying new code, check the "Pending" section below.
2. Run each SQL step in order on production phpMyAdmin.
3. After confirmed, move the entry from "Pending" to "Applied".

---

## Pending

### 2026-03-06 — Vehicle Availability & Delivery Tracking
**SQL file:** `migrations/releases/2026-03-06_vehicle_availability.sql`
```sql
ALTER TABLE reservations ADD COLUMN delivered_at DATETIME DEFAULT NULL;
```
**Notes:** Adds delivered_at column to track exact delivery time. Used by Vehicle Availability page to distinguish reserved vs delivered vehicles.

### 2026-03-06 — GPS Delivery Location
**SQL file:** `migrations/releases/2026-03-06_delivery_location.sql`
```sql
ALTER TABLE reservations ADD COLUMN delivery_location VARCHAR(255) DEFAULT NULL;
```
**Notes:** Adds a field to store where the vehicle was delivered. Used by GPS tracking page and delivery form.

---

## Applied

| Date | Release ID | SQL File | Notes |
|------|------------|----------|-------|
| *(none yet)* | | | |
