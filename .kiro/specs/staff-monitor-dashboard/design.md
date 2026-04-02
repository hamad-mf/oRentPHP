# Design Document: Staff Monitor Dashboard

## Overview

The Staff Monitor Dashboard is a read-only analytics interface that visualizes existing `staff_activity_log` data. The system provides administrators with real-time visibility into staff operational activities through a dashboard displaying global KPIs, individual staff performance cards, active status indicators, and detailed chronological timelines.

The feature leverages the existing `staff_activity_log` table (structure: `id, user_id, action, entity_type, entity_id, description, created_at`) without requiring any database schema changes. The dashboard is accessible only to users with admin privileges or the `view_staff_monitor` permission, which has already been added to the system.

Key design principles:
- Read-only data visualization (no modifications to activity logs)
- Date-based filtering via URL parameters for historical analysis
- AJAX-based timeline loading for responsive user experience
- Responsive grid layout adapting to mobile, tablet, and desktop viewports
- Consistent with existing Tailwind `bg-mb-*` namespace styling

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Browser (Client)                          │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  staff_monitor/index.php                             │   │
│  │  ├─ KPI Panel (4 metric cards)                       │   │
│  │  ├─ Date Filter (navigation controls)                │   │
│  │  └─ Staff Grid (responsive card layout)              │   │
│  └──────────────────────────────────────────────────────┘   │
│                          │                                   │
│                          │ AJAX Request                      │
│                          ▼                                   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Timeline Modal (slide-over panel)                   │   │
│  │  └─ Chronological activity list with icons           │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                           │
                           │ HTTP GET
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Server (PHP)                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  staff_monitor/ajax_timeline.php                     │   │
│  │  └─ Query activity_log by user_id + date             │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                           │
                           │ SQL Query
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database (MySQL)                          │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  staff_activity_log                                   │   │
│  │  [id, user_id, action, entity_type, entity_id,       │   │
│  │   description, created_at]                            │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  users                                                │   │
│  │  [id, username, role, is_active, staff_id]           │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Request Flow

1. User navigates to `staff_monitor/index.php` (optionally with `?date=YYYY-MM-DD`)
2. Server validates authentication and `view_staff_monitor` permission
3. Server queries `staff_activity_log` for the selected date to calculate KPIs
4. Server queries `users` table for active staff members
5. Server aggregates activity counts per staff member using GROUP BY
6. Server calculates "last seen" timestamps and active status
7. Page renders with KPI panel, date filter, and staff cards
8. User clicks a staff card, triggering `openTimelineModal(userId)`
9. JavaScript fetches `ajax_timeline.php?user_id=X&date=YYYY-MM-DD`
10. Server returns HTML timeline content
11. Modal slides in from right with chronological activity list

### Technology Stack

- Backend: PHP 7.4+ with PDO for database access
- Database: MySQL with existing `staff_activity_log` table
- Frontend: Tailwind CSS with `bg-mb-*` custom color namespace
- JavaScript: Vanilla JS for AJAX and modal interactions
- Authentication: Existing `auth_check()` and `auth_has_perm()` functions

## Components and Interfaces

### 1. Main Dashboard Page (`staff_monitor/index.php`)

**Responsibilities:**
- Authentication and authorization enforcement
- Date parameter handling and validation
- Global KPI calculation from activity logs
- Staff member data aggregation
- UI rendering for KPI panel, date filter, and staff grid
- Timeline modal container and JavaScript

**Key Functions:**

```php
// Authentication
auth_check();
if (!$isAdmin && !in_array('view_staff_monitor', $cuPerms, true)) {
    redirect('../index.php');
}

// Date handling
$selectedDate = $_GET['date'] ?? date('Y-m-d');
// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Global KPI queries
$totalActions = $pdo->prepare("SELECT COUNT(*) FROM staff_activity_log WHERE DATE(created_at) = ?");
$activeStaff = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM staff_activity_log WHERE DATE(created_at) = ?");
$leadActions = $pdo->prepare("SELECT COUNT(*) FROM staff_activity_log WHERE DATE(created_at) = ? AND action LIKE '%lead%'");
$reservationPaymentActions = $pdo->prepare("SELECT COUNT(*) FROM staff_activity_log WHERE DATE(created_at) = ? AND (action LIKE '%reservation%' OR action LIKE '%payment%')");

// Staff activity aggregation
$staffData = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        s.name,
        s.role,
        COUNT(sal.id) as total_actions,
        SUM(CASE WHEN sal.action LIKE '%lead%' THEN 1 ELSE 0 END) as lead_actions,
        SUM(CASE WHEN sal.action LIKE '%reservation%' THEN 1 ELSE 0 END) as reservation_actions,
        SUM(CASE WHEN sal.action LIKE '%payment%' THEN 1 ELSE 0 END) as payment_actions,
        MAX(sal.created_at) as last_activity
    FROM users u
    INNER JOIN staff s ON s.id = u.staff_id
    LEFT JOIN staff_activity_log sal ON sal.user_id = u.id AND DATE(sal.created_at) = ?
    WHERE u.is_active = 1 AND u.role = 'staff'
    GROUP BY u.id
    ORDER BY total_actions DESC, s.name ASC
");
```

**Active Status Calculation:**

```php
function getActiveStatus(string $lastActivity): array {
    if (!$lastActivity) {
        return ['status' => 'inactive', 'text' => 'No activity', 'color' => 'red'];
    }
    
    $lastTime = strtotime($lastActivity);
    $now = time();
    $diffMinutes = floor(($now - $lastTime) / 60);
    
    if ($diffMinutes <= 15) {
        return ['status' => 'active', 'text' => 'Active Now', 'color' => 'green'];
    }
    
    // Format relative time
    if ($diffMinutes < 60) {
        $text = "Last seen {$diffMinutes}m ago";
    } elseif ($diffMinutes < 1440) {
        $hours = floor($diffMinutes / 60);
        $text = "Last seen {$hours}h ago";
    } else {
        $days = floor($diffMinutes / 1440);
        $text = "Last seen {$days}d ago";
    }
    
    return ['status' => 'inactive', 'text' => $text, 'color' => 'red'];
}
```

### 2. Timeline AJAX Handler (`staff_monitor/ajax_timeline.php`)

**Responsibilities:**
- Authentication and authorization enforcement
- User ID and date parameter validation
- Activity log retrieval for specific user and date
- HTML timeline rendering with icons and formatting

**Interface:**

```php
// Input: GET parameters
$userId = (int) ($_GET['user_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

// Validation
if (!$userId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('Invalid parameters');
}

// Query
$stmt = $pdo->prepare("
    SELECT action, description, created_at 
    FROM staff_activity_log 
    WHERE user_id = ? AND DATE(created_at) = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId, $date]);
$activities = $stmt->fetchAll();

// Output: HTML fragment
foreach ($activities as $activity) {
    // Render timeline entry with icon, time, and description
}
```

**Action Icon Mapping:**

```php
$actionIcons = [
    'delivery' => '🚗',
    'return' => '🔄',
    'created_reservation' => '📋',
    'created_lead' => '👤',
    'payment' => '💰',
    'default' => '📌'
];

function getActionIcon(string $action): string {
    global $actionIcons;
    foreach ($actionIcons as $keyword => $icon) {
        if (stripos($action, $keyword) !== false) {
            return $icon;
        }
    }
    return $actionIcons['default'];
}
```

### 3. JavaScript Timeline Modal

**Responsibilities:**
- Modal open/close state management
- AJAX request to timeline endpoint
- Dynamic content injection
- Slide-over animation

**Interface:**

```javascript
function openTimelineModal(userId) {
    const modal = document.getElementById('timeline-modal');
    const content = document.getElementById('timeline-content');
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date') || new Date().toISOString().split('T')[0];
    
    // Show loading state
    content.innerHTML = '<div class="text-center py-8 text-mb-subtle">Loading...</div>';
    modal.classList.remove('translate-x-full');
    
    // Fetch timeline data
    fetch(`ajax_timeline.php?user_id=${userId}&date=${date}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="text-center py-8 text-red-400">Failed to load timeline</div>';
        });
}

function closeTimelineModal() {
    const modal = document.getElementById('timeline-modal');
    modal.classList.add('translate-x-full');
}
```

## Data Models

### Existing Database Schema

**staff_activity_log table:**
```sql
CREATE TABLE staff_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_date (created_at)
);
```

**users table (relevant columns):**
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    role ENUM('admin', 'staff'),
    is_active TINYINT(1) DEFAULT 1,
    staff_id INT,
    -- other columns...
);
```

**staff table (relevant columns):**
```sql
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(100),
    -- other columns...
);
```

### Data Transfer Objects

**KPI Metrics:**
```php
[
    'total_actions' => int,        // Total activity log entries for date
    'active_staff' => int,         // Distinct user_ids with activity
    'lead_actions' => int,         // Actions containing 'lead' keyword
    'reservation_payment_actions' => int  // Actions with 'reservation' or 'payment'
]
```

**Staff Card Data:**
```php
[
    'user_id' => int,
    'username' => string,
    'name' => string,
    'role' => string,
    'total_actions' => int,
    'lead_actions' => int,
    'reservation_actions' => int,
    'payment_actions' => int,
    'last_activity' => string,     // ISO timestamp
    'active_status' => [
        'status' => 'active'|'inactive',
        'text' => string,          // "Active Now" or "Last seen Xm ago"
        'color' => 'green'|'red'
    ]
]
```

**Timeline Entry:**
```php
[
    'action' => string,            // e.g., 'created_reservation'
    'description' => string,       // e.g., 'Created reservation #123'
    'created_at' => string,        // ISO timestamp
    'icon' => string,              // Emoji or SVG icon
    'formatted_time' => string     // e.g., '09:45 AM'
]
```

### Action Categorization Logic

Actions are categorized using keyword matching:

```php
function categorizeAction(string $action): array {
    $categories = [];
    
    $action_lower = strtolower($action);
    
    if (stripos($action, 'lead') !== false) {
        $categories[] = 'lead';
    }
    
    if (stripos($action, 'reservation') !== false) {
        $categories[] = 'reservation';
    }
    
    if (stripos($action, 'payment') !== false) {
        $categories[] = 'payment';
    }
    
    return $categories;
}
```

### Date Navigation

Date filtering uses URL parameters with validation:

```php
// Parse and validate date parameter
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Calculate previous/next dates
$prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
$canGoNext = $nextDate <= date('Y-m-d'); // Don't allow future dates
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: KPI Accuracy

*For any* valid date, the displayed KPI metrics (Total Actions, Active Staff, Lead Actions, Reservation/Payment Actions) should exactly match the counts derived from querying the `staff_activity_log` table filtered by that date.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5**

### Property 2: Staff Card Completeness

*For any* active staff member, the dashboard should display exactly one Staff_Card containing their name, role, and all four action count categories (Total, Leads, Reservations, Payments).

**Validates: Requirements 4.1, 4.2, 4.3**

### Property 3: Action Count Accuracy

*For any* staff member and date combination, the action counts displayed on their Staff_Card should exactly match the counts from the activity log filtered by that user_id and date, with correct categorization by keyword matching.

**Validates: Requirements 4.3, 8.1, 8.2, 8.3**

### Property 4: Multi-Category Action Counting

*For any* action that contains multiple category keywords (e.g., an action containing both 'reservation' and 'payment'), the action should be counted in all applicable category totals.

**Validates: Requirements 8.4**

### Property 5: Active Status Threshold

*For any* staff member, if their most recent activity timestamp is within 15 minutes of the current server time, their Active_Status should display "Active Now" with a green indicator; otherwise, it should display relative time ("Last seen Xm/h/d ago") with a red indicator.

**Validates: Requirements 5.1, 5.2, 5.3, 5.4**

### Property 6: Timeline Chronological Ordering

*For any* staff member and date, the Timeline_Modal should display activities in reverse chronological order, with the most recent activity appearing first.

**Validates: Requirements 6.2, 7.3**

### Property 7: Timeline Data Completeness

*For any* activity entry in the timeline, the rendered output should include the timestamp (formatted in 12-hour AM/PM format), action type, and description.

**Validates: Requirements 6.3, 7.4, 9.5**

### Property 8: Timeline Date Filtering

*For any* user_id and date combination, the Timeline_Modal should display only activities where the activity's created_at date matches the selected date.

**Validates: Requirements 6.5, 7.2**

### Property 9: Icon Assignment Consistency

*For any* activity entry, the assigned icon should be determined by keyword matching on the action field, with payment actions receiving payment icons, lead/client actions receiving user-profile icons, and other actions receiving default icons.

**Validates: Requirements 9.2, 9.3, 9.4**

### Property 10: Date Parameter Validation

*For any* URL parameter value, if it matches the YYYY-MM-DD format, the dashboard should use that date for filtering; otherwise, it should default to the current date.

**Validates: Requirements 3.1, 3.4**

### Property 11: Authorization Enforcement

*For any* non-admin user, access to the Staff_Monitor_Dashboard should be granted if and only if the user has the `view_staff_monitor` permission in their permissions list.

**Validates: Requirements 1.2, 1.3**

## Error Handling

### Authentication Failures

**Scenario:** Unauthenticated user attempts to access dashboard
- **Detection:** `auth_check()` function verifies session
- **Response:** Redirect to login page via `redirect('../auth/login.php')`
- **User Feedback:** Standard login page with return URL preservation

**Scenario:** Authenticated user lacks required permissions
- **Detection:** Check `$_currentUser['role'] === 'admin'` OR `in_array('view_staff_monitor', $cuPerms)`
- **Response:** Redirect to main dashboard `redirect('../index.php')`
- **User Feedback:** Flash message "You don't have permission to access this page"

### Data Retrieval Errors

**Scenario:** Database connection failure
- **Detection:** PDO exception during query execution
- **Response:** Catch exception, log error, display user-friendly message
- **User Feedback:** "Unable to load dashboard data. Please try again later."
- **Fallback:** Display empty state with retry button

**Scenario:** Invalid date parameter
- **Detection:** Regex validation fails on `$_GET['date']`
- **Response:** Silently default to current date
- **User Feedback:** None (graceful degradation)
- **Logging:** No logging needed (user input sanitization)

**Scenario:** Invalid user_id in AJAX request
- **Detection:** `(int) $_GET['user_id']` results in 0 or non-existent user
- **Response:** Return HTTP 400 with error message
- **User Feedback:** Timeline modal shows "Invalid user ID"

### Edge Cases

**Empty Activity Log:**
- **Scenario:** No activities recorded for selected date
- **Handling:** Display KPIs with zero values, show empty state message
- **UI:** "No staff activity recorded for this date"

**Staff Member with No Activity:**
- **Scenario:** Active staff member has zero actions on selected date
- **Handling:** Display card with all counts as zero
- **UI:** Show "0" for all categories, "No activity" for last seen

**Future Date Selection:**
- **Scenario:** User manually enters future date in URL
- **Handling:** Allow display (may show zero data), but disable "next day" navigation
- **UI:** Next arrow button disabled when date equals today

**Timeline AJAX Timeout:**
- **Scenario:** Network delay or server timeout loading timeline
- **Handling:** JavaScript catch block displays error message
- **UI:** "Failed to load timeline. Please try again."
- **Recovery:** User can close modal and retry

### Input Validation

**Date Parameter:**
```php
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}
// Additional validation: ensure date is not in far future/past
$timestamp = strtotime($selectedDate);
if ($timestamp === false || $timestamp < strtotime('2020-01-01') || $timestamp > strtotime('+1 year')) {
    $selectedDate = date('Y-m-d');
}
```

**User ID Parameter (AJAX):**
```php
$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid user ID']));
}

// Verify user exists and is staff
$userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'staff'");
$userCheck->execute([$userId]);
if (!$userCheck->fetch()) {
    http_response_code(404);
    exit(json_encode(['error' => 'User not found']));
}
```

## Testing Strategy

### Dual Testing Approach

This feature requires both unit testing and property-based testing to ensure comprehensive correctness:

- **Unit tests** verify specific examples, edge cases, and error conditions
- **Property tests** verify universal properties across all inputs
- Both approaches are complementary and necessary for full coverage

### Property-Based Testing

**Library:** We will use [PHPUnit with Eris](https://github.com/giorgiosironi/eris) for property-based testing in PHP.

**Configuration:**
- Each property test must run a minimum of 100 iterations
- Each test must include a comment tag referencing the design property
- Tag format: `// Feature: staff-monitor-dashboard, Property {number}: {property_text}`

**Property Test Examples:**

```php
// Feature: staff-monitor-dashboard, Property 1: KPI Accuracy
public function testKPIAccuracyForAnyDate() {
    $this->forAll(Generator\date())
        ->then(function ($date) {
            $expected = $this->countActivitiesForDate($date);
            $displayed = $this->getDisplayedKPIs($date);
            $this->assertEquals($expected, $displayed);
        });
}

// Feature: staff-monitor-dashboard, Property 3: Action Count Accuracy
public function testActionCountsMatchActivityLog() {
    $this->forAll(
        Generator\int(1, 100), // user_id
        Generator\date()
    )->then(function ($userId, $date) {
        $expected = $this->getActivityCountsFromDB($userId, $date);
        $displayed = $this->getDisplayedCounts($userId, $date);
        $this->assertEquals($expected, $displayed);
    });
}

// Feature: staff-monitor-dashboard, Property 5: Active Status Threshold
public function testActiveStatusThreshold() {
    $this->forAll(Generator\int(0, 1440)) // minutes ago
        ->then(function ($minutesAgo) {
            $timestamp = date('Y-m-d H:i:s', strtotime("-{$minutesAgo} minutes"));
            $status = getActiveStatus($timestamp);
            
            if ($minutesAgo <= 15) {
                $this->assertEquals('active', $status['status']);
                $this->assertEquals('Active Now', $status['text']);
                $this->assertEquals('green', $status['color']);
            } else {
                $this->assertEquals('inactive', $status['status']);
                $this->assertEquals('red', $status['color']);
                $this->assertStringContainsString('Last seen', $status['text']);
            }
        });
}
```

### Unit Testing

**Focus Areas:**
- Authentication and authorization edge cases
- Date parameter validation with malformed inputs
- Empty state handling (no activities, no staff)
- AJAX error responses (invalid user_id, missing parameters)
- Action categorization with specific keyword combinations
- Time formatting edge cases (midnight, timezone boundaries)

**Example Unit Tests:**

```php
public function testUnauthenticatedUserRedirects() {
    unset($_SESSION['user']);
    $response = $this->get('/staff_monitor/index.php');
    $this->assertRedirect('/auth/login.php');
}

public function testUserWithoutPermissionDenied() {
    $this->actingAs($this->createStaffUser(['permissions' => []]));
    $response = $this->get('/staff_monitor/index.php');
    $this->assertRedirect('/index.php');
}

public function testEmptyActivityLogShowsZeros() {
    $this->clearActivityLog();
    $response = $this->get('/staff_monitor/index.php');
    $this->assertSee('0'); // Total Actions
    $this->assertSee('No staff activity recorded');
}

public function testInvalidDateDefaultsToToday() {
    $response = $this->get('/staff_monitor/index.php?date=invalid');
    $this->assertQueryParameter('date', date('Y-m-d'));
}

public function testMultiCategoryActionCounting() {
    $this->createActivity(['action' => 'reservation_payment_lead']);
    $counts = $this->getActionCounts();
    $this->assertEquals(1, $counts['total']);
    $this->assertEquals(1, $counts['leads']);
    $this->assertEquals(1, $counts['reservations']);
    $this->assertEquals(1, $counts['payments']);
}
```

### Integration Testing

**Timeline Modal Flow:**
1. Load dashboard with test data
2. Simulate click on staff card
3. Verify AJAX request sent with correct parameters
4. Mock AJAX response with sample timeline HTML
5. Verify modal opens and displays content
6. Verify close button dismisses modal

**Date Navigation Flow:**
1. Load dashboard for specific date
2. Click previous day arrow
3. Verify URL updates with new date parameter
4. Verify KPIs recalculate for new date
5. Verify staff cards update with new date's data

### Test Data Setup

**Fixtures:**
```php
// Create test staff members
$staff1 = createStaff(['name' => 'John Doe', 'role' => 'Sales']);
$staff2 = createStaff(['name' => 'Jane Smith', 'role' => 'Operations']);

// Create activity log entries
createActivity([
    'user_id' => $staff1->id,
    'action' => 'created_lead',
    'description' => 'Added lead for Client A',
    'created_at' => '2026-03-30 09:30:00'
]);

createActivity([
    'user_id' => $staff1->id,
    'action' => 'created_reservation',
    'description' => 'Created reservation #123',
    'created_at' => '2026-03-30 10:15:00'
]);

createActivity([
    'user_id' => $staff2->id,
    'action' => 'payment_received',
    'description' => 'Received payment $500',
    'created_at' => '2026-03-30 11:00:00'
]);
```

### Performance Testing

**Load Scenarios:**
- 50 active staff members with 100 activities each (5,000 total records)
- Date range queries spanning 30 days
- Concurrent timeline modal requests from multiple users

**Performance Targets:**
- Dashboard page load: < 2 seconds
- Timeline AJAX response: < 500ms
- KPI calculation query: < 200ms

**Optimization Strategies:**
- Add database indexes on `staff_activity_log(user_id, created_at)`
- Add index on `staff_activity_log(created_at)` for date filtering
- Use prepared statements for all queries
- Implement query result caching for frequently accessed dates

