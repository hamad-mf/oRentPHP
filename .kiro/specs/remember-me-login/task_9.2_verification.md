# Task 9.2 Verification: Password Change Token Invalidation

## Implementation Summary

Added password change token invalidation to ensure all remember tokens are deleted when a user's password is changed, as required by Requirement 14.4.

## Changes Made

### 1. staff/edit.php (Line 101-110)
**Location**: Password update section in the POST handler

**Change**: Added token invalidation after password hash update
```php
if ($newPassword) {
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $uu = $pdo->prepare("UPDATE users SET name=?, username=?, password_hash=?, role=?, is_active=? WHERE id=?");
    $uu->execute([$name, $username, $hash, $userRole, $isActive, $userId]);
    
    // Invalidate all remember tokens when password changes (security requirement)
    delete_all_user_tokens($userId);
    app_log('AUTH', "All remember tokens invalidated due to password change for user {$userId}");
}
```

**Rationale**: When an admin changes a staff member's password through the staff edit page, all remember tokens for that user should be invalidated to force re-authentication on all devices.

### 2. reset_admin.php (Line 27-38)
**Location**: Admin password reset section

**Change**: Added token invalidation after admin password reset
```php
if ($exists) {
    // Get admin user ID before updating
    $adminUserId = (int) $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
    
    $pdo->prepare("UPDATE users SET password_hash=?, role='admin', is_active=1 WHERE username='admin'")->execute([$hash]);
    
    // Invalidate all remember tokens when password changes (security requirement)
    if ($adminUserId) {
        delete_all_user_tokens($adminUserId);
        app_log('AUTH', "All remember tokens invalidated due to admin password reset for user {$adminUserId}");
    }
    
    $msg = '✅ Admin password reset.';
}
```

**Rationale**: When the admin password is reset using the reset_admin.php utility, all remember tokens for the admin user should be invalidated for security.

## Security Considerations

1. **Multi-Device Security**: When a password is changed, all devices with active remember tokens are forced to re-authenticate
2. **Logging**: All token invalidations are logged with the AUTH category for audit trail
3. **Graceful Handling**: The `delete_all_user_tokens()` function handles errors gracefully and logs failures

## Testing Checklist

### Manual Testing
- [ ] Change a staff member's password via staff/edit.php
- [ ] Verify all remember tokens for that user are deleted from the database
- [ ] Verify the token invalidation is logged in the application logs
- [ ] Reset admin password via reset_admin.php
- [ ] Verify all admin remember tokens are deleted
- [ ] Verify the admin token invalidation is logged

### Database Verification
```sql
-- Before password change
SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?;

-- After password change (should be 0)
SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?;
```

### Log Verification
Check application logs for entries like:
```
[AUTH] All remember tokens invalidated due to password change for user {user_id}
[AUTH] All remember tokens deleted for user {user_id} (count: X)
```

## Requirements Satisfied

✅ **Requirement 14.4**: "WHEN a user changes their password, THE Remember_Me_System SHALL delete all remember tokens for that user"

## Files Modified

1. `staff/edit.php` - Added token invalidation after password update
2. `reset_admin.php` - Added token invalidation after admin password reset

## Dependencies

- `includes/remember_token_helpers.php` - Provides `delete_all_user_tokens()` function
- `config/db.php` - Includes remember_token_helpers.php (already in place)

## Notes

- The `delete_all_user_tokens()` function was already implemented in task 3.1
- No additional password change locations were found in the codebase
- Users cannot change their own passwords through `staff/my_profile.php` (no password change UI exists there)
- The implementation follows the existing error handling and logging patterns in the codebase
