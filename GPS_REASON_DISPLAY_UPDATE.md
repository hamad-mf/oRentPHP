# GPS Tracking - Display Reason on Card

## Summary
Updated the GPS tracking screen to display the "Not at Location" reason directly on the collapsed card without needing to expand it.

## Changes Made

### Database Migration
**NO DATABASE MIGRATION NEEDED** ✅
- The `notes` field already exists in the `gps_daily_checks` table
- No schema changes required

### Files Modified

#### 1. `gps/index.php`
**Line ~650-660** - Added reason display in the "Checks Today" column

**What changed:**
- When the latest check shows "Not at Location" (tracking_active = 0)
- AND there's a reason in the notes field
- The reason now displays directly on the card in red italic text

**Code added:**
```php
<?php if ($latestStatus === 0 && !empty($latestCheck['notes'])): ?>
    <span class="text-[11px] text-red-400 italic mt-0.5">Reason: <?= e($latestCheck['notes']) ?></span>
<?php endif; ?>
```

#### 2. `SERVER UPDATE/gps/index.php`
**Same changes applied** - Production file updated with identical modification

## Visual Changes

### Before:
```
Checks Today
2/3 checks
Last: No
```

### After:
```
Checks Today
2/3 checks
Last: No
Reason: Vehicle taken for maintenance
```

## Production Deployment

### Files to Update on Production Server:
1. `gps/index.php`

### Steps:
1. Copy the updated `gps/index.php` file to production
2. No database migration needed
3. Clear any PHP cache if applicable
4. Test by viewing a reservation with "Not at Location" status

## Testing Checklist
- [ ] View GPS tracking screen
- [ ] Find a reservation marked as "Not at Location"
- [ ] Verify reason displays on collapsed card in red italic text
- [ ] Verify reason still shows in expanded details
- [ ] Verify "At Location" cards don't show reason
- [ ] Verify cards without checks show "Not checked today"

## Notes
- The reason only displays when `tracking_active = 0` (Not at Location)
- The reason text is displayed in red italic font for visibility
- The existing expanded view still shows all details including the reason
- No breaking changes - fully backward compatible
