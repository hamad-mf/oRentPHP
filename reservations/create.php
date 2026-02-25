<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/voucher_helpers.php';
$pdo = db();

voucher_ensure_schema($pdo);

$clients = $pdo->query("SELECT id, name, voucher_balance FROM clients WHERE is_blacklisted=0 ORDER BY name")->fetchAll();
$vehicles = $pdo->query("SELECT id, brand, model, license_plate, daily_rate, monthly_rate, rate_1day, rate_7day, rate_15day, rate_30day FROM vehicles WHERE status='available' ORDER BY brand")->fetchAll();

$hiddenCount = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_blacklisted=1")->fetchColumn();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $rentalType = $_POST['rental_type'] ?? 'daily';
    
    // Assemble Start Date
    $sD = $_POST['start_date'] ?? '';
    $sH = (int) ($_POST['start_hour'] ?? 12);
    $sM = (int) ($_POST['start_min'] ?? 0);
    $sA = $_POST['start_ampm'] ?? 'AM';
    if ($sA === 'PM' && $sH < 12) $sH += 12;
    if ($sA === 'AM' && $sH === 12) $sH = 0;
    $startDate = $sD ? sprintf('%s %02d:%02d:00', $sD, $sH, $sM) : '';

    // Assemble End Date
    $eD = $_POST['end_date'] ?? '';
    $eH = (int) ($_POST['end_hour'] ?? 12);
    $eM = (int) ($_POST['end_min'] ?? 0);
    $eA = $_POST['end_ampm'] ?? 'AM';
    if ($eA === 'PM' && $eH < 12) $eH += 12;
    if ($eA === 'AM' && $eH === 12) $eH = 0;
    $endDate = $eD ? sprintf('%s %02d:%02d:00', $eD, $eH, $eM) : '';

    $totalPrice = (float) ($_POST['total_price'] ?? 0);
    $voucherRequest = max(0, (float) ($_POST['voucher_amount'] ?? 0));
    $voucherApplied = 0.0;
    $clientVoucherBalance = 0.0;

    if (!$clientId)
        $errors['client_id'] = 'Please select a client.';
    if (!$vehicleId)
        $errors['vehicle_id'] = 'Please select a vehicle.';
    if (!$startDate)
        $errors['start_date'] = 'Start date is required.';
    if (!$endDate)
        $errors['end_date'] = 'End date is required.';
    if ($startDate && $endDate && $endDate <= $startDate)
        $errors['end_date'] = 'End date must be after start date.';
    if ($totalPrice <= 0)
        $errors['total_price'] = 'Total price must be greater than 0.';

    // Guard: reject blacklisted
    if (!isset($errors['client_id'])) {
        $chk = $pdo->prepare('SELECT is_blacklisted, voucher_balance FROM clients WHERE id=?');
        $chk->execute([$clientId]);
        $cl = $chk->fetch();
        if ($cl && $cl['is_blacklisted'])
            $errors['client_id'] = 'This client is blacklisted and cannot make reservations.';
        if ($cl) {
            $clientVoucherBalance = max(0, (float) ($cl['voucher_balance'] ?? 0));
        }
    }

    if (!isset($errors['client_id']) && $voucherRequest > 0) {
        if ($voucherRequest > $totalPrice) {
            $errors['voucher_amount'] = 'Voucher amount cannot exceed total price.';
        } elseif ($voucherRequest > $clientVoucherBalance) {
            $errors['voucher_amount'] = 'Voucher amount cannot exceed client voucher balance ($' . number_format($clientVoucherBalance, 2) . ').';
        } else {
            $voucherApplied = round(min($voucherRequest, $totalPrice, $clientVoucherBalance), 2);
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO reservations (client_id,vehicle_id,rental_type,start_date,end_date,total_price,voucher_applied,status) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$clientId, $vehicleId, $rentalType, $startDate, $endDate, $totalPrice, $voucherApplied, 'confirmed']);
            $id = (int) $pdo->lastInsertId();

            if ($voucherApplied > 0) {
                voucher_apply_debit($pdo, $clientId, $voucherApplied, $id, 'Applied on reservation #' . $id);
            }

            $pdo->commit();

            // Get client name
            $cn = $pdo->prepare('SELECT name FROM clients WHERE id=?');
            $cn->execute([$clientId]);
            $clientName = $cn->fetchColumn();

            $msg = "Reservation confirmed for $clientName.";
            if ($voucherApplied > 0) {
                $payNow = max(0, $totalPrice - $voucherApplied);
                $msg .= ' Voucher used: $' . number_format($voucherApplied, 2) . '. Collect at delivery: $' . number_format($payNow, 2) . '.';
            }
            flash('success', $msg);
            redirect("show.php?id=$id");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['db'] = 'Could not create reservation. Please try again.';
        }
    }
}

$pageTitle = 'New Reservation';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Reservations</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">New Reservation</span>
    </div>

    <?php if ($hiddenCount > 0): ?>
        <div
            class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg px-5 py-3 text-yellow-400 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <?= $hiddenCount ?> blacklisted client(s) are hidden from selection.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull;
                    <?= e($e) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6" id="resForm">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
                    <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Reservation Details</h3>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Client <span
                                class="text-red-400">*</span></label>
                        <select name="client_id" id="clientSelect"
                            class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm"
                            required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>"
                                    data-voucher-balance="<?= number_format((float) ($cl['voucher_balance'] ?? 0), 2, '.', '') ?>"
                                    <?= (($_POST['client_id'] ?? '') == $cl['id']) ? 'selected' : '' ?>>
                                    <?= e($cl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['client_id'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['client_id']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-xs text-green-400 mt-2" id="clientVoucherInfo">Client voucher balance: $0.00</p>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Vehicle <span
                                class="text-red-400">*</span></label>
                        <select name="vehicle_id" id="vehicleSelect"
                            class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm"
                            required>
                            <option value="">-- Select Available Vehicle --</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" data-daily="<?= $v['daily_rate'] ?>"
                                    data-monthly="<?= $v['monthly_rate'] ?? '' ?>"
                                    data-1day="<?= $v['rate_1day'] ?? '' ?>"
                                    data-7day="<?= $v['rate_7day'] ?? '' ?>"
                                    data-15day="<?= $v['rate_15day'] ?? '' ?>"
                                    data-30day="<?= $v['rate_30day'] ?? '' ?>"
                                    <?= (($_POST['vehicle_id'] ?? '') == $v['id']) ? 'selected' : '' ?>>
                                    <?= e($v['brand']) ?>
                                    <?= e($v['model']) ?> —
                                    <?= e($v['license_plate']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['vehicle_id'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['vehicle_id']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Rental Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <?php
                            $rentalTypes = [
                                'daily'   => 'Daily',
                                '1day'    => '1 Day',
                                '7day'    => '7 Days',
                                '15day'   => '15 Days',
                                '30day'   => '30 Days',
                                'monthly' => 'Monthly',
                            ];
                            foreach ($rentalTypes as $val => $lbl): ?>
                                <label class="flex items-center gap-2 cursor-pointer bg-mb-black/30 border border-mb-subtle/10 rounded-lg px-3 py-2 hover:border-mb-accent/40 transition-all">
                                    <input type="radio" name="rental_type" value="<?= $val ?>"
                                        <?= (($_POST['rental_type'] ?? 'daily') === $val) ? 'checked' : '' ?>
                                    class="accent-mb-accent" onchange="onRentalTypeChange()">
                                    <span class="text-mb-silver text-sm"><?= $lbl ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <!-- Start Date & Time -->
                        <div class="space-y-3">
                            <label class="block text-sm text-mb-silver">Start Date & Time <span class="text-red-400">*</span></label>
                            <div class="flex flex-col gap-2">
                                <input type="date" name="start_date" id="startDate" 
                                    value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>" onchange="calcPrice()"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none">
                                
                                <div class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                                    <select name="start_hour" id="startHour" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['start_hour'] ?? date('g')) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="text-mb-subtle font-bold px-0.5">:</span>
                                    <select name="start_min" id="startMin" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 0; $i < 60; $i += 5): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['start_min'] ?? floor(date('i') / 5) * 5) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                                    <select name="start_ampm" id="startAMPM" onchange="calcPrice()"
                                        class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                                        <option value="AM" <?= (($_POST['start_ampm'] ?? date('A')) == 'AM') ? 'selected' : '' ?> class="bg-mb-surface text-white">AM</option>
                                        <option value="PM" <?= (($_POST['start_ampm'] ?? date('A')) == 'PM') ? 'selected' : '' ?> class="bg-mb-surface text-white">PM</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- End Date & Time -->
                        <div class="space-y-3">
                            <label class="block text-sm text-mb-silver">End Date & Time <span class="text-red-400">*</span></label>
                            <div class="flex flex-col gap-2">
                                <input type="date" name="end_date" id="endDate" 
                                    value="<?= e($_POST['end_date'] ?? '') ?>" onchange="calcPrice()"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none">
                                
                                <div class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                                    <select name="end_hour" id="endHour" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['end_hour'] ?? '') == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="text-mb-subtle font-bold px-0.5">:</span>
                                    <select name="end_min" id="endMin" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 0; $i < 60; $i += 5): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['end_min'] ?? '') === (string)$i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                                    <select name="end_ampm" id="endAMPM" onchange="calcPrice()"
                                        class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                                        <option value="AM" <?= (($_POST['end_ampm'] ?? '') == 'AM') ? 'selected' : '' ?> class="bg-mb-surface text-white">AM</option>
                                        <option value="PM" <?= (($_POST['end_ampm'] ?? '') == 'PM') ? 'selected' : '' ?> class="bg-mb-surface text-white">PM</option>
                                    </select>
                                </div>
                            </div>
                            <?php if (isset($errors['end_date'])): ?>
                                <p class="text-red-400 text-xs mt-1"><?= e($errors['end_date']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Total Price (USD) <span
                                class="text-red-400">*</span></label>
                        <input type="number" name="total_price" id="totalPrice"
                            value="<?= e($_POST['total_price'] ?? '') ?>" step="0.01" min="0" required
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Use Voucher (Optional)</label>
                        <input type="number" name="voucher_amount" id="voucherAmount"
                            value="<?= e($_POST['voucher_amount'] ?? '') ?>" step="0.01" min="0"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-green-500/60 text-sm"
                            placeholder="0.00">
                        <p class="text-xs text-mb-subtle mt-1">
                            Voucher reduces amount collected at delivery. Remaining voucher stays in client account.
                        </p>
                        <?php if (isset($errors['voucher_amount'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['voucher_amount']) ?>
                            </p>
                        <?php endif; ?>
                        <p id="voucherError" class="text-red-400 text-xs mt-1" style="display:none"></p>
                    </div>
                </div>
            </div>

            <!-- Price Calculator -->
            <div class="space-y-4">
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-3" id="vehicleInfo"
                    style="display:none">
                    <h4 class="text-white font-light text-sm border-l-2 border-mb-accent pl-3">Vehicle Rates</h4>
                    <p id="vName" class="text-mb-silver text-sm"></p>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Daily</span><span id="vDaily" class="text-mb-accent">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>1-Day Pkg</span><span id="v1day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>7-Day Pkg</span><span id="v7day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>15-Day Pkg</span><span id="v15day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>30-Day Pkg</span><span id="v30day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Monthly</span><span id="vMonthly" class="text-white">—</span></div>
                </div>

                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-3" id="priceCalc">
                    <h4 class="text-white font-light text-sm border-l-2 border-yellow-400 pl-3">Price Breakdown</h4>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Days</span><span
                            id="calcDays">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Rate/Day</span><span
                            id="calcRate">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle">
                        <span>Available Voucher</span>
                        <span id="calcVoucherBalance">$0.00</span>
                    </div>
                    <div id="calcVoucherRow" class="hidden justify-between text-xs text-green-400">
                        <span>Voucher Applied</span>
                        <span id="calcVoucherApplied">-$0.00</span>
                    </div>
                    <div class="border-t border-mb-subtle/20 pt-2 flex justify-between text-sm font-medium">
                        <span class="text-mb-silver">Estimated Total</span>
                        <span class="text-mb-accent" id="calcTotal">$0</span>
                    </div>
                    <div class="flex justify-between text-sm font-medium">
                        <span class="text-mb-silver">Collect at Delivery</span>
                        <span class="text-green-400" id="calcCollectNow">$0</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Confirm Reservation
            </button>
        </div>
    </form>
</div>

<?php
$extraScripts = <<<JS
<script>
const vehicleSelect = document.getElementById('vehicleSelect');
const clientSelect = document.getElementById('clientSelect');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const totalPrice = document.getElementById('totalPrice');
const voucherAmount = document.getElementById('voucherAmount');
const calcVoucherBalance = document.getElementById('calcVoucherBalance');
const calcVoucherRow = document.getElementById('calcVoucherRow');
const calcVoucherApplied = document.getElementById('calcVoucherApplied');
const calcCollectNow = document.getElementById('calcCollectNow');
const clientVoucherInfo = document.getElementById('clientVoucherInfo');

function getTimestamp(prefix) {
    const dVal = document.getElementById(prefix + 'Date').value;
    if (!dVal) return null;
    let h = parseInt(document.getElementById(prefix + 'Hour').value);
    const m = parseInt(document.getElementById(prefix + 'Min').value);
    const ampm = document.getElementById(prefix + 'AMPM').value;
    if (ampm === 'PM' && h < 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    const d = new Date(dVal);
    d.setHours(h, m, 0, 0);
    return d;
}

function calcPrice() {
    const opt = vehicleSelect.options[vehicleSelect.selectedIndex];
    if (!opt || !opt.value) {
        updateVoucherPreview();
        return;
    }
    const daily = parseFloat(opt.dataset.daily || 0);
    const type  = getSelectedType();
    const FIXED_DAYS = { '1day': 1, '7day': 7, '15day': 15, '30day': 30 };
    
    const s = getTimestamp('start');
    const e = getTimestamp('end');
    let days = 0, rate = daily, total = 0;

    if (FIXED_DAYS[type]) {
        days = FIXED_DAYS[type];
        const pkgKey = type.replace('day','') + 'day';
        const pkgRate = parseFloat(opt.dataset[pkgKey] || 0);
        if (pkgRate > 0) { total = pkgRate; rate = pkgRate / days; }
        else             { total = days * daily; rate = daily; }
        
        if (s && !isNaN(s.getTime())) {
            const autoEnd = new Date(s);
            autoEnd.setDate(autoEnd.getDate() + days);
            
            // Set end components
            document.getElementById('endDate').value = autoEnd.toISOString().split('T')[0];
            let eh = autoEnd.getHours();
            const em = autoEnd.getMinutes();
            const eampm = eh >= 12 ? 'PM' : 'AM';
            eh = eh % 12 || 12;
            document.getElementById('endHour').value = eh;
            document.getElementById('endMin').value = Math.floor(em / 5) * 5;
            document.getElementById('endAMPM').value = eampm;
        }
    } else if (type === 'monthly') {
        const monthly = parseFloat(opt.dataset.monthly || 0);
        if (s && e && e > s) days = Math.ceil((e - s) / 86400000) || 1;
        rate  = monthly > 0 ? monthly : daily;
        total = rate * (days / 30 || 1);
    } else {
        if (!s || !e || e <= s) return;
        days  = Math.ceil((e - s) / 86400000) || 1;
        rate  = daily;
        total = days * daily;
    }
    document.getElementById('calcDays').textContent  = days;
    document.getElementById('calcRate').textContent  = '$' + parseFloat(rate).toFixed(2);
    document.getElementById('calcTotal').textContent = '$' + total.toFixed(2);
    totalPrice.value = total.toFixed(2);
    updateVoucherPreview();
}

function onRentalTypeChange() { calcPrice(); }
function getSelectedType() {
    return document.querySelector('input[name="rental_type"]:checked')?.value || 'daily';
}

function getSelectedClientVoucherBalance() {
    const opt = clientSelect.options[clientSelect.selectedIndex];
    if (!opt || !opt.value) {
        return 0;
    }
    return parseFloat(opt.dataset.voucherBalance || 0);
}

const voucherError = document.getElementById('voucherError');

function showVoucherError(msg) {
    if (!voucherError) return;
    voucherError.textContent = msg;
    voucherError.style.display = 'block';
    if (voucherAmount) voucherAmount.style.borderColor = 'rgb(239 68 68 / 0.6)';
}

function clearVoucherError() {
    if (!voucherError) return;
    voucherError.textContent = '';
    voucherError.style.display = 'none';
    if (voucherAmount) voucherAmount.style.borderColor = '';
}

function getVoucherRequested() {
    const v = parseFloat(voucherAmount?.value || 0);
    return isFinite(v) && v > 0 ? v : 0;
}

function validateVoucher() {
    if (!voucherAmount) return true;
    const total = parseFloat(totalPrice.value || 0) || 0;
    const balance = getSelectedClientVoucherBalance();
    const requested = getVoucherRequested();

    voucherAmount.max = Math.max(0, Math.min(total, balance)).toFixed(2);

    if (requested <= 0) { clearVoucherError(); return true; }
    if (requested > total) {
        showVoucherError('Voucher cannot exceed total price ($' + total.toFixed(2) + ')');
        return false;
    }
    if (requested > balance) {
        showVoucherError('Voucher exceeds client balance ($' + balance.toFixed(2) + ')');
        return false;
    }
    clearVoucherError();
    return true;
}

function updateVoucherPreview() {
    const total = parseFloat(totalPrice.value || 0) || 0;
    const balance = getSelectedClientVoucherBalance();
    const requested = getVoucherRequested();
    const isValid = validateVoucher();
    const applied = isValid ? Math.min(requested, total, balance) : 0;

    if (calcVoucherBalance) {
        calcVoucherBalance.textContent = '$' + balance.toFixed(2);
    }
    if (clientVoucherInfo) {
        clientVoucherInfo.textContent = 'Client voucher balance: $' + balance.toFixed(2);
    }
    if (calcVoucherRow && calcVoucherApplied) {
        if (applied > 0) {
            calcVoucherRow.classList.remove('hidden');
            calcVoucherRow.classList.add('flex');
            calcVoucherApplied.textContent = '-$' + applied.toFixed(2);
        } else {
            calcVoucherRow.classList.add('hidden');
            calcVoucherRow.classList.remove('flex');
            calcVoucherApplied.textContent = '-$0.00';
        }
    }
    if (calcCollectNow) {
        calcCollectNow.textContent = '$' + Math.max(0, total - applied).toFixed(2);
    }
}

function updateVehicleInfo() {
    const opt = vehicleSelect.options[vehicleSelect.selectedIndex];
    const info = document.getElementById('vehicleInfo');
    if (!opt.value) { info.style.display='none'; return; }
    info.style.display='block';
    document.getElementById('vName').textContent = opt.text;
    document.getElementById('vDaily').textContent = '$' + parseFloat(opt.dataset.daily||0).toFixed(0) + '/day';
    const fmt = (k, suffix) => opt.dataset[k] ? '$' + parseFloat(opt.dataset[k]).toFixed(0) + suffix : '—';
    document.getElementById('v1day').textContent  = fmt('1day',  ' flat');
    document.getElementById('v7day').textContent  = fmt('7day',  ' flat');
    document.getElementById('v15day').textContent = fmt('15day', ' flat');
    document.getElementById('v30day').textContent = fmt('30day', ' flat');
    document.getElementById('vMonthly').textContent = fmt('monthly', '/mo');
    calcPrice();
}

$(vehicleSelect).on('change', updateVehicleInfo);
$(clientSelect).on('change', updateVoucherPreview);
// Bind all components
['start', 'end'].forEach(p => {
    ['Date', 'Hour', 'Min', 'AMPM'].forEach(c => {
        document.getElementById(p + c).addEventListener('change', calcPrice);
    });
});
if (voucherAmount) {
    voucherAmount.addEventListener('input', updateVoucherPreview);
    voucherAmount.addEventListener('blur', function () {
        validateVoucher();
        updateVoucherPreview();
    });
}
totalPrice.addEventListener('input', updateVoucherPreview);
document.getElementById('resForm').addEventListener('submit', function (e) {
    if (!validateVoucher()) {
        e.preventDefault();
        voucherAmount && voucherAmount.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
});
updateVehicleInfo();
updateVoucherPreview();
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
