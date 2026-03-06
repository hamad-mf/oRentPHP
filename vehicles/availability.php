<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/vehicle_helpers.php';

$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
vehicle_ensure_schema($pdo);

$selectedDate = trim($_GET['date'] ?? '');
$today = date('Y-m-d');

if ($selectedDate === '') {
    $selectedDate = $today;
}

$dateCheck = DateTime::createFromFormat('Y-m-d', $selectedDate);
if (!$dateCheck || $dateCheck->format('Y-m-d') !== $selectedDate) {
    $selectedDate = $today;
}

$sql = "SELECT v.*,
               r.id AS reservation_id,
               r.status AS reservation_status,
               r.start_date AS res_start_date,
               r.end_date AS res_end_date,
               r.delivered_at,
               c.name AS client_name,
               c.phone AS client_phone
        FROM vehicles v
        LEFT JOIN reservations r ON r.vehicle_id = v.id
            AND r.status IN ('pending', 'confirmed', 'active')
            AND DATE(r.start_date) <= ?
            AND DATE(r.end_date) >= ?
        LEFT JOIN clients c ON r.client_id = c.id
        ORDER BY v.brand, v.model, v.license_plate";

$stmt = $pdo->prepare($sql);
$stmt->execute([$selectedDate, $selectedDate]);
$vehicles = $stmt->fetchAll();

$availableCount = 0;
$reservedCount = 0;
$rentedCount = 0;
$maintenanceCount = 0;

$vehicleStatusList = [];
foreach ($vehicles as $v) {
    $status = 'available';
    $statusLabel = 'Available';
    $detail = '';
    $detail2 = '';
    
    if ($v['status'] === 'maintenance') {
        $status = 'maintenance';
        $statusLabel = 'Maintenance';
        $maintenanceCount++;
    } elseif ($v['reservation_id']) {
        $isDelivered = $v['reservation_status'] === 'active';
        
        if ($isDelivered) {
            $status = 'rented';
            $statusLabel = 'Rented (Delivered)';
            $rentedCount++;
            $detail = e($v['client_name']);
            $detail2 = 'Booking: ' . date('d M', strtotime($v['res_start_date'])) . ' - ' . date('d M', strtotime($v['res_end_date']));
            if (!empty($v['delivered_at'])) {
                $detail2 .= ' | Delivered: ' . date('d M, h:i A', strtotime($v['delivered_at']));
            }
        } else {
            $status = 'reserved';
            $statusLabel = 'Reserved (Not Delivered)';
            $reservedCount++;
            $detail = e($v['client_name']);
            $detail2 = 'Booking: ' . date('d M', strtotime($v['res_start_date'])) . ' - ' . date('d M', strtotime($v['res_end_date']));
        }
    } else {
        $availableCount++;
    }
    
    $vehicleStatusList[] = [
        'vehicle' => $v,
        'status' => $status,
        'statusLabel' => $statusLabel,
        'detail' => $detail,
        'detail2' => $detail2,
    ];
}

$totalCount = count($vehicles);
$success = getFlash('success');
$error = getFlash('error');

$pageTitle = 'Vehicle Availability';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">

    <?php if ($success): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Date Filter -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
        <form method="get" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm text-mb-subtle mb-2">Select Date</label>
                <input type="date" name="date" value="<?= e($selectedDate) ?>"
                    class="bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-mb-accent">
            </div>
            <button type="submit"
                class="bg-mb-accent hover:bg-mb-accent/80 text-white font-medium px-5 py-2.5 rounded-lg transition-colors">
                Check Availability
            </button>
            <a href="?date=<?= $today ?>" class="text-mb-subtle hover:text-white text-sm py-2.5">
                Today
            </a>
        </form>
    </div>

    <!-- Status Summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <?php
        $summaryCards = [
            ['label' => 'Total Vehicles', 'count' => $totalCount, 'color' => 'text-white', 'bg' => 'bg-mb-surface'],
            ['label' => 'Available', 'count' => $availableCount, 'color' => 'text-green-400', 'bg' => 'bg-green-500/10'],
            ['label' => 'Reserved', 'count' => $reservedCount, 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10'],
            ['label' => 'Rented', 'count' => $rentedCount, 'color' => 'text-mb-accent', 'bg' => 'bg-mb-accent/10'],
            ['label' => 'Maintenance', 'count' => $maintenanceCount, 'color' => 'text-red-400', 'bg' => 'bg-red-500/10'],
        ];
        foreach ($summaryCards as $card): ?>
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-4">
                <p class="text-mb-subtle text-xs uppercase tracking-wide mb-1"><?= e($card['label']) ?></p>
                <p class="<?= e($card['color']) ?> text-2xl font-semibold"><?= $card['count'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4 text-sm">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            <span class="text-mb-subtle">Available</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-amber-500"></span>
            <span class="text-mb-subtle">Reserved (Not Delivered)</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-mb-accent"></span>
            <span class="text-mb-subtle">Rented (Delivered)</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-red-500"></span>
            <span class="text-mb-subtle">Maintenance</span>
        </div>
    </div>

    <!-- Vehicle List -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-mb-black/50 text-mb-subtle text-sm uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-5 py-4 font-medium">Vehicle</th>
                        <th class="text-left px-5 py-4 font-medium">License Plate</th>
                        <th class="text-left px-5 py-4 font-medium">Status</th>
                        <th class="text-left px-5 py-4 font-medium">Client</th>
                        <th class="text-left px-5 py-4 font-medium">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/20">
                    <?php if (empty($vehicleStatusList)): ?>
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-mb-subtle">
                                No vehicles found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicleStatusList as $item): 
                            $v = $item['vehicle'];
                            $badgeClass = match($item['status']) {
                                'available' => 'bg-green-500/10 text-green-400 border-green-500/30',
                                'reserved' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                                'rented' => 'bg-mb-accent/10 text-mb-accent border-mb-accent/30',
                                'maintenance' => 'bg-red-500/10 text-red-400 border-red-500/30',
                                default => 'bg-gray-500/10 text-gray-400 border-gray-500/30',
                            };
                        ?>
                            <tr class="hover:bg-mb-black/30 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($v['image_url'])): ?>
                                            <img src="<?= e($v['image_url']) ?>" alt="" class="w-12 h-12 object-cover rounded-lg">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-mb-black rounded-lg flex items-center justify-center">
                                                <svg class="w-6 h-6 text-mb-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 012-2 2 2 0 002 2 2 2 0 11-2-2 2 2 0 00-2 2z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-white font-medium"><?= e($v['brand']) ?> <?= e($v['model']) ?></p>
                                            <p class="text-mb-subtle text-sm"><?= e($v['category'] ?? 'Sedan') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-white font-mono"><?= e($v['license_plate']) ?></span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium border <?= $badgeClass ?>">
                                        <?= e($item['statusLabel']) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <?php if ($item['detail']): ?>
                                        <p class="text-white"><?= $item['detail'] ?></p>
                                        <?php if ($item['detail2']): ?>
                                            <p class="text-mb-subtle text-sm"><?= $item['detail2'] ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-mb-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4">
                                    <?php if ($item['status'] !== 'available' && $item['status'] !== 'maintenance' && $v['reservation_id']): ?>
                                        <a href="../reservations/show.php?id=<?= (int) $v['reservation_id'] ?>" 
                                           class="text-mb-accent hover:text-mb-accent/80 text-sm">
                                            View Reservation
                                        </a>
                                    <?php elseif ($item['status'] === 'maintenance'): ?>
                                        <span class="text-mb-subtle text-sm">In workshop</span>
                                    <?php else: ?>
                                        <span class="text-mb-subtle text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
