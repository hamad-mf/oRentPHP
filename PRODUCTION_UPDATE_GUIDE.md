# Production Update Guide - Nearby Delivery Alerts

## Quick Reference

### Files to Update in Production

**Only 2 files need to be updated:**

1. ✅ **index.php** (root directory)
2. ✅ **settings/general.php** (settings/ directory)

### No Database Changes Required
- ❌ No SQL migrations
- ❌ No schema changes
- ❌ No new tables
- ✅ Uses existing tables only

---

## Step-by-Step Update Process

### Step 1: Backup (Optional but Recommended)
```bash
# Backup the 2 files you're about to replace
cp index.php index.php.backup
cp settings/general.php settings/general.php.backup
```

### Step 2: Update Files
Replace these files in production with the new versions:
- `index.php`
- `settings/general.php`

### Step 3: Configure Settings
1. Login to production as admin
2. Go to **Settings > General**
3. Scroll to **Delivery Settings** section
4. Find **"Upcoming Delivery Alert Threshold (Days)"**
5. Set your preferred threshold (default: 3 days, range: 1-30)
6. Click **Save Settings**

### Step 4: Verify
1. Go to **Dashboard**
2. Look for **"🚗 Upcoming Deliveries"** section
3. If you have confirmed reservations with delivery dates within your threshold, they will appear
4. Click an alert to verify navigation works

---

## What Changed in Each File

### index.php
**Added 3 things:**
1. `get_upcoming_delivery_alerts()` function (queries database)
2. `calculate_delivery_urgency()` function (determines badge colors)
3. Alert section HTML (displays between EMI alerts and Daily Operations)

**Location in file**: After EMI alerts section, before "Daily Operations" heading

### settings/general.php
**Added 2 things:**
1. Input field for threshold configuration (in Delivery Settings section)
2. POST handler to save the setting (in form submission handler)

**Location in file**: 
- Input field: After "Default Return Pickup Charge" field
- POST handler: In the `if ($_SERVER['REQUEST_METHOD'] === 'POST')` block

---

## Testing After Update

### Quick Test (2 minutes)
1. ✅ Dashboard loads without errors
2. ✅ Settings page loads without errors
3. ✅ Can save threshold setting
4. ✅ Alert section appears (if deliveries exist) or hidden (if none)

### Full Test (5 minutes)
1. Create test reservation:
   - Status: `confirmed`
   - Start date: Tomorrow
2. Refresh dashboard
3. Verify alert appears with "Tomorrow" badge
4. Click alert → should go to reservation details
5. Delete test reservation

---

## Troubleshooting

### "Alert section not showing"
**Cause**: No confirmed reservations within threshold
**Solution**: Normal behavior - section only shows when deliveries exist

### "Settings field not appearing"
**Cause**: File not updated correctly
**Solution**: Re-upload `settings/general.php`

### "Dashboard shows error"
**Cause**: PHP syntax error or database connection issue
**Solution**: Check PHP error logs, verify file upload was complete

### "Unknown Client" or "Unknown Vehicle" in alerts
**Cause**: Missing foreign key data
**Solution**: Normal behavior - graceful degradation for missing data

---

## Rollback Instructions

If you need to revert:

```bash
# Restore from backup
cp index.php.backup index.php
cp settings/general.php.backup settings/general.php
```

Or manually remove the added code sections (see NEARBY_DELIVERY_ALERTS_IMPLEMENTATION.md for details).

---

## Configuration Options

### Threshold Setting
- **Minimum**: 1 day
- **Maximum**: 30 days
- **Default**: 3 days
- **Recommended**: 2-5 days (depending on your preparation time)

### Alert Display
- **Maximum alerts shown**: 10 (most urgent first)
- **Sorting**: By delivery date (nearest first)
- **Status filter**: Only `confirmed` reservations

---

## Support

**Questions?** Refer to:
- `NEARBY_DELIVERY_ALERTS_IMPLEMENTATION.md` - Full technical details
- `.kiro/specs/nearby-delivery-alerts/` - Complete specification

**Need help?** Check:
- PHP error logs for any errors
- Browser console for JavaScript errors (none expected)
- Database connection status

---

**Last Updated**: April 4, 2026
**Version**: 1.0
**Status**: ✅ Ready for Production
