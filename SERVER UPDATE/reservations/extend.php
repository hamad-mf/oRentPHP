<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_reservations') && !auth_has_perm('do_delivery') && !auth_has_perm('do_return')) {
    flash('error', 'You do not have permission to extend reservations.');
    redirect('index.php');
}
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

$pdo = db();
reservation_payment_ensure_schema($pdo);
ledger_ensure_schema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate, v.daily_rate, v.monthly_rate, v.rate_1day, v.rate_7day, v.rate_15day, v.rate_30day FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r || $r['status'] !== 'active') {
    flash('error', 'Only active reservations can be extended.');
    redirect('index.php');
}

$now = app_now();
$oldEndDt = new DateTimeImmutable($r['end_date']);
$baseStartDt = $oldEndDt > $now ? $oldEndDt : $now;
$isGrace = $oldEndDt < $now;

function splitDT(DateTimeInterface $dt): array
{
    return [
        'date' => $dt->format('Y-m-d'),
        'h' => (int) $dt->format('g'),
        'm' => (int) (floor(((int) $dt->format('i')) / 5) * 5),
        'a' => $dt->format('A'),
    ];
}

function assembleReservationDateTime(string $date, int $hour12, int $minute, string $ampm): ?DateTimeImmutable
{
    $date = trim($date);
    if ($date === '') {
        return null;
    }
    $hour12 = max(1, min(12, $hour12));
    $minute = max(0, min(59, $minute));
    $ampm = strtoupper($ampm) === 'PM' ? 'PM' : 'AM';
    $hour24 = $hour12 % 12;
    if ($ampm === 'PM') {
        $hour24 += 12;
    }
    $dtString = sprintf('%s %02d:%02d:00', $date, $hour24, $minute);
    try {
        return new DateTimeImmutable($dtString);
    } catch (Throwable $e) {
        return null;
    }
}

function normalize_rental_type(?string $type): string
{
    $type = strtolower(trim((string) $type));
    $allowed = ['daily', '1day', '7day', '15day', '30day', 'monthly'];
    return in_array($type, $allowed, true) ? $type : 'daily';
}

function calc_extension(DateTimeInterface $baseStart, ?DateTimeInterface $desiredEnd, string $rentalType, array $rates): array
{
    $fixedDays = ['1day' => 1, '7day' => 7, '15day' => 15, '30day' => 30];
    $base = DateTimeImmutable::createFromInterface($baseStart);
    $daily = (float) ($rates['daily'] ?? 0);
    $monthly = (float) ($rates['monthly'] ?? 0);
    $result = [
        'days' => 0,
        'rate_per_day' => 0.0,
        'amount' => 0.0,
        'new_end_dt' => $desiredEnd ? DateTimeImmutable::createFromInterface($desiredEnd) : null,
    ];

    if (isset($fixedDays[$rentalType])) {
        $days = $fixedDays[$rentalType];
        $pkgRate = (float) ($rates[$rentalType] ?? 0);
        if ($pkgRate > 0) {
            $amount = $pkgRate;
            $ratePerDay = $pkgRate / $days;
        } else {
            $amount = $days * $daily;
            $ratePerDay = $daily;
        }
        $result['days'] = $days;
        $result['rate_per_day'] = round($ratePerDay, 2);
        $result['amount'] = round($amount, 2);
        $result['new_end_dt'] = $base->modify('+' . $days . ' day');
        return $result;
    }

    if (!$desiredEnd) {
        return $result;
    }

    $end = DateTimeImmutable::createFromInterface($desiredEnd);
    $diffSec = $end->getTimestamp() - $base->getTimestamp();
    if ($diffSec <= 0) {
        $result['new_end_dt'] = $end;
        return $result;
    }
    $days = (int) (ceil($diffSec / 86400) ?: 1) + 1;

    if ($rentalType === 'monthly') {
        $monthRate = $monthly > 0 ? $monthly : $daily;
        $amount = $monthRate * ($days / 30);
        $ratePerDay = $days > 0 ? ($amount / $days) : 0;
    } else {
        $amount = $days * $daily;
        $ratePerDay = $daily;
    }

    $result['days'] = $days;
    $result['rate_per_day'] = round($ratePerDay, 2);
    $result['amount'] = round($amount, 2);
    $result['new_end_dt'] = $end;
    return $result;
}

function vehicleHasOverlap(PDO $pdo, int $vehicleId, int $reservationId, string $startDate, string $endDate): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM reservations
        WHERE vehicle_id = ?
          AND id <> ?
          AND status IN ('pending','confirmed','active')
          AND start_date < ?
          AND end_date > ?");
    $stmt->execute([$vehicleId, $reservationId, $endDate, $startDate]);
    return (int) $stmt->fetchColumn() > 0;
}

$rates = [
    'daily' => (float) $r['daily_rate'],
    'monthly' => (float) ($r['monthly_rate'] ?? 0),
    '1day' => (float) ($r['rate_1day'] ?? 0),
    '7day' => (float) ($r['rate_7day'] ?? 0),
    '15day' => (float) ($r['rate_15day'] ?? 0),
    '30day' => (float) ($r['rate_30day'] ?? 0),
];

$errors = [];
$selectedType = normalize_rental_type($_POST['rental_type'] ?? ($r['rental_type'] ?? 'daily'));
$defaultEndDt = DateTimeImmutable::createFromInterface($baseStartDt)->modify('+1 day');
$desiredEndDt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desiredEndDt = assembleReservationDateTime(
        $_POST['end_date'] ?? '',
        (int) ($_POST['end_hour'] ?? 12),
        (int) ($_POST['end_min'] ?? 0),
        (string) ($_POST['end_ampm'] ?? 'AM')
    );
}
$previewEnd = $desiredEndDt ?? $defaultEndDt;
$calc = calc_extension($baseStartDt, $previewEnd, $selectedType, $rates);
$endParts = splitDT($calc['new_end_dt'] ?? $defaultEndDt);

$paymentMethod = reservation_payment_method_normalize($_POST['payment_method'] ?? 'cash') ?? 'cash';
$bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
$bankAccountId = $bankAccountId > 0 ? $bankAccountId : null;
$activeBankAccounts = array_values(array_filter(ledger_get_accounts($pdo), fn($a) => (int) ($a['is_active'] ?? 0) === 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($selectedType, ['daily', 'monthly'], true)) {
        if (!$desiredEndDt) {
            $errors['end_date'] = 'New end date is required.';
        } elseif ($desiredEndDt <= $baseStartDt) {
            $errors['end_date'] = 'New end date must be after the grace start time.';
        }
    }

    if ($calc['amount'] <= 0) {
        $errors['amount'] = 'Extension amount must be greater than 0. Check vehicle rates.';
    }

    if ($calc['new_end_dt'] instanceof DateTimeInterface) {
        $newEndSql = $calc['new_end_dt']->format('Y-m-d H:i:s');
        $baseStartSql = DateTimeImmutable::createFromInterface($baseStartDt)->format('Y-m-d H:i:s');
        if (vehicleHasOverlap($pdo, (int) $r['vehicle_id'], $id, $baseStartSql, $newEndSql)) {
            $errors['overlap'] = 'This extension overlaps another reservation for this vehicle.';
        }
    }

    if ($calc['amount'] > 0) {
        if ($paymentMethod === null) {
            $errors['payment_method'] = 'Please select how the extension was paid.';
        }
        if ($paymentMethod === 'account') {
            if ($bankAccountId === null) {
                $errors['bank_account_id'] = 'Please select a bank account.';
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
                $chk->execute([$bankAccountId]);
                if ((int) $chk->fetchColumn() === 0) {
                    $errors['bank_account_id'] = 'Selected bank account is invalid or inactive.';
                }
            }
        } else {
            $bankAccountId = null;
        }
    }

    if (empty($errors)) {
        $newEndSql = $calc['new_end_dt']->format('Y-m-d H:i:s');
        $baseStartSql = DateTimeImmutable::createFromInterface($baseStartDt)->format('Y-m-d H:i:s');
        $amount = (float) $calc['amount'];
        $days = (int) $calc['days'];
        $ratePerDay = (float) $calc['rate_per_day'];

        $pdo->prepare("UPDATE reservations SET end_date=?, total_price=total_price+?, extension_paid_amount=extension_paid_amount+? WHERE id=?")
            ->execute([$newEndSql, $amount, $amount, $id]);

        $extStmt = $pdo->prepare("INSERT INTO reservation_extensions (reservation_id, old_end_date, base_start_date, new_end_date, rental_type, days, rate_per_day, amount, payment_method, bank_account_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $extStmt->execute([
            $id,
            $oldEndDt->format('Y-m-d H:i:s'),
            $baseStartSql,
            $newEndSql,
            $selectedType,
            $days,
            $ratePerDay,
            $amount,
            $paymentMethod,
            $bankAccountId,
            (int) ($_SESSION['user']['id'] ?? 0)
        ]);
        $extensionId = (int) $pdo->lastInsertId();

        $ledgerUserId = (int) ($_SESSION['user']['id'] ?? 0);
        $resolvedBankId = ledger_resolve_bank_account_id($pdo, $paymentMethod, $bankAccountId);
        $description = "Reservation #$id - Extension payment";
        $ledgerEntryId = ledger_post(
            $pdo,
            'income',
            'Reservation Extension',
            $amount,
            $paymentMethod,
            $resolvedBankId,
            'reservation',
            $id,
            'extension',
            $description,
            $ledgerUserId,
            "reservation:extension:$extensionId"
        );
        if ($ledgerEntryId) {
            $pdo->prepare("UPDATE reservation_extensions SET ledger_entry_id=? WHERE id=?")
                ->execute([$ledgerEntryId, $extensionId]);
        }

        app_log('ACTION', "Extended reservation #$id to $newEndSql (amount $amount)");
        flash('success', 'Reservation extended. Extension collected: $' . number_format($amount, 2) . '.');
        redirect("show.php?id=$id");
    }
}

$pageTitle = 'Extend Reservation #' . $id;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white">Reservations</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white">#<?= $id ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Extend</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6" id="extendForm">
        <div class="lg:col-span-2 bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Extend Reservation</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4">
                    <div class="text-mb-subtle">Client</div>
                    <div class="text-white font-medium"><?= e($r['client_name']) ?></div>
                </div>
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4">
                    <div class="text-mb-subtle">Vehicle</div>
                    <div class="text-white font-medium"><?= e($r['brand'] . ' ' . $r['model']) ?></div>
                    <div class="text-mb-subtle text-xs"><?= e($r['license_plate']) ?></div>
                </div>
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4">
                    <div class="text-mb-subtle">Current End</div>
                    <div class="text-white font-medium"><?= e($oldEndDt->format('d M Y, h:i A')) ?></div>
                </div>
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4">
                    <div class="text-mb-subtle">Grace Start</div>
                    <div class="text-white font-medium"><?= e($baseStartDt->format('d M Y, h:i A')) ?></div>
                    <?php if ($isGrace): ?>
                        <div class="text-xs text-orange-400 mt-1">Overdue gap forgiven.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-2">Rental Type</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php $types = ['daily' => 'Daily', '1day' => '1 Day', '7day' => '7 Days', '15day' => '15 Days', '30day' => '30 Days', 'monthly' => 'Monthly']; ?>
                    <?php foreach ($types as $val => $label): ?>
                        <label class="flex items-center gap-2 bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-mb-silver">
                            <input type="radio" name="rental_type" value="<?= $val ?>" class="accent-mb-accent" <?= $selectedType === $val ? 'checked' : '' ?> onchange="calcExtension()">
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">New End Date</label>
                    <input type="date" name="end_date" id="endDate"
                        value="<?= e($endParts['date']) ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm">
                    <?php if (isset($errors['end_date'])): ?>
                        <p class="text-xs text-red-400 mt-1"><?= e($errors['end_date']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Time</label>
                    <div class="flex gap-2">
                        <select name="end_hour" id="endHour" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-2 text-white text-sm">
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                                <option value="<?= $h ?>" <?= $endParts['h'] === $h ? 'selected' : '' ?>><?= $h ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="end_min" id="endMin" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-2 text-white text-sm">
                            <?php for ($m = 0; $m < 60; $m += 5): ?>
                                <option value="<?= $m ?>" <?= $endParts['m'] === $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="end_ampm" id="endAMPM" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-2 text-white text-sm">
                            <option value="AM" <?= $endParts['a'] === 'AM' ? 'selected' : '' ?>>AM</option>
                            <option value="PM" <?= $endParts['a'] === 'PM' ? 'selected' : '' ?>>PM</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Extension Amount</label>
                    <input type="number" id="extensionAmount" value="<?= number_format((float) $calc['amount'], 2, '.', '') ?>" readonly
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm">
                    <?php if (isset($errors['amount'])): ?>
                        <p class="text-xs text-red-400 mt-1"><?= e($errors['amount']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-2">Payment Method</label>
                <div class="flex flex-wrap gap-3">
                    <?php foreach (['cash' => 'Cash', 'account' => 'Account', 'credit' => 'Credit'] as $pm => $label): ?>
                        <label class="flex items-center gap-2 bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-mb-silver">
                            <input type="radio" name="payment_method" value="<?= $pm ?>" class="accent-mb-accent" <?= $paymentMethod === $pm ? 'checked' : '' ?> onchange="syncBank()">
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['payment_method'])): ?>
                    <p class="text-xs text-red-400 mt-1"><?= e($errors['payment_method']) ?></p>
                <?php endif; ?>
            </div>

            <div id="bankAccountWrap" class="max-w-sm">
                <label class="block text-sm text-mb-silver mb-2">Bank Account</label>
                <select name="bank_account_id" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm">
                    <option value="">-- Select Account --</option>
                    <?php foreach ($activeBankAccounts as $acct): ?>
                        <option value="<?= (int) $acct['id'] ?>" <?= $bankAccountId === (int) $acct['id'] ? 'selected' : '' ?>><?= e($acct['name'] ?? ('Account #' . $acct['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['bank_account_id'])): ?>
                    <p class="text-xs text-red-400 mt-1"><?= e($errors['bank_account_id']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="show.php?id=<?= $id ?>" class="px-4 py-2 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white">Cancel</a>
                <button type="submit" class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80">Confirm Extension</button>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Extension Summary</h3>
            <div class="text-sm text-mb-silver space-y-2">
                <div class="flex justify-between"><span>Grace Start</span><span class="text-white"><?= e($baseStartDt->format('d M Y, h:i A')) ?></span></div>
                <div class="flex justify-between"><span>New End</span><span class="text-white" id="summaryEnd"><?= e(($calc['new_end_dt'] ?? $defaultEndDt)->format('d M Y, h:i A')) ?></span></div>
                <div class="flex justify-between"><span>Extension Days</span><span class="text-white" id="calcDays"><?= (int) $calc['days'] ?></span></div>
                <div class="flex justify-between"><span>Rate / Day</span><span class="text-white" id="calcRate">$<?= number_format((float) ($calc['rate_per_day'] ?? 0), 2) ?></span></div>
                <div class="flex justify-between border-t border-mb-subtle/20 pt-3"><span class="text-white">Total Extension</span><span class="text-white" id="calcTotal">$<?= number_format((float) $calc['amount'], 2) ?></span></div>
            </div>
            <p class="text-xs text-mb-subtle">Grace approach: we count extra days from today if the reservation is already overdue.</p>
        </div>
    </form>
</div>

<div id="rateData"
    data-daily="<?= e($rates['daily']) ?>"
    data-monthly="<?= e($rates['monthly']) ?>"
    data-1day="<?= e($rates['1day']) ?>"
    data-7day="<?= e($rates['7day']) ?>"
    data-15day="<?= e($rates['15day']) ?>"
    data-30day="<?= e($rates['30day']) ?>"
    data-base-start-ts="<?= (int) ($baseStartDt->getTimestamp() * 1000) ?>"></div>

<script>
const rateData = document.getElementById('rateData');
const DAILY = parseFloat(rateData.dataset.daily || '0');
const MONTHLY = parseFloat(rateData.dataset.monthly || '0');
const PKG = {
    '1day': parseFloat(rateData.dataset['1day'] || '0'),
    '7day': parseFloat(rateData.dataset['7day'] || '0'),
    '15day': parseFloat(rateData.dataset['15day'] || '0'),
    '30day': parseFloat(rateData.dataset['30day'] || '0')
};
const BASE_START_TS = parseInt(rateData.dataset.baseStartTs || '0', 10);

function getSelectedType() {
    const el = document.querySelector('input[name="rental_type"]:checked');
    return el ? el.value : 'daily';
}

function getEndDateFromInputs() {
    const date = document.getElementById('endDate').value;
    if (!date) return null;
    const h = parseInt(document.getElementById('endHour').value || '12', 10);
    const m = parseInt(document.getElementById('endMin').value || '0', 10);
    const ampm = document.getElementById('endAMPM').value || 'AM';
    let h24 = h % 12;
    if (ampm === 'PM') h24 += 12;
    const parts = date.split('-');
    return new Date(parseInt(parts[0],10), parseInt(parts[1],10) - 1, parseInt(parts[2],10), h24, m, 0, 0);
}

function setEndInputs(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    document.getElementById('endDate').value = `${y}-${m}-${day}`;
    let h = d.getHours();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    document.getElementById('endHour').value = String(h);
    document.getElementById('endMin').value = String(Math.floor(d.getMinutes() / 5) * 5);
    document.getElementById('endAMPM').value = ampm;
    const summaryEnd = document.getElementById('summaryEnd');
    if (summaryEnd) {
        summaryEnd.textContent = d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
}

function setEndInputsDisabled(disabled) {
    document.getElementById('endDate').disabled = disabled;
    document.getElementById('endHour').disabled = disabled;
    document.getElementById('endMin').disabled = disabled;
    document.getElementById('endAMPM').disabled = disabled;
}

function updateOutputs(days, rate, total) {
    document.getElementById('calcDays').textContent = String(days);
    document.getElementById('calcRate').textContent = '$' + rate.toFixed(2);
    document.getElementById('calcTotal').textContent = '$' + total.toFixed(2);
    document.getElementById('extensionAmount').value = total.toFixed(2);
}

function calcExtension() {
    const type = getSelectedType();
    const FIXED_DAYS = { '1day': 1, '7day': 7, '15day': 15, '30day': 30 };
    const base = new Date(BASE_START_TS);
    let days = 0, rate = DAILY, total = 0;
    let end = getEndDateFromInputs();

    if (FIXED_DAYS[type]) {
        days = FIXED_DAYS[type];
        const pkgRate = PKG[type] || 0;
        if (pkgRate > 0) { total = pkgRate; rate = pkgRate / days; }
        else { total = days * DAILY; rate = DAILY; }
        end = new Date(base);
        end.setDate(end.getDate() + days);
        setEndInputs(end);
        setEndInputsDisabled(true);
    } else if (type === 'monthly') {
        setEndInputsDisabled(false);
        if (!end || end <= base) { updateOutputs(0, 0, 0); return; }
        days = Math.ceil((end - base) / 86400000) + 1;
        const monthRate = MONTHLY > 0 ? MONTHLY : DAILY;
        total = monthRate * (days / 30 || 1);
        rate = days > 0 ? (total / days) : 0;
    } else {
        setEndInputsDisabled(false);
        if (!end || end <= base) { updateOutputs(0, 0, 0); return; }
        days = Math.ceil((end - base) / 86400000) + 1;
        rate = DAILY;
        total = days * DAILY;
    }

    updateOutputs(days, rate, total);
}

function syncBank() {
    const selected = document.querySelector('input[name="payment_method"]:checked');
    const wrap = document.getElementById('bankAccountWrap');
    if (!wrap) return;
    wrap.style.display = selected && selected.value === 'account' ? 'block' : 'none';
}

document.getElementById('endDate').addEventListener('change', calcExtension);
document.getElementById('endHour').addEventListener('change', calcExtension);
document.getElementById('endMin').addEventListener('change', calcExtension);
document.getElementById('endAMPM').addEventListener('change', calcExtension);

document.querySelectorAll('input[name="rental_type"]').forEach((el) => {
    el.addEventListener('change', calcExtension);
});

document.querySelectorAll('input[name="payment_method"]').forEach((el) => {
    el.addEventListener('change', syncBank);
});

calcExtension();
syncBank();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
