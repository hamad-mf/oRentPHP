<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gps_helpers.php';
require_once __DIR__ . '/../includes/activity_log.php';

$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));
gps_tracking_ensure_schema($pdo);

$search = trim($_GET['search'] ?? '');
$tracking = $_GET['tracking'] ?? '';
$todayDate = app_today_sql();

// Date filtering for history
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$historyOffset = (int) ($_GET['history_offset'] ?? 0);

// Validate and format dates - allow any valid date including future dates
if ($startDate) {
    $ts = strtotime($startDate);
    if ($ts !== false) {
        $startDate = date('Y-m-d', $ts);
    } else {
        $startDate = '';
    }
}
if ($endDate) {
    $ts = strtotime($endDate);
    if ($ts !== false) {
        $endDate = date('Y-m-d', $ts);
    } else {
        $endDate = '';
    }
}

// Default to last 5 days only if no dates specified
if (!$startDate && !$endDate) {
    $endDate = $todayDate;
    $startDate = date('Y-m-d', strtotime($todayDate . ' -4 days'));
}

$gpsDailyTableExists = false;
try {
    $gpsDailyTableExists = (int) $pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'gps_daily_checks'
    ")->fetchColumn() > 0;
} catch (Throwable $e) {
    app_log('ERROR', 'GPS daily checks table verify failed: ' . $e->getMessage());
}
$gpsDailyWarning = $gpsDailyTableExists ? '' : 'GPS daily checks table missing. Apply the latest GPS daily checks migration before saving.';

$allowedTracking = ['', 'yes', 'no'];
if (!in_array($tracking, $allowedTracking, true)) {
    $tracking = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fSearch = trim((string) ($_POST['f_search'] ?? ''));
    $fTracking = (string) ($_POST['f_tracking'] ?? '');
    $fPage = max(1, (int) ($_POST['f_page'] ?? 1));
    $isSingleSave = isset($_POST['save_single']) && $_POST['save_single'] === '1';
    $singleReservationId = $isSingleSave ? (int) ($_POST['single_reservation_id'] ?? 0) : 0;

    if (!in_array($fTracking, $allowedTracking, true)) {
        $fTracking = '';
    }

    $redirectParams = array_filter(
        [
            'search' => $fSearch,
            'tracking' => $fTracking,
            'page' => $fPage > 1 ? $fPage : null,
        ],
        static fn($value) => $value !== '' && $value !== null
    );
    $redirectUrl = 'index.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : '');

    if (!$gpsDailyTableExists) {
        if ($isSingleSave) {
            http_response_code(400);
            echo json_encode(['error' => 'GPS daily checks table missing.']);
            exit;
        }
        flash('error', 'GPS daily checks table missing. Please apply the GPS daily checks migration first.');
        redirect($redirectUrl);
    }

    $payload = $_POST['gps'] ?? [];
    if (!is_array($payload) || empty($payload)) {
        if ($isSingleSave) {
            http_response_code(400);
            echo json_encode(['error' => 'No GPS checks selected to save.']);
            exit;
        }
        flash('error', 'No GPS checks selected to save.');
        redirect($redirectUrl);
    }

    $errors = [];
    $updatedCount = 0;
    $nowSql = app_now_sql();
    $userId = (int) ($_SESSION['user']['id'] ?? 0);
    $reservationCache = [];

    $resStmt = $pdo->prepare("
        SELECT r.id, r.vehicle_id, c.name AS client_name, v.brand, v.model, v.license_plate
        FROM reservations r
        JOIN clients c ON c.id = r.client_id
        JOIN vehicles v ON v.id = r.vehicle_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $upsertDaily = $pdo->prepare("
        INSERT INTO gps_daily_checks
            (reservation_id, vehicle_id, check_date, check_slot, tracking_active, last_location, notes, updated_by, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            tracking_active = VALUES(tracking_active),
            last_location = VALUES(last_location),
            notes = VALUES(notes),
            updated_by = VALUES(updated_by),
            updated_at = VALUES(updated_at)
    ");
    $insHistory = $pdo->prepare("
        INSERT INTO gps_tracking (reservation_id, vehicle_id, last_location, tracking_active, notes, last_seen, updated_by, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($payload as $reservationId => $data) {
        $reservationId = (int) $reservationId;
        if ($reservationId <= 0 || !is_array($data)) {
            continue;
        }

        $lastLocation = trim((string) ($data['last_location'] ?? ''));
        if (strlen($lastLocation) > 255) {
            $errors[] = "Location is too long for reservation #{$reservationId}.";
            continue;
        }

        $slots = $data['slots'] ?? [];
        foreach ([1, 2, 3] as $slot) {
            $slotData = $slots[$slot] ?? null;
            if (!is_array($slotData)) {
                continue;
            }
            
            // Get descriptive slot names
            $slotNames = [
                1 => 'Morning (6AM-12PM)',
                2 => 'Afternoon (12PM-6PM)', 
                3 => 'Evening (6PM-10PM)'
            ];
            $slotName = $slotNames[$slot] ?? "Slot {$slot}";
            
            $trackingValue = (string) ($slotData['tracking_active'] ?? '');
            if ($trackingValue !== '1' && $trackingValue !== '0') {
                continue;
            }
            $trackingActive = $trackingValue === '0' ? 0 : 1;
            $inactiveReason = trim((string) ($slotData['reason'] ?? ''));

            if (strlen($inactiveReason) > 500) {
                $errors[] = "Reason is too long for reservation #{$reservationId} ({$slotName}).";
                continue;
            }
            if ($trackingActive === 0 && $inactiveReason === '') {
                $errors[] = "Reason required for reservation #{$reservationId} ({$slotName}).";
                continue;
            }

            if (!isset($reservationCache[$reservationId])) {
                $resStmt->execute([$reservationId]);
                $reservationCache[$reservationId] = $resStmt->fetch();
            }
            $reservation = $reservationCache[$reservationId];
            if (!$reservation) {
                $errors[] = "Reservation #{$reservationId} not found.";
                continue;
            }

            $upsertDaily->execute([
                $reservationId,
                (int) $reservation['vehicle_id'],
                $todayDate,
                $slot,
                $trackingActive,
                $lastLocation !== '' ? $lastLocation : null,
                $inactiveReason !== '' ? $inactiveReason : null,
                $userId ?: null,
                $nowSql
            ]);

            $insHistory->execute([
                $reservationId,
                (int) $reservation['vehicle_id'],
                $lastLocation !== '' ? $lastLocation : null,
                $trackingActive,
                $inactiveReason !== '' ? $inactiveReason : null,
                $nowSql,
                $userId ?: null,
                $nowSql
            ]);

            $updatedCount++;
        }
    }

    if ($errors) {
        if ($isSingleSave) {
            http_response_code(400);
            echo json_encode(['error' => $errors[0] . (count($errors) > 1 ? ' (and more)' : '')]);
            exit;
        }
        flash('error', $errors[0] . (count($errors) > 1 ? ' (and more)' : ''));
    }
    if ($updatedCount > 0) {
        if ($isSingleSave) {
            echo json_encode(['success' => true, 'message' => "Saved {$updatedCount} GPS check(s) for today."]);
            exit;
        }
        flash('success', "Saved {$updatedCount} GPS check(s) for today.");
    } elseif (!$errors) {
        if ($isSingleSave) {
            http_response_code(400);
            echo json_encode(['error' => 'No GPS checks selected to save.']);
            exit;
        }
        flash('error', 'No GPS checks selected to save.');
    }

    redirect($redirectUrl);
}

// Count active rentals
$activeCount = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn();

$latestGpsSql = gps_latest_tracking_join_sql();
$trackingCounts = ['all' => $activeCount, 'yes' => 0, 'no' => 0];
try {
    $trackingCounts['yes'] = (int) $pdo->query("
        SELECT COUNT(*)
        FROM reservations r
        LEFT JOIN ($latestGpsSql) g ON g.reservation_id = r.id
        WHERE r.status = 'active' AND COALESCE(g.tracking_active, 1) = 1
    ")->fetchColumn();
    $trackingCounts['no'] = (int) $pdo->query("
        SELECT COUNT(*)
        FROM reservations r
        LEFT JOIN ($latestGpsSql) g ON g.reservation_id = r.id
        WHERE r.status = 'active' AND COALESCE(g.tracking_active, 1) = 0
    ")->fetchColumn();
} catch (Throwable $e) {
    app_log('ERROR', 'GPS tracking count query failed: ' . $e->getMessage());
}

$where = ["r.status = 'active'"];
$params = [];

if ($search !== '') {
    $where[] = '(c.name LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.license_plate LIKE ? OR r.id = ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = ctype_digit($search) ? (int) $search : -1;
}
if ($tracking === 'yes') {
    $where[] = 'COALESCE(g.tracking_active, 1) = 1';
}
if ($tracking === 'no') {
    $where[] = 'COALESCE(g.tracking_active, 1) = 0';
}

$hasUsersTable = false;
try {
    $hasUsersTable = (int) $pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    ")->fetchColumn() > 0;
} catch (Throwable $e) {
    app_log('ERROR', 'Could not verify users table for GPS updater join: ' . $e->getMessage());
}

$updatedBySelect = $hasUsersTable
    ? ", u.name AS updated_by_name, u.username AS updated_by_username"
    : ", NULL AS updated_by_name, NULL AS updated_by_username";
$updatedByJoin = $hasUsersTable ? "LEFT JOIN users u ON u.id = g.updated_by" : "";

$latestGpsSql = gps_latest_tracking_join_sql();
$baseFrom = "
    FROM reservations r
    JOIN clients c ON c.id = r.client_id
    JOIN vehicles v ON v.id = r.vehicle_id
    LEFT JOIN ($latestGpsSql) g ON g.reservation_id = r.id
";
$whereSql = " WHERE " . implode(' AND ', $where);

$sql = "
    SELECT
        r.id,
        r.status,
        r.start_date,
        r.end_date,
        r.delivery_location AS res_delivery_location,
        c.id AS client_id,
        c.name AS client_name,
        v.id AS vehicle_id,
        v.brand,
        v.model,
        v.license_plate,
        g.last_location,
        g.tracking_active,
        g.notes,
        g.updated_by,
        g.last_seen,
        g.updated_at
        $updatedBySelect
    $baseFrom
    $updatedByJoin
    $whereSql
    ORDER BY r.created_at DESC
";

$gpsCountSql = "SELECT COUNT(*) $baseFrom $whereSql";
$pgGps = paginate_query($pdo, $sql, $gpsCountSql, $params, $page, $perPage);
$rows = $pgGps['rows'];

// DEBUG: Show what we found
if (empty($rows)) {
    echo "<div class='bg-yellow-100 border border-yellow-400 text-yellow-800 p-4 mb-4 rounded'>";
    echo "<h3>DEBUG INFO:</h3>";
    echo "<p><strong>Total reservations found:</strong> " . $pgGps['total'] . "</p>";
    echo "<p><strong>SQL Query:</strong> " . htmlspecialchars($sql) . "</p>";
    echo "<p><strong>Parameters:</strong> " . print_r($params, true) . "</p>";
    
    // Check all reservation statuses
    $allRes = $pdo->query("SELECT id, status FROM reservations ORDER BY id DESC LIMIT 5")->fetchAll();
    echo "<p><strong>Recent Reservations:</strong></p><ul>";
    foreach ($allRes as $res) {
        echo "<li>ID {$res['id']}: Status = '{$res['status']}'</li>";
    }
    echo "</ul>";
    echo "</div>";
}

$todayChecks = [];
if ($gpsDailyTableExists && !empty($rows)) {
    $reservationIds = array_map(static fn($r) => (int) $r['id'], $rows);
    $reservationIds = array_values(array_filter($reservationIds, static fn($id) => $id > 0));
    if ($reservationIds) {
        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $gpsUpdatedBySelect = $hasUsersTable
            ? ", u.name AS updated_by_name, u.username AS updated_by_username"
            : ", NULL AS updated_by_name, NULL AS updated_by_username";
        $gpsUpdatedByJoin = $hasUsersTable ? "LEFT JOIN users u ON u.id = d.updated_by" : "";
        $todayStmt = $pdo->prepare("
            SELECT d.reservation_id, d.check_slot, d.tracking_active, d.last_location, d.notes, d.updated_by, d.updated_at
                $gpsUpdatedBySelect
            FROM gps_daily_checks d
            $gpsUpdatedByJoin
            WHERE d.check_date = ?
              AND d.reservation_id IN ($placeholders)
        ");
        $todayStmt->execute(array_merge([$todayDate], $reservationIds));
        foreach ($todayStmt->fetchAll() as $check) {
            $resId = (int) ($check['reservation_id'] ?? 0);
            $slot = (int) ($check['check_slot'] ?? 0);
            if ($resId > 0 && $slot > 0) {
                $todayChecks[$resId][$slot] = $check;
            }
        }
    }
    
    // Get GPS history with date filtering - PER RESERVATION
    $gpsHistoryByReservation = [];
    if ($reservationIds) {
        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $historyWhere = ['d.reservation_id IN (' . $placeholders . ')'];
        $historyParams = $reservationIds;
        
        if ($startDate) {
            $historyWhere[] = 'd.check_date >= ?';
            $historyParams[] = $startDate;
        }
        if ($endDate) {
            $historyWhere[] = 'd.check_date <= ?';
            $historyParams[] = $endDate;
        }
        
        $historyWhereSql = 'WHERE ' . implode(' AND ', $historyWhere);
        
        $historySql = "
            SELECT 
                d.id,
                d.reservation_id,
                d.vehicle_id,
                d.check_date,
                d.check_slot,
                d.tracking_active,
                d.last_location,
                d.notes,
                d.updated_by,
                d.updated_at
            FROM gps_daily_checks d
            $historyWhereSql
            ORDER BY d.check_date DESC, d.check_slot DESC
        ";
        
        $historyStmt = $pdo->prepare($historySql);
        $historyStmt->execute($historyParams);
        foreach ($historyStmt->fetchAll() as $check) {
            $resId = (int) $check['reservation_id'];
            $gpsHistoryByReservation[$resId][] = $check;
        }
    }
}

if ($gpsDailyTableExists) {
    try {
        // Check if GPS pending notifications are enabled
        $notifyGpsPending = settings_get($pdo, 'notify_gps_pending', '1') !== '0';
        
        if ($notifyGpsPending) {
            $missingStmt = $pdo->prepare("
                SELECT r.id, c.name AS client_name, v.brand, v.model, COUNT(d.id) AS check_count
                FROM reservations r
                JOIN clients c ON c.id = r.client_id
                JOIN vehicles v ON v.id = r.vehicle_id
                LEFT JOIN gps_daily_checks d
                  ON d.reservation_id = r.id
                 AND d.check_date = ?
                WHERE r.status = 'active'
                GROUP BY r.id
                HAVING check_count < 3
            ");
            $missingStmt->execute([$todayDate]);
            $missingRows = $missingStmt->fetchAll();
            if ($missingRows) {
                $notifExists = $pdo->prepare("
                    SELECT id FROM notifications
                    WHERE reservation_id = ?
                      AND type = 'info'
                      AND message LIKE ?
                      AND DATE(created_at) = ?
                    LIMIT 1
                ");
                $notifInsert = $pdo->prepare("
                    INSERT INTO notifications (type, message, reservation_id)
                    VALUES ('info', ?, ?)
                ");
                foreach ($missingRows as $row) {
                    $reservationId = (int) ($row['id'] ?? 0);
                    if ($reservationId <= 0) {
                        continue;
                    }
                    $completed = (int) ($row['check_count'] ?? 0);
                    $message = "GPS check pending: {$row['client_name']}'s {$row['brand']} {$row['model']} (Reservation #{$reservationId}) — {$completed}/3 completed today.";
                    $notifExists->execute([$reservationId, 'GPS check pending:%', $todayDate]);
                    if (!$notifExists->fetchColumn()) {
                        $notifInsert->execute([$message, $reservationId]);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'GPS pending check notification generation failed: ' . $e->getMessage());
    }
}

$pageTitle = 'GPS Tracking';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <?php if ($success = getFlash('success')): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error = getFlash('error')): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($gpsDailyWarning): ?>
        <div class="flex items-center gap-3 bg-yellow-500/10 border border-yellow-500/30 text-yellow-300 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M5.07 19h13.86L12 5 5.07 19z" />
            </svg>
            <?= e($gpsDailyWarning) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-1 bg-mb-surface border border-mb-subtle/20 rounded-xl p-1 w-fit flex-wrap">
        <?php
        $trackingTabs = ['' => 'All', 'yes' => 'At Location', 'no' => 'Not at Location'];
        foreach ($trackingTabs as $val => $lbl):
            $active = $tracking === $val;
            $countKey = $val === '' ? 'all' : $val;
            $query = array_filter(
                ['tracking' => $val, 'search' => $search],
                static fn($v) => $v !== '' && $v !== null
            );
            ?>
            <a href="?<?= http_build_query($query) ?>"
                class="px-4 py-2 rounded-lg text-sm transition-all <?= $active ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white hover:bg-mb-black/50' ?>">
                <?= $lbl ?> <span class="ml-1 text-xs opacity-70">
                    <?= (int) ($trackingCounts[$countKey] ?? 0) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
            <?php if ($tracking): ?><input type="hidden" name="tracking" value="<?= e($tracking) ?>">
            <?php endif; ?>
            <div class="relative flex-1 min-w-[220px] max-w-sm">
                <input type="text" name="search" value="<?= e($search) ?>"
                    placeholder="Search reservation, client, vehicle..."
                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-2 pl-10 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
                <svg class="w-4 h-4 text-mb-subtle absolute left-4 top-2.5" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <button type="submit" class="text-mb-silver hover:text-white text-sm transition-colors">Search</button>
            <?php if ($search || $tracking): ?><a href="index.php"
                    class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a>
            <?php endif; ?>
        </form>
        <a href="history.php" class="inline-flex items-center gap-2 bg-mb-surface border border-mb-subtle/20 px-4 py-2 rounded-lg text-sm text-mb-silver hover:text-white hover:border-mb-accent/30 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            History
        </a>
    </div>

    <?php $saveDisabled = !$gpsDailyTableExists ? 'disabled' : ''; ?>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <form method="POST" id="gpsBulkForm">
            <input type="hidden" name="f_search" value="<?= e($search) ?>">
            <input type="hidden" name="f_tracking" value="<?= e($tracking) ?>">
            <input type="hidden" name="f_page" value="<?= (int) $page ?>">
            <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="text-white font-light text-lg">GPS Tracking <span class="text-mb-subtle text-sm font-normal">(Active Rentals)</span></h3>
                    <p class="text-mb-subtle text-xs mt-1">Complete 3 GPS checks per day to ensure vehicle security and location tracking throughout the rental period.</p>
                </div>
                <button type="submit" <?= $saveDisabled ?>
                    class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-xs font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                    Save All
                </button>
            </div>
        <?php if (empty($rows)): ?>
            <div class="py-20 text-center">
                <svg class="w-16 h-16 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                        d="M12 2a7 7 0 00-7 7c0 4.5 7 13 7 13s7-8.5 7-13a7 7 0 00-7-7zm0 10a3 3 0 110-6 3 3 0 010 6z" />
                </svg>
                <p class="text-mb-subtle text-lg">No active rentals found.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                        <tr class="text-mb-subtle text-xs uppercase">
                            <th class="px-6 py-4 text-left">Reservation</th>
                            <th class="px-6 py-4 text-left">Client</th>
                            <th class="px-6 py-4 text-left">Vehicle</th>
                            <th class="px-6 py-4 text-left">Period</th>
                            <th class="px-6 py-4 text-left">Delivery Location</th>
                            <th class="px-6 py-4 text-left">Checks Today</th>
                            <th class="px-6 py-4 text-left">Last Updated</th>
                            <th class="px-6 py-4 text-left">Updated By</th>
                            <th class="px-6 py-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($rows as $row):
                            $reservationId = (int) $row['id'];
                            $todaySlotData = $todayChecks[$reservationId] ?? [];
                            $completedCount = count($todaySlotData);
                            $latestCheck = null;
                            $latestTs = 0;
                            foreach ($todaySlotData as $slotRow) {
                                $slotUpdated = $slotRow['updated_at'] ?? null;
                                $slotTs = $slotUpdated ? strtotime((string) $slotUpdated) : 0;
                                if ($slotTs > $latestTs) {
                                    $latestTs = $slotTs;
                                    $latestCheck = $slotRow;
                                }
                            }
                            $latestStatus = $latestCheck ? (int) $latestCheck['tracking_active'] : null;
                            $latestUpdated = $latestCheck['updated_at'] ?? null;
                            $updatedByLabel = '-';
                            if ($latestCheck) {
                                if (!empty($latestCheck['updated_by_name'])) {
                                    $updatedByLabel = (string) $latestCheck['updated_by_name'];
                                } elseif (!empty($latestCheck['updated_by_username'])) {
                                    $updatedByLabel = (string) $latestCheck['updated_by_username'];
                                } elseif (!empty($latestCheck['updated_by'])) {
                                    $updatedByLabel = 'User #' . (int) $latestCheck['updated_by'];
                                }
                            }
                            $displayLocation = trim((string) ($row['last_location'] ?? '')) !== ''
                                ? $row['last_location']
                                : ($row['res_delivery_location'] ?? '');
                            $detailRowId = 'gps-detail-' . $reservationId;
                            $summaryTone = $completedCount >= 3 ? 'text-green-400' : ($completedCount > 0 ? 'text-yellow-400' : 'text-red-400');
                            ?>
                            <tr class="hover:bg-mb-black/30 transition-colors">
                                <td class="px-6 py-4">
                                    <a href="<?= '../reservations/show.php?id=' . $reservationId ?>"
                                        class="text-white hover:text-mb-accent transition-colors font-light">#<?= $reservationId ?></a>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="<?= '../clients/show.php?id=' . (int) $row['client_id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors"><?= e($row['client_name']) ?></a>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-mb-silver"><?= e($row['brand']) ?> <?= e($row['model']) ?></p>
                                    <p class="text-mb-subtle text-xs"><?= e($row['license_plate']) ?></p>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs">
                                    <?= e($row['start_date']) ?> &rarr; <?= e($row['end_date']) ?>
                                </td>
                                <td class="px-6 py-4 min-w-[240px]">
                                    <input type="text" name="gps[<?= $reservationId ?>][last_location]"
                                        value="<?= e((string) $displayLocation) ?>" placeholder="Enter delivery location"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-xs font-medium <?= $summaryTone ?>"><?= $completedCount ?>/3 checks</span>
                                        <?php if ($latestStatus !== null): ?>
                                            <span class="text-[11px] text-mb-silver">Last: <?= $latestStatus === 1 ? 'Yes' : 'No' ?></span>
                                        <?php else: ?>
                                            <span class="text-[11px] text-mb-subtle">Not checked today</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs whitespace-nowrap">
                                    <?= $latestUpdated ? e(date('d M Y, h:i A', strtotime((string) $latestUpdated))) : '<span class="text-mb-subtle">—</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs whitespace-nowrap">
                                    <?= e($updatedByLabel) ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button type="button" class="gps-row-toggle text-mb-silver hover:text-white text-xs inline-flex items-center gap-1"
                                        data-target="<?= $detailRowId ?>">
                                        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                        Today
                                    </button>
                                </td>
                            </tr>
                            <tr id="<?= $detailRowId ?>" class="gps-detail-row hidden bg-mb-black/20">
                                <td colspan="9" class="px-6 py-4">
                                    <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4">
                                        <div class="flex items-center justify-between gap-3 mb-3">
                                            <p class="text-white text-sm font-medium">Today's GPS Checks</p>
                                            <span class="text-mb-subtle text-xs">Track vehicle location 3x daily for security monitoring</span>
                                        </div>
                                        
                                        <!-- Date Filter -->
                                        <div class="mb-4 pb-4 border-b border-mb-subtle/20">
                                            <div>
                                                <input type="date" id="date-start-<?= $reservationId ?>" value="<?= e($startDate) ?>" style="width: 125px; height: 34px; background: #1a1a1a; border: 1px solid #444; color: white; padding: 4px 8px; border-radius: 4px; font-size: 13px; display: inline-block; vertical-align: middle;">
                                                <span style="color: #666; margin: 0 4px; display: inline-block; vertical-align: middle;">→</span>
                                                <input type="date" id="date-end-<?= $reservationId ?>" value="<?= e($endDate) ?>" style="width: 125px; height: 34px; background: #1a1a1a; border: 1px solid #444; color: white; padding: 4px 8px; border-radius: 4px; font-size: 13px; display: inline-block; vertical-align: middle;">
                                                <button type="button" onclick="filterReservationHistory(<?= $reservationId ?>)" style="background: #0ea5e9; color: white; padding: 6px 14px; border-radius: 4px; font-size: 13px; border: none; cursor: pointer; margin-left: 6px; display: inline-block; vertical-align: middle; height: 34px;">
                                                    Filter
                                                </button>
                                                <a href="index.php<?= $tracking ? '?tracking=' . e($tracking) : '' ?><?= $search ? ($tracking ? '&' : '?') . 'search=' . e($search) : '' ?>" style="color: #888; font-size: 13px; text-decoration: none; margin-left: 8px; display: inline-block; vertical-align: middle;">
                                                    Clear
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <!-- GPS History Display -->
                                        <?php 
                                        $reservationHistory = $gpsHistoryByReservation[$reservationId] ?? [];
                                        if (!empty($reservationHistory)): 
                                        ?>
                                            <div class="mb-4">
                                                <p class="text-mb-subtle text-xs mb-2">GPS History for This Reservation (<?= e($startDate) ?> to <?= e($endDate) ?>)</p>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-mb-black/60 text-mb-subtle uppercase">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left">Date</th>
                                                                <th class="px-3 py-2 text-left">Slot</th>
                                                                <th class="px-3 py-2 text-left">Status</th>
                                                                <th class="px-3 py-2 text-left">Location</th>
                                                                <th class="px-3 py-2 text-left">Time</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-mb-subtle/20">
                                                            <?php foreach (array_slice($reservationHistory, 0, 5) as $check): ?>
                                                                <tr class="hover:bg-mb-black/40 transition-colors">
                                                                    <td class="px-3 py-2 text-mb-silver"><?= e($check['check_date']) ?></td>
                                                                    <td class="px-3 py-2">
                                                                        <?php
                                                                        $slotLabels = [
                                                                            1 => 'Morning',
                                                                            2 => 'Afternoon',
                                                                            3 => 'Evening'
                                                                        ];
                                                                        $slotLabel = $slotLabels[(int)$check['check_slot']] ?? 'Slot ' . $check['check_slot'];
                                                                        $slotColors = [
                                                                            1 => 'bg-blue-500/10 text-blue-400',
                                                                            2 => 'bg-yellow-500/10 text-yellow-400',
                                                                            3 => 'bg-purple-500/10 text-purple-400'
                                                                        ];
                                                                        $slotColor = $slotColors[(int)$check['check_slot']] ?? 'bg-green-500/10 text-green-400';
                                                                        ?>
                                                                        <span class="px-1.5 py-0.5 rounded text-xs <?= $slotColor ?>">
                                                                            <?= $slotLabel ?>
                                                                        </span>
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        <span class="px-1.5 py-0.5 rounded text-xs <?= $check['tracking_active'] ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                                                            <?= $check['tracking_active'] ? 'Active' : 'Inactive' ?>
                                                                        </span>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-mb-silver truncate max-w-[100px]" title="<?= e($check['last_location'] ?? '') ?>">
                                                                        <?= e($check['last_location'] ?? '-') ?>
                                                                    </td>
                                                                    <td class="px-3 py-2 text-mb-silver">
                                                                        <?= date('H:i', strtotime($check['updated_at'])) ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    <?php if (count($reservationHistory) > 5): ?>
                                                        <div class="text-center mt-2">
                                                            <a href="history.php?start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&status=active" class="text-mb-accent hover:text-mb-accent/80 text-xs transition-colors">
                                                                View Full History →
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-xs">
                                                <thead class="text-mb-subtle uppercase">
                                                    <tr>
                                                        <th class="py-2 text-left">Check</th>
                                                        <th class="py-2 text-left">At Location</th>
                                                        <th class="py-2 text-left">Reason (If No)</th>
                                                        <th class="py-2 text-left">Updated At</th>
                                                        <th class="py-2 text-left">Updated By</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-mb-subtle/10">
                                                    <?php foreach ([1, 2, 3] as $slot):
                                                        $slotData = $todaySlotData[$slot] ?? null;
                                                        $slotStatus = $slotData ? (string) $slotData['tracking_active'] : '';
                                                        $slotReason = $slotData ? (string) ($slotData['notes'] ?? '') : '';
                                                        $slotUpdatedAt = $slotData['updated_at'] ?? null;
                                                        $slotUpdatedLabel = '-';
                                                        if ($slotData) {
                                                            if (!empty($slotData['updated_by_name'])) {
                                                                $slotUpdatedLabel = (string) $slotData['updated_by_name'];
                                                            } elseif (!empty($slotData['updated_by_username'])) {
                                                                $slotUpdatedLabel = (string) $slotData['updated_by_username'];
                                                            } elseif (!empty($slotData['updated_by'])) {
                                                                $slotUpdatedLabel = 'User #' . (int) $slotData['updated_by'];
                                                            }
                                                        }
                                                        // Get descriptive slot names
                                            $slotNames = [
                                                1 => 'Morning (6AM-12PM)',
                                                2 => 'Afternoon (12PM-6PM)', 
                                                3 => 'Evening (6PM-10PM)'
                                            ];
                                            $slotName = $slotNames[$slot] ?? "Slot {$slot}";
                                            ?>
                                                        <tr class="gps-slot-row">
                                                            <td class="py-2 pr-3 text-mb-silver"><?= $slotName ?></td>
                                                            <td class="py-2 pr-3 min-w-[160px]">
                                                                <select name="gps[<?= $reservationId ?>][slots][<?= $slot ?>][tracking_active]"
                                                                    class="gps-slot-select bg-mb-black border border-mb-subtle/20 rounded-md px-2 py-1 text-xs text-white focus:outline-none focus:border-mb-accent transition-colors w-full">
                                                                    <option value="">Not checked</option>
                                                                    <option value="1" <?= $slotStatus === '1' ? 'selected' : '' ?>>At Location</option>
                                                                    <option value="0" <?= $slotStatus === '0' ? 'selected' : '' ?>>Not at Location</option>
                                                                </select>
                                                            </td>
                                                            <td class="py-2 pr-3 min-w-[220px]">
                                                                <input type="text" name="gps[<?= $reservationId ?>][slots][<?= $slot ?>][reason]"
                                                                    value="<?= e($slotReason) ?>" maxlength="500"
                                                                    placeholder="Reason if not at location"
                                                                    class="gps-slot-reason w-full bg-mb-black border <?= ($slotStatus === '0' && $slotReason === '') ? 'border-red-500/40' : 'border-mb-subtle/20' ?> rounded-md px-2 py-1 text-xs text-white focus:outline-none focus:border-mb-accent transition-colors">
                                                            </td>
                                                            <td class="py-2 text-mb-subtle whitespace-nowrap">
                                                                <?= $slotUpdatedAt ? e(date('d M Y, h:i A', strtotime((string) $slotUpdatedAt))) : '—' ?>
                                                            </td>
                                                            <td class="py-2 text-mb-subtle whitespace-nowrap"><?= e($slotUpdatedLabel) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Individual Save Button for this Reservation -->
                                        <div class="mt-4 pt-3 border-t border-mb-subtle/20 flex justify-end">
                                            <button type="button" onclick="saveSingleReservation(<?= $reservationId ?>)" <?= $saveDisabled ?>
                                                class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg text-xs font-medium transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                                💾 Save This Reservation
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </form>
    </div>
</div>
<?php
$_qp = array_filter(
    [
        'search' => $search,
        'tracking' => $tracking,
    ],
    static fn($value) => $value !== null && $value !== ''
);
echo render_pagination($pgGps, $_qp);

$extraScripts = <<<JS
<script>
// Filter reservation history by date
function filterReservationHistory(reservationId) {
    const startDate = document.getElementById('date-start-' + reservationId).value;
    const endDate = document.getElementById('date-end-' + reservationId).value;
    
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Update date parameters
    urlParams.set('start_date', startDate);
    urlParams.set('end_date', endDate);
    
    // Remove page parameter to go to page 1
    urlParams.delete('page');
    
    // Navigate to new URL
    window.location.href = 'index.php?' + urlParams.toString();
}

// Save a single reservation via AJAX
function saveSingleReservation(reservationId) {
    const form = document.getElementById('gpsBulkForm');
    const formData = new FormData(form);
    
    // Filter form data to only include this reservation
    const filteredData = new FormData();
    filteredData.append('f_search', formData.get('f_search'));
    filteredData.append('f_tracking', formData.get('f_tracking'));
    filteredData.append('f_page', formData.get('f_page'));
    filteredData.append('save_single', '1');
    filteredData.append('single_reservation_id', reservationId);
    
    // Get location for this reservation
    const locationInput = document.querySelector('input[name="gps[' + reservationId + '][last_location]"]');
    if (locationInput) {
        filteredData.append('gps[' + reservationId + '][last_location]', locationInput.value);
    }
    
    // Get slot data for this reservation
    for (let slot = 1; slot <= 3; slot++) {
        const slotSelect = document.querySelector('select[name="gps[' + reservationId + '][slots][' + slot + '][tracking_active]"]');
        const slotReason = document.querySelector('input[name="gps[' + reservationId + '][slots][' + slot + '][reason]"]');
        
        if (slotSelect && slotSelect.value !== '') {
            filteredData.append('gps[' + reservationId + '][slots][' + slot + '][tracking_active]', slotSelect.value);
            if (slotReason) {
                filteredData.append('gps[' + reservationId + '][slots][' + slot + '][reason]', slotReason.value);
            }
        }
    }
    
    // Validate: if any slot is "Not at Location" (0), reason is required
    let valid = true;
    for (let slot = 1; slot <= 3; slot++) {
        const slotSelect = document.querySelector('select[name="gps[' + reservationId + '][slots][' + slot + '][tracking_active]"]');
        const slotReason = document.querySelector('input[name="gps[' + reservationId + '][slots][' + slot + '][reason]"]');
        if (slotSelect && slotSelect.value === '0' && slotReason && slotReason.value.trim() === '') {
            slotReason.setCustomValidity('Reason is required when vehicle is not at delivery location.');
            slotReason.reportValidity();
            valid = false;
            break;
        }
    }
    
    if (!valid) return;
    
    // Submit via fetch
    fetch('index.php', {
        method: 'POST',
        body: filteredData
    })
    .then(response => {
        if (response.ok) {
            // Show success message
            const btn = document.querySelector('button[onclick="saveSingleReservation(' + reservationId + ')"]');
            if (btn) {
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Saved!';
                btn.classList.remove('bg-green-600', 'hover:bg-green-500');
                btn.classList.add('bg-blue-500');
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.classList.remove('bg-blue-500');
                    btn.classList.add('bg-green-600', 'hover:bg-green-500');
                    // Reload page to show updated timestamps
                    window.location.reload();
                }, 800);
            } else {
                window.location.reload();
            }
        } else {
            alert('Failed to save. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving GPS check. Please try again.');
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('.gps-row-toggle');
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const row = targetId ? document.getElementById(targetId) : null;
            if (!row) return;
            row.classList.toggle('hidden');
            const icon = btn.querySelector('svg');
            if (icon) {
                icon.classList.toggle('rotate-180');
            }
        });
    });

    const slotRows = document.querySelectorAll('.gps-slot-row');
    const applySlotRule = function (selectEl, reasonEl) {
        if (!selectEl || !reasonEl) return;
        const isNo = selectEl.value === '0';
        reasonEl.disabled = !isNo;
        if (isNo && reasonEl.value.trim() === '') {
            reasonEl.classList.add('border-red-500/40');
        } else {
            reasonEl.classList.remove('border-red-500/40');
            reasonEl.setCustomValidity('');
        }
    };

    slotRows.forEach(function (row) {
        const selectEl = row.querySelector('.gps-slot-select');
        const reasonEl = row.querySelector('.gps-slot-reason');
        if (!selectEl || !reasonEl) return;

        selectEl.addEventListener('change', function () {
            applySlotRule(selectEl, reasonEl);
        });
        reasonEl.addEventListener('input', function () {
            applySlotRule(selectEl, reasonEl);
        });
        applySlotRule(selectEl, reasonEl);
    });

    const bulkForm = document.getElementById('gpsBulkForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            let invalid = false;
            slotRows.forEach(function (row) {
                const selectEl = row.querySelector('.gps-slot-select');
                const reasonEl = row.querySelector('.gps-slot-reason');
                if (!selectEl || !reasonEl) return;
                if (selectEl.value === '0' && reasonEl.value.trim() === '') {
                    invalid = true;
                    reasonEl.setCustomValidity('Reason is required when vehicle is not at delivery location.');
                    reasonEl.reportValidity();
                }
            });
            if (invalid) {
                event.preventDefault();
            }
        });
    }
});
</script>
JS;
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
