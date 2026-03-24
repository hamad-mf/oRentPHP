<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$pdo = db();
// Allow staff to view detail pages with any vehicle permission, or admins
$canViewVehicleDetails = ($_SESSION['user']['role'] ?? '') === 'admin' || 
                         auth_has_perm('add_vehicles') || 
                         auth_has_perm('view_all_vehicles') || 
                         auth_has_perm('view_vehicle_availability') || 
                         auth_has_perm('view_vehicle_requests');
if (!$canViewVehicleDetails) {
    flash('error', 'You do not have permission to view vehicle details.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'mark_sold') {
    if ($id <= 0) { flash('error', 'Invalid vehicle.'); redirect('index.php'); }
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        flash('error', 'Only admins can mark a vehicle as sold.');
        redirect("show.php?id=$id");
    }
    $vCheck = $pdo->prepare('SELECT status FROM vehicles WHERE id = ?');
    $vCheck->execute([$id]);
    $vRow = $vCheck->fetch();
    if (!$vRow) { flash('error', 'Vehicle not found.'); redirect('index.php'); }
    if ($vRow['status'] === 'rented') {
        flash('error', 'Vehicle is currently rented and cannot be sold. Return the vehicle first.');
        redirect("show.php?id=$id");
    }
    if ($vRow['status'] === 'maintenance') {
        flash('error', 'Vehicle is currently in maintenance and cannot be sold. Remove it from maintenance first.');
        redirect("show.php?id=$id");
    }
    if ($vRow['status'] === 'sold') {
        flash('error', 'Vehicle is already marked as sold.');
        redirect("show.php?id=$id");
    }
    $futureStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE vehicle_id = ? AND status IN ('pending','confirmed','active')");
    $futureStmt->execute([$id]);
    $futureCount = (int) $futureStmt->fetchColumn();
    if ($futureCount > 0) {
        flash('error', "Vehicle has $futureCount open reservation(s) (pending/confirmed/active). Cancel or complete them first before marking as sold.");
        redirect("show.php?id=$id");
    }
    try {
        $pdo->prepare('UPDATE vehicles SET status = \'sold\', sold_at = NOW() WHERE id = ?')->execute([$id]);
        app_log('ACTION', "Vehicle marked as sold (ID: $id)");
        flash('success', 'Vehicle has been marked as sold and removed from the active fleet.');
    } catch (Throwable $e) {
        app_log('ERROR', 'Mark vehicle sold failed - ' . $e->getMessage(), ['vehicle_id' => $id]);
        flash('error', 'Unable to mark vehicle as sold. Please apply the vehicle_sold_status migration and try again.');
    }
    redirect("show.php?id=$id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_condition') {
    if ($id <= 0) { flash('error', 'Invalid vehicle request.'); redirect('index.php'); }
    if (!auth_has_perm('add_vehicles')) { flash('error', 'You do not have permission to update vehicle condition notes.'); redirect("show.php?id=$id"); }
    $conditionNotes = trim((string) ($_POST['condition_notes'] ?? ''));
    if (mb_strlen($conditionNotes) > 5000) { flash('error', 'Condition notes are too long (max 5000 characters).'); redirect("show.php?id=$id"); }
    try {
        $upd = $pdo->prepare('UPDATE vehicles SET condition_notes = ? WHERE id = ?');
        $upd->execute([$conditionNotes !== '' ? $conditionNotes : null, $id]);
        app_log('ACTION', "Updated vehicle condition notes (ID: $id)");
        flash('success', $conditionNotes === '' ? 'Vehicle condition notes cleared.' : 'Vehicle condition notes updated.');
    } catch (Throwable $e) {
        app_log('ERROR', 'Vehicle condition notes update failed - ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'screen' => 'vehicles/show.php', 'vehicle_id' => $id]);
        flash('error', 'Unable to save condition notes. Please apply latest vehicle migration and try again.');
    }
    redirect("show.php?id=$id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_parts_due') {
    if ($id <= 0) { flash('error', 'Invalid vehicle request.'); redirect('index.php'); }
    if (!auth_has_perm('add_vehicles')) { flash('error', 'You do not have permission to update vehicle parts notes.'); redirect("show.php?id=$id"); }
    $partsDueNotes = trim((string) ($_POST['parts_due_notes'] ?? ''));
    if (mb_strlen($partsDueNotes) > 5000) { flash('error', 'Parts notes are too long (max 5000 characters).'); redirect("show.php?id=$id"); }
    try {
        $upd = $pdo->prepare('UPDATE vehicles SET parts_due_notes = ? WHERE id = ?');
        $upd->execute([$partsDueNotes !== '' ? $partsDueNotes : null, $id]);
        app_log('ACTION', "Updated vehicle parts due notes (ID: $id)");
        flash('success', $partsDueNotes === '' ? 'Vehicle parts notes cleared.' : 'Vehicle parts notes updated.');
    } catch (Throwable $e) {
        app_log('ERROR', 'Vehicle parts notes update failed - ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'screen' => 'vehicles/show.php', 'vehicle_id' => $id]);
        flash('error', 'Unable to save parts notes. Please apply latest vehicle migration and try again.');
    }
    redirect("show.php?id=$id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_storage') {
    if ($id <= 0) { flash('error', 'Invalid vehicle request.'); redirect('index.php'); }
    if (!auth_has_perm('add_vehicles')) { flash('error', 'You do not have permission to update storage details.'); redirect("show.php?id=$id"); }
    $secondKeyLocation = trim((string) ($_POST['second_key_location'] ?? ''));
    $originalDocsLocation = trim((string) ($_POST['original_documents_location'] ?? ''));
    if (mb_strlen($secondKeyLocation) > 255) { flash('error', 'Second key location is too long (max 255 characters).'); redirect("show.php?id=$id"); }
    if (mb_strlen($originalDocsLocation) > 255) { flash('error', 'Original documents location is too long (max 255 characters).'); redirect("show.php?id=$id"); }
    try {
        $upd = $pdo->prepare('UPDATE vehicles SET second_key_location = ?, original_documents_location = ? WHERE id = ?');
        $upd->execute([$secondKeyLocation !== '' ? $secondKeyLocation : null, $originalDocsLocation !== '' ? $originalDocsLocation : null, $id]);
        app_log('ACTION', "Updated vehicle storage details (ID: $id)");
        flash('success', 'Vehicle storage details updated.');
    } catch (Throwable $e) {
        app_log('ERROR', 'Vehicle storage details update failed - ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'screen' => 'vehicles/show.php', 'vehicle_id' => $id]);
        flash('error', 'Unable to save storage details. Please apply latest vehicle migration and try again.');
    }
    redirect("show.php?id=$id");
}

$vStmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
$vStmt->execute([$id]);
$v = $vStmt->fetch();
if (!$v) { flash('error', 'Vehicle not found.'); redirect('index.php'); }

$docs = $pdo->prepare('SELECT * FROM documents WHERE vehicle_id = ? ORDER BY created_at DESC');
$docs->execute([$id]);
$documents = $docs->fetchAll();

// Load uploaded photos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_images (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    app_log('ERROR', 'Vehicle show: vehicle_images table ensure failed - ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'screen' => 'vehicles/show.php', 'vehicle_id' => $id]);
}
$imgStmt = $pdo->prepare('SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY sort_order, id');
$imgStmt->execute([$id]);
$vehiclePhotos = $imgStmt->fetchAll();
$carouselSlides = [];
foreach ($vehiclePhotos as $p) { $carouselSlides[] = '../' . $p['file_path']; }
if (empty($carouselSlides) && !empty($v['image_url'])) { $carouselSlides[] = $v['image_url']; }

$resStmt = $pdo->prepare('SELECT r.*, c.name AS client_name FROM reservations r JOIN clients c ON r.client_id = c.id WHERE r.vehicle_id = ? ORDER BY r.created_at DESC');
$resStmt->execute([$id]);
$reservations = $resStmt->fetchAll();
$totalRevenue = array_sum(array_column($reservations, 'total_price'));

$expStmt = $pdo->prepare("SELECT le.*, u.name AS created_by_name FROM ledger_entries le LEFT JOIN users u ON u.id = le.created_by WHERE le.source_type = 'vehicle_expense' AND le.source_id = ? ORDER BY le.posted_at DESC");
$expStmt->execute([$id]);
$vehicleExpenses = $expStmt->fetchAll();
$totalExpenses = array_sum(array_column($vehicleExpenses, 'amount'));

ledger_ensure_schema($pdo);
$bankAccounts = $pdo->query("SELECT id, name, bank_name FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();
$activeReservation = array_filter($reservations, fn($r) => $r['status'] === 'active');
$activeReservation = reset($activeReservation) ?: null;

// Load vehicle challans
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_challans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        due_date DATE DEFAULT NULL,
        status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}
$challanStmt = $pdo->prepare('SELECT * FROM vehicle_challans WHERE vehicle_id = ? ORDER BY due_date ASC, created_at DESC');
$challanStmt->execute([$id]);
$vehicleChallans = $challanStmt->fetchAll();

$success = getFlash('success');
$error = getFlash('error');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = trim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/vehicles/show.php')), '/');
$vehicleCatalogUrl = $scheme . '://' . $host . ($basePath !== '' ? '/' . $basePath : '') . '/vehicles/catalog.php?vehicle_id=' . (int) $v['id'];

$pageTitle = e($v['brand']) . ' ' . e($v['model']);
require_once __DIR__ . '/../includes/header.php';

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$isSold = ($v['status'] ?? '') === 'sold';
$badge = ['available' => 'bg-green-500/10 text-green-400 border-green-500/30', 'rented' => 'bg-sky-500/10 text-sky-400 border-sky-500/30', 'maintenance' => 'bg-red-500/10 text-red-400 border-red-500/30', 'sold' => 'bg-amber-500/10 text-amber-400 border-amber-500/30'];
$badgeCls = $badge[$v['status']] ?? 'bg-gray-500/10 text-gray-400';
$canEditCondition = auth_has_perm('add_vehicles');
$conditionNotes = (string) ($v['condition_notes'] ?? '');
$partsDueNotes = (string) ($v['parts_due_notes'] ?? '');
$insuranceTypeRaw = strtolower(trim((string) ($v['insurance_type'] ?? '')));
if ($insuranceTypeRaw === 'thrid class') { $insuranceTypeRaw = 'third class'; } elseif ($insuranceTypeRaw === 'bumber to bumber') { $insuranceTypeRaw = 'bumper to bumper'; }
$insuranceTypeLabel = 'Not set';
if ($insuranceTypeRaw !== '') {
    if ($insuranceTypeRaw === 'third class') { $insuranceTypeLabel = 'Third Class'; }
    elseif ($insuranceTypeRaw === 'first class') { $insuranceTypeLabel = 'First Class'; }
    elseif ($insuranceTypeRaw === 'bumper to bumper') { $insuranceTypeLabel = 'Bumper to Bumper'; }
    else { $insuranceTypeLabel = ucwords($insuranceTypeRaw); }
}
$insuranceExpiryRaw = trim((string) ($v['insurance_expiry_date'] ?? ''));
$insuranceExpiryLabel = 'Not set';
$insuranceExpired = false;
if ($insuranceExpiryRaw !== '') {
    $expiryDateObj = DateTime::createFromFormat('Y-m-d', $insuranceExpiryRaw);
    if ($expiryDateObj && $expiryDateObj->format('Y-m-d') === $insuranceExpiryRaw) { $insuranceExpiryLabel = date('d M Y', strtotime($insuranceExpiryRaw)); $insuranceExpired = $insuranceExpiryRaw < date('Y-m-d'); }
}
$isThirdClassInsurance = ($insuranceTypeRaw === 'third class');
$insuranceRisk = $insuranceExpired || $isThirdClassInsurance;
$insuranceStatusLabel = 'Not set'; $insuranceStatusClass = 'text-mb-subtle';
if ($insuranceExpired) { $insuranceStatusLabel = 'Expired'; $insuranceStatusClass = 'text-red-400'; }
elseif ($isThirdClassInsurance) { $insuranceStatusLabel = 'Third Class Risk'; $insuranceStatusClass = 'text-red-300'; }
elseif ($insuranceTypeRaw !== '' || $insuranceExpiryRaw !== '') { $insuranceStatusLabel = 'Active'; $insuranceStatusClass = 'text-green-400'; }
$pollutionExpiryRaw = trim((string) ($v['pollution_expiry_date'] ?? ''));
$pollutionExpiryLabel = 'Not set'; $pollutionExpired = false;
if ($pollutionExpiryRaw !== '') {
    $expiryDateObj = DateTime::createFromFormat('Y-m-d', $pollutionExpiryRaw);
    if ($expiryDateObj && $expiryDateObj->format('Y-m-d') === $pollutionExpiryRaw) { $pollutionExpiryLabel = date('d M Y', strtotime($pollutionExpiryRaw)); $pollutionExpired = $pollutionExpiryRaw < date('Y-m-d'); }
}
$pollutionStatusLabel = 'Not set'; $pollutionStatusClass = 'text-mb-subtle';
if ($pollutionExpired) { $pollutionStatusLabel = 'Expired'; $pollutionStatusClass = 'text-red-400'; }
elseif ($pollutionExpiryRaw !== '') { $pollutionStatusLabel = 'Active'; $pollutionStatusClass = 'text-green-400'; }
$secondKeyLocation = trim((string) ($v['second_key_location'] ?? ''));
$originalDocsLocation = trim((string) ($v['original_documents_location'] ?? ''));
$secondKeyLabel = $secondKeyLocation !== '' ? $secondKeyLocation : 'Not set';
$originalDocsLabel = $originalDocsLocation !== '' ? $originalDocsLocation : 'Not set';
$maintenanceSinceLabel = '';
if (($v['status'] ?? '') === 'maintenance') {
    $maintenanceStartRaw = (string) ($v['maintenance_started_at'] ?? '');
    if ($maintenanceStartRaw === '') { $maintenanceStartRaw = (string) ($v['updated_at'] ?? $v['created_at'] ?? ''); }
    $startTs = $maintenanceStartRaw !== '' ? strtotime($maintenanceStartRaw) : false;
    if ($startTs !== false) { $maintenanceSinceLabel = date('d M Y, h:i A', $startTs); }
}
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        <span class="text-white"><?= e($v['brand']) ?> <?= e($v['model']) ?></span>
    </div>

    <!-- Hero -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="grid md:grid-cols-5">
            <!-- Photo Carousel -->
            <div class="md:col-span-2 h-64 md:h-auto bg-mb-black relative overflow-hidden" id="carousel-wrap">
                <?php if (empty($carouselSlides)): ?>
                    <div class="w-full h-full min-h-64 flex items-center justify-center">
                        <svg class="w-20 h-20 text-mb-subtle/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                <?php else: ?>
                    <?php foreach ($carouselSlides as $si => $slide): ?>
                        <img src="<?= e($slide) ?>" class="carousel-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-300 <?= $si === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' ?>" data-index="<?= $si ?>">
                    <?php endforeach; ?>
                    <?php if (count($carouselSlides) > 1): ?>
                        <button onclick="carouselMove(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-black/50 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button onclick="carouselMove(1)" class="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-black/50 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                            <?php foreach ($carouselSlides as $di => $s): ?>
                                <button onclick="carouselGo(<?= $di ?>)" class="carousel-dot w-2 h-2 rounded-full transition-colors <?= $di===0 ? 'bg-white' : 'bg-white/40' ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (count($carouselSlides) > 1): ?>
                    <div class="absolute bottom-8 left-0 right-0 flex justify-center gap-2 px-3 z-10">
                        <?php foreach ($carouselSlides as $ti => $slide): ?>
                            <img src="<?= e($slide) ?>" onclick="carouselGo(<?= $ti ?>)" class="carousel-thumb h-10 w-14 object-cover rounded cursor-pointer border-2 transition-all <?= $ti===0 ? 'border-white' : 'border-transparent opacity-60' ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <!-- Info -->
            <div class="md:col-span-3 p-7 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-white text-2xl font-light"><?= e($v['brand']) ?> <?= e($v['model']) ?></h2>
                        <p class="text-mb-silver text-sm mt-1"><?= e($v['year']) ?> &bull; <?= e($v['license_plate']) ?><?= $v['color'] ? ' &bull; ' . e($v['color']) : '' ?></p>
                    </div>
                    <span class="px-3 py-1.5 rounded-full text-sm border <?= $badgeCls ?>"><?= ucfirst($v['status']) ?></span>
                </div>
                <?php if (($v['status'] ?? '') === 'maintenance'): ?>
                    <div class="rounded-xl bg-red-500/10 border border-red-500/30 p-4 space-y-2">
                        <?php if (!empty($v['maintenance_workshop_name'])): ?><p class="text-sm text-red-200">Workshop: <span class="text-white"><?= e((string) $v['maintenance_workshop_name']) ?></span></p><?php endif; ?>
                        <?php if (!empty($v['maintenance_expected_return'])): ?><p class="text-sm text-yellow-300">Expected Return: <span class="text-white"><?= e(date('d M Y', strtotime((string) $v['maintenance_expected_return']))) ?></span></p><?php endif; ?>
                        <?php if ($maintenanceSinceLabel !== ''): ?><p class="text-xs text-red-200/80">In maintenance since <?= e($maintenanceSinceLabel) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                <div class="rounded-xl border p-4 <?= $insuranceRisk ? 'bg-red-500/10 border-red-500/40' : 'bg-mb-black/30 border-mb-subtle/20' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-white">Insurance Details</p>
                        <?php if ($insuranceRisk): ?><span class="text-[10px] uppercase tracking-wide text-red-300">Attention</span><?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div><p class="text-mb-subtle uppercase">Type</p><p class="text-white mt-0.5"><?= e($insuranceTypeLabel) ?></p></div>
                        <div><p class="text-mb-subtle uppercase">Expiry</p><p class="text-white mt-0.5"><?= e($insuranceExpiryLabel) ?></p></div>
                        <div><p class="text-mb-subtle uppercase">Status</p><p class="mt-0.5 font-medium <?= e($insuranceStatusClass) ?>"><?= e($insuranceStatusLabel) ?></p></div>
                    </div>
                </div>
                <div class="rounded-xl border p-4 <?= $pollutionExpired ? 'bg-red-500/10 border-red-500/40' : 'bg-mb-black/30 border-mb-subtle/20' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-white">Pollution Certificate</p>
                        <?php if ($pollutionExpired): ?><span class="text-[10px] uppercase tracking-wide text-red-300">Attention</span><?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div><p class="text-mb-subtle uppercase">Expiry</p><p class="text-white mt-0.5"><?= e($pollutionExpiryLabel) ?></p></div>
                        <div><p class="text-mb-subtle uppercase">Status</p><p class="mt-0.5 font-medium <?= e($pollutionStatusClass) ?>"><?= e($pollutionStatusLabel) ?></p></div>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-mb-black/40 rounded-xl p-4"><p class="text-mb-subtle text-xs uppercase">Daily Rate</p><p class="text-mb-accent text-2xl font-light mt-1">$<?= number_format($v['daily_rate'], 0) ?></p></div>
                    <div class="bg-mb-black/40 rounded-xl p-4"><p class="text-mb-subtle text-xs uppercase">Monthly</p><p class="text-white text-2xl font-light mt-1"><?= $v['monthly_rate'] ? '$' . number_format($v['monthly_rate'], 0) : '—' ?></p></div>
                    <div class="bg-mb-black/40 rounded-xl p-4"><p class="text-mb-subtle text-xs uppercase">Total Revenue</p><p class="text-green-400 text-2xl font-light mt-1">$<?= number_format($totalRevenue, 0) ?></p></div>
                    <div class="bg-mb-black/40 rounded-xl p-4"><p class="text-mb-subtle text-xs uppercase">Total Expenses</p><p class="text-red-400 text-2xl font-light mt-1">$<?= number_format($totalExpenses, 0) ?></p></div>
                </div>
                <!-- Sold Banner -->
                <?php if ($isSold): ?>
                    <div class="rounded-xl bg-amber-500/10 border border-amber-500/30 p-4 flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-amber-400 text-sm font-medium">This vehicle has been sold</p>
                            <p class="text-amber-300/70 text-xs mt-0.5">No further actions can be performed. Historical data is preserved for reference.</p>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Actions -->
                <div class="flex items-center gap-3 flex-wrap">
                    <?php if (!$isSold && $isAdmin): ?>
                        <a href="edit.php?id=<?= $v['id'] ?>" class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Edit Vehicle</a>
                    <?php endif; ?>
                    <?php if (!$isSold): ?>
                        <a href="quotation.php?id=<?= $v['id'] ?>" target="_blank" class="border border-amber-500/30 text-amber-400 px-5 py-2 rounded-full hover:bg-amber-500/10 transition-colors text-sm font-medium">Generate Quotation</a>
                    <?php endif; ?>
                    <button type="button" onclick="document.getElementById('vehicleShareModal').classList.remove('hidden')" class="border border-mb-subtle/30 text-mb-silver px-5 py-2 rounded-full hover:border-white/30 hover:text-white transition-all text-sm font-medium">Share Vehicle Catalog</button>
                    <?php if (!$isSold && $isAdmin): ?>
                        <button type="button" onclick="document.getElementById('addExpenseModal').classList.remove('hidden')" class="border border-amber-500/30 text-amber-400 px-5 py-2 rounded-full hover:bg-amber-500/10 transition-colors text-sm font-medium">Add Expense</button>
                        <?php if ($v['status'] !== 'rented'): ?>
                            <a href="delete.php?id=<?= $v['id'] ?>" onclick="return confirm('Remove this vehicle from the fleet?')" class="border border-red-500/30 text-red-400 px-5 py-2 rounded-full hover:bg-red-500/10 transition-colors text-sm">Delete</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$isSold && $isAdmin): ?>
                        <form method="POST" onsubmit="return confirm('Mark this vehicle as sold? This cannot be undone. The vehicle will be removed from all active operations.')">
                            <input type="hidden" name="action" value="mark_sold">
                            <input type="hidden" name="id" value="<?= (int) $id ?>">
                            <button type="submit" class="border border-amber-600/40 text-amber-500 px-5 py-2 rounded-full hover:bg-amber-500/10 transition-colors text-sm font-medium">Mark as Sold</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Storage Details</h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-mb-subtle">Optional</span>
                <?php if ($canEditCondition): ?>
                    <button type="button" id="storageEditToggle" class="text-xs text-mb-accent hover:text-mb-accent/80 border border-mb-accent/30 px-3 py-1 rounded-full transition-colors">Edit</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <div id="storageReadOnly" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><p class="text-mb-subtle uppercase text-xs">Second Key</p><p class="text-white mt-1"><?= e($secondKeyLabel) ?></p></div>
                <div><p class="text-mb-subtle uppercase text-xs">Original Documents</p><p class="text-white mt-1"><?= e($originalDocsLabel) ?></p></div>
            </div>
            <?php if ($canEditCondition): ?>
                <form method="POST" id="storageEditForm" class="space-y-4 hidden">
                    <input type="hidden" name="action" value="save_storage">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">Second Key Stored At</label>
                            <input type="text" name="second_key_location" value="<?= e($secondKeyLocation) ?>" placeholder="Key cabinet A / Office safe" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        </div>
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">Original Documents Stored At</label>
                            <input type="text" name="original_documents_location" value="<?= e($originalDocsLocation) ?>" placeholder="Main office drawer / Bank locker" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" id="storageEditCancel" class="px-4 py-2 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-xs">Cancel</button>
                        <button type="submit" class="px-5 py-2 rounded-full bg-mb-accent text-white hover:bg-mb-accent/80 transition-colors text-xs font-medium">Save</button>
                    </div>
                </form>
            <?php endif; ?>
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
                    <textarea name="condition_notes" rows="5" maxlength="5000" placeholder="Add notes about scratches, dents, tire condition, interior state, etc." class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"><?= e($conditionNotes) ?></textarea>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-mb-subtle">Use this note as the latest condition snapshot for this vehicle.</p>
                        <div class="flex items-center gap-2">
                            <?php if ($conditionNotes !== ''): ?><button type="submit" name="condition_notes" value="" class="px-4 py-2 rounded-full border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs">Clear</button><?php endif; ?>
                            <button type="submit" class="px-5 py-2 rounded-full bg-mb-accent text-white hover:bg-mb-accent/80 transition-colors text-xs font-medium">Save Notes</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <?php if ($conditionNotes !== ''): ?><p class="text-sm text-mb-silver whitespace-pre-line"><?= e($conditionNotes) ?></p><?php else: ?><p class="text-sm text-mb-subtle italic">No condition notes added yet.</p><?php endif; ?>
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
                    <textarea name="parts_due_notes" rows="5" maxlength="5000" placeholder="List parts that need replacement soon (e.g., brake pads, tires, battery, oil service) with any dates or notes." class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"><?= e($partsDueNotes) ?></textarea>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-mb-subtle">Use this to track upcoming maintenance parts for this vehicle.</p>
                        <div class="flex items-center gap-2">
                            <?php if ($partsDueNotes !== ''): ?><button type="submit" name="parts_due_notes" value="" class="px-4 py-2 rounded-full border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs">Clear</button><?php endif; ?>
                            <button type="submit" class="px-5 py-2 rounded-full bg-mb-accent text-white hover:bg-mb-accent/80 transition-colors text-xs font-medium">Save Parts Notes</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <?php if ($partsDueNotes !== ''): ?><p class="text-sm text-mb-silver whitespace-pre-line"><?= e($partsDueNotes) ?></p><?php else: ?><p class="text-sm text-mb-subtle italic">No upcoming parts notes added yet.</p><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vehicle Challans -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Challans <span class="text-mb-subtle text-sm ml-2"><?= count($vehicleChallans) ?> records</span></h3>
        </div>
        <?php if (empty($vehicleChallans)): ?>
            <p class="py-10 text-center text-mb-subtle text-sm italic">No challans recorded for this vehicle.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-mb-subtle/10">
                        <tr class="text-mb-subtle text-xs uppercase">
                            <th class="px-6 py-3 text-left">Title</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 text-left">Due Date</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($vehicleChallans as $ch):
                            $isOverdue = false;
                            $dueDateRaw = trim((string)($ch['due_date'] ?? ''));
                            if ($dueDateRaw !== '' && $ch['status'] === 'pending') { $isOverdue = $dueDateRaw < date('Y-m-d'); }
                            $chDueDateFormatted = $dueDateRaw !== '' ? date('d M Y', strtotime($dueDateRaw)) : '';
                        ?>
                            <tr class="hover:bg-mb-black/30 transition-colors">
                                <td class="px-6 py-3 text-white"><?= e($ch['title']) ?></td>
                                <td class="px-6 py-3 text-right text-red-400">$<?= number_format($ch['amount'], 2) ?></td>
                                <td class="px-6 py-3 text-mb-silver text-xs">
                                    <?= $dueDateRaw !== '' ? e($chDueDateFormatted) : '<span class="text-mb-subtle italic">No due date</span>' ?>
                                </td>
                                <td class="px-6 py-3">
                                    <?php if ($ch['status'] === 'paid'): ?>
                                        <span class="text-xs px-2 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/30">Paid</span>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="text-xs px-2 py-1 rounded-full bg-red-500/10 text-red-400 border border-red-500/30">Overdue</span>
                                    <?php else: ?>
                                        <span class="text-xs px-2 py-1 rounded-full bg-yellow-500/10 text-yellow-400 border border-yellow-500/30">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($ch['status'] === 'pending'): ?>
                                            <button type="button"
                                                onclick="openChallanPaymentModal(<?= (int)$ch['id'] ?>, '<?= e(addslashes($ch['title'])) ?>', <?= (float)$ch['amount'] ?>, '<?= e($chDueDateFormatted) ?>')"
                                                class="text-xs text-green-400 hover:text-green-300">Mark Paid</button>
                                        <?php endif; ?>
                                        <form method="POST" action="delete_challan.php" class="inline" onsubmit="return confirm('Delete this challan?');">
                                            <input type="hidden" name="id" value="<?= (int)$ch['id'] ?>">
                                            <input type="hidden" name="vehicle_id" value="<?= (int)$id ?>">
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-300">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-t border-mb-subtle/10">
                        <tr class="text-white">
                            <td class="px-6 py-3 text-sm font-medium">Total Pending</td>
                            <td class="px-6 py-3 text-right text-red-400 font-medium">
                                $<?= number_format(array_sum(array_filter(array_column($vehicleChallans, 'amount'), fn($a, $k) => $vehicleChallans[$k]['status'] === 'pending', ARRAY_FILTER_USE_BOTH)), 2) ?>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking Calendar -->
    <?php
    $bookedRanges = [];
    foreach ($reservations as $res) {
        if (in_array($res['status'], ['confirmed', 'active', 'pending'])) {
            $bookedRanges[] = ['start' => date('Y-m-d', strtotime($res['start_date'])), 'end' => date('Y-m-d', strtotime($res['end_date'])), 'client' => $res['client_name'], 'status' => $res['status']];
        }
    }
    $bookedJson = json_encode($bookedRanges);
    ?>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Booking Calendar <span class="text-mb-subtle text-sm ml-2">Booked dates for this vehicle</span></h3>
            <div class="flex items-center gap-4 text-xs text-mb-subtle">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-500/60 inline-block"></span> Booked</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-mb-accent/70 inline-block"></span> Today</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-mb-black/40 inline-block border border-mb-subtle/20"></span> Available</span>
            </div>
        </div>
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <button id="cal-prev" class="w-8 h-8 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-mb-accent/50 hover:text-mb-accent transition-all text-mb-silver"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
                <span id="cal-title" class="text-white font-light text-sm tracking-wide"></span>
                <button id="cal-next" class="w-8 h-8 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-mb-accent/50 hover:text-mb-accent transition-all text-mb-silver"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
            </div>
            <div id="cal-grid" class="select-none"></div>
            <div id="cal-tip" class="hidden mt-4 text-xs bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-mb-silver"></div>
        </div>
    </div>
    <script>
    (function () {
        var BOOKED = <?= $bookedJson ?>;
        var DAYS=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
        var viewYear,viewMonth;
        var today=new Date();today.setHours(0,0,0,0);
        viewYear=today.getFullYear();viewMonth=today.getMonth();
        function getBookingForDate(d){var ds=d.toISOString().slice(0,10);for(var i=0;i<BOOKED.length;i++){if(ds>=BOOKED[i].start&&ds<=BOOKED[i].end)return BOOKED[i];}return null;}
        function isStart(d){var ds=d.toISOString().slice(0,10);return BOOKED.some(function(b){return b.start===ds;});}
        function isEnd(d){var ds=d.toISOString().slice(0,10);return BOOKED.some(function(b){return b.end===ds;});}
        function render(){
            var grid=document.getElementById('cal-grid');var title=document.getElementById('cal-title');
            title.textContent=MONTHS[viewMonth]+' '+viewYear;
            var firstDay=new Date(viewYear,viewMonth,1).getDay();var daysInMonth=new Date(viewYear,viewMonth+1,0).getDate();
            var html='<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">';
            for(var di=0;di<DAYS.length;di++){html+='<div style="text-align:center;font-size:10px;text-transform:uppercase;color:#6b7280;padding:6px 0;font-weight:500;">'+DAYS[di]+'</div>';}
            for(var ei=0;ei<firstDay;ei++)html+='<div style="height:36px;"></div>';
            for(var day=1;day<=daysInMonth;day++){
                var d=new Date(viewYear,viewMonth,day);var booking=getBookingForDate(d);var isTdy=d.getTime()===today.getTime();
                var style='height:36px;display:flex;align-items:center;justify-content:center;font-size:12px;border-radius:6px;cursor:default;';var extra='';
                if(booking){var s=isStart(d),e=isEnd(d);style+='color:#fff;font-weight:500;cursor:pointer;';
                    if(s&&e)style+='background:rgba(239,68,68,0.7);margin:0 4px;';
                    else if(s)style+='background:rgba(239,68,68,0.7);border-radius:6px 0 0 6px;margin-left:4px;';
                    else if(e)style+='background:rgba(239,68,68,0.7);border-radius:0 6px 6px 0;margin-right:4px;';
                    else style+='background:rgba(239,68,68,0.4);border-radius:0;';
                    extra=' data-client="'+booking.client.replace(/"/g,'&quot;')+'" data-status="'+booking.status+'" data-start="'+booking.start+'" data-end="'+booking.end+'" onclick="calClick(this)"';
                }else if(isTdy){style+='background:rgba(var(--mb-accent-rgb,59,130,246),0.7);color:#fff;font-weight:600;outline:1px solid rgba(59,130,246,0.8);';
                }else{style+='color:#9ca3af;';}
                html+='<div style="'+style+'"'+extra+'>'+day+'</div>';
            }
            html+='</div>';grid.innerHTML=html;
        }
        window.calClick=function(el){var tip=document.getElementById('cal-tip');if(!el.dataset.client){tip.style.display='none';return;}var s=new Date(el.dataset.start),e=new Date(el.dataset.end);var opts={day:'numeric',month:'short',year:'numeric'};var days=Math.round((e-s)/86400000)+1;tip.style.display='block';tip.innerHTML='<span style="color:#fff;font-weight:500;">'+el.dataset.client+'</span> &mdash; <span style="color:#facc15;text-transform:capitalize;">'+el.dataset.status+'</span><br><span style="color:#6b7280;">'+s.toLocaleDateString('en-GB',opts)+' &rarr; '+e.toLocaleDateString('en-GB',opts)+'</span> <span style="color:#60a5fa;margin-left:8px;">'+days+' day'+(days>1?'s':'')+'</span>';};
        document.getElementById('cal-prev').onclick=function(){viewMonth--;if(viewMonth<0){viewMonth=11;viewYear--;}render();};
        document.getElementById('cal-next').onclick=function(){viewMonth++;if(viewMonth>11){viewMonth=0;viewYear++;}render();};
        render();
    })();
    </script>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Documents -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
                <h3 class="text-white font-light">Documents <span class="text-mb-subtle text-sm ml-2"><?= count($documents) ?> files</span></h3>
            </div>
            <?php if (empty($documents)): ?>
                <p class="py-10 text-center text-mb-subtle text-sm italic">No documents uploaded yet.</p>
            <?php else: ?>
                <div class="p-4 space-y-2">
                    <?php foreach ($documents as $doc): ?>
                        <div class="flex items-center gap-3 p-3 bg-mb-black/30 rounded-lg">
                            <svg class="w-8 h-8 text-mb-accent flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-white text-sm truncate"><?= e($doc['title']) ?></p>
                                <p class="text-mb-subtle text-xs uppercase"><?= e($doc['type']) ?></p>
                            </div>
                            <a href="../<?= e($doc['file_path']) ?>" target="_blank" class="text-mb-accent hover:text-white transition-colors text-xs">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rental History -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light">Rental History <span class="text-mb-subtle text-sm ml-2"><?= count($reservations) ?> trips</span></h3>
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
                                    <td class="px-6 py-3 text-white"><?= e($r['client_name']) ?></td>
                                    <td class="px-6 py-3 text-mb-silver text-xs"><?= e($r['start_date']) ?> → <?= e($r['end_date']) ?></td>
                                    <td class="px-6 py-3 text-right text-mb-accent">$<?= number_format($r['total_price'], 0) ?></td>
                                    <td class="px-6 py-3"><span class="text-xs capitalize"><?= e($r['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>  <!-- END ADMIN-ONLY SECTIONS -->

</div>

<!-- Share Modal -->
<div id="vehicleShareModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Share This Vehicle</h3>
                <p class="text-mb-subtle text-sm mt-0.5">This link opens a public catalog page for only this vehicle.</p>
            </div>
            <button type="button" onclick="document.getElementById('vehicleShareModal').classList.add('hidden')" class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs px-2.5 py-1 rounded-full bg-mb-black/50 text-mb-silver border border-mb-subtle/20"><?= e($v['brand']) ?> <?= e($v['model']) ?></span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-mb-black/50 text-mb-silver border border-mb-subtle/20"><?= e($v['license_plate']) ?></span>
        </div>
        <div class="bg-mb-black border border-mb-subtle/20 rounded-xl p-3 flex items-center gap-3 mb-4">
            <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
            <input type="text" id="vehicleCatalogLinkInput" value="<?= e($vehicleCatalogUrl) ?>" readonly class="flex-1 bg-transparent text-mb-silver text-sm focus:outline-none font-mono truncate cursor-text select-all">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <button type="button" id="copyVehicleCatalogBtn" onclick="copyVehicleCatalogLink()" class="bg-mb-accent text-white py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Copy Link</button>
            <button type="button" onclick="nativeShareVehicleCatalog()" class="border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-white/20 py-2.5 rounded-full transition-colors text-sm font-medium">Share</button>
            <a href="<?= e($vehicleCatalogUrl) ?>" target="_blank" class="text-center border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-white/20 py-2.5 rounded-full transition-colors text-sm font-medium">Preview</a>
        </div>
    </div>
</div>

<!-- Challan Payment Modal -->
<div id="challanPaymentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" onclick="if(event.target===this)closeChallanPaymentModal()">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Pay Challan</h3>
                <p class="text-mb-subtle text-sm mt-0.5">Select how this challan was settled.</p>
            </div>
            <button type="button" onclick="closeChallanPaymentModal()" class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form method="POST" action="mark_challan_paid.php" id="challanPaymentForm" class="space-y-4">
            <input type="hidden" name="id" id="payment_challan_id">
            <input type="hidden" name="vehicle_id" value="<?= (int)$id ?>">

            <!-- Challan Info -->
            <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-white text-sm font-medium" id="payment_challan_title">Challan Title</p>
                        <p class="text-mb-subtle text-xs mt-1">Due: <span id="payment_challan_due">-</span></p>
                    </div>
                    <p class="text-red-400 text-lg font-medium" id="payment_challan_amount">$0.00</p>
                </div>
            </div>

            <!-- Payment Method — 4 options, 2×2 grid -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Payment Method</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="customer_paid" class="peer sr-only" required>
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Customer Paid
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="cash" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Cash
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="account" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Bank
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="credit" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Credit
                        </div>
                    </label>
                </div>
            </div>

            <!-- Bank Account (only for Bank mode) -->
            <div id="bankAccountWrapper" class="hidden">
                <label class="block text-sm text-mb-silver mb-2">Select Bank Account</label>
                <select name="bank_account_id" id="bank_account_select"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <option value="">Select account...</option>
                    <?php foreach ($bankAccounts as $bank): ?>
                        <option value="<?= (int)$bank['id'] ?>"><?= e($bank['name']) ?><?= !empty($bank['bank_name']) ? ' (' . e($bank['bank_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment Date (hidden for Customer Paid) -->
            <div id="paymentDateWrapper">
                <label class="block text-sm text-mb-silver mb-2">Payment Date</label>
                <input type="date" name="payment_date" id="payment_date_input" value="<?= date('Y-m-d') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeChallanPaymentModal()" class="px-5 py-2.5 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-full bg-green-500 text-white hover:bg-green-600 transition-colors text-sm font-medium">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function copyVehicleCatalogLink() {
    const input = document.getElementById('vehicleCatalogLinkInput');
    const btn = document.getElementById('copyVehicleCatalogBtn');
    const done = () => { const old = btn.textContent; btn.textContent = 'Copied'; setTimeout(() => { btn.textContent = old; }, 1400); };
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(input.value).then(done).catch(() => { input.focus(); input.select(); document.execCommand('copy'); done(); }); return; }
    input.focus(); input.select(); document.execCommand('copy'); done();
}
function nativeShareVehicleCatalog() {
    const url = document.getElementById('vehicleCatalogLinkInput').value;
    const title = '<?= e($v['brand'] . ' ' . $v['model']) ?>';
    if (navigator.share) { navigator.share({ title: title + ' - Vehicle Catalog', url: url }).catch(() => {}); } else { copyVehicleCatalogLink(); }
}

// Challan Payment Modal
function openChallanPaymentModal(challanId, title, amount, dueDate) {
    document.getElementById('challanPaymentForm').reset();
    document.getElementById('payment_challan_id').value = challanId;
    document.getElementById('payment_date_input').value = new Date().toISOString().split('T')[0];
    document.getElementById('payment_challan_title').textContent = title;
    document.getElementById('payment_challan_amount').textContent = '$' + parseFloat(amount).toFixed(2);
    document.getElementById('payment_challan_due').textContent = dueDate || 'No due date';
    document.getElementById('bankAccountWrapper').classList.add('hidden');
    document.getElementById('paymentDateWrapper').classList.remove('hidden');
    const bankSelect = document.getElementById('bank_account_select');
    if (bankSelect) { bankSelect.required = false; bankSelect.value = ''; }
    document.getElementById('challanPaymentModal').classList.remove('hidden');
}
function closeChallanPaymentModal() {
    document.getElementById('challanPaymentModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function () {
    const radios = document.querySelectorAll('#challanPaymentForm input[name="payment_mode"]');
    const bankWrapper = document.getElementById('bankAccountWrapper');
    const bankSelect = document.getElementById('bank_account_select');
    const dateWrapper = document.getElementById('paymentDateWrapper');

    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            // Bank account dropdown
            if (this.value === 'account') {
                bankWrapper.classList.remove('hidden');
                if (bankSelect) bankSelect.required = true;
            } else {
                bankWrapper.classList.add('hidden');
                if (bankSelect) { bankSelect.required = false; bankSelect.value = ''; }
            }
            // Hide date field for Customer Paid — it's irrelevant
            if (this.value === 'customer_paid') {
                dateWrapper.classList.add('hidden');
            } else {
                dateWrapper.classList.remove('hidden');
            }
        });
    });
});
</script>
<script>
(function () {
    const toggleBtn = document.getElementById('storageEditToggle');
    const form = document.getElementById('storageEditForm');
    const readOnly = document.getElementById('storageReadOnly');
    const cancelBtn = document.getElementById('storageEditCancel');
    if (!toggleBtn || !form || !readOnly) return;
    function showForm() { form.classList.remove('hidden'); readOnly.classList.add('hidden'); }
    function hideForm() { form.classList.add('hidden'); readOnly.classList.remove('hidden'); }
    toggleBtn.addEventListener('click', showForm);
    if (cancelBtn) cancelBtn.addEventListener('click', hideForm);
})();
</script>

<?php if ($isAdmin): ?>
<!-- Vehicle Expenses -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
        <h3 class="text-white font-light">Vehicle Expenses <span class="text-mb-subtle text-sm ml-2"><?= count($vehicleExpenses) ?> entries &bull; Total: $<?= number_format($totalExpenses, 0) ?></span></h3>
        <button type="button" onclick="document.getElementById('addExpenseModal').classList.remove('hidden')" class="text-mb-accent hover:text-mb-accent/80 text-sm">+ Add Expense</button>
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
                        <th class="px-6 py-3 text-left">KM</th>
                        <th class="px-6 py-3 text-left">Payment</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php foreach ($vehicleExpenses as $exp):
                        $expDesc = $exp['description'] ?? '-';
                        $expKm = null;
                        if (preg_match('/\[KM:\s*(\d+)\]/', $expDesc, $kmMatch)) {
                            $expKm = (int) $kmMatch[1];
                            $expDesc = trim(preg_replace('/\s*\[KM:\s*\d+\]/', '', $expDesc));
                        }
                    ?>
                        <tr class="hover:bg-mb-black/30 transition-colors">
                            <td class="px-6 py-3 text-mb-silver text-xs"><?= date('d M Y', strtotime($exp['posted_at'])) ?></td>
                            <td class="px-6 py-3 text-white"><?= e($exp['source_event'] ?? '') ?></td>
                            <td class="px-6 py-3 text-mb-subtle text-xs"><?= e($expDesc) ?></td>
                            <td class="px-6 py-3 text-xs"><?= $expKm !== null ? '<span class="text-mb-accent font-medium">' . number_format($expKm) . ' km</span>' : '<span class="text-mb-subtle/40">—</span>' ?></td>
                            <td class="px-6 py-3"><span class="text-xs px-2 py-0.5 rounded-full <?= ($exp['payment_mode'] === 'cash') ? 'bg-green-500/10 text-green-400' : (($exp['payment_mode'] === 'credit') ? 'bg-amber-500/10 text-amber-400' : 'bg-mb-accent/10 text-mb-accent') ?>"><?= ucfirst($exp['payment_mode'] ?? 'N/A') ?></span></td>
                            <td class="px-6 py-3 text-right text-red-400 font-medium">$ <?= number_format($exp['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Add Vehicle Expense</h3>
                <p class="text-mb-subtle text-sm mt-0.5"><?= e($v['brand'] . ' ' . $v['model']) ?> &bull; <?= e($v['license_plate']) ?></p>
            </div>
            <button type="button" onclick="document.getElementById('addExpenseModal').classList.add('hidden')" class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" action="add_expense.php" class="space-y-4">
            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Category <span class="text-red-400">*</span></label>
                <select name="category" id="expCategory" required onchange="onExpCategoryChange(this.value)" class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
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
            <div id="expKmWrap" style="display:none">
                <label class="block text-sm text-mb-subtle mb-1.5">KM Reading at Service <span class="text-red-400">*</span></label>
                <input type="number" name="km_reading" id="expKmReading" min="0" step="1" placeholder="e.g. 45000" class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
                <p class="text-xs text-mb-subtle mt-1">Current odometer reading when this service was done.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Amount <span class="text-red-400">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Payment Mode <span class="text-red-400">*</span></label>
                <select name="payment_mode" id="expPaymentMode" required onchange="document.getElementById('expBankSelect').style.display = this.value==='account' ? 'block' : 'none'" class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
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
                <input type="text" name="description" placeholder="Optional notes..." class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>
            <div>
                <label class="block text-sm text-mb-subtle mb-1.5">Date</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" class="w-full bg-mb-black border border-mb-subtle/30 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            </div>
            <button type="submit" class="w-full bg-mb-accent hover:bg-mb-accent/80 text-white font-medium py-2.5 rounded-full transition-colors text-sm">Add Expense</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
function onExpCategoryChange(val) {
    const kmCategories = ['Service', 'Tyre', 'Spare Parts'];
    const wrap = document.getElementById('expKmWrap');
    const input = document.getElementById('expKmReading');
    if (kmCategories.includes(val)) {
        wrap.style.display = 'block';
        input.required = true;
    } else {
        wrap.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}
</script>