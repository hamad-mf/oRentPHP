<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_reservations')) {
    flash('error', 'You do not have permission to edit reservations.');
    redirect('index.php');
}
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate, v.daily_rate, v.monthly_rate, v.rate_1day, v.rate_7day, v.rate_15day, v.rate_30day FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r) {
    flash('error', 'Reservation not found.');
    redirect('index.php');
}

if (!in_array($r['status'], ['pending', 'confirmed', 'active'])) {
    flash('error', 'Only pending, confirmed, or active reservations can be edited.');
    redirect("show.php?id=$id");
}

$clients = $pdo->query("SELECT id,name FROM clients WHERE is_blacklisted=0 ORDER BY name")->fetchAll();
$vehicles = $pdo->query("SELECT id, brand, model, license_plate, daily_rate, monthly_rate, rate_1day, rate_7day, rate_15day, rate_30day FROM vehicles WHERE status IN ('available','rented') ORDER BY brand")->fetchAll();
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
    if ($sA === 'PM' && $sH < 12)
        $sH += 12;
    if ($sA === 'AM' && $sH === 12)
        $sH = 0;
    $startDate = $sD ? sprintf('%s %02d:%02d:00', $sD, $sH, $sM) : '';

    // Assemble End Date
    $eD = $_POST['end_date'] ?? '';
    $eH = (int) ($_POST['end_hour'] ?? 12);
    $eM = (int) ($_POST['end_min'] ?? 0);
    $eA = $_POST['end_ampm'] ?? 'AM';
    if ($eA === 'PM' && $eH < 12)
        $eH += 12;
    if ($eA === 'AM' && $eH === 12)
        $eH = 0;
    $endDate = $eD ? sprintf('%s %02d:%02d:00', $eD, $eH, $eM) : '';

    $totalPrice = (float) ($_POST['total_price'] ?? 0);

    if ($totalPrice <= 0)
        $errors['total_price'] = 'Total price must be greater than 0.';
    if (!$clientId)
        $errors['client_id'] = 'Please select a client.';
    if (!$vehicleId)
        $errors['vehicle_id'] = 'Please select a vehicle.';
    if (!$startDate)
        $errors['start_date'] = 'Start date is required.';
    if (!$endDate)
        $errors['end_date'] = 'End date is required.';
    if ($endDate && $startDate && $endDate <= $startDate)
        $errors['end_date'] = 'End date must be after start date.';

    if (!isset($errors['client_id'])) {
        $chk = $pdo->prepare('SELECT is_blacklisted FROM clients WHERE id=?');
        $chk->execute([$clientId]);
        $cl = $chk->fetch();
        if ($cl && $cl['is_blacklisted'])
            $errors['client_id'] = 'Client is blacklisted.';
    }

    if (empty($errors)) {
        $pdo->prepare('UPDATE reservations SET client_id=?,vehicle_id=?,rental_type=?,start_date=?,end_date=?,total_price=? WHERE id=?')
            ->execute([$clientId, $vehicleId, $rentalType, $startDate, $endDate, $totalPrice, $id]);
        app_log('ACTION', "Updated reservation (ID: $id)");
        flash('success', 'Reservation updated.');
        redirect("show.php?id=$id");
    }
    $r = array_merge($r, $_POST);
}

$pageTitle = 'Edit Reservation #' . $id;

// Helper to split datetime for 12h pickers
function splitDT(?string $dt)
{
    if (!$dt)
        return ['date' => '', 'h' => 12, 'm' => 0, 'a' => 'AM'];
    $ts = strtotime($dt);
    $h = (int) date('g', $ts);
    $m = (int) date('i', $ts);
    $a = date('A', $ts);
    return ['date' => date('Y-m-d', $ts), 'h' => $h, 'm' => (floor($m / 5) * 5), 'a' => $a];
}
$sParts = splitDT($r['start_date']);
$eParts = splitDT($r['end_date']);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white">Reservations</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white">#
            <?= $id ?>
        </a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Edit</span>
    </div>

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
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Edit Reservation</h3>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Client <span class="text-red-400">*</span></label>
                <select name="client_id"
                    class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm"
                    required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($r['client_id'] == $cl['id']) ? 'selected' : '' ?>>
                            <?= e($cl['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['client_id'])): ?>
                    <p class="text-red-400 text-xs mt-1">
                        <?= e($errors['client_id']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Vehicle <span class="text-red-400">*</span></label>
                <select name="vehicle_id" id="vehicleSelect"
                    class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm"
                    required>
                    <option value="">-- Select Vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" 
                            data-daily="<?= $v['daily_rate'] ?>"
                            data-monthly="<?= $v['monthly_rate'] ?? '' ?>"
                            data-1day="<?= $v['rate_1day'] ?? '' ?>"
                            data-7day="<?= $v['rate_7day'] ?? '' ?>"
                            data-15day="<?= $v['rate_15day'] ?? '' ?>"
                            data-30day="<?= $v['rate_30day'] ?? '' ?>"
                            <?= ($r['vehicle_id'] == $v['id']) ? 'selected' : '' ?>>
                            <?= e($v['brand']) ?>
                            <?= e($v['model']) ?> —
                            <?= e($v['license_plate']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                                <?= (($r['rental_type'] ?? 'daily') === $val) ? 'checked' : '' ?>
                            class="accent-mb-accent" onchange="calcPrice()">
                            <span class="text-mb-silver text-sm"><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                <!-- Start Date & Time -->
                <div class="space-y-3">
                    <label class="block text-sm text-mb-silver">Start Date & Time <span
                            class="text-red-400">*</span></label>
                    <div class="flex flex-col gap-2">
                        <input type="date" name="start_date" id="startDate" value="<?= e($sParts['date']) ?>"
                            onchange="calcPrice()"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none">

                        <div
                            class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                            <select name="start_hour" id="startHour" onchange="calcPrice()"
                                class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($sParts['h'] == $i) ? 'selected' : '' ?>
                                        class="bg-mb-surface text-white"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="text-mb-subtle font-bold px-0.5">:</span>
                            <select name="start_min" id="startMin" onchange="calcPrice()"
                                class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                <?php for ($i = 0; $i < 60; $i += 5): ?>
                                    <option value="<?= $i ?>" <?= ($sParts['m'] == $i) ? 'selected' : '' ?>
                                        class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                            <select name="start_ampm" id="startAMPM" onchange="calcPrice()"
                                class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                                <option value="AM" <?= ($sParts['a'] == 'AM') ? 'selected' : '' ?>
                                    class="bg-mb-surface text-white">AM</option>
                                <option value="PM" <?= ($sParts['a'] == 'PM') ? 'selected' : '' ?>
                                    class="bg-mb-surface text-white">PM</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- End Date & Time -->
                <div class="space-y-3">
                    <label class="block text-sm text-mb-silver">End Date & Time <span
                            class="text-red-400">*</span></label>
                    <div class="flex flex-col gap-2">
                        <input type="date" name="end_date" id="endDate" value="<?= e($eParts['date']) ?>"
                            onchange="calcPrice()"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none">

                        <div
                            class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                            <select name="end_hour" id="endHour" onchange="calcPrice()"
                                class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($eParts['h'] == $i) ? 'selected' : '' ?>
                                        class="bg-mb-surface text-white"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="text-mb-subtle font-bold px-0.5">:</span>
                            <select name="end_min" id="endMin" onchange="calcPrice()"
                                class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                <?php for ($i = 0; $i < 60; $i += 5): ?>
                                    <option value="<?= $i ?>" <?= ($eParts['m'] == $i) ? 'selected' : '' ?>
                                        class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                            <select name="end_ampm" id="endAMPM" onchange="calcPrice()"
                                class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                                <option value="AM" <?= ($eParts['a'] == 'AM') ? 'selected' : '' ?>
                                    class="bg-mb-surface text-white">AM</option>
                                <option value="PM" <?= ($eParts['a'] == 'PM') ? 'selected' : '' ?>
                                    class="bg-mb-surface text-white">PM</option>
                            </select>
                        </div>
                    </div>
                    <?php if (isset($errors['end_date'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['end_date']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Total Price (USD)</label>
                <input type="number" name="total_price" id="totalPrice" value="<?= e($r['total_price']) ?>" step="0.01"
                    min="0.01" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                <?php if (isset($errors['total_price'])): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['total_price']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <a href="show.php?id=<?= $id ?>" class="text-mb-silver hover:text-white text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium">Save
                Changes</button>
        </div>
    </form>
</div>
<?php
$extraScripts = <<<JS
<script>
const vehicleSelect = document.getElementById("vehicleSelect");
const totalPrice = document.getElementById("totalPrice");

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
    if (!opt || !opt.value) return;
    const daily = parseFloat(opt.dataset.daily || 0);
    const type  = document.querySelector('input[name="rental_type"]:checked')?.value || 'daily';
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
        if (s && e && e > s) days = (Math.ceil((e - s) / 86400000) || 1) + 1; // inclusive
        rate  = monthly > 0 ? monthly : daily;
        total = rate * (days / 30 || 1);
    } else {
        if (!s || !e || e <= s) return;
        days  = (Math.ceil((e - s) / 86400000) || 1) + 1; // inclusive: both start & end day
        rate  = daily;
        total = days * daily;
    }
    totalPrice.value = total.toFixed(2);
}

$(vehicleSelect).on("change", calcPrice);
['start', 'end'].forEach(p => {
    ['Date', 'Hour', 'Min', 'AMPM'].forEach(c => {
        document.getElementById(p + c).addEventListener('change', calcPrice);
    });
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>