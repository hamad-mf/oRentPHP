<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/vehicle_helpers.php';

$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = max(12, get_per_page($pdo));
$page    = max(1, (int) ($_GET['page'] ?? 1));
vehicle_ensure_schema($pdo);
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$rentedDate = trim($_GET['rented_date'] ?? '');

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(v.brand LIKE ? OR v.model LIKE ? OR v.license_plate LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($status !== '') {
    $where[] = 'v.status = ?';
    $params[] = $status;
}
if ($rentedDate !== '') {
    $dateCheck = DateTime::createFromFormat('Y-m-d', $rentedDate);
    if ($dateCheck && $dateCheck->format('Y-m-d') === $rentedDate) {
        $where[] = 'DATE(ar.start_date) = ?';
        $params[] = $rentedDate;
    } else {
        $rentedDate = '';
    }
}

$baseFrom = 'FROM vehicles v
        LEFT JOIN (
            SELECT r1.vehicle_id, r1.client_id, r1.start_date, r1.end_date
            FROM reservations r1
            INNER JOIN (
                SELECT vehicle_id, MAX(id) AS max_id
                FROM reservations
                WHERE status = "active"
                GROUP BY vehicle_id
            ) latest ON latest.max_id = r1.id
        ) ar ON ar.vehicle_id = v.id
        LEFT JOIN clients rc ON rc.id = ar.client_id
        WHERE ' . implode(' AND ', $where);

$sql = 'SELECT v.*,
               ar.start_date AS rented_start_date,
               ar.end_date AS rented_end_date,
               rc.name AS rented_client_name,
               (SELECT COUNT(*) FROM documents d WHERE d.vehicle_id = v.id) AS doc_count
        ' . $baseFrom . '
        ORDER BY
            CASE
                WHEN v.status = "rented" THEN 0
                WHEN v.status = "available" THEN 1
                WHEN v.status = "maintenance" THEN 2
                ELSE 3
            END,
            CASE
                WHEN v.status = "rented" AND ar.end_date >= NOW() THEN 0
                WHEN v.status = "rented" AND ar.end_date < NOW() THEN 1
                WHEN v.status = "rented" AND ar.end_date IS NULL THEN 2
                ELSE 0
            END,
            CASE
                WHEN v.status = "rented" AND ar.end_date >= NOW() THEN ar.end_date
                ELSE NULL
            END ASC,
            CASE
                WHEN v.status = "rented" AND ar.end_date < NOW() THEN ar.end_date
                ELSE NULL
            END DESC,
            v.created_at DESC';

$countSql2 = 'SELECT COUNT(*) ' . $baseFrom;
$pgResult  = paginate_query($pdo, $sql, $countSql2, $params, $page, $perPage);
$vehicles  = $pgResult['rows'];

$totalCount = $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
$available = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn();
$rented = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='rented'")->fetchColumn();
$maintenance = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='maintenance'")->fetchColumn();

$success = getFlash('success');
$error = getFlash('error');

// Fetch all vehicle images for the grid
try {
    $allImgsRaw = $pdo->query("SELECT * FROM vehicle_images ORDER BY vehicle_id, sort_order, id")->fetchAll();
    $vehicleImgMap = [];
    foreach ($allImgsRaw as $img) {
        $vehicleImgMap[$img['vehicle_id']][] = $img['file_path'];
    }
} catch (Exception $e) {
    $vehicleImgMap = [];
}

// Build catalog share URL - handles both root-level and subdirectory installs cleanly
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = trim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/vehicles/index.php')), '/');
$catalogUrl = $scheme . '://' . $host . ($basePath !== '' ? '/' . $basePath : '') . '/vehicles/catalog.php';

$pageTitle = 'Vehicles (Fleet)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">

    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Fleet Status Bar -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php
        $statusCards = [
            ['label' => 'Total Fleet', 'count' => $totalCount, 'color' => 'text-white', 'filter' => '', 'active' => $status === ''],
            ['label' => 'Available', 'count' => $available, 'color' => 'text-green-400', 'filter' => 'available', 'active' => $status === 'available'],
            ['label' => 'Rented', 'count' => $rented, 'color' => 'text-mb-accent', 'filter' => 'rented', 'active' => $status === 'rented'],
            ['label' => 'Workshop', 'count' => $maintenance, 'color' => 'text-red-400', 'filter' => 'maintenance', 'active' => $status === 'maintenance'],
        ];
        $borderActive = ['' => 'border-white/30', 'available' => 'border-green-500/50', 'rented' => 'border-mb-accent/50', 'maintenance' => 'border-red-500/50'];
        foreach ($statusCards as $card):
            $href = '?' . http_build_query(array_filter([
                'status' => $card['filter'],
                'search' => $search,
                'rented_date' => $rentedDate,
            ], static fn($v) => $v !== null && $v !== ''));
            $activeBorder = $card['active'] ? ($borderActive[$card['filter']] ?? 'border-white/20') : '';
            ?>
            <a href="<?= $href ?>"
                class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-4 text-center hover:border-white/20 transition-all <?= $activeBorder ?>">
                <p class="text-3xl font-light <?= $card['color'] ?>">
                    <?= $card['count'] ?>
                </p>
                <p class="text-mb-silver text-xs uppercase mt-1">
                    <?= $card['label'] ?>
                </p>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <div class="relative flex-1 max-w-sm">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search brand, model or plate..."
                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-2 pl-10 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
                <svg class="w-4 h-4 text-mb-subtle absolute left-4 top-2.5" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="date" name="rented_date" value="<?= e($rentedDate) ?>"
                class="bg-mb-surface border border-mb-subtle/20 rounded-full py-2 px-4 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors"
                title="Filter by rented start date">
            <button type="submit" class="text-mb-silver hover:text-white text-sm transition-colors">Search</button>
            <?php if ($search || $status || $rentedDate): ?><a href="index.php"
                    class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (auth_has_perm('add_vehicles')): ?>
            <a href="create.php"
                class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors flex items-center gap-2 text-sm font-medium flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                Add Vehicle
            </a>
        <?php endif; ?>
        <!-- Share Catalog Button -->
        <button onclick="document.getElementById('catalogModal').classList.remove('hidden')"
            class="bg-mb-surface border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-white/20 px-5 py-2 rounded-full transition-colors flex items-center gap-2 text-sm font-medium flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
            Share Catalog
        </button>
    </div>

    <!-- Catalog Share Modal -->
    <div id="catalogModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
        onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div
            class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50 animate-[fadeUp_0.2s_ease]">
            <!-- Header -->
            <div class="flex items-start justify-between mb-5">
                <div>
                    <h3 class="text-white font-semibold text-lg">Share Vehicle Catalog</h3>
                    <p class="text-mb-subtle text-sm mt-0.5">Anyone with this link can browse your available fleet - no
                        login required.</p>
                </div>
                <button onclick="document.getElementById('catalogModal').classList.add('hidden')"
                    class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <!-- Preview badges -->
            <div class="flex flex-wrap gap-2 mb-4">
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/20 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse inline-block"></span>
                    <?= $available ?> Available
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full bg-mb-black/50 text-mb-silver border border-mb-subtle/20">
                    <?= $totalCount ?> Total Vehicles
                </span>
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-mb-black/50 text-mb-silver border border-mb-subtle/20">No
                    Login Required</span>
            </div>
            <!-- URL Box -->
            <div class="bg-mb-black border border-mb-subtle/20 rounded-xl p-3 flex items-center gap-3 mb-4">
                <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
                <input type="text" id="catalogLinkInput" value="<?= e($catalogUrl) ?>" readonly
                    class="flex-1 bg-transparent text-mb-silver text-sm focus:outline-none font-mono truncate cursor-text select-all">
            </div>
            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button id="copyCatalogBtn" onclick="copyCatalogLink()"
                    class="flex-1 bg-mb-accent text-white py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    Copy Link
                </button>
                <a href="<?= e($catalogUrl) ?>" target="_blank"
                    class="flex-1 text-center border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-white/20 py-2.5 rounded-full transition-colors text-sm font-medium">
                    Preview ->
                </a>
            </div>
        </div>
    </div>

    <!-- Vehicle Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (empty($vehicles)): ?>
            <div class="col-span-full py-20 text-center">
                <svg class="w-16 h-16 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="text-mb-subtle text-lg">No vehicles found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($vehicles as $v):
                $badge = [
                    'available' => 'bg-green-500/20 text-green-400 border-green-500/30',
                    'rented' => 'bg-mb-accent/20 text-mb-accent border-mb-accent/30',
                    'maintenance' => 'bg-red-500/20 text-red-400 border-red-500/30',
                ];
                $dot = [
                    'available' => 'bg-green-500 animate-pulse',
                    'rented' => 'bg-mb-accent',
                    'maintenance' => 'bg-red-500',
                ];
                $statusLabel = ucfirst($v['status']);
                $badgeCls = $badge[$v['status']] ?? 'bg-gray-500/20 text-gray-400';
                $dotCls = $dot[$v['status']] ?? 'bg-gray-500';
                $remainingLabel = '';
                $remainingTone = 'text-mb-subtle';
                $usageLabel = '';
                $maintenanceLabel = '';
                $maintenanceSinceLabel = '';
                $maintenanceWorkshopLabel = trim((string) ($v['maintenance_workshop_name'] ?? ''));
                $insuranceType = strtolower(trim((string) ($v['insurance_type'] ?? '')));
                if ($insuranceType === 'thrid class') {
                    $insuranceType = 'third class';
                } elseif ($insuranceType === 'bumber to bumber') {
                    $insuranceType = 'bumper to bumper';
                }
                $insuranceExpiryDate = trim((string) ($v['insurance_expiry_date'] ?? ''));
                $insuranceExpired = false;
                if ($insuranceExpiryDate !== '') {
                    $expDateObj = DateTime::createFromFormat('Y-m-d', $insuranceExpiryDate);
                    if ($expDateObj && $expDateObj->format('Y-m-d') === $insuranceExpiryDate) {
                        $insuranceExpired = $insuranceExpiryDate < date('Y-m-d');
                    }
                }
                $isThirdClassInsurance = ($insuranceType === 'third class');
                $insuranceAlert = $insuranceExpired || $isThirdClassInsurance;
                $insuranceAlertLabel = '';
                if ($insuranceExpired && $isThirdClassInsurance) {
                    $insuranceAlertLabel = 'Insurance expired • Third class';
                } elseif ($insuranceExpired) {
                    $insuranceAlertLabel = 'Insurance expired';
                } elseif ($isThirdClassInsurance) {
                    $insuranceAlertLabel = 'Third class insurance';
                }
                $cardShellClasses = 'relative bg-mb-surface rounded-xl border overflow-hidden group transition-all duration-300 flex flex-col cursor-pointer';
                if ($insuranceAlert) {
                    $cardShellClasses .= ' border-red-500/70 ring-2 ring-red-500/50 shadow-[0_0_18px_rgba(239,68,68,0.25)]';
                } else {
                    $cardShellClasses .= ' border-mb-subtle/20 hover:border-mb-accent/30';
                }
                if (($v['status'] ?? '') === 'rented' && !empty($v['rented_end_date'])) {
                    $endTs = strtotime((string) $v['rented_end_date']);
                    $nowTs = time();
                    if ($endTs !== false) {
                        if ($endTs > $nowTs) {
                            $diff = $endTs - $nowTs;
                            $days = intdiv($diff, 86400);
                            $hours = intdiv($diff % 86400, 3600);
                            $mins = intdiv($diff % 3600, 60);
                            if ($days > 0) {
                                $remainingLabel = $days . 'd ' . $hours . 'h left';
                            } elseif ($hours > 0) {
                                $remainingLabel = $hours . 'h ' . max(1, $mins) . 'm left';
                            } else {
                                $remainingLabel = max(1, $mins) . 'm left';
                            }
                            $remainingTone = 'text-mb-accent';
                        } else {
                            $diff = $nowTs - $endTs;
                            $days = intdiv($diff, 86400);
                            $hours = intdiv($diff % 86400, 3600);
                            if ($days > 0) {
                                $remainingLabel = 'Overdue by ' . $days . 'd ' . $hours . 'h';
                            } else {
                                $remainingLabel = 'Overdue by ' . max(1, $hours) . 'h';
                            }
                            $remainingTone = 'text-red-400';
                        }
                    }
                }
                if (($v['status'] ?? '') === 'rented' && !empty($v['rented_start_date'])) {
                    $startTs = strtotime((string) $v['rented_start_date']);
                    if ($startTs !== false) {
                        $elapsed = max(0, time() - $startTs);
                        $days = intdiv($elapsed, 86400);
                        $hours = intdiv($elapsed % 86400, 3600);
                        $mins = intdiv($elapsed % 3600, 60);
                        if ($days > 0) {
                            $usageLabel = 'In use for ' . $days . 'd ' . $hours . 'h';
                        } elseif ($hours > 0) {
                            $usageLabel = 'In use for ' . $hours . 'h ' . max(1, $mins) . 'm';
                        } else {
                            $usageLabel = 'In use for ' . max(1, $mins) . 'm';
                        }
                    }
                }
                if (($v['status'] ?? '') === 'maintenance') {
                    $maintenanceStartRaw = (string) ($v['maintenance_started_at'] ?? '');
                    if ($maintenanceStartRaw === '') {
                        $maintenanceStartRaw = (string) ($v['updated_at'] ?? $v['created_at'] ?? '');
                    }
                    $startTs = $maintenanceStartRaw !== '' ? strtotime($maintenanceStartRaw) : false;
                    if ($startTs !== false) {
                        $elapsed = max(0, time() - $startTs);
                        $days = intdiv($elapsed, 86400);
                        $hours = intdiv($elapsed % 86400, 3600);
                        $mins = intdiv($elapsed % 3600, 60);
                        if ($days > 0) {
                            $maintenanceLabel = $days . 'd ' . $hours . 'h in maintenance';
                        } elseif ($hours > 0) {
                            $maintenanceLabel = $hours . 'h ' . max(1, $mins) . 'm in maintenance';
                        } else {
                            $maintenanceLabel = max(1, $mins) . 'm in maintenance';
                        }
                        $maintenanceSinceLabel = date('d M Y, h:i A', $startTs);
                    }
                }
                ?>
                <div onclick="window.location='show.php?id=<?= $v['id'] ?>'"
                    class="<?= e($cardShellClasses) ?>">
                    <?php if ($insuranceAlert): ?>
                        <div class="pointer-events-none absolute inset-0 rounded-xl border-2 border-red-500/80 animate-pulse z-20"></div>
                    <?php endif; ?>
                    <!-- Image -->
                    <div class="h-44 bg-mb-black relative overflow-hidden">
                        <?php
                        $gridImg = !empty($vehicleImgMap[$v['id']]) ? '../' . $vehicleImgMap[$v['id']][0] : ($v['image_url'] ?? '');
                        if ($gridImg): ?>
                            <img src="<?= e($gridImg) ?>" alt="<?= e($v['brand']) ?> <?= e($v['model']) ?>"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <div
                                class="w-full h-full flex items-center justify-center bg-gradient-to-br from-mb-black to-mb-surface">
                                <svg class="w-14 h-14 text-mb-subtle/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="absolute top-3 right-3">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium border backdrop-blur-sm flex items-center gap-1.5 <?= $badgeCls ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $dotCls ?>"></span>
                                <?= $statusLabel ?>
                            </span>
                        </div>
                        <?php if ($v['doc_count'] > 0): ?>
                            <div class="absolute top-3 left-3">
                                <span
                                    class="px-2 py-1 rounded-full text-xs bg-black/50 text-mb-silver border border-white/10 backdrop-blur-sm flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <?= $v['doc_count'] ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Content -->
                    <div class="p-5 flex-1 flex flex-col">
                        <h3 class="text-white font-light text-lg leading-tight">
                            <?= e($v['brand']) ?>
                            <?= e($v['model']) ?>
                        </h3>
                        <p class="text-mb-silver text-sm mt-0.5">
                            <?= e($v['year']) ?> &bull;
                            <?= e($v['license_plate']) ?>
                        </p>
                        <?php if ($v['color']): ?>
                            <p class="text-mb-subtle text-xs mt-0.5">
                                <?= e($v['color']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($insuranceAlertLabel !== ''): ?>
                            <p class="text-[10px] text-red-400 mt-1 font-medium uppercase tracking-wide">
                                <?= e($insuranceAlertLabel) ?>
                            </p>
                        <?php endif; ?>
                        <?php if (($v['status'] ?? '') === 'rented'): ?>
                            <div class="mt-3 rounded-lg bg-mb-black/40 border border-mb-subtle/20 px-3 py-2">
                                <?php if ($remainingLabel !== ''): ?>
                                    <p class="text-xs font-medium <?= $remainingTone ?>">
                                        <?= e($remainingLabel) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-xs font-medium text-mb-subtle">Rented</p>
                                <?php endif; ?>
                                <?php if ($usageLabel !== ''): ?>
                                    <p class="text-[10px] text-mb-silver mt-0.5">
                                        <?= e($usageLabel) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($v['rented_start_date']) && !empty($v['rented_end_date'])): ?>
                                    <p class="text-[10px] text-mb-subtle mt-0.5">
                                        <?= e(date('d M Y, h:i A', strtotime((string) $v['rented_start_date']))) ?> ->
                                        <?= e(date('d M Y, h:i A', strtotime((string) $v['rented_end_date']))) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($v['rented_client_name'])): ?>
                                    <p class="text-[10px] text-mb-subtle mt-0.5">Client: <?= e((string) $v['rented_client_name']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (($v['status'] ?? '') === 'maintenance'): ?>
                            <div class="mt-3 rounded-lg bg-red-500/10 border border-red-500/30 px-3 py-2">
                                <p class="text-xs font-medium text-red-300">
                                    <?= e($maintenanceLabel !== '' ? $maintenanceLabel : 'In maintenance') ?>
                                </p>
                                <?php if ($maintenanceSinceLabel !== ''): ?>
                                    <p class="text-[10px] text-red-300/80 mt-0.5">Since
                                        <?= e($maintenanceSinceLabel) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($maintenanceWorkshopLabel !== ''): ?>
                                    <p class="text-[10px] text-orange-300/90 mt-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 7.5l-9 9L7.5 12m13.5-4.5l-4.5-4.5L3 16.5V21h4.5L21 7.5z" />
                                        </svg>
                                        Workshop: <?= e($maintenanceWorkshopLabel) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($v['maintenance_expected_return'])): ?>
                                    <p class="text-[10px] text-yellow-400/90 mt-1 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Expected back: <?= date('d M Y', strtotime($v['maintenance_expected_return'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3 flex items-end gap-3">
                            <div>
                                <span class="text-mb-accent text-xl font-medium">$
                                    <?= number_format($v['daily_rate'], 0) ?>
                                </span>
                                <span class="text-mb-subtle text-xs">/day</span>
                            </div>
                            <?php if ($v['monthly_rate']): ?>
                                <div class="text-mb-subtle text-xs pb-0.5">$
                                    <?= number_format($v['monthly_rate'], 0) ?>/mo
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex items-center justify-between border-t border-mb-subtle/10 pt-4"
                            onclick="event.stopPropagation()">
                            <a href="show.php?id=<?= $v['id'] ?>"
                                class="text-sm text-mb-silver hover:text-white transition-colors flex items-center gap-1">
                                View Details
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <div class="flex items-center gap-2">
                                <?php if (auth_has_perm('add_vehicles')): ?>
                                    <a href="edit.php?id=<?= $v['id'] ?>"
                                        class="text-mb-subtle hover:text-white transition-colors p-1.5 rounded hover:bg-white/5"
                                        title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <a href="delete.php?id=<?= $v['id'] ?>"
                                        onclick="return confirm('Remove <?= e($v['brand']) ?> <?= e($v['model']) ?> from the fleet?')"
                                        class="text-mb-subtle hover:text-red-400 transition-colors p-1.5 rounded hover:bg-red-500/5"
                                        title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<?php
$_vqp = array_filter(['search' => $search, 'status' => $status, 'rented_date' => $rentedDate], fn($v) => $v !== null && $v !== '');
echo render_pagination($pgResult, $_vqp);
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
