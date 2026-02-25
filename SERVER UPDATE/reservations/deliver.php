<?php
require_once __DIR__ . '/../config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r || !in_array($r['status'], ['pending', 'confirmed'])) {
    flash('error', 'This reservation cannot be delivered in its current state.');
    redirect('index.php');
}

$voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
$baseCollectNow = max(0, (float) $r['total_price'] - $voucherApplied);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel = (int) ($_POST['fuel_level'] ?? 100);
    $miles = (int) ($_POST['mileage'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $kmLimit = $_POST['km_limit'] !== '' ? (int) $_POST['km_limit'] : null;
    $extraKmPrice = $_POST['extra_km_price'] !== '' ? (float) $_POST['extra_km_price'] : null;

    if ($fuel < 0 || $fuel > 100)
        $errors['fuel_level'] = 'Fuel level must be 0–100.';
    if ($miles < 0)
        $errors['mileage'] = 'Mileage must be a positive number.';

    if (empty($errors)) {
        $iStmt = $pdo->prepare('INSERT INTO vehicle_inspections (reservation_id,type,fuel_level,mileage,notes) VALUES (?,?,?,?,?)');
        $iStmt->execute([$id, 'delivery', $fuel, $miles, $notes]);
        $inspectionId = $pdo->lastInsertId();

        // Handle Photos
        if (isset($_FILES['photos'])) {
            $dir = __DIR__ . '/../uploads/inspections/';
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            foreach ($_FILES['photos']['name'] as $area => $name) {
                if ($_FILES['photos']['error'][$area] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = 'insp_' . $inspectionId . '_' . $area . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$area], $dir . $filename)) {
                        $pStmt = $pdo->prepare('INSERT INTO inspection_photos (inspection_id, view_name, file_path) VALUES (?,?,?)');
                        $pStmt->execute([$inspectionId, $area, 'uploads/inspections/' . $filename]);
                    }
                }
            }
        }

        $pdo->prepare("UPDATE reservations SET status='active', km_limit=?, extra_km_price=? WHERE id=?")
            ->execute([$kmLimit, $extraKmPrice, $id]);
        $pdo->prepare("UPDATE vehicles SET status='rented' WHERE id=?")->execute([$r['vehicle_id']]);
        $msg = 'Vehicle delivered. Amount collected at delivery: $' . number_format($baseCollectNow, 2) . '.';
        if ($voucherApplied > 0) {
            $msg .= ' Voucher used: $' . number_format($voucherApplied, 2) . '.';
        }
        $msg .= ' Reservation is now active.';
        flash('success', $msg);
        redirect("show.php?id=$id");
    }
}

$pageTitle = 'Deliver Vehicle';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">Reservation #<?= $id ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Deliver Vehicle</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-8">
        <!-- Header Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Client</span>
                <p class="text-white text-lg font-light"><?= e($r['client_name']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Vehicle</span>
                <p class="text-white text-lg font-light"><?= e($r['brand']) ?> <?= e($r['model']) ?></p>
                <p class="text-mb-silver text-sm"><?= e($r['license_plate']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Start → End</span>
                <p class="text-white font-light"><?= date('d M y, h:i A', strtotime($r['start_date'])) ?></p>
                <p class="text-mb-silver text-sm">→ <?= date('d M y, h:i A', strtotime($r['end_date'])) ?></p>
            </div>
        </div>

        <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
            <p class="text-xs uppercase tracking-wider text-green-400 mb-1">Charge At Delivery</p>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between text-mb-silver">
                    <span>Base Rental Value</span>
                    <span>$<?= number_format((float) $r['total_price'], 2) ?></span>
                </div>
                <?php if ($voucherApplied > 0): ?>
                    <div class="flex justify-between text-green-400">
                        <span>Voucher Applied</span>
                        <span>-$<?= number_format($voucherApplied, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-white font-semibold pt-1 border-t border-green-500/20">
                    <span>Collect Now</span>
                    <span>$<?= number_format($baseCollectNow, 2) ?></span>
                </div>
            </div>
            <p class="text-xs text-mb-subtle mt-1">At return, only extra charges (late, KM overage, damage, etc.) will be calculated.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Readings -->
            <div class="space-y-6">
                <h3 class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Odometer &amp; Fuel</h3>
                <div>
                    <label for="mileage" class="block text-sm font-medium text-mb-silver mb-2">Current Mileage
                        (km)</label>
                    <input type="number" name="mileage" id="mileage" required
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="e.g. 15000" value="<?= e($_POST['mileage'] ?? '') ?>">
                </div>
                <div>
                    <label for="fuel_level" class="block text-sm font-medium text-mb-silver mb-2">Fuel Level (%)</label>
                    <div class="relative pt-1">
                        <input type="range" name="fuel_level" id="fuelSlider" min="0" max="100"
                            value="<?= e($_POST['fuel_level'] ?? 100) ?>"
                            class="w-full h-2 bg-mb-subtle/50 rounded-lg appearance-none cursor-pointer accent-mb-accent"
                            oninput="document.getElementById('fuel-val').innerText = this.value + '%'">
                        <span id="fuel-val"
                            class="absolute right-0 top-0 text-mb-accent text-sm font-bold"><?= e($_POST['fuel_level'] ?? 100) ?>%</span>
                    </div>
                    <div class="h-2 bg-mb-black/60 rounded-full overflow-hidden mt-3">
                        <div id="fuelBar" class="h-2 bg-green-500 rounded-full transition-all"
                            style="width:<?= e($_POST['fuel_level'] ?? 100) ?>%"></div>
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-mb-silver mb-2">Inspection Notes</label>
                    <textarea name="notes" id="notes" rows="4"
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="Note any existing scratches, dents, or issues..."><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
                <!-- KM Limit Section -->
                <div class="pt-4 border-t border-mb-subtle/10 space-y-4">
                    <h4 class="text-white font-light border-l-2 border-yellow-500 pl-3">KM Limit <span
                            class="text-mb-subtle text-xs font-normal">(optional)</span></h4>
                    <p class="text-xs text-mb-subtle">Client will be charged extra per KM if they exceed this limit on
                        return.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">KM Limit</label>
                            <input type="number" name="km_limit" min="0" placeholder="e.g. 500 (blank = unlimited)"
                                value="<?= e($_POST['km_limit'] ?? '') ?>"
                                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-yellow-500/50 transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">Extra Price / KM ($)</label>
                            <input type="number" name="extra_km_price" min="0" step="0.01" placeholder="e.g. 0.50"
                                value="<?= e($_POST['extra_km_price'] ?? '') ?>"
                                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-yellow-500/50 transition-colors">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos -->
            <div class="space-y-4">
                <h3 class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Vehicle Condition Photos</h3>
                <p class="text-xs text-mb-subtle">Upload clear photos for each area.</p>
                <?php
                $photoViews = [
                    'front' => 'Front',
                    'back' => 'Back',
                    'left' => 'Left',
                    'right' => 'Right',
                    'interior' => 'Interior',
                    'odometer' => 'Photo of Odometer',
                    'with_customer' => 'Photo with Customer',
                ];
                foreach ($photoViews as $areaKey => $areaLabel):
                    ?>
                    <div
                        class="bg-mb-black/30 p-4 rounded-lg border border-mb-subtle/10 hover:border-mb-accent/30 transition-colors">
                        <label class="block text-sm font-medium text-mb-silver mb-2"><?= $areaLabel ?> View</label>
                        <input type="file" name="photos[<?= $areaKey ?>]" accept="image/*" class="block w-full text-sm text-mb-silver
                                   file:mr-4 file:py-2 file:px-4
                                   file:rounded-full file:border-0
                                   file:text-xs file:font-semibold
                                   file:bg-mb-surface file:text-mb-accent
                                   hover:file:bg-mb-surface/80 cursor-pointer">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-between pt-8 border-t border-mb-subtle/10">
            <a href="bill.php?id=<?= $id ?>" target="_blank"
                class="border border-yellow-500/40 text-yellow-400 px-5 py-2.5 rounded-full hover:bg-yellow-500/10 transition-colors text-sm font-medium">🧾
                Preview Bill</a>
            <div class="flex items-center gap-4">
                <a href="show.php?id=<?= $id ?>"
                    class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
                <button type="submit"
                    class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                    Confirm Delivery
                </button>
            </div>
        </div>
    </form>
</div>
<?php
$extraScripts = '<script>
const slider = document.getElementById("fuelSlider");
const valEl  = document.getElementById("fuel-val");
const barEl  = document.getElementById("fuelBar");
function updateFuel(v) {
    valEl.textContent = v + "%";
    barEl.style.width = v + "%";
    barEl.className = "h-2 rounded-full " + (v>=75?"bg-green-500":v>=50?"bg-yellow-400":v>=25?"bg-orange-400":"bg-red-500");
}
slider.addEventListener("input", () => updateFuel(slider.value));
updateFuel(slider.value);
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>
