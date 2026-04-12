# Bugfix Requirements Document

## Introduction

After adding the upcoming deliveries screen (`deliveries/upcoming.php`), users navigating to this page from the dashboard cannot use the side menu to navigate to other pages. When clicking on side menu items like "Accounts" or "Reservations", the navigation fails with 404 errors because the links are incorrectly prefixed with `/deliveries/`.

For example:
- Clicking "Accounts" from the deliveries page navigates to `/deliveries/accounts/index.php` (404) instead of `/accounts/index.php`
- Clicking "Reservations" navigates to `/deliveries/reservations/index.php` (404) instead of `/reservations/index.php`

This prevents users from navigating away from the deliveries pages using the side menu, forcing them to use browser back buttons or manually edit URLs.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user is on `/deliveries/upcoming.php` and clicks a side menu link (e.g., "Accounts", "Reservations", "Clients") THEN the system navigates to an incorrect path with `/deliveries/` prepended (e.g., `/deliveries/accounts/index.php`) resulting in a 404 error

1.2 WHEN a user is on any page within the `/deliveries/` directory and clicks any side menu navigation link THEN the system incorrectly calculates the `$root` variable to include `deliveries/` in the path

1.3 WHEN the `$root` calculation logic in `includes/header.php` processes the script path for files in the `deliveries/` directory THEN the system fails to recognize `deliveries` as a module directory and incorrectly includes it in the root path prefix

### Expected Behavior (Correct)

2.1 WHEN a user is on `/deliveries/upcoming.php` and clicks a side menu link (e.g., "Accounts", "Reservations", "Clients") THEN the system SHALL navigate to the correct absolute path from the application root (e.g., `/accounts/index.php`, `/reservations/index.php`)

2.2 WHEN a user is on any page within the `/deliveries/` directory and clicks any side menu navigation link THEN the system SHALL calculate the `$root` variable correctly as `/` (or appropriate prefix) without including `deliveries/`

2.3 WHEN the `$root` calculation logic in `includes/header.php` processes the script path for files in the `deliveries/` directory THEN the system SHALL recognize `deliveries` as a module directory and exclude it from the root path prefix

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user is on pages in other module directories (e.g., `/accounts/`, `/reservations/`, `/vehicles/`, `/clients/`) and clicks side menu links THEN the system SHALL CONTINUE TO navigate correctly to the intended pages

3.2 WHEN a user is on the root dashboard (`/index.php`) and clicks side menu links THEN the system SHALL CONTINUE TO navigate correctly with relative paths from the root

3.3 WHEN the side menu is rendered on any existing page (non-deliveries pages) THEN the system SHALL CONTINUE TO display the correct active state highlighting for the current page

3.4 WHEN a user navigates using the mobile bottom navigation menu THEN the system SHALL CONTINUE TO function correctly with proper path resolution
