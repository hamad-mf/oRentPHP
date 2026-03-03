<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gps_helpers.php';
require_once __DIR__ . '/../includes/activity_log.php';

$pdo = db();
gps_tracking_ensure_schema($pdo);

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$tracking = $_GET['tracking'] ?? '';
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));

$isValidDate = static function (string $date): bool {
    if ($date === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $date;
};

$allowedStatuses = ['', 'pending', 'confirmed', 'active', 'completed'];
$allowedTracking = ['', 'yes', 'no'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
if (!in_array($tracking, $allowedTracking, true)) {
    $tracking = '';
}
if (!$isValidDate($fromDate)) {
    $fromDate = '';
}
if (!$isValidDate($toDate)) {
    $toDate = '';
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $lastLocation = trim((string) ($_POST['last_location'] ?? ''));
    $inactiveReason = trim((string) ($_POST['inactive_reason'] ?? ''));
    $trackingActive = ($_POST['tracking_active'] ?? '1') === '0' ? 0 : 1;

    $fSearch = trim((string) ($_POST['f_search'] ?? ''));
    $fStatus = (string) ($_POST['f_status'] ?? '');
    $fTracking = (string) ($_POST['f_tracking'] ?? '');
    $fFromDate = trim((string) ($_POST['f_from_date'] ?? ''));
    $fToDate = trim((string) ($_POST['f_to_date'] ?? ''));

    if (!in_array($fStatus, $allowedStatuses, true)) {
        $fStatus = '';
    }
    if (!in_array($fTracking, $allowedTracking, true)) {
        $fTracking = '';
    }
    if (!$isValidDate($fFromDate)) {
        $fFromDate = '';
    }
    if (!$isValidDate($fToDate)) {
        $fToDate = '';
    }
    if ($fFromDate !== '' && $fToDate !== '' && $fFromDate > $fToDate) {
        [$fFromDate, $fToDate] = [$fToDate, $fFromDate];
    }

    $redirectParams = array_filter(
        [
            'search' => $fSearch,
            'status' => $fStatus,
            'tracking' => $fTracking,
            'from_date' => $fFromDate,
            'to_date' => $fToDate,
        ],
        static fn($value) => $value !== '' && $value !== null
    );
    $redirectUrl = 'index.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : '');

    if ($reservationId <= 0) {
        flash('error', 'Invalid reservation selected for GPS update.');
        redirect($redirectUrl);
    }
    if (strlen($lastLocation) > 255) {
        flash('error', 'Location text is too long. Maximum 255 characters.');
        redirect($redirectUrl);
    }
    if (strlen($inactiveReason) > 500) {
        flash('error', 'Reason text is too long. Maximum 500 characters.');
        redirect($redirectUrl);
    }
    if ($trackingActive === 0 && $inactiveReason === '') {
        flash('error', 'Reason is required when GPS is set to No.');
        redirect($redirectUrl);
    }

    try {
        $resStmt = $pdo->prepare("
            SELECT r.id, r.vehicle_id, c.name AS client_name, v.brand, v.model, v.license_plate
            FROM reservations r
            JOIN clients c ON c.id = r.client_id
            JOIN vehicles v ON v.id = r.vehicle_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $resStmt->execute([$reservationId]);
        $reservation = $resStmt->fetch();

        if (!$reservation) {
            flash('error', 'Reservation not found.');
            redirect($redirectUrl);
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $existingStmt = $pdo->prepare("SELECT id FROM gps_tracking WHERE reservation_id = ? ORDER BY id DESC LIMIT 1");
        $existingStmt->execute([$reservationId]);
        $existingId = (int) ($existingStmt->fetchColumn() ?: 0);

        if ($existingId === 0) {
            $fallbackStmt = $pdo->prepare("SELECT id FROM gps_tracking WHERE reservation_id IS NULL AND vehicle_id = ? ORDER BY id DESC LIMIT 1");
            $fallbackStmt->execute([(int) $reservation['vehicle_id']]);
            $existingId = (int) ($fallbackStmt->fetchColumn() ?: 0);
        }

        if ($existingId > 0) {
            $upd = $pdo->prepare("
                UPDATE gps_tracking
                SET reservation_id = ?, vehicle_id = ?, last_location = ?, tracking_active = ?, notes = ?, last_seen = NOW(), updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $reservationId,
                (int) $reservation['vehicle_id'],
                $lastLocation !== '' ? $lastLocation : null,
                $trackingActive,
                $inactiveReason !== '' ? $inactiveReason : null,
                $userId ?: null,
                $existingId
            ]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO gps_tracking (reservation_id, vehicle_id, last_location, tracking_active, notes, last_seen, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())
            ");
            $ins->execute([
                $reservationId,
                (int) $reservation['vehicle_id'],
                $lastLocation !== '' ? $lastLocation : null,
                $trackingActive,
                $inactiveReason !== '' ? $inactiveReason : null,
                $userId ?: null
            ]);
        }

        $trackingLabel = $trackingActive === 1 ? 'Yes' : 'No';
        $locationLabel = $lastLocation !== '' ? $lastLocation : 'Not set';
        $reasonLabel = $inactiveReason !== '' ? $inactiveReason : 'n/a';
        $message = "GPS updated for reservation #{$reservationId}. Tracking: {$trackingLabel}. Location: {$locationLabel}.";
        if ($trackingActive === 0) {
            $message .= " Reason: {$reasonLabel}.";
        }

        app_log(
            'ACTION',
            "GPS update for reservation #{$reservationId} ({$reservation['brand']} {$reservation['model']} - {$reservation['client_name']}): tracking={$trackingLabel}, location={$locationLabel}, reason={$reasonLabel}"
        );
        log_activity(
            $pdo,
            'gps_update',
            'reservation',
            $reservationId,
            "Updated GPS for reservation #{$reservationId} ({$reservation['brand']} {$reservation['model']} - {$reservation['license_plate']}). Tracking {$trackingLabel}, location: {$locationLabel}, reason: {$reasonLabel}."
        );

        flash('success', $message);
    } catch (Throwable $e) {
        app_log('ERROR', 'GPS update failed for reservation #' . $reservationId . ': ' . $e->getMessage());
        flash('error', 'Could not update GPS details right now. Please try again.');
    }

    redirect($redirectUrl);
}

$counts = [
    'all' => (int) $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn(),
    'confirmed' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed'")->fetchColumn(),
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn(),
    'completed' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='completed'")->fetchColumn(),
];

$trackingCounts = ['all' => 0, 'yes' => 0, 'no' => 0];
try {
    $latestGpsSql = gps_latest_tracking_join_sql();
    $trackingCounts['all'] = (int) $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
    $trackingCounts['yes'] = (int) $pdo->query("
        SELECT COUNT(*)
        FROM reservations r
        LEFT JOIN ($latestGpsSql) g ON g.reservation_id = r.id
        WHERE COALESCE(g.tracking_active, 1) = 1
    ")->fetchColumn();
    $trackingCounts['no'] = (int) $pdo->query("
        SELECT COUNT(*)
        FROM reservations r
        LEFT JOIN ($latestGpsSql) g ON g.reservation_id = r.id
        WHERE COALESCE(g.tracking_active, 1) = 0
    ")->fetchColumn();
} catch (Throwable $e) {
    app_log('ERROR', 'GPS tracking count query failed: ' . $e->getMessage());
}

$where = ['1=1'];
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
if ($status !== '') {
    $where[] = 'r.status = ?';
    $params[] = $status;
}
if ($fromDate !== '') {
    $where[] = 'DATE(r.end_date) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'DATE(r.start_date) <= ?';
    $params[] = $toDate;
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
$sql = "
    SELECT
        r.id,
        r.status,
        r.start_date,
        r.end_date,
        r.actual_end_date,
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
    FROM reservations r
    JOIN clients c ON c.id = r.client_id
    JOIN vehicles v ON v.id = r.vehicle_id
    LEFT JOIN ($latestGpsSql) g ON g.reservation_id = r.id
    $updatedByJoin
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'GPS Tracking';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <?php if ($success = getFlash('success')): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error = getFlash('error')): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-1 bg-mb-surface border border-mb-subtle/20 rounded-xl p-1 w-fit flex-wrap">
        <?php
        $tabs = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'active' => 'Active', 'completed' => 'Completed'];
        foreach ($tabs as $val => $lbl):
            $active = $status === $val;
            $cnt = $counts[$val === '' ? 'all' : $val] ?? 0;
            $query = array_filter(
                ['status' => $val, 'tracking' => $tracking, 'search' => $search, 'from_date' => $fromDate, 'to_date' => $toDate],
                static fn($v) => $v !== '' && $v !== null
            );
            ?>
            <a href="?<?= http_build_query($query) ?>"
                class="px-4 py-2 rounded-lg text-sm transition-all <?= $active ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white hover:bg-mb-black/50' ?>">
                <?= $lbl ?> <span class="ml-1 text-xs opacity-70">
                    <?= $cnt ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex items-center gap-1 bg-mb-surface border border-mb-subtle/20 rounded-xl p-1 w-fit flex-wrap">
        <?php
        $trackingTabs = ['' => 'Tracking: All', 'yes' => 'GPS Yes', 'no' => 'GPS No'];
        foreach ($trackingTabs as $val => $lbl):
            $active = $tracking === $val;
            $countKey = $val === '' ? 'all' : $val;
            $query = array_filter(
                ['status' => $status, 'tracking' => $val, 'search' => $search, 'from_date' => $fromDate, 'to_date' => $toDate],
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
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
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
            <div class="flex items-center gap-2">
                <span class="text-mb-subtle text-xs uppercase tracking-wide">From</span>
                <input type="date" name="from_date" value="<?= e($fromDate) ?>"
                    class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
            </div>
            <div class="flex items-center gap-2">
                <span class="text-mb-subtle text-xs uppercase tracking-wide">To</span>
                <input type="date" name="to_date" value="<?= e($toDate) ?>"
                    class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
            </div>
            <button type="submit" class="text-mb-silver hover:text-white text-sm transition-colors">Search</button>
            <?php if ($search || $status || $tracking || $fromDate || $toDate): ?><a href="index.php"
                    class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h3 class="text-white font-light text-lg">GPS Tracking</h3>
            <p class="text-mb-subtle text-xs mt-1">Update current location and tracking status for any reservation, including completed ones.</p>
        </div>
        <?php if (empty($rows)): ?>
            <div class="py-20 text-center">
                <svg class="w-16 h-16 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                        d="M12 2a7 7 0 00-7 7c0 4.5 7 13 7 13s7-8.5 7-13a7 7 0 00-7-7zm0 10a3 3 0 110-6 3 3 0 010 6z" />
                </svg>
                <p class="text-mb-subtle text-lg">No reservations found for the selected filters.</p>
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
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-left">Current Location</th>
                            <th class="px-6 py-4 text-left">GPS Active</th>
                            <th class="px-6 py-4 text-left">Reason (If No)</th>
                            <th class="px-6 py-4 text-left">Last Updated</th>
                            <th class="px-6 py-4 text-left">Updated By</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($rows as $row):
                            $trackingActiveVal = array_key_exists('tracking_active', $row) && $row['tracking_active'] !== null
                                ? (int) $row['tracking_active']
                                : 1;
                            $isGpsOff = $trackingActiveVal === 0;
                            $isActiveWarning = $isGpsOff && $row['status'] === 'active';
                            $statusColors = [
                                'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
                                'confirmed' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                'active' => 'bg-green-500/10 text-green-400 border-green-500/30',
                                'completed' => 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/30',
                            ];
                            $sc = $statusColors[$row['status']] ?? 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/30';
                            $lastUpdated = $row['updated_at'] ?? $row['last_seen'] ?? null;
                            $formId = 'gps-form-' . (int) $row['id'];
                            $trackingInputId = 'gps-active-' . (int) $row['id'];
                            $yesBtnId = 'gps-yes-' . (int) $row['id'];
                            $noBtnId = 'gps-no-' . (int) $row['id'];
                            $reasonId = 'gps-reason-' . (int) $row['id'];
                            $inactiveReason = trim((string) ($row['notes'] ?? ''));
                            $updatedByLabel = '—';
                            if (!empty($row['updated_by_name'])) {
                                $updatedByLabel = (string) $row['updated_by_name'];
                            } elseif (!empty($row['updated_by_username'])) {
                                $updatedByLabel = (string) $row['updated_by_username'];
                            } elseif (!empty($row['updated_by'])) {
                                $updatedByLabel = 'User #' . (int) $row['updated_by'];
                            }
                            ?>
                            <tr
                                class="hover:bg-mb-black/30 transition-colors <?= $isActiveWarning ? 'bg-red-500/5 border-l-2 border-red-500/40' : '' ?>">
                                <td class="px-6 py-4">
                                    <a href="<?= '../reservations/show.php?id=' . (int) $row['id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors font-light">#
                                        <?= (int) $row['id'] ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="<?= '../clients/show.php?id=' . (int) $row['client_id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors">
                                        <?= e($row['client_name']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-mb-silver">
                                        <?= e($row['brand']) ?>
                                        <?= e($row['model']) ?>
                                    </p>
                                    <p class="text-mb-subtle text-xs">
                                        <?= e($row['license_plate']) ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs">
                                    <?= e($row['start_date']) ?> → <?= e($row['end_date']) ?>
                                    <?php if ($row['status'] === 'completed' && !empty($row['actual_end_date'])): ?>
                                        <p class="text-mb-subtle mt-1">Returned: <?= e($row['actual_end_date']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs border capitalize <?= $sc ?>">
                                        <?= e($row['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 min-w-[240px]">
                                    <input type="text" name="last_location" form="<?= $formId ?>"
                                        value="<?= e((string) ($row['last_location'] ?? '')) ?>" placeholder="Enter current location"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                                </td>
                                <td class="px-6 py-4 min-w-[150px]">
                                    <div class="inline-flex rounded-lg border border-mb-subtle/20 overflow-hidden">
                                        <button type="button" id="<?= $yesBtnId ?>" data-track-id="<?= $trackingInputId ?>"
                                            data-track-value="1"
                                            class="px-3 py-2 text-xs font-medium transition-colors <?= $trackingActiveVal === 1 ? 'bg-green-500/20 text-green-300 border-green-500/40' : 'bg-mb-black text-mb-silver hover:text-white' ?>">
                                            Yes
                                        </button>
                                        <button type="button" id="<?= $noBtnId ?>" data-track-id="<?= $trackingInputId ?>"
                                            data-track-value="0"
                                            class="px-3 py-2 text-xs font-medium transition-colors border-l border-mb-subtle/20 <?= $trackingActiveVal === 0 ? 'bg-red-500/20 text-red-300 border-red-500/40' : 'bg-mb-black text-mb-silver hover:text-white' ?>">
                                            No
                                        </button>
                                    </div>
                                    <?php if ($isActiveWarning): ?>
                                        <p class="text-red-400 text-xs mt-1">Warning: Active rental with GPS off.</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 min-w-[260px]">
                                    <input id="<?= $reasonId ?>" type="text" name="inactive_reason" form="<?= $formId ?>"
                                        value="<?= e($inactiveReason) ?>" maxlength="500"
                                        placeholder="Reason required when GPS is No"
                                        class="w-full bg-mb-black border <?= ($isGpsOff && $inactiveReason === '') ? 'border-red-500/40' : 'border-mb-subtle/20' ?> rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors">
                                    <?php if ($isGpsOff && $inactiveReason === ''): ?>
                                        <p class="text-red-400 text-xs mt-1">Please add reason and save.</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs whitespace-nowrap">
                                    <?= $lastUpdated ? e(date('d M Y, h:i A', strtotime((string) $lastUpdated))) : '<span class="text-mb-subtle">Never updated</span>' ?>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs whitespace-nowrap">
                                    <?= e($updatedByLabel) ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form id="<?= $formId ?>" method="POST" class="inline"
                                        data-tracking-id="<?= $trackingInputId ?>" data-yes-btn-id="<?= $yesBtnId ?>"
                                        data-no-btn-id="<?= $noBtnId ?>" data-reason-id="<?= $reasonId ?>">
                                        <input type="hidden" name="reservation_id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" id="<?= $trackingInputId ?>" name="tracking_active"
                                            value="<?= $trackingActiveVal ?>">
                                        <input type="hidden" name="f_search" value="<?= e($search) ?>">
                                        <input type="hidden" name="f_status" value="<?= e($status) ?>">
                                        <input type="hidden" name="f_tracking" value="<?= e($tracking) ?>">
                                        <input type="hidden" name="f_from_date" value="<?= e($fromDate) ?>">
                                        <input type="hidden" name="f_to_date" value="<?= e($toDate) ?>">
                                        <button type="submit"
                                            class="bg-mb-accent text-white px-4 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-xs font-medium">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$extraScripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[id^="gps-form-"]');
    forms.forEach(function (form) {
        const trackingId = form.getAttribute('data-tracking-id');
        const yesBtnId = form.getAttribute('data-yes-btn-id');
        const noBtnId = form.getAttribute('data-no-btn-id');
        const reasonId = form.getAttribute('data-reason-id');
        if (!trackingId || !reasonId || !yesBtnId || !noBtnId) return;

        const trackingInput = document.getElementById(trackingId);
        const yesBtn = document.getElementById(yesBtnId);
        const noBtn = document.getElementById(noBtnId);
        const reason = document.getElementById(reasonId);
        if (!trackingInput || !reason || !yesBtn || !noBtn) return;

        const paintButtons = function () {
            const isNo = trackingInput.value === '0';
            if (isNo) {
                yesBtn.classList.remove('bg-green-500/20', 'text-green-300', 'border-green-500/40');
                yesBtn.classList.add('bg-mb-black', 'text-mb-silver');
                noBtn.classList.add('bg-red-500/20', 'text-red-300', 'border-red-500/40');
                noBtn.classList.remove('bg-mb-black', 'text-mb-silver');
            } else {
                yesBtn.classList.add('bg-green-500/20', 'text-green-300', 'border-green-500/40');
                yesBtn.classList.remove('bg-mb-black', 'text-mb-silver');
                noBtn.classList.remove('bg-red-500/20', 'text-red-300', 'border-red-500/40');
                noBtn.classList.add('bg-mb-black', 'text-mb-silver');
            }
        };

        const applyRule = function () {
            const needsReason = trackingInput.value === '0';
            reason.required = needsReason;
            if (needsReason && reason.value.trim() === '') {
                reason.classList.add('border-red-500/40');
            } else {
                reason.classList.remove('border-red-500/40');
                reason.setCustomValidity('');
            }
            paintButtons();
        };

        yesBtn.addEventListener('click', function () {
            trackingInput.value = '1';
            applyRule();
        });
        noBtn.addEventListener('click', function () {
            trackingInput.value = '0';
            applyRule();
        });
        reason.addEventListener('input', function () {
            reason.setCustomValidity('');
            applyRule();
        });

        form.addEventListener('submit', function (e) {
            applyRule();
            if (trackingInput.value === '0' && reason.value.trim() === '') {
                e.preventDefault();
                reason.setCustomValidity('Reason is required when GPS is set to No.');
                reason.reportValidity();
            }
        });

        applyRule();
    });
});
</script>
JS;
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
