<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$dueToday = isset($_GET['due_today']);
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));

$isValidDate = static function (string $date): bool {
    if ($date === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $date;
};

if (!$isValidDate($fromDate)) {
    $fromDate = '';
}
if (!$isValidDate($toDate)) {
    $toDate = '';
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(c.name LIKE ? OR v.brand LIKE ? OR v.license_plate LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
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
if ($dueToday) {
    $where[] = "r.status = 'active' AND DATE(r.end_date) = CURDATE()";
}

$sql = 'SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate, v.daily_rate
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn(),
    'confirmed' => $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='confirmed'")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='completed'")->fetchColumn(),
];

$success = getFlash('success');
$error = getFlash('error');
$pageTitle = 'Reservations';
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

    <!-- Due Today Banner -->
    <?php if ($dueToday): ?>
        <div
            class="flex items-center justify-between bg-orange-500/10 border border-orange-500/30 text-orange-300 rounded-lg px-5 py-3 text-sm">
            <div class="flex items-center gap-2">
                <span>⏰</span>
                <span>Showing <strong>active rentals due today</strong> — these vehicles should be returned today.</span>
            </div>
            <a href="index.php" class="text-orange-400 hover:text-white text-xs underline ml-4">Clear filter</a>
        </div>
    <?php endif; ?>

    <!-- Status Tabs -->
    <?php if (!$dueToday): ?>
        <div class="flex items-center gap-1 bg-mb-surface border border-mb-subtle/20 rounded-xl p-1 w-fit flex-wrap">
            <?php
            $tabs = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'active' => 'Active', 'completed' => 'Completed'];
            foreach ($tabs as $val => $lbl):
                $active = $status === $val;
                $cnt = $counts[$val === '' ? 'all' : $val];
                ?>
                <a href="?<?= http_build_query(array_filter(['status' => $val, 'search' => $search, 'from_date' => $fromDate, 'to_date' => $toDate])) ?>"
                    class="px-4 py-2 rounded-lg text-sm transition-all <?= $active ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white hover:bg-mb-black/50' ?>">
                    <?= $lbl ?> <span class="ml-1 text-xs opacity-70">
                        <?= $cnt ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-3 flex-1">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <?php if ($dueToday): ?><input type="hidden" name="due_today" value="1">
            <?php endif; ?>
            <div class="relative flex-1 min-w-[220px] max-w-sm">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search client or vehicle..."
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
            <?php if ($search || $status || $fromDate || $toDate || $dueToday): ?><a href="index.php"
                    class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (auth_has_perm('add_reservations')): ?>
            <a href="create.php"
                class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors flex items-center gap-2 text-sm font-medium flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                New Reservation
            </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <?php if (empty($reservations)): ?>
            <div class="py-20 text-center">
                <svg class="w-16 h-16 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-mb-subtle text-lg">No reservations found.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                        <tr class="text-mb-subtle text-xs uppercase">
                            <th class="px-6 py-4 text-left">Client</th>
                            <th class="px-6 py-4 text-left">Vehicle</th>
                            <th class="px-6 py-4 text-left">Period</th>
                            <th class="px-6 py-4 text-right">Total</th>
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($reservations as $r):
                            $overdue = isOverdue($r['end_date'], $r['status']);
                            $statusColors = [
                                'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30',
                                'confirmed' => 'bg-sky-500/10 text-sky-400 border-sky-500/30',
                                'active' => 'bg-green-500/10 text-green-400 border-green-500/30',
                                'completed' => 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/30',
                            ];
                            $sc = $statusColors[$r['status']] ?? '';
                            ?>
                            <tr class="hover:bg-mb-black/30 transition-colors <?= $overdue ? 'bg-red-500/5' : '' ?>">
                                <td class="px-6 py-4">
                                    <a href="../clients/show.php?id=<?= $r['client_id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors font-light">
                                        <?= e($r['client_name']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-mb-silver">
                                        <?= e($r['brand']) ?>
                                        <?= e($r['model']) ?>
                                    </p>
                                    <p class="text-mb-subtle text-xs">
                                        <?= e($r['license_plate']) ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-mb-silver text-xs">
                                    <?= e($r['start_date']) ?> →
                                    <?= e($r['end_date']) ?>
                                    <?php if ($overdue): ?>
                                        <span
                                            class="ml-2 text-xs bg-red-500/20 text-red-400 border border-red-500/30 rounded-full px-2 py-0.5 animate-pulse">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right text-mb-accent font-medium">
                                    <?php
                                    $basePrice = (float) $r['total_price'];
                                    $voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
                                    $deliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
                                    $deliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
                                    $delivDiscType = $r['delivery_discount_type'] ?? null;
                                    $delivDiscVal = (float) ($r['delivery_discount_value'] ?? 0);
                                    $delivBase = max(0, $basePrice - $voucherApplied) + $deliveryCharge + $deliveryManualAmount;
                                    $delivDiscountAmt = 0;
                                    if ($delivDiscType === 'percent') {
                                        $delivDiscountAmt = round($delivBase * min($delivDiscVal, 100) / 100, 2);
                                    } elseif ($delivDiscType === 'amount') {
                                        $delivDiscountAmt = min($delivDiscVal, $delivBase);
                                    }
                                    $baseCollectedAtDelivery = max(0, $delivBase - $delivDiscountAmt);

                                    $returnVoucherApplied = max(0, (float) ($r['return_voucher_applied'] ?? 0));
                                    $overdueAmt = (float) $r['overdue_amount'];
                                    $kmOverageChg = (float) ($r['km_overage_charge'] ?? 0);
                                    $damageChg = (float) ($r['damage_charge'] ?? 0);
                                    $additionalChg = (float) ($r['additional_charge'] ?? 0);
                                    $chellanChg = (float) ($r['chellan_amount'] ?? 0);
                                    $discType = $r['discount_type'] ?? null;
                                    $discVal = (float) ($r['discount_value'] ?? 0);

                                    $returnChargesBeforeDiscount = $overdueAmt + $kmOverageChg + $damageChg + $additionalChg + $chellanChg;
                                    $discountAmt = 0;
                                    if ($discType === 'percent') {
                                        $discountAmt = round($returnChargesBeforeDiscount * min($discVal, 100) / 100, 2);
                                    } elseif ($discType === 'amount') {
                                        $discountAmt = min($discVal, $returnChargesBeforeDiscount);
                                    }
                                    $amountDueAtReturn = max(0, $returnChargesBeforeDiscount - $discountAmt);
                                    $cashDueAtReturn = max(0, $amountDueAtReturn - $returnVoucherApplied);
                                    $totalCollected = $baseCollectedAtDelivery + $cashDueAtReturn;
                                    echo '$' . number_format($totalCollected, 2);
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs border capitalize <?= $sc ?>">
                                        <?= e($r['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="show.php?id=<?= $r['id'] ?>"
                                            class="text-mb-subtle hover:text-white transition-colors p-1.5 rounded hover:bg-white/5"
                                            title="View">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                        <?php if (auth_has_perm('add_reservations') && in_array($r['status'], ['pending', 'confirmed', 'active'])): ?>
                                            <a href="edit.php?id=<?= $r['id'] ?>"
                                                class="text-mb-subtle hover:text-white transition-colors p-1.5 rounded hover:bg-white/5"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (auth_has_perm('do_delivery') && $r['status'] === 'confirmed'): ?>
                                            <a href="deliver.php?id=<?= $r['id'] ?>"
                                                class="text-green-400 hover:text-white transition-colors p-1.5 rounded hover:bg-green-500/10 text-xs font-medium"
                                                title="Deliver">▶ Deliver</a>
                                        <?php endif; ?>
                                        <?php if (auth_has_perm('do_return') && $r['status'] === 'active'): ?>
                                            <a href="return.php?id=<?= $r['id'] ?>"
                                                class="text-mb-accent hover:text-white transition-colors p-1.5 rounded hover:bg-mb-accent/10 text-xs font-medium"
                                                title="Return">⏎ Return</a>
                                        <?php endif; ?>
                                        <?php if (auth_has_perm('add_reservations') && !in_array($r['status'], ['active', 'completed'])): ?>
                                            <a href="delete.php?id=<?= $r['id'] ?>"
                                                onclick="return confirm('Cancel and delete this reservation?')"
                                                class="text-mb-subtle hover:text-red-400 transition-colors p-1.5 rounded hover:bg-red-500/5"
                                                title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
