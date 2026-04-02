<?php
require_once __DIR__ . '/../config/db.php';

$pdo = db();

// Authentication check
auth_check();

// Permission validation (admin OR view_staff_monitor)
$_currentUser = current_user();
$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$cuPerms = $_currentUser['permissions'] ?? [];

if (!$isAdmin && !in_array('view_staff_monitor', $cuPerms, true)) {
    flash('error', "You don't have permission to access this page");
    redirect('../index.php');
}

// Date parameter handling with validation (YYYY-MM-DD format)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Additional validation: ensure date is valid and not too far in past/future
$timestamp = strtotime($selectedDate);
if ($timestamp === false || $timestamp < strtotime('2020-01-01') || $timestamp > strtotime('+1 year')) {
    $selectedDate = date('Y-m-d');
}

// KPI Data Aggregation Queries

// Total Actions count for selected date
$totalActionsStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_activity_log WHERE DATE(created_at) = ?");
$totalActionsStmt->execute([$selectedDate]);
$totalActions = (int) $totalActionsStmt->fetchColumn();

// Active Staff count (distinct user_ids with activity on selected date)
$activeStaffStmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM staff_activity_log WHERE DATE(created_at) = ?");
$activeStaffStmt->execute([$selectedDate]);
$activeStaff = (int) $activeStaffStmt->fetchColumn();

// Lead Actions count (actions containing 'lead' keyword)
$leadActionsStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_activity_log WHERE DATE(created_at) = ? AND action LIKE '%lead%'");
$leadActionsStmt->execute([$selectedDate]);
$leadActions = (int) $leadActionsStmt->fetchColumn();

// Reservation/Payment Actions count (actions containing 'reservation' OR 'payment' keywords)
$reservationPaymentStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_activity_log WHERE DATE(created_at) = ? AND (action LIKE '%reservation%' OR action LIKE '%payment%')");
$reservationPaymentStmt->execute([$selectedDate]);
$reservationPaymentActions = (int) $reservationPaymentStmt->fetchColumn();

// Active Status Calculation Function
function getActiveStatus($lastActivity) {
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

// Staff Activity Aggregation Query
// Join users, staff, and staff_activity_log tables
// Group by user_id and count actions by category
// Calculate last_activity timestamp per staff member
// Filter for active staff members only
$staffDataStmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.username,
        COALESCE(s.name, u.username) as name,
        COALESCE(s.role, u.role) as role,
        COUNT(sal.id) as total_actions,
        COALESCE(SUM(CASE WHEN sal.action LIKE '%lead%' THEN 1 ELSE 0 END), 0) as lead_actions,
        COALESCE(SUM(CASE WHEN sal.action LIKE '%reservation%' THEN 1 ELSE 0 END), 0) as reservation_actions,
        COALESCE(SUM(CASE WHEN sal.action LIKE '%payment%' THEN 1 ELSE 0 END), 0) as payment_actions,
        MAX(sal.created_at) as last_activity
    FROM users u
    LEFT JOIN staff s ON s.id = u.staff_id
    LEFT JOIN staff_activity_log sal ON sal.user_id = u.id AND DATE(sal.created_at) = ?
    WHERE u.is_active = 1
    GROUP BY u.id, u.username, s.name, s.role
    ORDER BY total_actions DESC, COALESCE(s.name, u.username) ASC
");
$staffDataStmt->execute([$selectedDate]);
$staffMembers = $staffDataStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Staff Monitor Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-white text-xl font-light">Staff Monitor Dashboard</h2>
            <p class="text-mb-subtle text-sm mt-0.5">Real-time staff activity tracking</p>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="flex items-center justify-center gap-3 bg-mb-surface border border-mb-subtle/20 rounded-xl px-4 py-3 w-fit">
        <?php
        $prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));
        $canGoNext = $nextDate <= date('Y-m-d');
        ?>
        <a href="?date=<?= e($prevDate) ?>" 
           class="text-mb-subtle hover:text-white transition-colors p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-mb-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-white font-medium"><?= date('d M Y', strtotime($selectedDate)) ?></span>
            <?php if ($selectedDate === date('Y-m-d')): ?>
                <span class="text-[10px] bg-mb-accent/20 text-mb-accent px-2 py-0.5 rounded-full">Today</span>
            <?php endif; ?>
        </div>
        
        <?php if ($canGoNext): ?>
            <a href="?date=<?= e($nextDate) ?>" 
               class="text-mb-subtle hover:text-white transition-colors p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        <?php else: ?>
            <span class="text-mb-subtle/30 p-1 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        <?php endif; ?>
    </div>

    <!-- KPI Panel -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Total Actions</p>
            <p class="text-white text-3xl font-light"><?= e($totalActions) ?></p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Active Staff</p>
            <p class="text-white text-3xl font-light"><?= e($activeStaff) ?></p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Lead Actions</p>
            <p class="text-white text-3xl font-light"><?= e($leadActions) ?></p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Reservation/Payment Actions</p>
            <p class="text-white text-3xl font-light"><?= e($reservationPaymentActions) ?></p>
        </div>
    </div>

    <!-- Staff Grid -->
    <?php if (empty($staffMembers)): ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
            <p class="text-center text-mb-subtle py-10">No staff activity recorded for this date</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($staffMembers as $staff): ?>
                <?php
                $activeStatus = getActiveStatus($staff['last_activity']);
                $initials = '';
                $nameParts = explode(' ', $staff['name']);
                foreach ($nameParts as $part) {
                    if (!empty($part)) {
                        $initials .= strtoupper($part[0]);
                    }
                }
                $initials = substr($initials, 0, 2); // Max 2 characters
                ?>
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 hover:border-mb-subtle/40 transition-colors cursor-pointer"
                     onclick="openTimelineModal(<?= e($staff['user_id']) ?>)">
                    <!-- Staff Header -->
                    <div class="flex items-start gap-3 mb-4">
                        <!-- Avatar/Initials -->
                        <div class="w-12 h-12 rounded-full bg-mb-accent/20 flex items-center justify-center flex-shrink-0">
                            <span class="text-mb-accent font-medium text-sm"><?= e($initials) ?></span>
                        </div>
                        
                        <!-- Name and Role -->
                        <div class="flex-1 min-w-0">
                            <h3 class="text-white font-medium truncate"><?= e($staff['name']) ?></h3>
                            <p class="text-mb-subtle text-sm truncate"><?= e($staff['role']) ?></p>
                        </div>
                        
                        <!-- Active Status Indicator -->
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <div class="w-2 h-2 rounded-full <?= $activeStatus['color'] === 'green' ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                            <span class="text-xs <?= $activeStatus['color'] === 'green' ? 'text-green-400' : 'text-red-400' ?>">
                                <?= e($activeStatus['text']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Action Count Boxes -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-mb-bg/50 rounded-lg p-3">
                            <p class="text-mb-subtle text-xs mb-1">Total</p>
                            <p class="text-white text-xl font-light"><?= e($staff['total_actions']) ?></p>
                        </div>
                        <div class="bg-mb-bg/50 rounded-lg p-3">
                            <p class="text-mb-subtle text-xs mb-1">Leads</p>
                            <p class="text-white text-xl font-light"><?= e($staff['lead_actions']) ?></p>
                        </div>
                        <div class="bg-mb-bg/50 rounded-lg p-3">
                            <p class="text-mb-subtle text-xs mb-1">Reservations</p>
                            <p class="text-white text-xl font-light"><?= e($staff['reservation_actions']) ?></p>
                        </div>
                        <div class="bg-mb-bg/50 rounded-lg p-3">
                            <p class="text-mb-subtle text-xs mb-1">Payments</p>
                            <p class="text-white text-xl font-light"><?= e($staff['payment_actions']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Timeline Modal -->
<div id="timeline-modal" class="fixed inset-y-0 right-0 w-full sm:w-96 bg-mb-surface border-l border-mb-subtle/20 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-50 overflow-y-auto">
    <div class="sticky top-0 bg-mb-surface border-b border-mb-subtle/20 p-4 flex items-center justify-between">
        <h3 class="text-white font-medium">Activity Timeline</h3>
        <button onclick="closeTimelineModal()" class="text-mb-subtle hover:text-white transition-colors p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <div id="timeline-content" class="p-4">
        <!-- Timeline content will be loaded here -->
    </div>
</div>

<!-- Overlay for modal -->
<div id="timeline-overlay" class="fixed inset-0 bg-black/50 opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

<script>
function openTimelineModal(userId) {
    const modal = document.getElementById('timeline-modal');
    const overlay = document.getElementById('timeline-overlay');
    const content = document.getElementById('timeline-content');
    const urlParams = new URLSearchParams(window.location.search);
    const date = urlParams.get('date') || new Date().toISOString().split('T')[0];
    
    // Show loading state
    content.innerHTML = '<div class="text-center py-8 text-mb-subtle">Loading...</div>';
    
    // Show modal and overlay
    modal.classList.remove('translate-x-full');
    overlay.classList.remove('opacity-0', 'pointer-events-none');
    
    // Fetch timeline data
    fetch(`ajax_timeline.php?user_id=${userId}&date=${date}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load timeline');
            }
            return response.text();
        })
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="text-center py-8 text-red-400">Failed to load timeline. Please try again.</div>';
        });
}

function closeTimelineModal() {
    const modal = document.getElementById('timeline-modal');
    const overlay = document.getElementById('timeline-overlay');
    modal.classList.add('translate-x-full');
    overlay.classList.add('opacity-0', 'pointer-events-none');
}

// Close modal when clicking overlay
document.getElementById('timeline-overlay').addEventListener('click', closeTimelineModal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
