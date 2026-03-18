# Permission Conflict Resolution - Vehicle Permissions

**Issue Date:** March 18, 2026
**Status:** ✅ RESOLVED

## Problem Statement

There was a logical conflict between two staff permissions:

- **"Add / Edit Vehicles"** (`add_vehicles`)
- **"View Full Vehicle List"** (`view_all_vehicles`)

### The Issue

The vehicle list logic in `vehicles/index.php` used an OR condition:

```php
$canViewFullVehicles = auth_has_perm('add_vehicles') || auth_has_perm('view_all_vehicles');
```

This meant that staff members could have `add_vehicles` (ability to add/edit vehicles) while NOT having `view_all_vehicles` (ability to view full list), but the OR condition would still grant them full list access.

**Manifestation:**

- When you disabled "View Full Vehicle List" permission for a staff member
- But kept "Add / Edit Vehicles" permission enabled
- The staff member could still see the full vehicle list (because they had `add_vehicles` permission)
- They had to manually disable "Add / Edit Vehicles" as well to get the minimal list view
- This created a confusing user experience and violated the principle of permission independence

## Solution

Implemented a **permission dependency system** that automatically manages permission relationships:

### How It Works

1. **Permission Dependencies Defined** (`/config/db.php`):

   ```php
   'add_vehicles' => ['view_all_vehicles']
   ```

   This means: `add_vehicles` requires `view_all_vehicles`

2. **Automatic Synchronization** (`validate_and_sync_permissions()` function):
   - When `add_vehicles` is ENABLED → `view_all_vehicles` is automatically ENABLED
   - When `view_all_vehicles` is DISABLED → `add_vehicles` is automatically DISABLED
   - This prevents the logical conflict

3. **Two-Stage Validation:**
   - **Server-side:** Applied automatically when permissions are saved (backend validation)
   - **Client-side:** Real-time visual feedback with warnings as admin selects/deselects permissions

### Key Changes

#### 1. New Helper Functions in `/config/db.php`

```php
get_permission_dependencies(): array
- Returns the dependency rules between permissions

validate_and_sync_permissions(array $permissions): array
- Validates and automatically syncs permissions based on dependency rules
- Ensures dependencies are always satisfied

get_dependents(string $permission): array
- Returns which permissions depend on a given permission
```

#### 2. Updated Permission Edit Screens

**Staff Permissions (bulk edit):** `/settings/staff_permissions.php`

- Added dependency validation in POST handler
- Added visual indicators showing which permissions have dependencies
- Lightning bolt icon (⚡) shows permissions with requirements
- Real-time permission syncing with visual warnings
- Tooltip shows dependency requirements on hover

**Staff Individual Edit:** `/staff/edit.php`

- Same validation and visual feedback as bulk edit
- Applied during form submission
- Client-side warnings before save

### Visual Indicators

1. **Dependency Icon:** ⚡ Lightning bolt appears next to permissions that have dependencies
2. **Hover Tooltip:** Shows required permissions when hovering over a checkbox
3. **Warning Messages:** When syncing, shows:
   - ✓ Enabled "required permission" (automatically enabled due to dependency)
   - ✗ Disabled "dependent permission" (automatically disabled because prerequisite was disabled)

### User Experience Flow

#### Before Implementation

1. Disable "View Full Vehicle List" for staff member
2. Click Save
3. Staff member can still see full list (because of `add_vehicles` permission)
4. User has to go BACK and disable "Add / Edit Vehicles" manually
5. Confusing and error-prone

#### After Implementation

1. Disable "View Full Vehicle List" for staff member
2. JavaScript immediately shows warning: ✗ "Add / Edit Vehicles" automatically disabled
3. Click Save
4. Server-side validation confirms the sync
5. Staff member sees minimal vehicle list only
6. Clear, predictable behavior

## Files Modified

### 1. `/config/db.php`

- Added `get_permission_dependencies()` function
- Added `validate_and_sync_permissions()` function
- Added `get_dependents()` helper function

### 2. `/settings/staff_permissions.php`

- Modified POST handler to call `validate_and_sync_permissions()`
- Updated permission checkbox HTML with dependency attributes
- Added warning notice element for each staff member
- Added comprehensive JavaScript validation script with real-time syncing

### 3. `/staff/edit.php`

- Modified POST handler to call `validate_and_sync_permissions()`
- Updated permission checkbox HTML with dependency indicators and attributes
- Added warning notice element
- Added JavaScript validation script matching staff_permissions.php

## How Admins Use It

### Scenario 1: Enabling "Add / Edit Vehicles"

1. Go to Settings > Staff Permissions (or edit individual staff member)
2. Check "Add / Edit Vehicles"
3. Notice: "View Full Vehicle List" is automatically checked (greyed out in UI)
4. Warning shows: ✓ Enabled "View Full Vehicle List" (required for "Add / Edit Vehicles")
5. Save - both permissions are saved together

### Scenario 2: Disabling "View Full Vehicle List"

1. When checking if "Add / Edit Vehicles" is enabled
2. Uncheck "View Full Vehicle List"
3. Notice: "Add / Edit Vehicles" is automatically unchecked
4. Warning shows: ✗ Disabled "Add / Edit Vehicles" (requires disabled "View Full Vehicle List")
5. Save - both are disabled together

### Scenario 3: Minimal Access (only view available vehicles)

1. Don't enable either "Add / Edit Vehicles" or "View Full Vehicle List"
2. Staff member will see only available vehicles (minimal list)
3. They can't add/edit vehicles
4. Clean, restricted access as intended

## Technical Details

### Permission Dependency Algorithm

**First Pass - Enable Dependencies:**

```
For each enabled permission:
  Check if it has dependencies
  If yes, enable all dependencies
```

**Second Pass - Disable Dependents:**

```
For each dependency:
  If dependency is disabled:
    Disable all permissions that depend on it
```

**Result:** A valid permission state where all dependencies are satisfied

### JavaScript Sync Implementation

- Real-time checking/unchecking without page reload
- Visual feedback with color-coded warnings (✓ for additions, ✗ for removals)
- Syncing happens immediately when user clicks checkbox
- Works on both single staff edit and bulk edit screens
- Persisted on server via `validate_and_sync_permissions()`

## Adding New Permission Dependencies

If you need to add more permission dependencies in the future:

1. **Edit `/config/db.php`** - Update `get_permission_dependencies()`:

   ```php
   function get_permission_dependencies(): array
   {
       return [
           'add_vehicles' => ['view_all_vehicles'],
           'new_permission' => ['required_permission'],  // Add new dependency here
       ];
   }
   ```

2. **Update JavaScript** in both:
   - `/settings/staff_permissions.php`
   - `/staff/edit.php`

   Add to `permDependencies` object:

   ```javascript
   'new_permission': ['required_permission'],
   ```

3. **Add to `permLabels` object:**
   ```javascript
   'new_permission': 'Human-Readable Label',
   'required_permission': 'Required Permission Label',
   ```

## Backwards Compatibility

- Existing permissions are NOT changed
- Only the management UI adds validation and visual feedback
- Staff members with conflicting permissions will continue to have access
- The next time admin edits their permissions, validation will automatically sync them

## Testing Checklist

- ✅ **Server-side validation:** Disabled "View Full Vehicle List" while "Add / Edit Vehicles" is enabled
- ✅ **Client-side validation:** Real-time JavaScript updates
- ✅ **Visual feedback:** Warning messages appear/disappear correctly
- ✅ **Persistence:** Permission changes save correctly to database
- ✅ **Staff access:** Staff use vehicle list with correct restrictions based on final permissions
- ✅ **Admin session:** Permissions reload correctly if current admin user's permissions are modified

## Future Enhancements

Potential improvements that could be added:

1. Add more permission dependencies for other features (reservations, leads, etc.)
2. Create a visual permission dependency graph showing all relationships
3. Add batch import/export with permission validation
4. Create permission templates (e.g., "Driver", "Manager", "Accountant")
5. Add permission change audit log with before/after states

## Questions & Support

For issues with this implementation:

1. Check that both `/config/db.php` functions exist
2. Verify JavaScript is not conflicting with other scripts
3. Test in browser console: `console.log(permDependencies)`
4. Check database logs for any permission save errors
5. Clear browser cache if JavaScript changes aren't taking effect
