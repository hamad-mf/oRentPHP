# Vehicle Permission & Access Control Implementation

## Overview

This document details the implementation of permission-based access control and role-based visibility restrictions for the vehicle management system, specifically addressing permission conflicts and staff-level access limitations.

---

## Phase 1: Permission Dependency System

### Problem Identified

When a staff member had "Add / Edit Vehicles" permission enabled, they could view the full vehicle list even if "View Full Vehicle List" was disabled. This created a conflict where two different permissions provided overlapping access.

### Solution Implemented: Bidirectional Permission Syncing

**File**: `/config/db.php`

Created three new functions:

#### 1. `get_permission_dependencies()`

```php
Returns: ['add_vehicles' => ['view_all_vehicles']]
```

- Defines that "Add/Edit Vehicles" depends on "View Full Vehicle List"
- If you enable `add_vehicles`, you must have `view_all_vehicles`
- If you disable `view_all_vehicles`, you must disable `add_vehicles`

#### 2. `validate_and_sync_permissions($permissions_array)`

- **Two-pass algorithm**:
  - **Pass 1**: Enable dependents (if `view_all_vehicles` is enabled, ensure `add_vehicles` can be enabled)
  - **Pass 2**: Disable dependents (if `view_all_vehicles` is disabled, disable `add_vehicles`)
- **Server-side validation** ensures conflicts never save to database
- Called in: `/settings/staff_permissions.php` and `/staff/edit.php`

#### 3. `get_dependents($permission)`

- Returns permissions that depend on a given permission
- Used for finding cascade effects

### JavaScript Validation (Client-Side)

**Files Modified**:

- `/settings/staff_permissions.php`
- `/staff/edit.php`

**Implementation**:

```javascript
const permDependencies = {
  view_all_vehicles: ["add_vehicles"],
};
```

- Real-time validation as user clicks checkboxes
- Shows warning message when dependency is auto-triggered
- Provides immediate user feedback (unlike server-side which happens on save)

### Result

✅ **Automatic Conflict Resolution**

- Enable one → auto-enable dependencies
- Disable one → auto-disable dependents
- Prevents invalid permission combinations from being saved

---

## Phase 2: Staff Vehicle Detail Page Restrictions

### Problem Identified

When staff accessed vehicle detail pages, they could see ALL information including:

- Insurance details
- Pollution certificate
- Storage information
- Condition notes
- Parts due notes
- Booking calendar
- Documents
- Rental history
- Vehicle expenses (and Add Expense button)

This exposed sensitive admin-level information to staff users.

### Solution Implemented: Role-Based Section Hiding

**File**: `/vehicles/show.php`

#### Step 1: Role Detection

```php
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
```

- Simple true/false check for admin role
- Used throughout the page to conditionally show/hide sections

#### Step 2: Wrapping Admin-Only Sections

Each sensitive section is wrapped with:

```php
<?php if ($isAdmin): ?>
    <!-- Section content -->
<?php endif; ?>
```

**Sections Hidden from Staff**:

1. **Insurance Details** (~lines 389-425)
   - Insurance type, validity dates, policy number
2. **Pollution Certificate** (~lines 415-427)
   - Certificate number, expiry date, pollution details
3. **Storage Information** (~lines 490-530)
   - Location, type, monthly cost
4. **Condition Notes** (~lines 531-575)
   - Maintenance status, damage notes
5. **Parts Due** (~lines 576-622)
   - Scheduled service parts and dates
6. **Booking Calendar** (~lines 623-750)
   - Full booking history and availability
7. **Documents** (~lines 751-800)
   - RC, insurance, driving license documents
8. **Rental History** (~lines 801-850)
   - Complete rental transaction history
9. **Vehicle Expenses Section** (~lines 813-928)
   - Entire expenses table and Add Expense modal

### What Staff CAN See

- ✅ Vehicle photos and images
- ✅ Vehicle specifications (brand, model, year, license plate)
- ✅ Current status (Available, Rented, Maintenance)
- ✅ Pricing information (Daily Rate, Monthly Rate)
- ✅ Revenue and financial summary
- ✅ Generate Quotation button
- ✅ Share Vehicle Catalog button

### What Staff CANNOT See or Do

- ❌ Insurance, pollution, storage details
- ❌ Condition notes or maintenance history
- ❌ Documents or vehicle registration
- ❌ Booking calendar or rental history
- ❌ Expenses section or Add Expense button
- ❌ Edit, Delete, or Add Expense actions

---

## Phase 3: Permission Flow & Access Control

### Problem Identified

Detail pages were only accessible with "View Full Vehicle List" permission, preventing staff with other vehicle permissions from accessing vehicle details.

### Solution Implemented: Multi-Permission Access

**File 1**: `/vehicles/show.php` (Detail Page)

```php
$canViewVehicleDetails = ($_SESSION['user']['role'] ?? '') === 'admin' ||
                         auth_has_perm('add_vehicles') ||
                         auth_has_perm('view_all_vehicles') ||
                         auth_has_perm('view_vehicle_availability') ||
                         auth_has_perm('view_vehicle_requests');

if (!$canViewVehicleDetails) {
    flash('error', 'You do not have permission to view vehicle details.');
    redirect('index.php');
}
```

**Logic**: Staff can access detail pages with ANY of these permissions:

1. Admin role (full access)
2. `add_vehicles` (Add/Edit Vehicles)
3. `view_all_vehicles` (View Full Vehicle List)
4. `view_vehicle_availability` (View Vehicle Availability)
5. `view_vehicle_requests` (View Vehicle Requests)

**File 2**: `/vehicles/index.php` (Vehicle List)

```php
$canViewFullVehicles = auth_has_perm('add_vehicles') || auth_has_perm('view_all_vehicles');
$canAccessVehicles = $canViewFullVehicles ||
                     auth_has_perm('view_vehicle_availability') ||
                     auth_has_perm('view_vehicle_requests');

if (!$canAccessVehicles) {
    flash('error', 'You do not have permission to view vehicles.');
    redirect('index.php');
}
$restrictedView = !$canViewFullVehicles;
```

**Logic**:

- **Full List Access**: Only `add_vehicles` OR `view_all_vehicles`
- **Restricted List Access**: `view_vehicle_availability` OR `view_vehicle_requests` (sees only available or rented vehicles)
- **Detail Page Access**: Visible "View Details" link for all staff with vehicle permissions
- **Detail Page Content**: Filtered by role (admin sees all, staff sees limited data)

### Access Behavior by Permission

| Permission                | List View      | Detail Access   | See Expenses | Can Edit/Delete |
| ------------------------- | -------------- | --------------- | ------------ | --------------- |
| Admin Role                | All vehicles   | ✅ Full data    | ✅ Yes       | ✅ Yes          |
| add_vehicles              | All vehicles   | ✅ Full data    | ❌ No        | ❌ No           |
| view_all_vehicles         | All vehicles   | ✅ Full data    | ❌ No        | ❌ No           |
| view_vehicle_availability | Available only | ✅ Limited data | ❌ No        | ❌ No           |
| view_vehicle_requests     | Rented only    | ✅ Limited data | ❌ No        | ❌ No           |
| None of above             | No access      | ❌ Redirected   | ❌ N/A       | ❌ N/A          |

---

## Implementation Summary

### Key Files Modified

1. **`/config/db.php`**
   - Added permission dependency system
   - Bidirectional syncing logic
   - Server-side validation

2. **`/vehicles/show.php`**
   - Multi-permission access check (5 conditions)
   - Role-based section hiding (9 admin-only sections)
   - Wrapped entire Vehicle Expenses section with admin check

3. **`/vehicles/index.php`**
   - Split permission logic: full view vs. restricted view
   - Allow staff with any vehicle permission to access page
   - Removed "Details hidden" message, show link for all vehicle-permitted staff

4. **`/settings/staff_permissions.php`**
   - Server-side permission validation on save
   - JavaScript client-side validation with UI feedback

5. **`/staff/edit.php`**
   - Server-side permission validation on save
   - JavaScript client-side validation with UI feedback

---

## Testing Checklist

### Permission Dependency Tests

- [ ] Enable "Add/Edit Vehicles" → "View Full Vehicle List" auto-enables
- [ ] Disable "View Full Vehicle List" → "Add/Edit Vehicles" auto-disables
- [ ] Cannot manually enable conflict (validation prevents it)
- [ ] Warning message shows when auto-sync occurs

### Staff Access Tests (without "View Full Vehicle List")

- [ ] Staff with `view_vehicle_availability` can see list
- [ ] Staff with `view_vehicle_availability` sees only available vehicles
- [ ] Staff with `view_vehicle_availability` can click "View Details"
- [ ] Staff with `view_vehicle_availability` sees limited detail data
- [ ] Staff with `view_vehicle_requests` can see list
- [ ] Staff with `view_vehicle_requests` sees only rented vehicles
- [ ] Staff with `view_vehicle_requests` can click "View Details"

### Staff Detail Page Visibility Tests

- [ ] Insurance section hidden from staff
- [ ] Pollution certificate hidden from staff
- [ ] Storage information hidden from staff
- [ ] Condition notes hidden from staff
- [ ] Parts due hidden from staff
- [ ] Booking calendar hidden from staff
- [ ] Documents hidden from staff
- [ ] Rental history hidden from staff
- [ ] Vehicle Expenses section COMPLETELY hidden from staff
- [ ] "Add Expense" button NOT visible to staff
- [ ] Edit/Delete buttons NOT visible to staff

### Admin Tests

- [ ] Admin sees all vehicles regardless of permission
- [ ] Admin sees all detail page sections
- [ ] Admin sees Vehicle Expenses section
- [ ] Admin sees "Add Expense" button
- [ ] Admin can Edit and Delete vehicles

---

## Technical Notes

### Permission Check Order (show.php)

```php
1. Check if admin role → ALLOW
2. Check if has add_vehicles → ALLOW
3. Check if has view_all_vehicles → ALLOW
4. Check if has view_vehicle_availability → ALLOW
5. Check if has view_vehicle_requests → ALLOW
6. If none → DENY and redirect
```

Uses **logical OR** (`||`) for inclusive checking.

### Variable Naming

- `$canViewVehicleDetails` = Permission to access detail page (5 checks)
- `$canViewFullVehicles` = Permission for full list view (2 checks)
- `$canAccessVehicles` = Permission to access vehicles page at all (3+ checks)
- `$isAdmin` = Boolean check for admin role
- `$restrictedView` = Boolean flag for filtered list vs. full list

### Section Hiding Strategy

- **Approach**: Wrap each sensitive section with `<?php if ($isAdmin): ?>`
- **Benefit**: Sections don't render, CSS doesn't load, data doesn't transfer
- **Consistency**: Used for all admin-only features without exception

---

## Future Considerations

1. **Granular Expense Visibility**: Could allow staff with specific permission to see expenses but not add them
2. **Permission Cascade**: More sophisticated dependency trees (A → B → C)
3. **Audit Logging**: Log when staff accesses detail pages
4. **API Access**: Similar restrictions should apply to any API endpoints
5. **Customizable Periods**: Allow admins to configure which sections are visible to different roles

---

## Success Criteria

✅ **Conflict Prevention**: No invalid permission combinations can be saved
✅ **Auto-Sync**: Permissions sync bidirectionally and automatically
✅ **Detail Access**: Staff with any vehicle permission can access detail pages
✅ **Data Privacy**: Admin-only sections completely hidden from staff view
✅ **Graceful Degradation**: Staff see useful information without sensitive data
✅ **Admin Unaffected**: Admin functionality unchanged, has full access
