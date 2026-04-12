# Requirements Document

## Introduction

This feature consolidates the Vehicle Inspection Job Card and Permanent Scratches features into a single unified page with tab navigation. Currently, these are two separate menu items under the Vehicles menu, requiring users to navigate between different pages. The unified interface will provide a tabbed layout where users can easily switch between the 37-item inspection checklist and permanent scratch management without leaving the page, improving workflow efficiency and user experience.

## Glossary

- **Unified_Inspection_Page**: The new single page that hosts both Job Card and Permanent Scratches features
- **Tab_Navigation**: The client-side tab switching interface at the top of the Unified_Inspection_Page
- **Job_Card_Tab**: The first tab displaying the existing 37-item vehicle inspection checklist
- **Permanent_Scratches_Tab**: The second tab displaying the existing permanent scratch management interface
- **Active_Tab**: The currently visible tab in the Tab_Navigation
- **Tab_Content_Area**: The section of the page that displays the content of the Active_Tab
- **Vehicles_Menu**: The main navigation menu section containing vehicle-related features
- **Legacy_Job_Card_Page**: The existing vehicles/job_card.php page
- **Legacy_Permanent_Scratches_Page**: The existing vehicles/permanent_scratches.php page

## Requirements

### Requirement 1: Create Unified Inspection Page

**User Story:** As a staff member, I want to access both job card and permanent scratches from a single page, so that I can manage vehicle inspections more efficiently.

#### Acceptance Criteria

1. THE System SHALL create a new Unified_Inspection_Page accessible at vehicles/inspection.php
2. THE Unified_Inspection_Page SHALL display Tab_Navigation with two tabs at the top of the page
3. THE Tab_Navigation SHALL include a Job_Card_Tab labeled "Job Card"
4. THE Tab_Navigation SHALL include a Permanent_Scratches_Tab labeled "Permanent Scratches"
5. THE Unified_Inspection_Page SHALL use the existing oRentPHP dark theme styling (mb-surface, mb-accent, mb-subtle colors)

### Requirement 2: Tab Navigation Display

**User Story:** As a staff member, I want clear visual tabs at the top of the page, so that I can see which features are available and which one is currently active.

#### Acceptance Criteria

1. THE Tab_Navigation SHALL display both tabs horizontally at the top of the Tab_Content_Area
2. THE Active_Tab SHALL have a distinct visual appearance (highlighted background or border)
3. THE inactive tab SHALL have a subdued visual appearance
4. WHEN hovering over an inactive tab, THE System SHALL display a hover state visual effect
5. THE Tab_Navigation SHALL be fixed at the top of the content area and remain visible when scrolling

### Requirement 3: Client-Side Tab Switching

**User Story:** As a staff member, I want to switch between tabs instantly without page reload, so that I can work efficiently without interruption.

#### Acceptance Criteria

1. WHEN a user clicks on the Job_Card_Tab, THE System SHALL display the Job Card content in the Tab_Content_Area without page reload
2. WHEN a user clicks on the Permanent_Scratches_Tab, THE System SHALL display the Permanent Scratches content in the Tab_Content_Area without page reload
3. THE System SHALL use JavaScript to toggle visibility of tab content
4. WHEN switching tabs, THE System SHALL preserve any unsaved form data in the previously active tab
5. THE System SHALL update the Active_Tab visual indicator when switching tabs

### Requirement 4: Job Card Tab Content

**User Story:** As a staff member, I want the Job Card tab to show the full inspection checklist, so that I can perform vehicle inspections as before.

#### Acceptance Criteria

1. THE Job_Card_Tab SHALL display the complete 37-item inspection checklist from the Legacy_Job_Card_Page
2. THE Job_Card_Tab SHALL display the vehicle selection dropdown
3. THE Job_Card_Tab SHALL display all inspection item input fields (check values and notes)
4. THE Job_Card_Tab SHALL display the Save and Print buttons
5. THE Job_Card_Tab SHALL maintain all existing functionality from the Legacy_Job_Card_Page

### Requirement 5: Permanent Scratches Tab Content

**User Story:** As a staff member, I want the Permanent Scratches tab to show the scratch management interface, so that I can manage permanent vehicle damage as before.

#### Acceptance Criteria

1. THE Permanent_Scratches_Tab SHALL display the vehicle selection dropdown
2. THE Permanent_Scratches_Tab SHALL display the list of existing permanent scratches for the selected vehicle
3. THE Permanent_Scratches_Tab SHALL display the form to add new permanent scratches
4. THE Permanent_Scratches_Tab SHALL display delete buttons for each permanent scratch
5. THE Permanent_Scratches_Tab SHALL maintain all existing functionality from the Legacy_Permanent_Scratches_Page

### Requirement 6: Update Navigation Menu

**User Story:** As a staff member, I want a single "Vehicle Inspection" menu item, so that I can access both features from one place.

#### Acceptance Criteria

1. THE Vehicles_Menu SHALL display a single menu item labeled "Vehicle Inspection"
2. WHEN clicking the "Vehicle Inspection" menu item, THE System SHALL navigate to the Unified_Inspection_Page
3. THE System SHALL remove the separate "Job Card" menu item from the Vehicles_Menu
4. THE System SHALL remove the separate "Permanent Scratches" menu item from the Vehicles_Menu
5. THE "Vehicle Inspection" menu item SHALL be positioned in the Vehicles_Menu where the "Job Card" item previously appeared

### Requirement 7: Default Tab Selection

**User Story:** As a staff member, I want the Job Card tab to be selected by default, so that I can start inspections immediately.

#### Acceptance Criteria

1. WHEN the Unified_Inspection_Page loads without a tab parameter, THE System SHALL display the Job_Card_Tab as the Active_Tab
2. WHEN the Unified_Inspection_Page loads with a tab parameter, THE System SHALL display the specified tab as the Active_Tab
3. THE System SHALL support URL parameters to directly link to specific tabs (e.g., ?tab=permanent_scratches)
4. THE Job_Card_Tab content SHALL be visible by default on page load
5. THE Permanent_Scratches_Tab content SHALL be hidden by default on page load

### Requirement 8: Preserve Existing Functionality

**User Story:** As a staff member, I want all existing features to work exactly as before, so that my workflow is not disrupted.

#### Acceptance Criteria

1. THE Job_Card_Tab SHALL save inspection data to the same database tables as the Legacy_Job_Card_Page
2. THE Permanent_Scratches_Tab SHALL save scratch data to the same database tables as the Legacy_Permanent_Scratches_Page
3. THE Job_Card_Tab SHALL display validation errors in the same manner as the Legacy_Job_Card_Page
4. THE Permanent_Scratches_Tab SHALL display validation errors in the same manner as the Legacy_Permanent_Scratches_Page
5. THE System SHALL maintain all existing permission checks for both features

### Requirement 9: Responsive Tab Layout

**User Story:** As a staff member using a mobile device, I want the tabs to display properly on small screens, so that I can use the feature on any device.

#### Acceptance Criteria

1. THE Tab_Navigation SHALL display horizontally on desktop viewports (width >= 768px)
2. THE Tab_Navigation SHALL display horizontally on mobile viewports (width < 768px) with appropriate sizing
3. THE Tab_Content_Area SHALL adjust layout for mobile viewports
4. THE Active_Tab indicator SHALL be clearly visible on mobile viewports
5. THE System SHALL maintain touch-friendly tap targets for tab buttons on mobile devices

### Requirement 10: Access Control

**User Story:** As a system administrator, I want the unified page to enforce the same permissions as the separate pages, so that security is maintained.

#### Acceptance Criteria

1. WHEN an unauthenticated user attempts to access the Unified_Inspection_Page, THE System SHALL redirect to the login page
2. WHEN an authenticated user without vehicle management permissions attempts to access the Unified_Inspection_Page, THE System SHALL display an access denied message
3. THE System SHALL verify the user has the 'add_vehicles' permission before displaying the page
4. THE System SHALL record the authenticated user ID when saving data from either tab
5. THE System SHALL maintain audit trail consistency with the Legacy_Job_Card_Page and Legacy_Permanent_Scratches_Page

### Requirement 11: Vehicle Selection Synchronization

**User Story:** As a staff member, I want the vehicle selection to persist when switching tabs, so that I don't have to reselect the vehicle.

#### Acceptance Criteria

1. WHEN a vehicle is selected in the Job_Card_Tab, THE System SHALL retain the vehicle selection when switching to the Permanent_Scratches_Tab
2. WHEN a vehicle is selected in the Permanent_Scratches_Tab, THE System SHALL retain the vehicle selection when switching to the Job_Card_Tab
3. THE System SHALL use URL parameters or JavaScript state to maintain vehicle selection across tabs
4. WHEN switching tabs with a vehicle selected, THE System SHALL load the appropriate data for that vehicle in the new tab
5. THE System SHALL display the same vehicle dropdown options in both tabs

### Requirement 12: Legacy Page Deprecation

**User Story:** As a system administrator, I want the old separate pages to remain accessible temporarily, so that I can ensure a smooth transition.

#### Acceptance Criteria

1. THE Legacy_Job_Card_Page SHALL remain accessible at vehicles/job_card.php after deployment
2. THE Legacy_Permanent_Scratches_Page SHALL remain accessible at vehicles/permanent_scratches.php after deployment
3. THE System SHALL not display the legacy pages in the navigation menu
4. THE System SHALL allow direct URL access to legacy pages for backward compatibility
5. THE System SHALL display a notice on legacy pages indicating they have been replaced by the Unified_Inspection_Page
