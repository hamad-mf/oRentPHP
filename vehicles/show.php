<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_condition') {
    if ($id <= 0) {
        flash('error', 'Invalid vehicle request.');
        redirect('index.php');
    }

    if (!auth_has_perm('add_vehicles')) {
        flash('error', 'You do not have permission to update vehicle condition notes.');
        redirect("show.php?id=$id");
    }

    $conditionNotes = trim((string) ($_POST['condition_notes'] ?? ''));
    if (mb_strlen($conditionNotes) > 5000) {
        flash('error', 'Condition notes are too long (max 5000 characters).');
        redirect("show.php?id=$id");
    }

    try {
        $upd = $pdo->prepare('UPDATE vehicles SET condition_notes = ? WHERE id = ?');
        $upd->execute([$conditionNotes !== '' ? $conditionNotes : null, $id]);
        app_log('ACTION', "Updated vehicle condition notes (ID: $id)");
        flash('success', $conditionNotes === '' ? 'Vehicle condition notes cleared.' : 'Vehicle condition notes updated.');
    } catch (Throwable $e) {
        app_log('ERROR', 'Vehicle condition notes update failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'vehicles/show.php',
            'vehicle_id' => $id,
        ]);
        flash('error', 'Unable to save condition notes. Please apply latest vehicle migration and try again.');
    }

    redirect("show.php?id=$id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_parts_due') {
    if ($id <= 0) {
        flash('error', 'Invalid vehicle request.');
        redirect('index.php');
    }

    if (!auth_has_perm('add_vehicles')) {
        flash('error', 'You do not have permission to update vehicle parts notes.');
        redirect("show.php?id=$id");
    }

    $partsDueNotes = trim((string) ($_POST['parts_due_notes'] ?? ''));
    if (mb_strlen($partsDueNotes) > 5000) {
        flash('error', 'Parts notes are too long (max 5000 characters).');
        redirect("show.php?id=$id");
    }

    try {
        $upd = $pdo->prepare('UPDATE vehicles SET parts_due_notes = ? WHERE id = ?');
        $upd->execute([$partsDueNotes !== '' ? $partsDueNotes : null, $id]);
        app_log('ACTION', "Updated vehicle parts due notes (ID: $id)");
        flash('success', $partsDueNotes === '' ? 'Vehicle parts notes cleared.' : 'Vehicle parts notes updated.');
    } catch (Throwable $e) {
        app_log('ERROR', 'Vehicle parts notes update failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'vehicles/show.php',
            'vehicle_id' => $id,
        ]);
        flash('error', 'Unable to save parts notes. Please apply latest vehicle migration and try again.');
    }

    redirect("show.php?id=$id");
}

$vStmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
$vStmt->execute([$id]);
$v = $vStmt->fetch();
if (!$v) {
    flash('error', 'Vehicle not found.');
    redirect('index.php');
}

$docs = $pdo->prepare('SELECT * FROM documents WHERE vehicle_id = ? ORDER BY created_at DESC');
$docs->execute([$id]);
$documents = $docs->fetchAll();

// Load uploaded photos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_images (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
      app_log('ERROR', 'Vehicle show: vehicle_images table ensure failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'vehicles/show.php',
        'vehicle_id' => $id,
    ]);
}
$imgStmt = $pdo->prepare('SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY sort_order, id');
$imgStmt->execute([$id]);
$vehiclePhotos = $imgStmt->fetchAll();
// Build slides: uploaded first, then URL fallback
$carouselSlides = [];
foreach ($vehiclePhotos as $p) { $carouselSlides[] = '../' . $p['file_path']; }
if (empty($carouselSlides) && !empty($v['image_url'])) { $carouselSlides[] = $v['image_url']; }

$resStmt = $pdo->prepare('SELECT r.*, c.name AS client_name FROM reservations r JOIN clients c ON r.client_id = c.id WHERE r.vehicle_id = ? ORDER BY r.created_at DESC');
$resStmt->execute([$id]);
$reservations = $resStmt->fetchAll();

$totalRevenue = array_sum(array_column($reservations, 'total_price'));

// Vehicle expenses from ledger
$expStmt = $pdo->prepare("SELECT le.*, u.name AS created_by_name FROM ledger_entries le LEFT JOIN users u ON u.id = le.created_by WHERE le.source_type = 'vehicle_expense' AND le.source_id = ? ORDER BY le.posted_at DESC");
$expStmt->execute([$id]);
$vehicleExpenses = $expStmt->fetchAll();
$totalExpenses = array_sum(array_column($vehicleExpenses, 'amount'));

// Bank accounts for expense form
ledger_ensure_schema($pdo);
$bankAccounts = $pdo->query("SELECT id, name FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();
$activeReservation = array_filter($reservations, fn($r) => $r['status'] === 'active');
$activeReservation = reset($activeReservation) ?: null;

$success = getFlash('success');
$error = getFlash('error');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = trim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/vehicles/show.php')), '/');
$vehicleCatalogUrl = $scheme . '://' . $host . ($basePath !== '' ? '/' . $basePath : '') . '/vehicles/catalog.php?vehicle_id=' . (int) $v['id'];

$pageTitle = e($v['brand']) . ' ' . e($v['model']);
require_once __DIR__ . '/../includes/header.php';

$badge = ['available' => 'bg-green-500/10 text-green-400 border-green-500/30', 'rented' => 'bg-sky-500/10 text-sky-400 border-sky-500/30', 'maintenance' => 'bg-red-500/10 text-red-400 border-red-500/30'];
$badgeCls = $badge[$v['status']] ?? 'bg-gray-500/10 text-gray-400';
$canEditCondition = auth_has_perm('add_vehicles');
$conditionNotes = (string) ($v['condition_notes'] ?? '');
$partsDueNotes = (string) ($v['parts_due_notes'] ?? '');
$insuranceTypeRaw = strtolower(trim((string) ($v['insurance_type'] ?? '')));
if ($insuranceTypeRaw === 'thrid class') {
    $insuranceTypeRaw = 'third class';
} elseif ($insuranceTypeRaw === 'bumber to bumber') {
    $insuranceTypeRaw = 'bumper to bumper';
}
$insuranceTypeLabel = 'Not set';
if ($insuranceTypeRaw !== '') {
    if ($insuranceTypeRaw === 'third class') {
        $insuranceTypeLabel = 'Third Class';
    } elseif ($insuranceTypeRaw === 'first class') {
        $insuranceTypeLabel = 'First Class';
    } elseif ($insuranceTypeRaw === 'bumper to bumper') {
        $insuranceTypeLabel = 'Bumper to Bumper';
    } else {
        $insuranceTypeLabel = ucwords($insuranceTypeRaw);
    }
}
$insuranceExpiryRaw = trim((string) ($v['insurance_expiry_date'] ?? ''));
$insuranceExpiryLabel = 'Not set';
$insuranceExpired = false;
if ($insuranceExpiryRaw !== '') {
    $expiryDateObj = DateTime::createFromFormat('Y-m-d', $insuranceExpiryRaw);
    if ($expiryDateObj && $expiryDateObj->format('Y-m-d') === $insuranceExpiryRaw) {
        $insuranceExpiryLabel = date('d M Y', strtotime($insuranceExpiryRaw));
        $insuranceExpired = $insuranceExpiryRaw < date('Y-m-d');
    }
}
$isThirdClassInsurance = ($insuranceTypeRaw === 'third class');
$insuranceRisk = $insuranceExpired || $isThirdClassInsurance;
$insuranceStatusLabel = 'Not set';
$insuranceStatusClass = 'text-mb-subtle';
if ($insuranceExpired) {
    $insuranceStatusLabel = 'Expired';
    $insuranceStatusClass = 'text-red-400';
} elseif ($isThirdClassInsurance) {
    $insuranceStatusLabel = 'Third Class Risk';
    $insuranceStatusClass = 'text-red-300';
} elseif ($insuranceTypeRaw !== '' || $insuranceExpiryRaw !== '') {
    $insuranceStatusLabel = 'Active';
    $insuranceStatusClass = 'text-green-400';
}
$maintenanceSinceLabel = '';
if (($v['status'] ?? '') === 'maintenance') {
    $maintenanceStartRaw = (string) ($v['maintenance_started_at'] ?? '');
    if ($maintenanceStartRaw === '') {
        $maintenanceStartRaw = (string) ($v['updated_at'] ?? $v['created_at'] ?? '');
    }
    $startTs = $maintenanceStartRaw !== '' ? strtotime($maintenanceStartRaw) : false;
    if ($startTs !== false) {
        $maintenanceSinceLabel = date('d M Y, h:i A', $startTs);
    }
}
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

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">
            <?= e($v['brand']) ?>
            <?= e($v['model']) ?>
        </span>
    </div>

    <!-- Hero -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="grid md:grid-cols-5">
            <!-- Photo Carousel -->
            <div class="md:col-span-2 h-64 md:h-auto bg-mb-black relative overflow-hidden" id="carousel-wrap">
                <?php if (empty($carouselSlides)): ?>
                    <div class="w-full h-full min-h-64 flex items-center justify-center">
                        <svg class="w-20 h-20 text-mb-subtle/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                <?php else: ?>
                    <!-- Slides -->
                    <?php foreach ($carouselSlides as $si => $slide): ?>
                        <img src="<?= e($slide) ?>"
                            class="carousel-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-300 <?= $si === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' ?>"
                            data-index="<?= $si ?>">
                    <?php endforeach; ?>
                    <?php if (count($carouselSlides) > 1): ?>
                        <!-- Arrows -->
                        <button onclick="carouselMove(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-black/50 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button onclick="carouselMove(1)" class="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-black/50 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <!-- Dots -->
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                            <?php foreach ($carouselSlides as $di => $s): ?>
                                <button onclick="carouselGo(<?= $di ?>)" class="carousel-dot w-2 h-2 rounded-full transition-colors <?= $di===0 ? 'bg-white' : 'bg-white/40' ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <!-- Thumbnail strip -->
                    <?php if (count($carouselSlides) > 1): ?>
                    <div class="absolute bottom-8 left-0 right-0 flex justify-center gap-2 px-3 z-10">
                        <?php foreach ($carouselSlides as $ti => $slide): ?>
                            <img src="<?= e($slide) ?>" onclick="carouselGo(<?= $ti ?>)"
                                class="carousel-thumb h-10 w-14 object-cover rounded cursor-pointer border-2 transition-all <?= $ti===0 ? 'border-white' : 'border-transparent opacity-60' ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <!-- Info -->
            <div class="md:col-span-3 p-7 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-white text-2xl font-light">
                            <?= e($v['brand']) ?>
                            <?= e($v['model']) ?>
                        </h2>
                        <p class="text-mb-silver text-sm mt-1">
                            <?= e($v['year']) ?> &bull;
                            <?= e($v['license_plate']) ?>
                            <?= $v['color'] ? ' &bull; ' . e($v['color']) : '' ?>
                        </p>
                    </div>
                    <span class="px-3 py-1.5 rounded-full text-sm border <?= $badgeCls ?>">
                        <?= ucfirst($v['status']) ?>
                    </span>
                </div>
                <?php if (($v['status'] ?? '') === 'maintenance'): ?>
                    <div class="rounded-xl bg-red-500/10 border border-red-500/30 p-4 space-y-2">
                        <?php if (!empty($v['maintenance_workshop_name'])): ?>
                            <p class="text-sm text-red-200">Workshop:
                                <span class="text-white"><?= e((string) $v['maintenance_workshop_name']) ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($v['maintenance_expected_return'])): ?>
                            <p class="text-sm text-yellow-300">Expected Return:
                                <span class="text-white"><?= e(date('d M Y', strtotime((string) $v['maintenance_expected_return']))) ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if ($maintenanceSinceLabel !== ''): ?>
                            <p class="text-xs text-red-200/80">In maintenance since
                                <?= e($maintenanceSinceLabel) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="rounded-xl border p-4 <?= $insuranceRisk ? 'bg-red-500/10 border-red-500/40' : 'bg-mb-black/30 border-mb-subtle/20' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-white">Insurance Details</p>
                        <?php if ($insuranceRisk): ?>
                            <span class="text-[10px] uppercase tracking-wide text-red-300">Attention</span>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <p class="text-mb-subtle uppercase">Type</p>
                            <p class="text-white mt-0.5"><?= e($insuranceTypeLabel) ?></p>
                        </div>
                        <div>
                            <p class="text-mb-subtle uppercase">Expiry</p>
                            <p class="text-white mt-0.5"><?= e($insuranceExpiryLabel) ?></p>
                        </div>
                        <div>
                            <p class="text-mb-subtle uppercase">Status</p>
                            <p class="mt-0.5 font-medium <?= e($insuranceStatusClass) ?>"><?= e($insuranceStatusLabel) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Daily Rate</p>
                        <p class="text-mb-accent text-2xl font-light mt-1">$
                            <?= number_format($v['daily_rate'], 0) ?>
                        </p>
                    </div>
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Monthly</p>
                        <p class="text-white text-2xl font-light mt-1">
                            <?= $v['monthly_rate'] ? '$' . number_format($v['monthly_rate'], 0) : '—' ?>
                        </p>
                    </div>
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Total Revenue</p>
                        <p class="text-green-400 text-2xl font-light mt-1">$
                            <?= number_format($totalRevenue, 0) ?>
                        </p>
                    </div>
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Total Expenses</p>
                        <p class="text-red-400 text-2xl font-light mt-1">$
                            <?= number_format($totalExpenses, 0) ?>
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="edit.php?id=<?= $v['id'] ?>"
                        class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Edit
                        Vehicle</a>
                    <a href="quotation.php?id=<?= $v['id'] ?>" target="_blank"
                        class="border border-amber-500/30 text-amber-400 px-5 py-2 rounded-full hover:bg-amber-500/10 transition-colors text-sm font-medium">Generate
                        Quotation</a>
                    <button type="button" onclick="document.getElementById('vehicleShareModal').classList.remove('hidden')"
                        class="border border-mb-subtle/30 text-mb-silver px-5 py-2 rounded-full hover:border-white/30 hover:text-white transition-all text-sm font-medium">Share
                        Vehicle Catalog</button>
                    <button type="button" onclick="document.getElementById('addExpenseModal').classList.remove('hidden')"
                        class="border border-amber-500/30 text-amber-400 px-5 py-2 rounded-full hover:bg-amber-500/10 transition-colors text-sm font-medium">Add Expense</button>
                    <?php if ($v['status'] !== 'rented'): ?>
                        <a href="delete.php?id=<?= $v['id'] ?>"
                            onclick="return confirm('Remove this vehicle from the fleet?')"
                            class="border border-red-500/30 text-red-400 px-5 py-2 rounded-full hover:bg-red-500/10 transition-colors text-sm">Delete</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Vehicle Condition Notes</h3>
            <span class="text-xs text-mb-subtle">Optional</span>
        </div>
        <div class="p-6">
            <?php if ($canEditCondition): ?>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="save_condition">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <textarea name="condition_notes" rows="5" maxlength="5000"
                        placeholder="Add notes about scratches, dents, tire condition, interior state, etc."
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"><?= e($conditionNotes) ?></textarea>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-mb-subtle">Use this note as the latest condition snapshot for this vehicle.</p>
                        <div class="flex items-center gap-2">
                            <?php if ($conditionNotes !== ''): ?>
                                <button type="submit" name="condition_notes" value=""
                                    class="px-4 py-2 rounded-full border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs">Clear</button>
                            <?php endif; ?>
                            <button type="submit"
                                class="px-5 py-2 rounded-full bg-mb-accent text-white hover:bg-mb-accent/80 transition-colors text-xs font-medium">Save
                                Notes</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <?php if ($conditionNotes !== ''): ?>
                    <p class="text-sm text-mb-silver whitespace-pre-line"><?= e($conditionNotes) ?></p>
                <?php else: ?>
                    <p class="text-sm text-mb-subtle italic">No condition notes added yet.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Parts Due (Upcoming Changes)</h3>
            <span class="text-xs text-mb-subtle">Optional</span>
        </div>
        <div class="p-6">
            <?php if ($canEditCondition): ?>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="save_parts_due">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <textarea name="parts_due_notes" rows="5" maxlength="5000"
                        placeholder="List parts that need replacement soon (e.g., brake pads, tires, battery, oil service) with any dates or notes."
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"><?= e($partsDueNotes) ?></textarea>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-mb-subtle">Use this to track upcoming maintenance parts for this vehicle.</p>
                        <div class="flex items-center gap-2">
                            <?php if ($partsDueNotes !== ''): ?>
                                <button type="submit" name="parts_due_notes" value=""
                                    class="px-4 py-2 rounded-full border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs">Clear</button>
                            <?php endif; ?>
                            <button type="submit"
                                class="px-5 py-2 rounded-full bg-mb-accent text-white hover:bg-mb-accent/80 transition-colors text-xs font-medium">Save
                                Parts Notes</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <?php if ($partsDueNotes !== ''): ?>
                    <p class="text-sm text-mb-silver whitespace-pre-line"><?= e($partsDueNotes) ?></p>
                <?php else: ?>
                    <p class="text-sm text-mb-subtle italic">No upcoming parts notes added yet.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Calendar -->
    <?php
    // Build a map of booked intervals: [['start' => 'Y-m-d', 'end' => 'Y-m-d', 'client' => '...', 'status' => '...'], ...]
    $bookedRanges = [];
    foreach ($reservations as $r) {
        if (in_array($r['status'], ['confirmed', 'active', 'pending'])) {
            $bookedRanges[] = [
                'start' => date('Y-m-d', strtotime($r['start_date'])),
                'end' => date('Y-m-d', strtotime($r['end_date'])),
                'client' => $r['client_name'],
                'status' => $r['status'],
            ];
        }
    }
    $bookedJson = json_encode($bookedRanges);
    ?>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Booking Calendar
                <span class="text-mb-subtle text-sm ml-2">Booked dates for this vehicle</span>
            </h3>
            <div class="flex items-center gap-4 text-xs text-mb-subtle">
                <span class="flex items-center gap-1.5"><span
                        class="w-3 h-3 rounded-sm bg-red-500/60 inline-block"></span> Booked</span>
                <span class="flex items-center gap-1.5"><span
                        class="w-3 h-3 rounded-sm bg-mb-accent/70 inline-block"></span> Today</span>
                <span class="flex items-center gap-1.5"><span
                        class="w-3 h-3 rounded-sm bg-mb-black/40 inline-block border border-mb-subtle/20"></span>
                    Available</span>
            </div>
        </div>
        <div class="p-6">
            <!-- Month navigation -->
            <div class="flex items-center justify-between mb-6">
                <button id="cal-prev"
                    class="w-8 h-8 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-mb-accent/50 hover:text-mb-accent transition-all text-mb-silver">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <span id="cal-title" class="text-white font-light text-sm tracking-wide"></span>
                <button id="cal-next"
                    class="w-8 h-8 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-mb-accent/50 hover:text-mb-accent transition-all text-mb-silver">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            <!-- Calendar grid -->
            <div id="cal-grid" class="select-none"></div>
            <!-- Tooltip -->
            <div id="cal-tip"
                class="hidden mt-4 text-xs bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-mb-silver">
            </div>
        </div>
    </div>

    
<script>
(function(){
    var slides = Array.from(document.querySelectorAll('.carousel-slide'));
    var dots   = Array.from(document.querySelectorAll('.carousel-dot'));
    var thumbs = Array.from(document.querySelectorAll('.carousel-thumb'));
    var cur = 0;
    function go(n) {
        if (!slides.length) return;
        slides[cur].classList.remove('opacity-100'); slides[cur].classList.add('opacity-0','pointer-events-none');
        dots[cur] && dots[cur].classList.replace('bg-white','bg-white/40');
        thumbs[cur] && thumbs[cur].classList.remove('border-white','opacity-100') && thumbs[cur].classList.add('border-transparent','opacity-60');
        cur = (n + slides.length) % slides.length;
        slides[cur].classList.remove('opacity-0','pointer-events-none'); slides[cur].classList.add('opacity-100');
        dots[cur] && dots[cur].classList.replace('bg-white/40','bg-white');
        thumbs[cur] && (thumbs[cur].classList.add('border-white'), thumbs[cur].classList.remove('border-transparent','opacity-60'));
    }
    window.carouselMove = function(d){ go(cur + d); };
    window.carouselGo   = function(n){ go(n); };
    document.addEventListener('keydown', function(e){ if(e.key==='ArrowLeft') go(cur-1); if(e.key==='ArrowRight') go(cur+1); });
})();
</script>
<script>
        (function () {
            const BOOKED = <?= $bookedJson ?>;
            const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            let viewYear, viewMonth;
            const today = new Date(); today.setHours(0, 0, 0, 0);
            viewYear = today.getFullYear();
            viewMonth = today.getMonth();

            function toDate(str) { const p = str.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

            function getBookingForDate(d) {
                const ds = d.toISOString().slice(0, 10);
                for (const b of BOOKED) {
                    if (ds >= b.start && ds <= b.end) return b;
                }
                return null;
            }

            function isStart(d) {
                const ds = d.toISOString().slice(0, 10);
                return BOOKED.some(b => b.start === ds);
            }

            function isEnd(d) {
                const ds = d.toISOString().slice(0, 10);
                return BOOKED.some(b => b.end === ds);
            }

            function render() {
                const grid = document.getElementById('cal-grid');
                const title = document.getElementById('cal-title');
                title.textContent = MONTHS[viewMonth] + ' ' + viewYear;

                const firstDay = new Date(viewYear, viewMonth, 1).getDay();
                const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

                let html = '<div class="grid grid-cols-7 gap-px">';
                // Day headers
                for (const d of DAYS) {
                    html += `<div class="text-center text-[10px] uppercase text-mb-subtle/60 py-1.5 font-medium tracking-wider">${d}</div>`;
                }
                // Empty cells before first
                for (let i = 0; i < firstDay; i++) {
                    html += '<div class="h-9"></div>';
                }
                // Day cells
                for (let day = 1; day <= daysInMonth; day++) {
                    const d = new Date(viewYear, viewMonth, day);
                    const booking = getBookingForDate(d);
                    const isToday = d.getTime() === today.getTime();

                    let cls = 'h-9 flex items-center justify-center text-xs rounded-md transition-all ';
                    let style = '';
                    let dataAttr = '';

                    if (booking) {
                        const s = isStart(d), e = isEnd(d);
                        cls += 'text-white font-medium cursor-pointer ';
                        if (s && e) cls += 'bg-red-500/70 rounded-md mx-1 ';
                        else if (s) cls += 'bg-red-500/70 rounded-l-md rounded-r-none ml-1 ';
                        else if (e) cls += 'bg-red-500/70 rounded-r-md rounded-l-none mr-1 ';
                        else cls += 'bg-red-500/40 rounded-none ';
                        dataAttr = `data-client="${booking.client}" data-status="${booking.status}" data-start="${booking.start}" data-end="${booking.end}"`;
                    } else if (isToday) {
                        cls += 'bg-mb-accent/70 text-white font-semibold ring-1 ring-mb-accent ';
                    } else {
                        cls += 'text-mb-silver hover:bg-mb-black/40 ';
                    }

                    html += `<div class="${cls}" ${dataAttr} onclick="calClick(this)">${day}</div>`;
                }
                html += '</div>';
                grid.innerHTML = html;
            }

            window.calClick = function (el) {
                const tip = document.getElementById('cal-tip');
                if (!el.dataset.client) { tip.classList.add('hidden'); return; }
                const s = new Date(el.dataset.start), e = new Date(el.dataset.end);
                const fmt = d => d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                const days = Math.round((e - s) / 86400000) + 1;
                tip.classList.remove('hidden');
                tip.innerHTML = `<span class="text-white font-medium">${el.dataset.client}</span> &mdash; <span class="capitalize text-yellow-400">${el.dataset.status}</span><br>
                <span class="text-mb-subtle">${fmt(s)} → ${fmt(e)}</span> <span class="text-mb-accent ml-2">${days} day${days > 1 ? 's' : ''}</span>`;
            };

            document.getElementById('cal-prev').onclick = () => {
                viewMonth--;
                if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                render();
            };
            document.getElementById('cal-next').onclick = () => {
                viewMonth++;
                if (viewMonth > 11) { viewMonth = 0; viewYear++; }
                render();
            };

            render();
        })();
    </script>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Documents -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
                <h3 class="text-white font-light">Documents <span class="text-mb-subtle text-sm ml-2">
                        <?= count($documents) ?> files
                    </span></h3>
            </div>
            <?php if (empty($documents)): ?>
                <p class="py-10 text-center text-mb-subtle text-sm italic">No documents uploaded yet.</p>
            <?php else: ?>
                <div class="p-4 space-y-2">
                    <?php foreach ($documents as $doc): ?>
                        <div class="flex items-center gap-3 p-3 bg-mb-black/30 rounded-lg">
                            <svg class="w-8 h-8 text-mb-accent flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-white text-sm truncate">
                                    <?= e($doc['title']) ?>
                                </p>
                                <p class="text-mb-subtle text-xs uppercase">
                                    <?= e($doc['type']) ?>
                                </p>
                            </div>
                            <a href="../<?= e($doc['file_path']) ?>" target="_blank"
                                class="text-mb-accent hover:text-white transition-colors text-xs">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rental History -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light">Rental History <span class="text-mb-subtle text-sm ml-2">
                        <?= count($reservations) ?> trips
                    </span></h3>
            </div>
            <?php if (empty($reservations)): ?>
                <p class="py-10 text-center text-mb-subtle text-sm italic">No rental history yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-mb-subtle/10">
                            <tr class="text-mb-subtle text-xs uppercase">
                                <th class="px-6 py-3 text-left">Client</th>
                                <th class="px-6 py-3 text-left">Period</th>
                                <th class="px-6 py-3 text-right">Total</th>
                                <th class="px-6 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mb-subtle/10">
                            <?php foreach ($reservations as $r): ?>
                                <tr class="hover:bg-mb-black/30 transition-colors">
                                    <td class="px-6 py-3 text-white">
                                        <?= e($r['client_name']) ?>
                                    </td>
                                    <td class="px-6 py-3 text-mb-silver text-xs">
                                        <?= e($r['start_date']) ?> →
                                        <?= e($r['end_date']) ?>
                                    </td>
                                    <td class="px-6 py-3 text-right text-mb-accent">$
                                        <?= number_format($r['total_price'], 0) ?>
                                    </td>
                                    <td class="px-6 py-3"><span class="text-xs capitalize">
                                            <?= e($r['status']) ?>
                                        </span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Single Vehicle Share Modal -->
<div id="vehicleShareModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Share This Vehicle</h3>
                <p class="text-mb-subtle text-sm mt-0.5">This link opens a public catalog page for only this vehicle.</p>
            </div>
            <button type="button" onclick="document.getElementById('vehicleShareModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs px-2.5 py-1 rounded-full bg-mb-black/50 text-mb-silver border border-mb-subtle/20">
                <?= e($v['brand']) ?> <?= e($v['model']) ?>
            </span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-mb-black/50 text-mb-silver border border-mb-subtle/20">
                <?= e($v['license_plate']) ?>
            </span>
        </div>

        <div class="bg-mb-black border border-mb-subtle/20 rounded-xl p-3 flex items-center gap-3 mb-4">
            <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <input type="text" id="vehicleCatalogLinkInput" value="<?= e($vehicleCatalogUrl) ?>" readonly
                class="flex-1 bg-transparent text-mb-silver text-sm focus:outline-none font-mono truncate cursor-text select-all">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <button type="button" id="copyVehicleCatalogBtn" onclick="copyVehicleCatalogLink()"
                class="bg-mb-accent text-white py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">
                Copy Link
            </button>
            <button type="button" onclick="nativeShareVehicleCatalog()"
                class="border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-white/20 py-2.5 rounded-full transition-colors text-sm font-medium">
                Share
            </button>
            <a href="<?= e($vehicleCatalogUrl) ?>" target="_blank"
                class="text-center border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-white/20 py-2.5 rounded-full transition-colors text-sm font-medium">
                Preview
            </a>
        </div>
    </div>
</div>

<script>
function copyVehicleCatalogLink() {
    const input = document.getElementById('vehicleCatalogLinkInput');
    const btn = document.getElementById('copyVehicleCatalogBtn');
    const done = () => {
        const old = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = old; }, 1400);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(done).catch(() => {
            input.focus();
            input.select();
            document.execCommand('copy');
            done();
        });
        return;
    }

    input.focus();
    input.select();
    document.execCommand('copy');
    done();
}

function nativeShareVehicleCatalog() {
    const url = document.getElementById('vehicleCatalogLinkInput').value;
    const title = '<?= e($v['brand'] . ' ' . $v['model']) ?>';
    if (navigator.share) {
        navigator.share({ title: title + ' - Vehicle Catalog', url: url }).catch(() => {});
    } else {
        copyVehicleCatalogLink();
    }
}
</script>

<!-- Vehicle Expenses -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
        <h3 class="text-white font-light">Vehicle Expenses <span class="text-mb-subtle text-sm ml-2"><?= count($vehicleExpenses) ?> entries &bull; Total: $<?= number_format($totalExpenses, 0) ?></span></h3>
        <button type="button" onclick="document.getElementById('addExpenseModal').classList.remove('hidden')"
            class="text-mb-accent hover:text-mb-accent/80 text-sm">+ Add Expense</button>
    </div>
    <?php if (empty($vehicleExpenses)): ?>
        <p class="py-10 text-center text-mb-subtle text-sm italic">No expenses recorded yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-mb-subtle/10">
                    <tr class="text-mb-subtle text-xs uppercase">
                        <th class="px-6 py-3 text-left">Date</th>
                        <th class="px-6 py-3 text-left">Category</th>
                        <th class="px-6 py-3 text-left">Description</th>
                        <th class="px-6 py-3 text-left">Payment</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php foreach ($vehicleExpenses as $exp): ?>
                        <tr class="hover:bg-mb-black/30 transition-colors">
                            <td class="px-6 py-3 text-mb-silver text-xs"><?= date('d M Y', strtotime($exp['posted_at'])) ?></td>
                            <td class="px-6 py-3 text-white"><?= e($exp['source_event'] ?? '') ?></td>
                            <td class="px-6 py-3 text-mb-subtle text-xs"><?= e($exp['description'] ?? '-') ?></td>
                            <td class="px-6 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full <?= ($exp['payment_mode'] === 'cash') ? 'bg-green-500/10 text-green-400' : (($exp['payment_mode'] === 'credit') ? 'bg-amber-500/10 text-amber-400' : 'bg-mb-accent/10 text-mb-accent') ?>"><?= ucfirst($exp['payment_mode'] ?? 'N/A') ?></span>
                            </td>
                            <td class="px-6 py-3 text-right text-red-400 font-medium">$ <?= number_format($exp['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Add Vehicle Expense</h3>
                <p class="text-mb-subtle text-sm mt-0.5"><?= e($v['brand'] . ' ' . $v['model']) ?> &bull; <?= e($v['license_plate']) ?></p>
            </div>
            <button type="button" onclick="document.getElementById('addExpenseModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" action="add_expense.php" class="space-y-4">
            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Category <span class="text-red-400">*</span></label>
                <select name="category" required class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value="">Select category...</option>
                    <option value="Fuel">Fuel</option>
                    <option value="Service">Service / Repair</option>
                    <option value="Insurance">Insurance</option>
                    <option value="Tyre">Tyre</option>
                    <option value="Washing">Washing</option>
                    <option value="Spare Parts">Spare Parts</option>
                    <option value="Toll/Parking">Toll / Parking</option>
                    <option value="RTO/Tax">RTO / Tax</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Amount <span class="text-red-400">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"
                    class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Payment Mode <span class="text-red-400">*</span></label>
                <select name="payment_mode" id="expPaymentMode" required onchange="document.getElementById('expBankSelect').style.display = this.value==='account' ? 'block' : 'none'"
                    class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value="cash">Cash</option>
                    <option value="credit">Credit</option>
                    <option value="account">Bank Account</option>
                </select>
            </div>
            <div id="expBankSelect" style="display:none">
                <label class="block text-sm text-mb-subtle mb-1.5">Bank Account</label>
                <select name="bank_account_id" class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <option value="">Select account...</option>
                    <?php foreach ($bankAccounts as $ba): ?>
                        <option value="<?= $ba['id'] ?>"><?= e($ba['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Description</label>
                <input type="text" name="description" placeholder="Optional notes..."
                    class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Date</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>
            <button type="submit"
                class="w-full bg-mb-accent hover:bg-mb-accent/80 text-white font-medium py-2.5 rounded-full transition-colors text-sm">
                Add Expense
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
