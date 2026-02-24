<?php
require_once __DIR__ . '/../config/db.php';

$pdo = db();
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(brand LIKE ? OR model LIKE ? OR license_plate LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT v.*, (SELECT COUNT(*) FROM documents d WHERE d.vehicle_id = v.id) AS doc_count FROM vehicles v WHERE ' . implode(' AND ', $where) . ' ORDER BY v.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

$totalCount = $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
$available = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn();
$rented = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='rented'")->fetchColumn();
$maintenance = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='maintenance'")->fetchColumn();

$success = getFlash('success');
$error = getFlash('error');

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
            $href = '?' . http_build_query(array_filter(['status' => $card['filter'], 'search' => $search]));
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
        <form method="GET" class="flex items-center gap-3 flex-1">
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
            <button type="submit" class="text-mb-silver hover:text-white text-sm transition-colors">Search</button>
            <?php if ($search || $status): ?><a href="index.php"
                    class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a>
            <?php endif; ?>
        </form>
        <a href="create.php"
            class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors flex items-center gap-2 text-sm font-medium flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
            </svg>
            Add Vehicle
        </a>
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
                ?>
                <div onclick="window.location='show.php?id=<?= $v['id'] ?>'"
                    class="bg-mb-surface rounded-xl border border-mb-subtle/20 overflow-hidden group hover:border-mb-accent/30 transition-all duration-300 flex flex-col cursor-pointer">
                    <!-- Image -->
                    <div class="h-44 bg-mb-black relative overflow-hidden">
                        <?php if ($v['image_url']): ?>
                            <img src="<?= e($v['image_url']) ?>" alt="<?= e($v['brand']) ?> <?= e($v['model']) ?>"
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
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>