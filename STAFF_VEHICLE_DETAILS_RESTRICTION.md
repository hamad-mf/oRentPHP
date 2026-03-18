# Staff Vehicle Details Restrictions

**Issue Date:** March 18, 2026  
**Status:** ✅ IMPLEMENTED

## Overview

Restricted vehicle detail screen access for staff members to show **only essential information** - vehicle details and charges. All sensitive admin data is hidden from staff view.

## Changes Made

### Modified Files

- **`vehicles/show.php`** - Added role-based visibility controls

### What Staff Members See

✅ **Vehicle Information (Always Visible)**

- Vehicle photos/carousel
- Brand, model, year, license plate, color
- Status badge (Available/Rented/Maintenance)
- Maintenance details (if vehicle is in maintenance)

✅ **Pricing Information**

- Daily Rate
- Monthly Rate
- Total Revenue
- Total Expenses

✅ **Actions Available**

- Generate Quotation (opens in new tab)
- Share Vehicle Catalog link

### What Staff Members Do NOT See

❌ **Hidden from Staff:**

- Insurance Details (Type, Expiry, Status)
- Pollution Certificate (Expiry, Status)
- Storage Details (Key location, Documents location)
- Vehicle Condition Notes
- Parts Due Notes
- Documents list
- Booking Calendar
- Rental History
- Vehicle Expenses table
- Vehicle Edit button
- Add Expense button
- Delete Vehicle button

## Technical Implementation

### Role Detection

```php
// At the top of vehicles/show.php
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$showRestrictedSections = !$isAdmin;  // Only staff view restrictions
```

### Visibility Control Pattern

```php
<?php if ($isAdmin): ?>
    <!-- Admin-only content here -->
<?php endif; ?>
```

### Sections Wrapped

1. **Insurance & Pollution boxes** → Lines ~386-425
2. **Storage Details** → Lines ~482-530
3. **Condition Notes** → Lines ~531-575
4. **Parts Due** → Lines ~576-622
5. **Booking Calendar** → Lines ~640-735
6. **Documents & Rental History** → Lines ~736-825
7. **Vehicle Expenses** → Lines ~826-880
8. **Add Expense Modal** → Lines ~890-950
9. **Action Buttons** → Edit, Delete, Add Expense buttons hidden

## User Experience

### For Admin Users

- Full access to all vehicle details
- Can view all sensitive information
- Can edit vehicles, add expenses, delete vehicles

### For Staff Users

- Limited to essential vehicle information only
- Cannot access or view:
  - Financial/administrative details
  - Storage and key information
  - Maintenance notes
  - Document management
  - Expense tracking
  - Historical rental data
- Can still view quotations and share vehicle links

## Permission Requirements

This restriction applies based on user **role**, not permissions:

- **Admin role** → Full access (no restrictions)
- **Staff role** → Limited access (restrictions apply)

Note: This is separate from the permission system. A staff member with "Add / Edit Vehicles" permission still cannot see the admin-only details on the show screen.

## Rationale

**Why Staff Don't Need This Data:**

- Storage info (keys, documents) is for admin staff only
- Insurance/pollution is compliance data for management
- Condition/parts notes are internal maintenance tracking
- Booking calendar and history is for reservations team
- Expenses are financial/accounting data
- Edit/delete/expense-add functions are admin operations

**What Staff Actually Need:**

- Basic vehicle specs (to reference when discussing with clients)
- Pricing/rates (to provide quotes and information)
- Quotation tool (to generate documents for clients)
- Share capability (to send vehicle links to clients)

## Testing Checklist

- ✅ Staff members cannot see insurance details
- ✅ Staff members cannot see pollution certificate
- ✅ Staff members cannot see storage details
- ✅ Staff members cannot see condition notes
- ✅ Staff members cannot see parts due notes
- ✅ Staff members cannot access documents section
- ✅ Staff members cannot view booking calendar
- ✅ Staff members cannot see rental history
- ✅ Staff members cannot view expenses
- ✅ Staff members cannot see Edit button
- ✅ Staff members cannot see Add Expense button
- ✅ Staff members cannot see Delete button
- ✅ Staff members CAN see basic vehicle info
- ✅ Staff members CAN see pricing/charges
- ✅ Staff members CAN generate quotations
- ✅ Staff members CAN share vehicle catalog links

## Future Considerations

If you want more granular control over what staff sees, consider:

1. Adding a new permission like `view_admin_vehicle_details`
2. Creating different staff roles (e.g., "driver", "sales", "mechanic")
3. Using the permission system instead of role-based controls
4. Adding an admin preference to control staff visibility

## Related Documentation

- [PERMISSION_CONFLICT_RESOLUTION.md](PERMISSION_CONFLICT_RESOLUTION.md) - Permission dependency system
- [vehicles/show.php](vehicles/show.php) - Implementation details
