<?php
/**
 * staff_monitor/ajax_timeline.php - AJAX handler for staff activity timeline
 * Returns HTML timeline content for a specific staff member and date
 */
require_once __DIR__ . '/../config/db.php';

// Authentication check
auth_check();

// Permission validation (admin OR view_staff_monitor)
$_currentUser = current_user();
$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$cuPerms = $_currentUser['permissions'] ?? [];

if (!$isAdmin && !in_array('view_staff_monitor', $cuPerms, true)) {
    http_response_code(403);
    exit('Access denied');
}

// Validate user_id parameter
$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    exit('Invalid user_id parameter');
}

// Validate date parameter
$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('Invalid date parameter');
}

// Get database connection
$pdo = db();

// Query timeline data
$stmt = $pdo->prepare("
    SELECT action, description, created_at 
    FROM staff_activity_log 
    WHERE user_id = ? AND DATE(created_at) = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId, $date]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Action icon mapping function
function getActionIcon($action) {
    $action_lower = strtolower($action);
    
    if (stripos($action, 'delivery') !== false || stripos($action, 'deliver') !== false) {
        return '🚗';
    }
    if (stripos($action, 'return') !== false) {
        return '🔄';
    }
    if (stripos($action, 'payment') !== false || stripos($action, 'paid') !== false) {
        return '💰';
    }
    if (stripos($action, 'reservation') !== false || stripos($action, 'booking') !== false) {
        return '📋';
    }
    if (stripos($action, 'lead') !== false || stripos($action, 'client') !== false) {
        return '👤';
    }
    
    return '📌'; // default icon
}

// Render timeline HTML
if (empty($activities)) {
    echo '<div class="text-center py-8 text-mb-subtle">No activity recorded for this date</div>';
} else {
    echo '<div class="space-y-4">';
    
    foreach ($activities as $index => $activity) {
        $icon = getActionIcon($activity['action']);
        $time = date('h:i A', strtotime($activity['created_at']));
        $isLast = ($index === count($activities) - 1);
        
        echo '<div class="flex gap-3">';
        
        // Timeline line and icon
        echo '<div class="flex flex-col items-center">';
        echo '<div class="w-8 h-8 rounded-full bg-mb-accent/20 flex items-center justify-center flex-shrink-0">';
        echo '<span class="text-base">' . e($icon) . '</span>';
        echo '</div>';
        
        if (!$isLast) {
            echo '<div class="w-0.5 flex-1 bg-mb-subtle/20 mt-1"></div>';
        }
        
        echo '</div>';
        
        // Content
        echo '<div class="flex-1 pb-6">';
        echo '<div class="flex items-center gap-2 mb-1">';
        echo '<span class="text-white font-medium text-sm">' . e($activity['action']) . '</span>';
        echo '<span class="text-mb-subtle text-xs">' . e($time) . '</span>';
        echo '</div>';
        
        if (!empty($activity['description'])) {
            echo '<p class="text-mb-subtle text-sm">' . e($activity['description']) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}
