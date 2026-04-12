# Design Document: Vehicle Inspection Unified Tabs

## Overview

This feature consolidates the Vehicle Inspection Job Card and Permanent Scratches features into a single unified page with client-side tab navigation. Currently, these exist as two separate pages (`vehicles/job_card.php` and `vehicles/permanent_scratches.php`) with separate menu items, requiring users to navigate between different pages to perform related vehicle inspection tasks.

The unified interface provides a tabbed layout where users can seamlessly switch between the 37-item inspection checklist and permanent scratch management without page reloads. This improves workflow efficiency by keeping related functionality in one place and reducing navigation overhead.

The implementation uses client-side JavaScript for instant tab switching while preserving all existing functionality from both legacy pages. The legacy pages remain accessible via direct URL for backward compatibility but are removed from the navigation menu.

## Architecture

### System Components

1. **Unified Inspection Page** (`vehicles/inspection.php`)
   - Tab navigation interface (Job Card and Permanent Scratches tabs)
   - Client-side tab switching with JavaScript
   - Vehicle selection synchronization across tabs
   - URL parameter support for direct tab linking

2. **Job Card Tab Content**
   - Complete 37-item inspection checklist
   - Vehicle selector dropdown
   - Check value and note inputs for each item
   - Save and Print buttons
   - Form submission to vehicle_job_cards tables

3. **Permanent Scratches Tab Content**
   - Vehicle selector dropdown
   - List of existing permanent scratches with photos
   - Add new scratch form (photo upload + description)
   - Delete scratch functionality
   - Form submission to vehicle_permanent_scratches table

4. **Navigation Integration**
   - Single "Vehicle Inspection" menu item in Vehicles submenu
   - Removal of separate "Job Card" and "Permanent Scratches" menu items
   - Legacy page deprecation notices

5. **Legacy Page Compatibility**
   - Legacy pages remain accessible via direct URL
   - Deprecation notices on legacy pages
   - No changes to legacy page functionality


### Data Flow

```
User navigates to vehicles/inspection.php
    ↓
Authentication & Permission Check (add_vehicles)
    ↓
Page renders with tab navigation
    ↓
Default: Job Card tab active, Permanent Scratches tab hidden
    ↓
User clicks tab → JavaScript toggles visibility (no page reload)
    ↓
Vehicle selection in one tab → Synced to other tab via URL parameter
    ↓
Form submission → POST to same page → Processed by appropriate handler
    ↓
Success → Flash message + redirect to preserve tab state
```

### Component Interaction

```
┌─────────────────────────────────────────────────────────────┐
│              vehicles/inspection.php                         │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Tab Navigation (Job Card | Permanent Scratches)        ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Job Card Tab Content (hidden/visible)                  ││
│  │  - Vehicle selector                                      ││
│  │  - 37-item checklist                                     ││
│  │  - Save/Print buttons                                    ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Permanent Scratches Tab Content (hidden/visible)       ││
│  │  - Vehicle selector                                      ││
│  │  - Scratch list with photos                             ││
│  │  - Add scratch form                                      ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
         ↓                                    ↓
    vehicle_job_cards              vehicle_permanent_scratches
    vehicle_job_card_items         (existing tables)
    (existing tables)
```

### Technology Stack

- **Backend**: PHP 7.4+ with PDO
- **Frontend**: Tailwind CSS for styling, vanilla JavaScript for tab switching
- **Database**: MySQL/MariaDB (no schema changes required)
- **Authentication**: Existing auth system with 'add_vehicles' permission
- **File Structure**: Single PHP file with embedded tab content


## Components and Interfaces

### 1. Unified Inspection Page Structure

**File**: `vehicles/inspection.php`

**Page Layout**:
```
┌─────────────────────────────────────────────────────────────┐
│  Header (from includes/header.php)                           │
├─────────────────────────────────────────────────────────────┤
│  Page Title: Vehicle Inspection                              │
├─────────────────────────────────────────────────────────────┤
│  Tab Navigation                                               │
│  [Job Card] [Permanent Scratches]                            │
├─────────────────────────────────────────────────────────────┤
│  Tab Content Area                                             │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Job Card Content (visible by default)                  ││
│  │  OR                                                       ││
│  │  Permanent Scratches Content (hidden by default)        ││
│  └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

**Access Control**:
```php
<?php
require_once __DIR__ . '/../config/db.php';

// Permission check: require 'add_vehicles' permission
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to access vehicle inspection.');
    redirect('../index.php');
}

$pdo = db();
$pageTitle = 'Vehicle Inspection';
```

### 2. Tab Navigation Component

**HTML Structure**:
```php
<!-- Tab Navigation -->
<div class="bg-mb-surface rounded-t-xl border-b border-mb-subtle/20">
    <div class="flex gap-2 px-6 pt-4">
        <button 
            id="tab-job-card" 
            onclick="switchTab('job-card')"
            class="tab-button px-6 py-3 rounded-t-lg font-medium transition-all active"
        >
            Job Card
        </button>
        <button 
            id="tab-permanent-scratches" 
            onclick="switchTab('permanent-scratches')"
            class="tab-button px-6 py-3 rounded-t-lg font-medium transition-all"
        >
            Permanent Scratches
        </button>
    </div>
</div>
```

**CSS Styling**:
```css
.tab-button {
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
    border-bottom: 2px solid transparent;
}

.tab-button:hover {
    color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 0.05);
}

.tab-button.active {
    color: #00d4ff; /* mb-accent */
    background: rgba(0, 212, 255, 0.1);
    border-bottom-color: #00d4ff;
}
```

**JavaScript Tab Switching**:
```javascript
function switchTab(tabName) {
    // Update tab button states
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Toggle content visibility
    document.getElementById('content-job-card').style.display = 
        tabName === 'job-card' ? 'block' : 'none';
    document.getElementById('content-permanent-scratches').style.display = 
        tabName === 'permanent-scratches' ? 'block' : 'none';
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

// Initialize tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'job-card';
    switchTab(activeTab);
});
```


### 3. Job Card Tab Content

The Job Card tab embeds the complete functionality from `vehicles/job_card.php`:

**Content Structure**:
```php
<div id="content-job-card" class="tab-content">
    <!-- Company Header -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
        <!-- Logo and title (same as job_card.php) -->
    </div>
    
    <!-- Vehicle Selection -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
        <select name="vehicle_id_job_card" id="vehicleSelectJobCard" 
                onchange="syncVehicleSelection(this.value, 'job-card')">
            <!-- Vehicle options -->
        </select>
    </div>
    
    <!-- 37-Item Inspection Checklist -->
    <form method="POST" action="inspection.php?tab=job-card">
        <input type="hidden" name="action" value="save_job_card">
        <table class="w-full">
            <!-- 37 inspection items with check values and notes -->
        </table>
        <button type="submit">Save Inspection</button>
        <button type="button" onclick="printJobCard()">Print</button>
    </form>
</div>
```

**Form Processing**:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_job_card') {
    // Same validation and save logic as job_card.php
    // Insert into vehicle_job_cards and vehicle_job_card_items
    // Redirect to inspection.php?tab=job-card&vehicle_id={id} on success
}
```

### 4. Permanent Scratches Tab Content

The Permanent Scratches tab embeds the complete functionality from `vehicles/permanent_scratches.php`:

**Content Structure**:
```php
<div id="content-permanent-scratches" class="tab-content" style="display: none;">
    <!-- Vehicle Selection -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
        <select name="vehicle_id_scratches" id="vehicleSelectScratches" 
                onchange="syncVehicleSelection(this.value, 'permanent-scratches')">
            <!-- Vehicle options -->
        </select>
    </div>
    
    <!-- Current Permanent Scratches -->
    <div class="bg-mb-surface rounded-xl p-6 mb-6">
        <h2>Current Permanent Scratches</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Scratch cards with photos and delete buttons -->
        </div>
    </div>
    
    <!-- Add New Scratch Form -->
    <form method="POST" action="inspection.php?tab=permanent-scratches" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_scratch">
        <input type="file" name="photo" required>
        <input type="text" name="description" maxlength="255" required>
        <button type="submit">Add Permanent Scratch</button>
    </form>
    
    <!-- Delete Scratch Form (per scratch) -->
    <form method="POST" action="inspection.php?tab=permanent-scratches">
        <input type="hidden" name="action" value="delete_scratch">
        <input type="hidden" name="scratch_id" value="{id}">
        <button type="submit">Delete</button>
    </form>
</div>
```

**Form Processing**:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_scratch') {
        // Same validation and save logic as permanent_scratches.php
        // Insert into vehicle_permanent_scratches
        // Redirect to inspection.php?tab=permanent-scratches&vehicle_id={id}
    }
    
    if ($action === 'delete_scratch') {
        // Same deletion logic as permanent_scratches.php
        // Delete from vehicle_permanent_scratches and file system
        // Redirect to inspection.php?tab=permanent-scratches&vehicle_id={id}
    }
}
```


### 5. Vehicle Selection Synchronization

**JavaScript Synchronization Function**:
```javascript
function syncVehicleSelection(vehicleId, sourceTab) {
    // Update both dropdowns
    document.getElementById('vehicleSelectJobCard').value = vehicleId;
    document.getElementById('vehicleSelectScratches').value = vehicleId;
    
    // Update URL parameter
    const url = new URL(window.location);
    url.searchParams.set('vehicle_id', vehicleId);
    window.history.pushState({}, '', url);
    
    // Reload page to fetch vehicle-specific data
    window.location.href = url.toString();
}
```

**PHP Vehicle Selection Handling**:
```php
$selectedVehicleId = (int)($_GET['vehicle_id'] ?? 0);

// Load job card data if vehicle selected
$loadedJobCard = null;
$loadedItems = [];
if ($selectedVehicleId > 0) {
    // Fetch latest job card for this vehicle
    $cardStmt = $pdo->prepare('SELECT * FROM vehicle_job_cards WHERE vehicle_id = ? ORDER BY inspection_date DESC LIMIT 1');
    $cardStmt->execute([$selectedVehicleId]);
    $loadedJobCard = $cardStmt->fetch();
    
    if ($loadedJobCard) {
        // Fetch job card items
        $itemsStmt = $pdo->prepare('SELECT * FROM vehicle_job_card_items WHERE job_card_id = ? ORDER BY item_number ASC');
        $itemsStmt->execute([$loadedJobCard['id']]);
        $items = $itemsStmt->fetchAll();
        foreach ($items as $item) {
            $loadedItems[$item['item_number']] = $item;
        }
    }
}

// Load permanent scratches if vehicle selected
$permanentScratches = [];
if ($selectedVehicleId > 0) {
    $scratchStmt = $pdo->prepare('SELECT * FROM vehicle_permanent_scratches WHERE vehicle_id = ? ORDER BY created_at ASC');
    $scratchStmt->execute([$selectedVehicleId]);
    $permanentScratches = $scratchStmt->fetchAll();
}
```

### 6. Navigation Menu Integration

**Modified Navigation** (`includes/header.php`):

Replace the existing Job Card and Permanent Scratches menu items with a single unified item:

```php
// OLD (to be removed):
// echo '<a href="' . $root . 'vehicles/job_card.php" ...>Job Card</a>';
// echo '<a href="' . $root . 'vehicles/permanent_scratches.php" ...>Permanent Scratches</a>';

// NEW (single unified menu item):
if ($isAdmin || in_array('add_vehicles', $cuPerms, true)) {
    echo '<a href="' . $root . 'vehicles/inspection.php" 
        class="block text-xs px-3 py-1.5 rounded-lg ' . 
        ($currentPage === 'inspection.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . 
        ' transition-colors">Vehicle Inspection</a>';
}
```

**Menu Position**: Place the "Vehicle Inspection" item where "Job Card" previously appeared (after "Challans" in the Vehicles submenu).


### 7. Legacy Page Deprecation

**Deprecation Notice Component**:

Add to the top of both `vehicles/job_card.php` and `vehicles/permanent_scratches.php`:

```php
<!-- Deprecation Notice -->
<div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 mb-6">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-yellow-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <h3 class="text-yellow-400 font-medium mb-1">This page has been replaced</h3>
            <p class="text-yellow-200/80 text-sm">
                This page is now part of the unified 
                <a href="inspection.php" class="underline hover:text-yellow-100">Vehicle Inspection</a> page. 
                This legacy page remains accessible for backward compatibility but is no longer linked in the navigation menu.
            </p>
        </div>
    </div>
</div>
```

**Legacy Page Behavior**:
- Pages remain fully functional (no code changes to core functionality)
- Deprecation notice displayed at the top
- Direct URL access still works
- Not linked from navigation menu

## Data Models

### Existing Tables (No Changes Required)

**vehicle_job_cards**:
- Stores job card header records
- Fields: id, vehicle_id, inspection_date, created_by, created_at
- Used by both legacy job_card.php and new inspection.php

**vehicle_job_card_items**:
- Stores 37 inspection item details per job card
- Fields: id, job_card_id, item_number, item_name, check_value, note
- Used by both legacy job_card.php and new inspection.php

**vehicle_permanent_scratches**:
- Stores permanent scratch records with photos
- Fields: id, vehicle_id, description, file_path, created_at, created_by
- Used by both legacy permanent_scratches.php and new inspection.php

### Data Consistency

The unified page uses the exact same database operations as the legacy pages:
- Job card saves: INSERT into vehicle_job_cards + 37 INSERTs into vehicle_job_card_items
- Scratch adds: INSERT into vehicle_permanent_scratches + file upload
- Scratch deletes: DELETE from vehicle_permanent_scratches + file deletion

No migration is required as no schema changes are needed.


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, I identified the following redundancies and consolidations:

**Redundancy Analysis**:

1. **Criteria 1.3 and 1.4** (tab labels) are both testing the presence of specific tab elements. These can be combined into a single property about tab navigation completeness.

2. **Criteria 3.1 and 3.2** (clicking tabs to switch content) are testing the same behavior in opposite directions. These can be combined into a single property about tab switching.

3. **Criteria 6.3 and 6.4** (removal of legacy menu items) are both testing the absence of specific menu items. These can be combined into a single property.

4. **Criteria 7.2 and 7.3** (URL parameter support) are testing the same behavior. These are redundant and can be combined.

5. **Criteria 7.4 and 7.5** (default tab visibility) are testing opposite sides of the same behavior (Job Card visible, Permanent Scratches hidden). These can be combined.

6. **Criteria 11.1 and 11.2** (vehicle selection persistence) are testing the same behavior in opposite directions. These can be combined into a single bidirectional property.

7. **Criteria 12.1 and 12.2** (legacy page accessibility) are testing the same behavior for different pages. These can be combined.

**Consolidated Properties**:
- Tab navigation completeness (combines 1.2, 1.3, 1.4)
- Tab switching behavior (combines 3.1, 3.2, 3.5)
- Legacy menu removal (combines 6.3, 6.4)
- URL parameter tab selection (combines 7.2, 7.3)
- Default tab state (combines 7.1, 7.4, 7.5)
- Vehicle selection persistence (combines 11.1, 11.2)
- Legacy page accessibility (combines 12.1, 12.2, 12.4)

**Unique Properties Retained**:
- Form data preservation during tab switching (3.4)
- Job Card content completeness (4.1, 4.2, 4.3, 4.4)
- Permanent Scratches content completeness (5.1, 5.2, 5.3, 5.4)
- Navigation menu integration (6.1, 6.2, 6.5)
- Database table consistency (8.1, 8.2)
- Validation error display (8.3, 8.4)
- Permission enforcement (8.5, 10.1, 10.2, 10.3)
- Audit trail (10.4)
- Vehicle data loading (11.4)
- Vehicle dropdown consistency (11.5)
- Legacy page deprecation notice (12.5)


### Property 1: Tab Navigation Completeness

*For any* page load of the unified inspection page, the tab navigation should contain exactly two tabs labeled "Job Card" and "Permanent Scratches" with appropriate styling classes.

**Validates: Requirements 1.2, 1.3, 1.4, 1.5**

### Property 2: Tab Switching Behavior

*For any* tab click event, the system should update the active tab visual indicator, show the corresponding tab content, hide the other tab content, and update the URL parameter without triggering a page reload.

**Validates: Requirements 3.1, 3.2, 3.5**

### Property 3: Form Data Preservation

*For any* form input entered in one tab, switching to another tab and then switching back should preserve the entered form data in its original state.

**Validates: Requirements 3.4**

### Property 4: Job Card Content Completeness

*For any* page load with the Job Card tab active, the tab content should display the vehicle selection dropdown, all 37 inspection items with check value and note inputs, and both Save and Print buttons.

**Validates: Requirements 4.1, 4.2, 4.3, 4.4**

### Property 5: Permanent Scratches Content Completeness

*For any* page load with the Permanent Scratches tab active and a vehicle selected, the tab content should display the vehicle selection dropdown, the list of existing scratches for that vehicle, the add scratch form, and delete buttons for each scratch.

**Validates: Requirements 5.1, 5.2, 5.3, 5.4**

### Property 6: Navigation Menu Integration

*For any* authenticated user with add_vehicles permission, the Vehicles menu should contain a single "Vehicle Inspection" menu item that links to inspection.php, positioned where the Job Card item previously appeared, and should not contain separate "Job Card" or "Permanent Scratches" menu items.

**Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5**

### Property 7: Default Tab State

*For any* page load without a tab URL parameter, the Job Card tab should be active and visible, and the Permanent Scratches tab content should be hidden.

**Validates: Requirements 7.1, 7.4, 7.5**

### Property 8: URL Parameter Tab Selection

*For any* page load with a tab URL parameter, the system should display the specified tab as active and visible, with the other tab content hidden.

**Validates: Requirements 7.2, 7.3**

### Property 9: Job Card Database Consistency

*For any* job card form submission from the unified page, the data should be saved to the vehicle_job_cards and vehicle_job_card_items tables using the same structure and validation as the legacy job_card.php page.

**Validates: Requirements 8.1**

### Property 10: Permanent Scratches Database Consistency

*For any* permanent scratch addition or deletion from the unified page, the data should be saved to or removed from the vehicle_permanent_scratches table using the same structure and validation as the legacy permanent_scratches.php page.

**Validates: Requirements 8.2**

### Property 11: Validation Error Display

*For any* invalid form submission (job card or permanent scratch), the system should display validation errors in the same format and location as the legacy pages, without losing the user's entered data.

**Validates: Requirements 8.3, 8.4**

### Property 12: Permission Enforcement

*For any* request to the unified inspection page, the system should verify the user is authenticated and has the add_vehicles permission before displaying any content, redirecting unauthenticated users to login and unauthorized users to the dashboard with an error message.

**Validates: Requirements 8.5, 10.1, 10.2, 10.3**

### Property 13: Audit Trail Recording

*For any* data save operation (job card or permanent scratch) from the unified page, the system should record the authenticated user's ID in the created_by field of the respective database table.

**Validates: Requirements 10.4**

### Property 14: Vehicle Selection Persistence

*For any* vehicle selection in either tab, switching to the other tab should maintain the same vehicle selection, and the vehicle dropdown in both tabs should display the same selected vehicle.

**Validates: Requirements 11.1, 11.2, 11.5**

### Property 15: Vehicle Data Loading

*For any* vehicle selection followed by a tab switch, the new tab should load and display the appropriate data for the selected vehicle (job card items or permanent scratches).

**Validates: Requirements 11.4**

### Property 16: Legacy Page Accessibility

*For any* direct URL access to vehicles/job_card.php or vehicles/permanent_scratches.php, the legacy pages should remain accessible and fully functional, displaying a deprecation notice at the top.

**Validates: Requirements 12.1, 12.2, 12.3, 12.4, 12.5**


## Error Handling

### Access Control Errors

**Scenario**: Unauthenticated user attempts to access inspection.php
- **Handling**: Redirect to login page via auth check in config/db.php
- **User Experience**: Redirected to login with flash message: "Please log in to continue."
- **Security**: No page content exposed to unauthenticated users

**Scenario**: Authenticated user without add_vehicles permission
- **Handling**: Permission check fails, flash error, redirect to dashboard
- **User Experience**: Error message: "You do not have permission to access vehicle inspection."
- **Security**: Permission verified before any content rendering

### Form Submission Errors

**Scenario**: Job card submission with no vehicle selected
- **Handling**: Validation error displayed in Job Card tab
- **User Experience**: Error message: "Please select a vehicle." Form data preserved.
- **Tab State**: Remains on Job Card tab, error displayed at top of form

**Scenario**: Job card submission with invalid data
- **Handling**: Same validation as legacy job_card.php
- **User Experience**: Specific error messages for each validation failure
- **Tab State**: Remains on Job Card tab with errors displayed

**Scenario**: Permanent scratch submission without photo
- **Handling**: Validation error displayed in Permanent Scratches tab
- **User Experience**: Error message: "Photo is required." Form data preserved.
- **Tab State**: Remains on Permanent Scratches tab

**Scenario**: Permanent scratch submission with invalid file type
- **Handling**: File type validation fails
- **User Experience**: Error message: "Invalid file type. Only JPG, PNG, and GIF are allowed."
- **Tab State**: Remains on Permanent Scratches tab

**Scenario**: Database error during save operation
- **Handling**: Transaction rollback, exception caught, error logged
- **User Experience**: Error message: "Could not save data. Please try again."
- **Logging**: Full exception details logged via app_log()

### Tab Switching Errors

**Scenario**: JavaScript disabled in browser
- **Handling**: Graceful degradation - tabs still clickable but may cause page reload
- **User Experience**: Tabs function but with page reloads instead of instant switching
- **Fallback**: Consider adding noscript warning or server-side tab handling

**Scenario**: Invalid tab parameter in URL
- **Handling**: Default to Job Card tab
- **User Experience**: Job Card tab displayed, invalid parameter ignored
- **Robustness**: System doesn't break with malformed URLs

### Vehicle Selection Errors

**Scenario**: Vehicle deleted after page load but before form submission
- **Handling**: Validation query returns no vehicle, error displayed
- **User Experience**: Error message: "Selected vehicle does not exist."
- **Recovery**: User can refresh and select a different vehicle

**Scenario**: No vehicles in database
- **Handling**: Dropdown shows only "-- Select Vehicle --" option
- **User Experience**: Form still functional, validation will catch empty selection
- **Graceful**: No errors displayed, form remains usable

### File Upload Errors

**Scenario**: File upload fails (move_uploaded_file returns false)
- **Handling**: Error displayed, database insert prevented
- **User Experience**: Error message: "Failed to upload photo. Please try again."
- **Data Integrity**: No orphaned database records created

**Scenario**: Upload directory doesn't exist
- **Handling**: Directory created automatically with mkdir()
- **User Experience**: Transparent to user, upload proceeds normally
- **Fallback**: If mkdir fails, error displayed

**Scenario**: File deletion fails during scratch removal
- **Handling**: Database record deleted, file deletion failure logged
- **User Experience**: Warning: "Scratch removed, but photo file may need manual cleanup."
- **Graceful Degradation**: Operation completes despite file system error


## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests for comprehensive coverage:

**Unit Tests** focus on:
- Specific examples of tab navigation and switching
- Edge cases (no vehicles, invalid tab parameters, JavaScript disabled)
- Error conditions (missing permissions, invalid form data)
- Integration points (navigation menu, legacy page notices)
- UI rendering (tab buttons, content visibility, styling classes)

**Property-Based Tests** focus on:
- Universal properties that hold for all inputs (form data preservation, vehicle selection sync)
- Comprehensive input coverage through randomization (various vehicle IDs, tab parameters)
- Invariants (permission checks, database consistency, audit trails)

### Property-Based Testing Configuration

**Library**: PHPUnit with a property-based testing library (e.g., Eris or php-quickcheck)

**Test Configuration**:
- Minimum 100 iterations per property test
- Each test tagged with feature name and property reference
- Tag format: `@group vehicle-inspection-unified-tabs @property {number}`

### Unit Test Examples

#### Test 1: Tab Navigation Rendering
```php
public function testTabNavigationRendersWithBothTabs()
{
    $user = $this->createUserWithPermission('add_vehicles');
    $this->actingAs($user);
    
    $response = $this->get('/vehicles/inspection.php');
    
    $response->assertStatus(200);
    $response->assertSee('Job Card');
    $response->assertSee('Permanent Scratches');
    $response->assertSee('tab-button');
}
```

#### Test 2: Default Tab State
```php
public function testJobCardTabIsActiveByDefault()
{
    $user = $this->createUserWithPermission('add_vehicles');
    $this->actingAs($user);
    
    $response = $this->get('/vehicles/inspection.php');
    
    // Job Card tab should have active class
    $response->assertSee('id="tab-job-card"');
    $response->assertSee('class="tab-button px-6 py-3 rounded-t-lg font-medium transition-all active"');
    
    // Job Card content should be visible
    $response->assertSee('id="content-job-card"');
    $response->assertDontSee('style="display: none;"', false); // in job card content
    
    // Permanent Scratches content should be hidden
    $response->assertSee('id="content-permanent-scratches"');
    $response->assertSee('style="display: none;"'); // in scratches content
}
```

#### Test 3: Permission Enforcement
```php
public function testUnauthorizedUserCannotAccessInspectionPage()
{
    $user = $this->createUserWithoutPermission('add_vehicles');
    $this->actingAs($user);
    
    $response = $this->get('/vehicles/inspection.php');
    
    $response->assertRedirect('/index.php');
    $response->assertSessionHas('error', 'You do not have permission to access vehicle inspection.');
}
```

#### Test 4: Navigation Menu Integration
```php
public function testNavigationMenuShowsUnifiedInspectionItem()
{
    $user = $this->createUserWithPermission('add_vehicles');
    $this->actingAs($user);
    
    $response = $this->get('/index.php');
    
    // Should show unified menu item
    $response->assertSee('Vehicle Inspection');
    $response->assertSee('href="vehicles/inspection.php"');
    
    // Should NOT show legacy menu items
    $response->assertDontSee('href="vehicles/job_card.php"');
    $response->assertDontSee('href="vehicles/permanent_scratches.php"');
}
```

#### Test 5: Legacy Page Deprecation Notice
```php
public function testLegacyPagesShowDeprecationNotice()
{
    $user = $this->createUserWithPermission('add_vehicles');
    $this->actingAs($user);
    
    $response = $this->get('/vehicles/job_card.php');
    $response->assertSee('This page has been replaced');
    $response->assertSee('Vehicle Inspection');
    
    $response = $this->get('/vehicles/permanent_scratches.php');
    $response->assertSee('This page has been replaced');
    $response->assertSee('Vehicle Inspection');
}
```


### Property Test Implementations

#### Property 2: Tab Switching Behavior
```php
/**
 * @group vehicle-inspection-unified-tabs
 * @property 2: For any tab click event, the system should update the active tab visual indicator,
 * show the corresponding tab content, hide the other tab content, and update the URL parameter
 * without triggering a page reload.
 */
public function testTabSwitchingBehavior()
{
    $this->forAll(
        Generator::elements(['job-card', 'permanent-scratches'])
    )->then(function ($targetTab) {
        $user = $this->createUserWithPermission('add_vehicles');
        $this->actingAs($user);
        
        // Load page with opposite tab active
        $initialTab = $targetTab === 'job-card' ? 'permanent-scratches' : 'job-card';
        $response = $this->get("/vehicles/inspection.php?tab={$initialTab}");
        
        // Simulate tab click via JavaScript
        $this->executeJavaScript("switchTab('{$targetTab}')");
        
        // Verify active tab indicator updated
        $activeButton = $this->getElement("#tab-{$targetTab}");
        $this->assertTrue($activeButton->hasClass('active'));
        
        // Verify target content visible
        $targetContent = $this->getElement("#content-{$targetTab}");
        $this->assertEquals('block', $targetContent->getStyle('display'));
        
        // Verify other content hidden
        $otherTab = $targetTab === 'job-card' ? 'permanent-scratches' : 'job-card';
        $otherContent = $this->getElement("#content-{$otherTab}");
        $this->assertEquals('none', $otherContent->getStyle('display'));
        
        // Verify URL updated without reload
        $this->assertUrlContains("tab={$targetTab}");
        $this->assertNoPageReload();
    })->runs(100);
}
```

#### Property 3: Form Data Preservation
```php
/**
 * @group vehicle-inspection-unified-tabs
 * @property 3: For any form input entered in one tab, switching to another tab and then
 * switching back should preserve the entered form data in its original state.
 */
public function testFormDataPreservation()
{
    $this->forAll(
        Generator::string(1, 100), // check value
        Generator::string(1, 255), // note
        Generator::int(1, 37) // item number
    )->then(function ($checkValue, $note, $itemNumber) {
        $user = $this->createUserWithPermission('add_vehicles');
        $this->actingAs($user);
        
        // Load page on Job Card tab
        $this->get('/vehicles/inspection.php?tab=job-card');
        
        // Enter data in job card form
        $this->fillField("items[{$itemNumber}][check_value]", $checkValue);
        $this->fillField("items[{$itemNumber}][note]", $note);
        
        // Switch to Permanent Scratches tab
        $this->executeJavaScript("switchTab('permanent-scratches')");
        
        // Switch back to Job Card tab
        $this->executeJavaScript("switchTab('job-card')");
        
        // Verify data preserved
        $checkValueField = $this->getFieldValue("items[{$itemNumber}][check_value]");
        $noteField = $this->getFieldValue("items[{$itemNumber}][note]");
        
        $this->assertEquals($checkValue, $checkValueField);
        $this->assertEquals($note, $noteField);
    })->runs(100);
}
```

#### Property 8: URL Parameter Tab Selection
```php
/**
 * @group vehicle-inspection-unified-tabs
 * @property 8: For any page load with a tab URL parameter, the system should display
 * the specified tab as active and visible, with the other tab content hidden.
 */
public function testUrlParameterTabSelection()
{
    $this->forAll(
        Generator::elements(['job-card', 'permanent-scratches'])
    )->then(function ($tabParam) {
        $user = $this->createUserWithPermission('add_vehicles');
        $this->actingAs($user);
        
        // Load page with tab parameter
        $response = $this->get("/vehicles/inspection.php?tab={$tabParam}");
        
        // Verify specified tab is active
        $activeButton = $this->getElement("#tab-{$tabParam}");
        $this->assertTrue($activeButton->hasClass('active'));
        
        // Verify specified tab content is visible
        $activeContent = $this->getElement("#content-{$tabParam}");
        $this->assertNotEquals('none', $activeContent->getStyle('display'));
        
        // Verify other tab content is hidden
        $otherTab = $tabParam === 'job-card' ? 'permanent-scratches' : 'job-card';
        $otherContent = $this->getElement("#content-{$otherTab}");
        $this->assertEquals('none', $otherContent->getStyle('display'));
    })->runs(100);
}
```

#### Property 13: Audit Trail Recording
```php
/**
 * @group vehicle-inspection-unified-tabs
 * @property 13: For any data save operation (job card or permanent scratch) from the unified page,
 * the system should record the authenticated user's ID in the created_by field.
 */
public function testAuditTrailRecording()
{
    $this->forAll(
        Generator::int(1, 100), // user ID
        Generator::int(1, 100), // vehicle ID
        Generator::elements(['job-card', 'permanent-scratch'])
    )->then(function ($userId, $vehicleId, $operationType) {
        $user = $this->createUserWithId($userId, 'add_vehicles');
        $this->actingAs($user);
        
        $vehicle = $this->createTestVehicle($vehicleId);
        
        if ($operationType === 'job-card') {
            // Submit job card
            $this->post('/vehicles/inspection.php?tab=job-card', [
                'action' => 'save_job_card',
                'vehicle_id' => $vehicleId,
                'items' => $this->generateJobCardItems()
            ]);
            
            // Verify created_by in vehicle_job_cards
            $record = $this->db->query("SELECT created_by FROM vehicle_job_cards WHERE vehicle_id = ? ORDER BY id DESC LIMIT 1", [$vehicleId])->fetch();
            $this->assertEquals($userId, $record['created_by']);
            
        } else {
            // Submit permanent scratch
            $this->post('/vehicles/inspection.php?tab=permanent-scratches', [
                'action' => 'add_scratch',
                'vehicle_id' => $vehicleId,
                'description' => 'Test scratch',
                'photo' => $this->generateTestPhoto()
            ]);
            
            // Verify created_by in vehicle_permanent_scratches
            $record = $this->db->query("SELECT created_by FROM vehicle_permanent_scratches WHERE vehicle_id = ? ORDER BY id DESC LIMIT 1", [$vehicleId])->fetch();
            $this->assertEquals($userId, $record['created_by']);
        }
    })->runs(100);
}
```

#### Property 14: Vehicle Selection Persistence
```php
/**
 * @group vehicle-inspection-unified-tabs
 * @property 14: For any vehicle selection in either tab, switching to the other tab should
 * maintain the same vehicle selection, and the vehicle dropdown in both tabs should display
 * the same selected vehicle.
 */
public function testVehicleSelectionPersistence()
{
    $this->forAll(
        Generator::int(1, 100), // vehicle ID
        Generator::elements(['job-card', 'permanent-scratches']) // starting tab
    )->then(function ($vehicleId, $startingTab) {
        $user = $this->createUserWithPermission('add_vehicles');
        $this->actingAs($user);
        
        $vehicle = $this->createTestVehicle($vehicleId);
        
        // Load page on starting tab
        $this->get("/vehicles/inspection.php?tab={$startingTab}");
        
        // Select vehicle in starting tab
        $dropdownId = $startingTab === 'job-card' ? 'vehicleSelectJobCard' : 'vehicleSelectScratches';
        $this->selectOption($dropdownId, $vehicleId);
        
        // This triggers syncVehicleSelection which reloads the page
        $this->waitForPageLoad();
        
        // Switch to other tab
        $otherTab = $startingTab === 'job-card' ? 'permanent-scratches' : 'job-card';
        $this->executeJavaScript("switchTab('{$otherTab}')");
        
        // Verify vehicle selection persisted in both dropdowns
        $jobCardDropdown = $this->getFieldValue('vehicleSelectJobCard');
        $scratchesDropdown = $this->getFieldValue('vehicleSelectScratches');
        
        $this->assertEquals($vehicleId, $jobCardDropdown);
        $this->assertEquals($vehicleId, $scratchesDropdown);
    })->runs(100);
}
```

