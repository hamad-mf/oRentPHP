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

// Fetch all vehicles with their reservation status for the selected date
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

// Only keep available vehicles (not in maintenance and no active reservation)
$availableVehicles = [];
foreach ($vehicles as $v) {
    if ($v['status'] !== 'maintenance' && !$v['reservation_id']) {
        $availableVehicles[] = $v;
    }
}

$availableCount = count($availableVehicles);
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

    <!-- Available Count -->
    <div class="inline-flex items-center gap-3 bg-green-500/10 border border-green-500/30 rounded-xl px-5 py-4">
        <span class="w-3 h-3 rounded-full bg-green-500"></span>
        <span class="text-green-400 text-lg font-semibold"><?= $availableCount ?></span>
        <span class="text-mb-subtle text-sm">vehicle<?= $availableCount !== 1 ? 's' : '' ?> available on <?= date('d M Y', strtotime($selectedDate)) ?></span>
    </div>

    <!-- Vehicle List -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-mb-black/50 text-mb-subtle text-sm uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-5 py-4 font-medium">Vehicle</th>
                        <th class="text-left px-5 py-4 font-medium">License Plate</th>
                        <th class="text-left px-5 py-4 font-medium">Daily Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/20">
                    <?php if (empty($availableVehicles)): ?>
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-mb-subtle">
                                No available vehicles for this date.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($availableVehicles as $v): ?>
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
                                            <p class="text-mb-subtle text-sm"><?= e($v['year'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-white font-mono"><?= e($v['license_plate']) ?></span>
                                </td>

                                <td class="px-5 py-4">
                                    <?php if (!empty($v['daily_rate']) && $v['daily_rate'] > 0): ?>
                                        <span class="text-green-400 font-medium">$ <?= number_format($v['daily_rate'], 0) ?> <span class="text-mb-subtle font-normal text-xs">/day</span></span>
                                    <?php else: ?>
                                        <span class="text-mb-subtle">-</span>
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
