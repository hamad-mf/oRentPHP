# Held Deposit Alert System - Testing Guide

## Overview
This system tracks held deposits and alerts when they exceed a configurable threshold. Includes a test mode for fast testing without waiting days.

## Features Implemented

### 1. Database Tracking
- `deposit_held_at` timestamp: Records when deposit was held
- System settings: `held_deposit_alert_days` (default: 7), `held_deposit_test_mode` (0/1)

### 2. Visual Indicators
- **Reservation List**: Yellow/red badge on completed reservations with held deposits
  - Yellow: Within threshold
  - Red + pulsing: Overdue (exceeded threshold)
- **Dashboard**: Alert section showing all overdue held deposits with details

### 3. Settings Configuration
- Navigate to Settings → General Settings → Held Deposit Alerts section
- Configure alert threshold (days)
- Enable/disable test mode

## Testing Without Waiting

### Method 1: Test Mode (Recommended)
1. Go to Settings → General Settings
2. Scroll to "Held Deposit Alerts" section
3. Check "Test Mode (Hours as Days)"
4. Set threshold to 2 (hours will be treated as days)
5. Save settings
6. Process a return with held deposit
7. Wait 2+ hours
8. Check dashboard and reservation list for alerts

### Method 2: Manual Database Edit (Instant)
1. Process a return with held deposit
2. Open phpMyAdmin
3. Find the reservation in `reservations` table
4. Edit `deposit_held_at` column
5. Set it to a past date (e.g., 10 days ago):
   ```sql
   UPDATE reservations 
   SET deposit_held_at = DATE_SUB(NOW(), INTERVAL 10 DAY)
   WHERE id = [reservation_id];
   ```
6. Refresh dashboard - alert should appear immediately

## Testing Checklist

### Basic Flow
- [ ] Process return with held deposit
- [ ] Verify `deposit_held_at` is set in database
- [ ] Check reservation list shows yellow "🔒 Held" badge
- [ ] Verify badge tooltip shows correct info

### Alert Threshold
- [ ] Enable test mode with threshold = 2
- [ ] Wait 2+ hours (or use Method 2)
- [ ] Dashboard shows "Held Deposits Alert" section
- [ ] Badge on reservation list turns red and pulses
- [ ] Click reservation link from dashboard alert

### Resolution
- [ ] Click "Release to Client" on reservation detail
- [ ] Verify alert disappears from dashboard
- [ ] Verify badge removed from reservation list
- [ ] Check `deposit_held_at` is cleared in database

### Settings
- [ ] Change threshold value
- [ ] Toggle test mode on/off
- [ ] Verify changes persist after save

## Production Deployment

### Before Going Live
1. **Disable test mode** in Settings
2. Set appropriate threshold (recommended: 7 days)
3. Run migration SQL: `migrations/releases/2026-03-24_held_deposit_tracking.sql`

### Migration Steps
```sql
-- Run in phpMyAdmin on production
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS deposit_held_at DATETIME DEFAULT NULL AFTER deposit_held;

INSERT IGNORE INTO system_settings (`key`, `value`) 
VALUES ('held_deposit_alert_days', '7');

INSERT IGNORE INTO system_settings (`key`, `value`) 
VALUES ('held_deposit_test_mode', '0');

SET FOREIGN_KEY_CHECKS = 1;
```

### Post-Deployment Verification
1. Check Settings page loads without errors
2. Process a test return with held deposit
3. Verify timestamp is recorded
4. Use Method 2 to create instant alert for testing
5. Verify dashboard alert appears
6. Resolve the test deposit
7. Disable test mode if enabled

## Troubleshooting

### Alert Not Showing
- Check `deposit_held_at` is set in database
- Verify threshold setting in system_settings
- Check if test mode is enabled when testing
- Ensure reservation status is 'completed'

### Badge Not Appearing
- Verify `deposit_held > 0` in database
- Check reservation status is 'completed'
- Clear browser cache

### Test Mode Not Working
- Verify setting is saved as '1' in system_settings
- Check helper function is using test mode flag
- Try manual database edit method instead

## Files Modified
- `reservations/return.php` - Sets deposit_held_at timestamp
- `reservations/resolve_held_deposit.php` - Clears timestamp on resolution
- `reservations/index.php` - Shows badge on list
- `index.php` - Dashboard alert widget
- `settings/general.php` - Configuration UI
- `includes/reservation_payment_helpers.php` - Helper functions
- `migrations/releases/2026-03-24_held_deposit_tracking.sql` - DB migration
