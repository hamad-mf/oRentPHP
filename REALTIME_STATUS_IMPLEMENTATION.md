# Real-Time Online/Offline Status Implementation

## Overview
Implemented session-based real-time online/offline status tracking for the Staff Monitor Dashboard.

## Status Logic

### For Admin:
- 🟢 **Online**: Logged in (has active session)
- 🔴 **Offline**: Logged out

### For Staff:
- 🟢 **Online**: Logged in AND Punched in (not punched out)
- 🔴 **Offline**: 
  - Logged out, OR
  - Not punched in today, OR
  - Punched out (even if still logged in)

## Implementation Details

### Database Changes
**File**: `migrations/releases/2026-04-09_user_session_tracking.sql`

Added columns to `users` table:
- `is_online` TINYINT(1) - Whether user is currently logged in (1 = online, 0 = offline)
- `last_login_at` DATETIME - Timestamp of last login
- `last_logout_at` DATETIME - Timestamp of last logout
- `session_id` VARCHAR(255) - Current PHP session ID for tracking
- Index on `is_online` for performance

### Code Changes

#### 1. Login Tracking (`auth/login.php`)
- On successful login, updates:
  - `is_online = 1`
  - `last_login_at = NOW()`
  - `session_id = current session ID`

#### 2. Logout Tracking (`auth/logout.php`)
- On logout, updates:
  - `is_online = 0`
  - `last_logout_at = NOW()`
  - `session_id = NULL`

#### 3. Punch Out Tracking (`attendance/punch.php`)
- When staff punches out:
  - Sets `is_online = 0` immediately
  - Staff shows as offline even if they're still logged into the system

#### 4. Staff Monitor Dashboard (`staff_monitor/index.php`)
- Replaced `getActiveStatus()` with `getOnlineStatus()`
- New function checks:
  - User's `is_online` flag
  - User's role (admin vs staff)
  - Today's attendance record (punch in/out status)
- Updated SQL query to join `staff_attendance` table
- Updated UI to show green (online) or gray (offline) status

## Key Features

### Session-Based (No Heartbeat Required)
- No JavaScript polling needed
- No periodic AJAX calls
- Simple and reliable
- Low server overhead

### Attendance Integration
- Staff must be punched in to show as online
- Punching out immediately shows offline
- Respects attendance workflow

### Admin vs Staff Distinction
- Admins don't use attendance system
- Admins only need to be logged in to show online
- Staff need both login AND punch-in

## Testing

### Test Scenarios

1. **Admin Login/Logout**
   - Login → Should show "Online" (green)
   - Logout → Should show "Offline" (gray)

2. **Staff Login (Not Punched In)**
   - Login → Should show "Not punched in" (gray)

3. **Staff Punch In**
   - After punch in → Should show "Online" (green)

4. **Staff Punch Out**
   - After punch out → Should show "Punched out" (gray)
   - Even if still logged in

5. **Staff Logout**
   - After logout → Should show "Logged out" (gray)

## Production Deployment

### Step 1: Run Migration
```sql
-- Run in phpMyAdmin on production database
-- File: migrations/releases/2026-04-09_user_session_tracking.sql
```

### Step 2: Deploy Code Files
Update these files on production:
1. `auth/login.php`
2. `auth/logout.php`
3. `attendance/punch.php`
4. `staff_monitor/index.php`

### Step 3: Verify
1. Login as admin → Check staff monitor → Should see "Offline" for all users initially
2. Login as staff → Punch in → Refresh staff monitor → Should see "Online"
3. Punch out → Refresh staff monitor → Should see "Punched out"

## Limitations

### Session Expiry
- If a user closes their browser without logging out, they'll still show as "online" until:
  - Their PHP session expires (typically 30 minutes to 2 hours)
  - They explicitly logout
  - For staff: They punch out

### No Real-Time Updates
- Dashboard doesn't auto-refresh
- Admin must manually refresh page to see latest status
- Could add auto-refresh with JavaScript if needed (every 30 seconds)

## Future Enhancements (Optional)

### Auto-Refresh Dashboard
Add JavaScript to refresh every 30 seconds:
```javascript
setInterval(() => location.reload(), 30000);
```

### Session Cleanup
Add cron job to mark users offline if session expired:
```php
// Clean up stale sessions (sessions older than 2 hours)
UPDATE users 
SET is_online = 0 
WHERE is_online = 1 
AND last_login_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
```

### Multi-Device Handling
Currently tracks only one session per user. Could enhance to:
- Track multiple sessions per user
- Show "Online (2 devices)" etc.

## Backward Compatibility

### Existing Functionality Preserved
- All existing staff monitor features still work
- Activity log tracking unchanged
- Action counts still calculated
- Timeline modal still functional

### Graceful Degradation
- If migration not run, code handles missing columns gracefully
- Try-catch blocks prevent errors from blocking login/logout
- Logs errors but doesn't break user experience

## Documentation Updated

1. `PRODUCTION_DB_STEPS.md` - Added migration entry
2. `SESSION_RULES/SESSION_2026_03_07_RULES.md` - Added change log entry
3. This file - Complete implementation documentation

---

**Implementation Date**: April 9, 2026  
**Status**: ✅ Complete  
**Breaking Changes**: None  
**Database Changes**: Yes (migration required)
