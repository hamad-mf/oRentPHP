<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gps_helpers.php';

$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));
gps_tracking_ensure_schema($pdo);

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$allowedStatuses = ['', 'pending', 'confirmed', 'active', 'completed'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$counts = [
    'all' => (int) $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn(),
    'confirmed' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed'")->fetchColumn(),
    'active' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn(),
    'completed' => (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='completed'")->fetchColumn(),
];

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

$hasUsersTable = false;
try {
    $hasUsersTable = (int) $pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
    ")->fetchColumn() > 0;
} catch (Throwable $e) {
    app_log('ERROR', 'GPS history: users table check failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

}

$updatedBySelect = $hasUsersTable
    ? ", u.name AS updated_by_name"
    : ", NULL AS updated_by_name";
$updatedByJoin = $hasUsersTable ? "LEFT JOIN users u ON u.id = g.updated_by" : "";

$whereSql = " WHERE " . implode(' AND ', $where);
$baseFrom = "
    FROM reservations r
    JOIN clients c ON c.id = r.client_id
    JOIN vehicles v ON v.id = r.vehicle_id
    LEFT JOIN gps_tracking g ON g.reservation_id = r.id
    $updatedByJoin
";

$sql = "
    SELECT
        g.id AS gps_id,
        r.id AS reservation_id,
        g.last_location,
        g.tracking_active,
        g.notes,
        g.last_seen,
        g.updated_at,
        g.updated_by,
        r.status,
        r.delivery_location AS res_delivery_location,
        c.name AS client_name,
        c.id AS client_id,
        v.brand,
        v.model,
        v.license_plate
        $updatedBySelect
    $baseFrom
    $whereSql
    ORDER BY r.id DESC, g.id DESC
";

$countSql = "SELECT COUNT(*) $baseFrom $whereSql";
$pgHist = paginate_query($pdo, $sql, $countSql, $params, $page, $perPage);
$rows = $pgHist['rows'];

$pageTitle = 'GPS History';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <a href="index.php" class="inline-flex items-center gap-2 text-mb-subtle hover:text-white transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7"/></svg>
        Back to GPS Tracking
    </a>

    <div class="flex items-center gap-1 bg-mb-surface border border-mb-subtle/20 rounded-xl p-1 w-fit flex-wrap">
        <?php
        $tabs = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'active' => 'Active', 'completed' => 'Completed'];
        foreach ($tabs as $val => $lbl):
            $active = $status === $val;
            $cnt = $counts[$val === '' ? 'all' : $val] ?? 0;
            $query = array_filter(
                ['status' => $val, 'search' => $search],
                static fn($v) => $v !== '' && $v !== null
            );
            ?>
            <a href="?<?= http_build_query($query) ?>"
                class="px-4 py-2 rounded-lg text-sm transition-all <?= $active ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white hover:bg-mb-black/50' ?>">
                <?= $lbl ?> <span class="ml-1 text-xs opacity-70"><?= $cnt ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-3">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="relative flex-1 min-w-[220px] max-w-sm">
            <input type="text" name="search" value="<?= e($search) ?>"
                placeholder="Search reservation, client, vehicle..."
                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-2 pl-10 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
            <svg class="w-4 h-4 text-mb-subtle absolute left-4 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <button type="submit" class="text-mb-silver hover:text-white text-sm transition-colors">Search</button>
        <?php if ($search || $status): ?><a href="history.php" class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a><?php endif; ?>
    </form>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h3 class="text-white font-light text-lg">Location Change History</h3>
            <p class="text-mb-subtle text-xs mt-1">Complete log of all location updates across all reservations.</p>
        </div>
        <?php if (empty($rows)): ?>
            <div class="py-20 text-center">
                <p class="text-mb-subtle text-lg">No GPS history entries found.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                        <tr class="text-mb-subtle text-xs uppercase">
                            <th class="px-6 py-4 text-left">Date</th>
                            <th class="px-6 py-4 text-left">Reservation</th>
                            <th class="px-6 py-4 text-left">Client</th>
                            <th class="px-6 py-4 text-left">Vehicle</th>
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-left">Location</th>
                            <th class="px-6 py-4 text-left">At Location</th>
                            <th class="px-6 py-4 text-left">Reason</th>
                            <th class="px-6 py-4 text-left">Updated By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($rows as $row):
                            $isAtLocation = ((int)($row['tracking_active'] ?? 1)) === 1;
                            $statusColors = [
                                'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
                                'confirmed' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                'active' => 'bg-green-500/10 text-green-400 border-green-500/30',
                                'completed' => 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/30',
                            ];
                            $sc = $statusColors[$row['status']] ?? 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/30';
                            $updatedAt = $row['updated_at'] ?? $row['last_seen'] ?? null;
                            $updatedByLabel = !empty($row['updated_by_name']) ? $row['updated_by_name'] : ($row['updated_by'] ? 'User #'.$row['updated_by'] : '-');
                            ?>
                            <tr class="hover:bg-mb-black/30 transition-colors">
                                <td class="px-6 py-3 text-mb-silver text-xs whitespace-nowrap">
                                    <?= $updatedAt ? e(date('d M Y, h:i A', strtotime((string) $updatedAt))) : '&mdash;' ?>
                                </td>
                                <td class="px-6 py-3">
                                    <a href="../reservations/show.php?id=<?= (int) $row['reservation_id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors font-light">#<?= (int) $row['reservation_id'] ?></a>
                                </td>
                                <td class="px-6 py-3">
                                    <a href="../clients/show.php?id=<?= (int) $row['client_id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors"><?= e($row['client_name']) ?></a>
                                </td>
                                <td class="px-6 py-3">
                                    <p class="text-mb-silver"><?= e($row['brand']) ?> <?= e($row['model']) ?></p>
                                    <p class="text-mb-subtle text-xs"><?= e($row['license_plate']) ?></p>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs border capitalize <?= $sc ?>"><?= e($row['status']) ?></span>
                                </td>
                                <td class="px-6 py-3 text-white text-sm"><?= e($row['last_location'] ?? '') ?: '<span class="text-mb-subtle">&mdash;</span>' ?></td>
                                <td class="px-6 py-3">
                                    <?php if ($isAtLocation): ?>
                                        <span class="text-green-400 text-xs font-medium">Yes</span>
                                    <?php else: ?>
                                        <span class="text-red-400 text-xs font-medium">No</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-mb-subtle text-xs max-w-xs truncate"><?= e($row['notes'] ?? '') ?></td>
                                <td class="px-6 py-3 text-mb-silver text-xs whitespace-nowrap"><?= e($updatedByLabel) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$_qp = array_filter(['search' => $search, 'status' => $status], static fn($v) => $v !== '' && $v !== null);
echo render_pagination($pgHist, $_qp);
require_once __DIR__ . '/../includes/footer.php';
?>