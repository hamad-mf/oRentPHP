<?php
require_once __DIR__ . '/../config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate, v.daily_rate FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r || $r['status'] !== 'active') {
    flash('error', 'Only active reservations can be returned.');
    redirect('index.php');
}

$iStmt = $pdo->prepare("SELECT * FROM vehicle_inspections WHERE reservation_id=? AND type='delivery' LIMIT 1");
$iStmt->execute([$id]);
$delivery = $iStmt->fetch();

// Fetch predefined damage costs
$damageItems = $pdo->query("SELECT * FROM damage_costs ORDER BY item_name ASC")->fetchAll();

// Fetch late return hourly rate from system_settings
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (`key` VARCHAR(100) NOT NULL PRIMARY KEY, `value` TEXT DEFAULT NULL) ENGINE=InnoDB");
$pdo->exec("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0')");
$lateRatePerHour = (float) $pdo->query("SELECT `value` FROM system_settings WHERE `key`='late_return_rate_per_hour'")->fetchColumn();

// Calculate overdue
$today = new DateTime(date('Y-m-d'));
$endDate = new DateTime($r['end_date']);
$overdueDays = 0;
$overdueAmt = 0;
if ($today > $endDate) {
    $overdueDays = (int) $today->diff($endDate)->days;
    $overdueAmt = $overdueDays * (float) $r['daily_rate'];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel = (int) ($_POST['fuel_level'] ?? 100);
    $miles = (int) ($_POST['mileage'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $kmDriven = $_POST['km_driven'] !== '' ? (int) $_POST['km_driven'] : null;
    $damageChg = max(0, (float) ($_POST['damage_charge'] ?? 0));
    $discType = in_array($_POST['discount_type'] ?? '', ['percent', 'amount']) ? $_POST['discount_type'] : null;
    $discVal = max(0, (float) ($_POST['discount_value'] ?? 0));
    $actualReturnDate = trim($_POST['actual_return_date'] ?? '');
    $actualReturnHour = (int) ($_POST['actual_return_hour'] ?? 12);
    $actualReturnMin = (int) ($_POST['actual_return_min'] ?? 0);
    $actualReturnAMPM = $_POST['actual_return_ampm'] ?? 'AM';

    // Late return charge calculation
    $lateChg = 0;
    $actualDtSt = date('Y-m-d H:i:s'); // Fallback
    if ($actualReturnDate && $lateRatePerHour > 0) {
        $h24 = $actualReturnHour;
        if ($actualReturnAMPM === 'PM' && $h24 < 12)
            $h24 += 12;
        if ($actualReturnAMPM === 'AM' && $h24 === 12)
            $h24 = 0;
        $actualDtSt = sprintf('%s %02d:%02d:00', $actualReturnDate, $h24, $actualReturnMin);
        $actualDt = new DateTime($actualDtSt);
        $scheduledEnd = new DateTime($r['end_date']);
        if ($actualDt > $scheduledEnd) {
            $lateMinutes = (int) round(($actualDt->getTimestamp() - $scheduledEnd->getTimestamp()) / 60);
            if ($lateMinutes >= 30) {
                // Pro-rated calculation: minutes * (Rate / 60)
                $lateChg = round($lateMinutes * ($lateRatePerHour / 60), 2);
            }
        }
    }
    $actualEndSave = $actualDtSt;

    // KM overage calc
    $kmOverageChg = 0;
    if ($kmDriven !== null && $r['km_limit'] && $r['extra_km_price'] && $kmDriven > $r['km_limit']) {
        $kmOverageChg = ($kmDriven - $r['km_limit']) * (float) $r['extra_km_price'];
    }

    // Discount calc
    $baseForDiscount = (float) $r['total_price'] + $overdueAmt + $kmOverageChg + $damageChg + $lateChg;
    $discountAmt = 0;
    if ($discType === 'percent') {
        $discountAmt = round($baseForDiscount * min($discVal, 100) / 100, 2);
    } elseif ($discType === 'amount') {
        $discountAmt = min($discVal, $baseForDiscount);
    }

    if ($fuel < 0 || $fuel > 100)
        $errors['fuel_level'] = 'Fuel level must be 0–100.';
    if ($miles < 0)
        $errors['mileage'] = 'Mileage must be 0 or more.';

    if (empty($errors)) {
        $iStmt = $pdo->prepare('INSERT INTO vehicle_inspections (reservation_id,type,fuel_level,mileage,notes) VALUES (?,?,?,?,?)');
        $iStmt->execute([$id, 'return', $fuel, $miles, $notes]);
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

        $pdo->prepare("UPDATE reservations SET status='completed', actual_end_date=?, overdue_amount=?,
            km_driven=?, km_overage_charge=?, damage_charge=?, discount_type=?, discount_value=? WHERE id=?")
            ->execute([$actualEndSave, $overdueAmt + $lateChg, $kmDriven, $kmOverageChg, $damageChg, $discType, $discVal, $id]);
        $pdo->prepare("UPDATE vehicles SET status='available' WHERE id=?")->execute([$r['vehicle_id']]);

        $grandTotal = (float) $r['total_price'] + $overdueAmt + $lateChg + $kmOverageChg + $damageChg - $discountAmt;
        $msg = 'Vehicle returned. Grand Total: $' . number_format($grandTotal, 2);
        if ($overdueAmt > 0)
            $msg .= " | Overdue: +$" . number_format($overdueAmt, 2);
        if ($lateChg > 0)
            $msg .= " | Late Return: +$" . number_format($lateChg, 2);
        if ($kmOverageChg > 0)
            $msg .= " | KM Overage: +$" . number_format($kmOverageChg, 2);
        if ($damageChg > 0)
            $msg .= " | Damage: +$" . number_format($damageChg, 2);
        if ($discountAmt > 0)
            $msg .= " | Discount: -$" . number_format($discountAmt, 2);
        flash('success', $msg);
        redirect("bill.php?id=$id");
    }
}

$pageTitle = 'Process Return';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">Reservation #<?= $id ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Process Return</span>
    </div>

    <?php if ($overdueDays > 0): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-400 text-sm">
            <p class="font-medium">⚠ Overdue by <?= $overdueDays ?> day(s)</p>
            <p class="mt-1">Additional charge: <strong>$<?= number_format($overdueAmt, 2) ?></strong> will be applied.</p>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-8">
        <!-- Header Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Client</span>
                <p class="text-white text-lg font-light"><?= e($r['client_name']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Vehicle</span>
                <p class="text-white text-lg font-light"><?= e($r['brand']) ?> <?= e($r['model']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Due Date & Time</span>
                <p class="text-white text-lg font-light"><?= date('d M Y', strtotime($r['end_date'])) ?></p>
                <p class="text-mb-silver text-sm"><?= date('h:i A', strtotime($r['end_date'])) ?></p>
            </div>
            <div class="md:col-span-2 space-y-4">
                <span class="block text-mb-subtle text-xs uppercase mb-1">Actual Return Date &amp; Time</span>
                <?php if ($lateRatePerHour > 0): ?>
                    <p class="text-xs text-mb-subtle mb-1 flex items-center gap-1.5 opacity-80">
                        <svg class="w-3.5 h-3.5 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Late charge: $<?= number_format($lateRatePerHour, 2) ?>/hr pro-rated (30m grace)
                    </p>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Date Input -->
                    <div class="flex-1">
                        <input type="date" name="actual_return_date" id="retDate"
                            value="<?= e($_POST['actual_return_date'] ?? date('Y-m-d')) ?>" onchange="updateSummary()"
                            class="w-full bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none appearance-none transition-all hover:border-mb-subtle/40 font-medium">
                    </div>

                    <!-- Time Compound Picker -->
                    <div
                        class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                        <select name="actual_return_hour" id="retHour" onchange="updateSummary()"
                            class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= (($_POST['actual_return_hour'] ?? date('g')) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="text-mb-subtle font-bold px-0.5">:</span>
                        <select name="actual_return_min" id="retMin" onchange="updateSummary()"
                            class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                            <?php for ($i = 0; $i < 60; $i += 5): ?>
                                <option value="<?= $i ?>" <?= (($_POST['actual_return_min'] ?? floor(date('i') / 5) * 5) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                        <select name="actual_return_ampm" id="retAMPM" onchange="updateSummary()"
                            class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                            <option value="AM" <?= (($_POST['actual_return_ampm'] ?? date('A')) == 'AM') ? 'selected' : '' ?> class="bg-mb-surface text-white">AM</option>
                            <option value="PM" <?= (($_POST['actual_return_ampm'] ?? date('A')) == 'PM') ? 'selected' : '' ?> class="bg-mb-surface text-white">PM</option>
                        </select>
                    </div>
                </div>
                <p id="lateGraceHint" class="text-xs text-green-400 mt-2 font-medium hidden">✓ Within 30-min grace
                    period — no charge</p>
            </div>
        </div>

        <?php if ($delivery): ?>
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-4 text-sm">
                <p class="text-mb-subtle text-xs uppercase mb-2">At Delivery</p>
                <div class="flex gap-6">
                    <div>
                        <p class="text-mb-subtle text-xs">Mileage</p>
                        <p class="text-white"><?= number_format($delivery['mileage']) ?> km</p>
                    </div>
                    <div>
                        <p class="text-mb-subtle text-xs">Fuel</p>
                        <p class="text-white"><?= $delivery['fuel_level'] ?>%</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Readings -->
            <div class="space-y-6">
                <h3 class="text-white text-lg font-light border-l-2 border-green-500 pl-3">Return Readings</h3>
                <div>
                    <label for="mileage" class="block text-sm font-medium text-mb-silver mb-2">Return Mileage
                        (km)</label>
                    <input type="number" name="mileage" id="mileage" required
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="e.g. 15500" value="<?= e($_POST['mileage'] ?? '') ?>">
                </div>
                <div>
                    <label for="fuel_level" class="block text-sm font-medium text-mb-silver mb-2">Return Fuel Level
                        (%)</label>
                    <div class="relative pt-1">
                        <input type="range" name="fuel_level" id="fuelSlider" min="0" max="100"
                            value="<?= e($_POST['fuel_level'] ?? 100) ?>"
                            class="w-full h-2 bg-mb-subtle/50 rounded-lg appearance-none cursor-pointer accent-green-500"
                            oninput="document.getElementById('fuel-val').innerText = this.value + '%'">
                        <span id="fuel-val"
                            class="absolute right-0 top-0 text-green-500 text-sm font-bold"><?= e($_POST['fuel_level'] ?? 100) ?>%</span>
                    </div>
                    <div class="h-2 bg-mb-black/60 rounded-full overflow-hidden mt-3">
                        <div id="fuelBar" class="h-2 bg-green-500 rounded-full transition-all"
                            style="width:<?= e($_POST['fuel_level'] ?? 100) ?>%"></div>
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-mb-silver mb-2">Return Inspection
                        Notes</label>
                    <textarea name="notes" id="notes" rows="4"
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="Any new damage or issues found?"><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>

                <!-- Additional Charges & Discount -->
                <div class="pt-4 border-t border-mb-subtle/10 space-y-5">
                    <h3 class="text-white text-lg font-light border-l-2 border-orange-500 pl-3">Additional Charges &amp;
                        Discount</h3>

                    <?php if (!empty($r['km_limit'])): ?>
                        <div class="bg-yellow-500/5 border border-yellow-500/20 rounded-lg p-4 space-y-3">
                            <p class="text-yellow-400 text-xs uppercase font-medium">
                                KM Limit: <?= number_format($r['km_limit']) ?> km &mdash;
                                $<?= number_format((float) $r['extra_km_price'], 2) ?>/extra km
                            </p>
                            <div>
                                <label class="block text-sm text-mb-silver mb-2">KM Driven by Client</label>
                                <input type="number" name="km_driven" id="kmDriven" min="0"
                                    placeholder="Enter total KM driven" value="<?= e($_POST['km_driven'] ?? '') ?>"
                                    oninput="updateSummary()"
                                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            <p id="kmOverageMsg" class="text-yellow-400 text-sm font-medium hidden"></p>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm text-mb-silver mb-3">Damage Charges</label>
                        <div class="space-y-3">
                            <?php if (empty($damageItems)): ?>
                                <p class="text-xs text-mb-subtle italic">No predefined damage costs. Add them in <a
                                        href="../settings/damage_costs.php" class="text-mb-accent underline">Settings</a>.
                                </p>
                                <input type="number" name="damage_charge" id="damageCharge" min="0" step="0.01"
                                    placeholder="0.00" value="<?= e($_POST['damage_charge'] ?? '0') ?>"
                                    oninput="updateSummary()"
                                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-orange-500/50 transition-colors">
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($damageItems as $di): ?>
                                        <label
                                            class="flex items-center gap-3 p-3 bg-mb-black/30 border border-mb-subtle/10 rounded-xl cursor-pointer hover:border-orange-500/30 transition-all group">
                                            <input type="checkbox"
                                                class="damage-checkbox w-5 h-5 rounded border-mb-subtle/30 text-orange-500 focus:ring-orange-500 bg-mb-surface"
                                                data-cost="<?= e($di['cost']) ?>" onchange="updateSummary()">
                                            <div class="flex-1">
                                                <p class="text-sm text-white group-hover:text-orange-500 transition-colors">
                                                    <?= e($di['item_name']) ?>
                                                </p>
                                                <p class="text-xs text-mb-subtle">$<?= number_format($di['cost'], 2) ?></p>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="damage_charge" id="damageCharge" value="0">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Discount</label>
                        <div class="flex gap-3">
                            <select name="discount_type" id="discountType" onchange="updateSummary()"
                                class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors">
                                <option value="">No Discount</option>
                                <option value="percent" <?= ($_POST['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>% Percentage</option>
                                <option value="amount" <?= ($_POST['discount_type'] ?? '') === 'amount' ? 'selected' : '' ?>>$ Fixed Amount</option>
                            </select>
                            <input type="number" name="discount_value" id="discountValue" min="0" step="0.01"
                                placeholder="0" value="<?= e($_POST['discount_value'] ?? '0') ?>"
                                oninput="updateSummary()"
                                class="flex-1 bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors">
                        </div>
                    </div>

                    <!-- Live Total Preview -->
                    <div class="bg-mb-black/50 border border-mb-subtle/20 rounded-lg p-4 space-y-2 text-sm">
                        <div class="flex justify-between text-mb-silver"><span>Base
                                Rental</span><span>$<?= number_format($r['total_price'], 2) ?></span></div>
                        <?php if ($overdueAmt > 0): ?>
                            <div class="flex justify-between text-red-400"><span>Overdue (<?= $overdueDays ?>
                                    days)</span><span>+$<?= number_format($overdueAmt, 2) ?></span></div>
                        <?php endif; ?>
                        <div id="previewLateRow" class="justify-between text-orange-400 hidden"><span
                                id="previewLateLabel">Late Return</span><span id="previewLateAmt"></span></div>
                        <div id="previewKmRow" class="justify-between text-yellow-400 hidden"><span
                                id="previewKmLabel">KM Overage</span><span id="previewKmAmt"></span></div>
                        <div id="previewDmgRow" class="justify-between text-orange-400 hidden"><span>Damage
                                Charges</span><span id="previewDmgAmt"></span></div>
                        <div id="previewDiscRow" class="justify-between text-green-400 hidden"><span
                                id="previewDiscLabel">Discount</span><span id="previewDiscAmt"></span></div>
                        <div
                            class="flex justify-between text-white font-semibold pt-2 border-t border-mb-subtle/10 text-base">
                            <span>Grand Total</span>
                            <span
                                id="grandTotalDisplay">$<?= number_format((float) $r['total_price'] + $overdueAmt, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos -->
            <div class="space-y-4">
                <h3 class="text-white text-lg font-light border-l-2 border-green-500 pl-3">Return Condition Photos</h3>
                <p class="text-xs text-mb-subtle">Upload clear photos to document return condition.</p>
                <?php foreach (['Front', 'Back', 'Left', 'Right', 'Interior'] as $area): ?>
                    <div
                        class="bg-mb-black/30 p-4 rounded-lg border border-mb-subtle/10 hover:border-green-500/30 transition-colors">
                        <label class="block text-sm font-medium text-mb-silver mb-2"><?= $area ?> View</label>
                        <input type="file" name="photos[<?= strtolower($area) ?>]" accept="image/*" class="block w-full text-sm text-mb-silver
                                   file:mr-4 file:py-2 file:px-4
                                   file:rounded-full file:border-0
                                   file:text-xs file:font-semibold
                                   file:bg-mb-surface file:text-green-500
                                   hover:file:bg-mb-surface/80 cursor-pointer">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4 pt-8 border-t border-mb-subtle/10">
            <a href="show.php?id=<?= $id ?>" class="text-mb-silver hover:text-white transition-colors">Cancel</a>
            <button type="submit"
                class="bg-green-600 text-white px-8 py-3 rounded-full hover:bg-green-500 transition-colors font-medium shadow-lg shadow-green-900/20">
                Complete Return
            </button>
        </div>
    </form>
</div>
<?php
$kmLimit = (int) ($r['km_limit'] ?? 0);
$extraKmPrice = (float) ($r['extra_km_price'] ?? 0);
$baseTotal = (float) $r['total_price'] + $overdueAmt;
$scheduledEndIso = date('Y-m-d\TH:i', strtotime($r['end_date']));
$extraScripts = '<script>
const KM_LIMIT=' . $kmLimit . ', KM_PRICE=' . $extraKmPrice . ', BASE=' . ((float) $r["total_price"] + $overdueAmt) . ';
const LATE_RATE=' . $lateRatePerHour . ';
const SCHEDULED_END = new Date("' . $r['end_date'] . '".replace(" ","T"));

function getActualDate() {
    var d = document.getElementById("retDate").value;
    var h = parseInt(document.getElementById("retHour").value);
    var m = parseInt(document.getElementById("retMin").value);
    var ampm = document.getElementById("retAMPM").value;
    
    if (ampm === "PM" && h < 12) h += 12;
    if (ampm === "AM" && h === 12) h = 0;
    
    return new Date(d + "T" + String(h).padStart(2,"0") + ":" + String(m).padStart(2,"0") + ":00");
}

function calcLateCharge() {
    if (LATE_RATE <= 0) return 0;
    var actual = getActualDate();
    if (actual <= SCHEDULED_END) return 0;
    var lateMinutes = Math.round((actual - SCHEDULED_END) / 60000);
    if (lateMinutes < 30) return 0; // grace period
    return lateMinutes * (LATE_RATE / 60);
}

function updateSummary(){
    var kmDrivenEl=document.getElementById("kmDriven");
    var dmgHiddenEl=document.getElementById("damageCharge");
    var discType=document.getElementById("discountType").value;
    var discVal=parseFloat(document.getElementById("discountValue").value)||0;
    var kmDriven=kmDrivenEl?parseFloat(kmDrivenEl.value)||0:0;

    // Damage from checkboxes or manual input
    var dmg = 0;
    var checkboxes = document.querySelectorAll(".damage-checkbox");
    if (checkboxes.length > 0) {
        checkboxes.forEach(function(cb) {
            if (cb.checked) dmg += parseFloat(cb.getAttribute("data-cost")) || 0;
        });
        if(dmgHiddenEl) dmgHiddenEl.value = dmg;
    } else {
        dmg = parseFloat(dmgHiddenEl ? dmgHiddenEl.value : 0) || 0;
    }

    var kmOverage=0;
    if(KM_LIMIT>0 && kmDriven>KM_LIMIT){ kmOverage=(kmDriven-KM_LIMIT)*KM_PRICE; }

    var lateChg = calcLateCharge();

    var total=BASE+kmOverage+dmg+lateChg;
    var disc=0;
    if(discType==="percent") disc=Math.round(total*Math.min(discVal,100)/100*100)/100;
    else if(discType==="amount") disc=Math.min(discVal,total);
    total-=disc;

    // Late charge row
    var lateRow=document.getElementById("previewLateRow");
    if(lateRow){
        if(lateChg>0){
            lateRow.classList.remove("hidden"); lateRow.classList.add("flex");
            var actualDt=getActualDate();
            var lateMin=Math.round((actualDt-SCHEDULED_END)/60000);
            var hrs = Math.floor(lateMin/60);
            var mins = lateMin%60;
            var timeStr = hrs > 0 ? hrs + "h " + mins + "m" : mins + "m";
            document.getElementById("previewLateLabel").textContent="Late Return ("+timeStr+" @ $"+LATE_RATE.toFixed(2)+"/hr)";
            document.getElementById("previewLateAmt").textContent="+$"+lateChg.toFixed(2);
        }else{
            lateRow.classList.add("hidden"); lateRow.classList.remove("flex");
            if(LATE_RATE>0){
                var actualDt = getActualDate();
                var lateMin  = Math.round((actualDt - SCHEDULED_END)/60000);
                // Grace period hint
                var hint = document.getElementById("lateGraceHint");
                if(hint){ if(actualDt > SCHEDULED_END && lateMin < 30){ hint.classList.remove("hidden"); } else { hint.classList.add("hidden"); } }
            }
        }
    }
    // KM row
    var kmRow=document.getElementById("previewKmRow");
    if(kmRow){if(kmOverage>0){kmRow.classList.remove("hidden");kmRow.classList.add("flex");document.getElementById("previewKmAmt").textContent="+$"+kmOverage.toFixed(2);}else{kmRow.classList.add("hidden");kmRow.classList.remove("flex");}}
    var kmMsg=document.getElementById("kmOverageMsg");
    if(kmMsg){if(kmOverage>0){kmMsg.textContent="⚠ Overage: "+(kmDriven-KM_LIMIT)+" km × $"+KM_PRICE.toFixed(2)+" = $"+kmOverage.toFixed(2);kmMsg.classList.remove("hidden");}else{kmMsg.classList.add("hidden");}}
    // Damage row
    var dmgRow=document.getElementById("previewDmgRow");
    if(dmgRow){if(dmg>0){dmgRow.classList.remove("hidden");dmgRow.classList.add("flex");document.getElementById("previewDmgAmt").textContent="+$"+dmg.toFixed(2);}else{dmgRow.classList.add("hidden");dmgRow.classList.remove("flex");}}
    // Discount row
    var discRow=document.getElementById("previewDiscRow");
    if(discRow){if(disc>0){discRow.classList.remove("hidden");discRow.classList.add("flex");document.getElementById("previewDiscLabel").textContent=discType==="percent"?"Discount ("+discVal+"%):":"Discount:";document.getElementById("previewDiscAmt").textContent="-$"+disc.toFixed(2);}else{discRow.classList.add("hidden");discRow.classList.remove("flex");}}
    document.getElementById("grandTotalDisplay").textContent="$"+total.toFixed(2);
}
const slider=document.getElementById("fuelSlider");
const valEl=document.getElementById("fuel-val");
const barEl=document.getElementById("fuelBar");
function updateFuel(v){valEl.textContent=v+"%";barEl.style.width=v+"%";barEl.className="h-2 rounded-full "+(v>=75?"bg-green-500":v>=50?"bg-yellow-400":v>=25?"bg-orange-400":"bg-red-500");}
slider.addEventListener("input",()=>updateFuel(slider.value));
updateFuel(slider.value);
updateSummary();
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>