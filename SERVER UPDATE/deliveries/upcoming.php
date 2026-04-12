<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/logger.php';

$pageTitle = 'Upcoming Deliveries';

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$searchQuery = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

/**
 * Get all upcoming deliveries (confirmed reservations with future start dates)
 */
function get_all_upcoming_deliveries(PDO $pdo, int $limit, int $offset, string $search = '', string $dateFrom = '', string $dateTo = ''): array
{
    try {
        $today = date('Y-m-d H:i:s');
        
        $sql = "
            SELECT r.id, r.start_date, r.end_date, r.total_price,
                   COALESCE(c.name, 'Unknown Client') AS client_name,
                   c.id AS client_id,
                   COALESCE(v.brand, 'Unknown') AS brand,
                   COALESCE(v.model, 'Vehicle') AS model,
                   COALESCE(v.license_plate, '') AS license_plate,
                   v.id AS vehicle_id
            FROM reservations r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            WHERE r.status = 'confirmed'
              AND r.start_date >= ?
        ";
        
        $params = [$today];
        
        // Search filter
        if ($search !== '') {
            $sql .= " AND (c.name LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.license_plate LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Date range filter
        if ($dateFrom !== '') {
            $sql .= " AND DATE(r.start_date) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND DATE(r.start_date) <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY r.start_date ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        app_log('ERROR', 'Upcoming deliveries query failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'deliveries/upcoming.php',
        ]);
        return [];
    }
}

/**
 * Count total upcoming deliveries
 */
function count_upcoming_deliveries(PDO $pdo, string $search = '', string $dateFrom = '', string $dateTo = ''): int
{
    try {
        $today = date('Y-m-d H:i:s');
        
        $sql = "
            SELECT COUNT(*) as total
            FROM reservations r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            WHERE r.status = 'confirmed'
              AND r.start_date >= ?
        ";
        
        $params = [$today];
        
        if ($search !== '') {
            $sql .= " AND (c.name LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.license_plate LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        if ($dateFrom !== '') {
            $sql .= " AND DATE(r.start_date) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= " AND DATE(r.start_date) <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        app_log('ERROR', 'Count upcoming deliveries failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
        ]);
        return 0;
    }
}

/**
 * Calculate urgency level for delivery
 */
function calculate_urgency(string $deliveryDate): array
{
    $today = date('Y-m-d');
    $deliveryDay = date('Y-m-d', strtotime($deliveryDate));
    $daysUntil = (int) floor((strtotime($deliveryDay) - strtotime($today)) / 86400);
    
    if ($daysUntil === 0) {
        return [
            'level' => 'critical',
            'text' => 'Due Today',
            'class' => 'bg-red-500/15 text-red-400 border-red-500/30'
        ];
    } elseif ($daysUntil === 1) {
        return [
            'level' => 'high',
            'text' => 'Tomorrow',
            'class' => 'bg-orange-500/15 text-orange-400 border-orange-500/30'
        ];
    } elseif ($daysUntil <= 3) {
        return [
            'level' => 'medium',
            'text' => "In {$daysUntil} days",
            'class' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'
        ];
    } else {
        return [
            'level' => 'normal',
            'text' => "In {$daysUntil} days",
            'class' => 'bg-blue-500/10 text-blue-400 border-blue-500/20'
        ];
    }
}

// Fetch data
$deliveries = get_all_upcoming_deliveries($pdo, $perPage, $offset, $searchQuery, $dateFrom, $dateTo);
$totalCount = count_upcoming_deliveries($pdo, $searchQuery, $dateFrom, $dateTo);
$totalPages = ceil($totalCount / $perPage);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-1">Upcoming Deliveries</h1>
            <p class="text-mb-subtle text-sm">All confirmed reservations scheduled for delivery</p>
        </div>
        <a href="../index.php" class="px-4 py-2 bg-mb-surface border border-mb-subtle/20 rounded-lg text-white hover:border-mb-accent transition-colors text-sm">
            ← Back to Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-4 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-sm text-mb-silver mb-2">Search</label>
                <input type="text" name="search" value="<?= e($searchQuery) ?>" 
                       placeholder="Client name, vehicle, license plate..."
                       class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
            </div>
            
            <!-- Date From -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">From Date</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                       class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
            </div>
            
            <!-- Date To -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">To Date</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>"
                       class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
            </div>
            
            <!-- Buttons -->
            <div class="md:col-span-4 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-mb-accent text-white rounded-lg hover:bg-mb-accent/90 transition-colors text-sm font-medium">
                    Apply Filters
                </button>
                <a href="upcoming.php" class="px-4 py-2 bg-mb-surface border border-mb-subtle/20 rounded-lg text-white hover:border-mb-accent transition-colors text-sm">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Results Count -->
    <div class="flex items-center justify-between mb-4">
        <p class="text-mb-subtle text-sm">
            Showing <?= count($deliveries) ?> of <?= $totalCount ?> upcoming deliveries
        </p>
    </div>

    <!-- Deliveries List -->
    <?php if (empty($deliveries)): ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-12 text-center">
            <svg class="w-16 h-16 text-mb-subtle/40 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-mb-subtle text-lg mb-2">No Upcoming Deliveries</p>
            <p class="text-mb-subtle/60 text-sm">There are no confirmed reservations scheduled for delivery.</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($deliveries as $delivery):
                $urgency = calculate_urgency($delivery['start_date']);
                $deliveryDate = date('d M Y', strtotime($delivery['start_date']));
                $deliveryTime = date('h:i A', strtotime($delivery['start_date']));
                $endDate = date('d M Y', strtotime($delivery['end_date']));
            ?>
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-4 hover:border-mb-accent/30 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <!-- Main Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <a href="../reservations/show.php?id=<?= $delivery['id'] ?>" 
                                   class="text-white font-semibold hover:text-mb-accent transition-colors">
                                    Reservation #<?= $delivery['id'] ?>
                                </a>
                                <span class="px-2 py-0.5 rounded text-xs font-medium border <?= $urgency['class'] ?>">
                                    <?= $urgency['text'] ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                <!-- Client -->
                                <div>
                                    <span class="text-mb-subtle/60 block mb-1">Client</span>
                                    <a href="../clients/show.php?id=<?= $delivery['client_id'] ?>" 
                                       class="text-white hover:text-mb-accent transition-colors">
                                        <?= e($delivery['client_name']) ?>
                                    </a>
                                </div>
                                
                                <!-- Vehicle -->
                                <div>
                                    <span class="text-mb-subtle/60 block mb-1">Vehicle</span>
                                    <div class="text-white">
                                        <?= e($delivery['brand']) ?> <?= e($delivery['model']) ?>
                                        <?php if ($delivery['license_plate']): ?>
                                            <span class="text-mb-subtle/60 text-xs ml-1">(<?= e($delivery['license_plate']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Delivery Date -->
                                <div>
                                    <span class="text-mb-subtle/60 block mb-1">Delivery</span>
                                    <div class="text-white">
                                        <?= $deliveryDate ?> <span class="text-mb-accent"><?= $deliveryTime ?></span>
                                    </div>
                                    <div class="text-mb-subtle/60 text-xs mt-0.5">
                                        Return: <?= $endDate ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-2">
                            <a href="../reservations/show.php?id=<?= $delivery['id'] ?>" 
                               class="px-3 py-1.5 bg-mb-black border border-mb-subtle/20 rounded text-white hover:border-mb-accent transition-colors text-sm">
                                View
                            </a>
                            <a href="../reservations/deliver.php?id=<?= $delivery['id'] ?>" 
                               class="px-3 py-1.5 bg-mb-accent text-white rounded hover:bg-mb-accent/90 transition-colors text-sm font-medium">
                                Deliver
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-6">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>" 
                       class="px-3 py-1.5 bg-mb-surface border border-mb-subtle/20 rounded text-white hover:border-mb-accent transition-colors text-sm">
                        Previous
                    </a>
                <?php endif; ?>
                
                <span class="text-mb-subtle text-sm">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>" 
                       class="px-3 py-1.5 bg-mb-surface border border-mb-subtle/20 rounded text-white hover:border-mb-accent transition-colors text-sm">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
